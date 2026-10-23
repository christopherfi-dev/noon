<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Support\Json;

/**
 * Each AI call is built anew from accepted state (round 6 §3.2). Order: the
 * architecture, the output contract (round 7 §3), the accepted binding
 * exchange, then one user message holding the trusted blocks as JSON. Learner
 * text appears only as JSON string data. No example content (owner rule): the
 * output shape is a skeleton derived from tutor-envelope.schema.json with type
 * placeholders.
 */
final class TutorPrompt
{
    public const ACTIVATION_REQUEST = 'CONTROL: Bind the operating contract in the system message. Respond only as its Section 16 specifies.';

    /**
     * Round 7 §3: the output contract, and nothing else. It defines the
     * envelope's fields and when each value is valid; every behaviour rule is
     * the architecture's.
     */
    public const OUTPUT_CONTRACT = <<<'TXT'
OUTPUT CONTRACT
Reply with exactly one JSON object that follows OUTPUT_CONTRACT in the JSON block. No prose outside it, no Markdown fences.
Identity: copy session_id and state_revision from TRUSTED_SESSION, and reply_to_event_id from TRUSTED_SESSION.event_id, exactly. architecture_version is "1.5".
Fields:
- message_type: QUESTION when item is not null; FEEDBACK when item is null; CONTROL when TURN.kind is READING. REPORT and HOLD are not used.
- student_message: the learner-facing text. For a READING turn it is the empty string.
- item: null, or one next problem with these fields:
  - purpose: practice or check; retry only with source retry.
  - skill_id: one of the ten skills in CURRICULUM.
  - source recipe: recipe_ref is a ref from PREPARED_PROBLEMS for that skill_id; spec is null; stated_answer is null or that problem's answer.
  - source spec: spec is {kind, expression}; recipe_ref is null; stated_answer is the answer you computed for the spec, in plain notation.
    spec.kind linear_equation: a linear equation in x with exactly one solution. expand: an expression to expand or simplify. signed_arithmetic: one operation on two whole numbers. numeric_step: a short calculation on numbers.
    spec.expression: the equation or expression in plain notation, using numbers within PROBLEM_LIMITS.
  - source retry: purpose retry, valid only when SLIP.retry_available is true; skill_id is SLIP.skill_id; recipe_ref, spec and stated_answer are null.
  - prompt: a string, which may be empty. Code renders the displayed wording of every problem from its spec; this value is recorded and not displayed.
  - Any item is valid only when ALLOWED.may_pose_problem is true. A check item is valid only when ALLOWED.may_pose_check is true; a check spec matches that skill's pattern in CHECK_PATTERNS, and a choice check question is a prepared problem.
- proposed_transition: null, or {to_mode, target_skill_id, reason, evidence_event_ids}. to_mode is one of the values in OUTPUT_CONTRACT.allowed_values. target_skill_id is one of the ten skills in CURRICULUM, or null. SLIP_CHECK is valid only when SLIP.confirmed is true, and an item posed with it has purpose practice. reason is a short string. evidence_event_ids lists attempt_id values from this request, or is empty.
- evidence_proposals: a list, possibly empty, of {kind, statement, evidence_event_ids, hypothesis_status}. kind is OBSERVATION, HYPOTHESIS or INSTRUCTIONAL_TRIAL. hypothesis_status is UNRESOLVED, PROVISIONAL, SUPPORTED_FOR_OBSERVED_PATTERN or CONTRADICTED for a HYPOTHESIS, and null otherwise.
- resource_requests: a list, possibly empty, of {skill_id, purpose}.
- teacher_report: null.
- issue: null, or {type, affected_scope, detail, next_action}. type VERIFICATION_CONFLICT is a conflict with the verdict in ANSWERED_PROBLEM; detail names the problem, the entry and the conflicting calculation.
- reading: null unless TURN.kind is READING. Then {is_answer, text, other_readings, clarifying_question}: is_answer is true when the entry is an answer to OPEN_PROBLEM; text is your reading of that answer in plain notation, or null; other_readings lists other plausible readings in plain notation, or is empty; clarifying_question is a short question to the student for when the entry cannot be read faithfully or the readings differ, or null. For a READING turn, item is null.
Plain notation: numbers, the problem's variables, + - * / ^ ( ) and =.
TXT;

    private ?array $skeleton = null;

    public function __construct(private SchemaValidator $schemas)
    {
    }

    /** @return list<array{role:string,content:string}> */
    public function bindingMessages(string $architecture): array
    {
        return [
            ['role' => 'system', 'content' => $architecture],
            ['role' => 'user', 'content' => self::ACTIVATION_REQUEST],
        ];
    }

    /**
     * @param array<string,mixed> $context trusted blocks keyed by label
     * @return list<array{role:string,content:string}>
     */
    public function messages(string $architecture, string $handshake, array $context): array
    {
        $payload = $context + [
            'OUTPUT_CONTRACT' => [
                'format' => 'Return one JSON object with every field below present. Values shown are type placeholders, not content.',
                'envelope_skeleton' => $this->skeleton(),
                'allowed_values' => $this->allowedValues(),
            ],
        ];
        return [
            ['role' => 'system', 'content' => $architecture],
            ['role' => 'system', 'content' => self::OUTPUT_CONTRACT],
            ['role' => 'user', 'content' => self::ACTIVATION_REQUEST],
            ['role' => 'assistant', 'content' => $handshake],
            ['role' => 'user', 'content' => 'JSON ' . Json::encode($payload)],
        ];
    }

    /**
     * One bounded repair request: the same context, the exact errors, nothing else.
     *
     * @param list<array{role:string,content:string}> $messages
     * @param list<string> $errors
     */
    public function repair(array $messages, ?string $previous, array $errors): array
    {
        if ($previous !== null && $previous !== '') {
            $messages[] = ['role' => 'assistant', 'content' => mb_substr($previous, 0, 8000)];
        }
        $messages[] = ['role' => 'user', 'content' => 'JSON ' . Json::encode(['REPAIR_REQUEST' => [
            'notice' => 'The application rejected the previous output. This is a private control message, not a student turn.',
            'errors' => array_values(array_slice($errors, 0, 12)),
            'remaining_repair_attempts' => 0,
            'instruction' => 'Return one corrected JSON envelope for the same turn. Fix exactly these errors and change nothing else.',
        ]])];
        return $messages;
    }

    /** @return array<string,string> */
    public function allowedValues(): array
    {
        $out = [];
        $walk = function (array $schema, string $path) use (&$walk, &$out): void {
            if (isset($schema['enum'])) {
                $out[$path] = 'one of ' . implode(', ', $schema['enum']);
            } elseif (array_key_exists('const', $schema)) {
                $out[$path] = 'exactly ' . json_encode($schema['const']);
            }
            foreach ($schema['anyOf'] ?? [] as $branch) {
                $walk($branch, $path);
            }
            foreach ($schema['properties'] ?? [] as $name => $sub) {
                $walk($sub, $path === '' ? $name : $path . '.' . $name);
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                $walk($schema['items'], $path . '[]');
            }
        };
        $walk($this->schemas->schema('tutor-envelope.schema.json'), '');
        return $out;
    }

    /** Every field present, type placeholders only. */
    public function skeleton(): array
    {
        return $this->skeleton ??= $this->shape($this->schemas->schema('tutor-envelope.schema.json'));
    }

    private function shape(array $schema): mixed
    {
        if (isset($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $branch) {
                if (($branch['type'] ?? null) !== 'null') {
                    return $this->shape($branch);
                }
            }
            return null;
        }
        if (array_key_exists('const', $schema)) {
            return '<string>';
        }
        return match ($schema['type'] ?? null) {
            'object' => isset($schema['properties']) ? array_map(fn ($p) => $this->shape($p), $schema['properties']) : new \stdClass(),
            'array' => isset($schema['items']) ? [$this->shape($schema['items'])] : [],
            'integer' => '<integer>',
            'number' => '<number>',
            'boolean' => '<boolean>',
            'string' => '<string>',
            default => null,
        };
    }
}
