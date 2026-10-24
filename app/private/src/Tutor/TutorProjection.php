<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Support\LearnerText;

/**
 * The only session data the browser receives: the conversation, the open
 * problem (wording only, never its answer), the goal dots from code's goal
 * status, and the allowed actions. It is validated against
 * tutor-view.schema.json and scanned for private keys before it leaves.
 */
final class TutorProjection
{
    public const FORBIDDEN_KEYS = [
        'answer', 'answer_spec', 'expected', 'rubric', 'response_sha256', 'evidence_proposals', 'hypotheses', 'teacher_report',
        'lease_token', 'parameters', 'params', 'spec', 'mathematical_objects', 'signatures', 'signature_answers', 'envelope',
        'fingerprint', 'template_id', 'checker_result_id', 'engine', 'snapshot', 'slip', 'reading', 'normalized',
    ];
    public const CHECK_LABEL = 'Check question: on your own';
    public const PRACTICE_LABEL = 'Practice';
    public const RETRY_LABEL = 'Try it again (this one does not count toward the goal)';

    public function __construct(private CurriculumRepository $curriculum, private SchemaValidator $schemas)
    {
    }

    public function build(array $row, ?string $pendingOperationId, array $events): array
    {
        $eng = $row['engine'];
        $status = match (true) {
            $eng['status'] === 'CLOSED' => 'CLOSED',
            $pendingOperationId !== null => 'PENDING',
            $eng['status'] === 'HOLD' => 'HOLD',
            default => 'READY',
        };
        $skill = fn (?string $id) => $id === null ? null : ['skill_id' => $id, 'title' => $this->curriculum->title($id)];
        $open = $status === 'CLOSED' ? null : $eng['open'];
        $view = [
            'status' => $status,
            'revision' => $row['revision'],
            'topic' => $skill($eng['topic']),
            'focus' => $skill($eng['focus']),
            'conversation' => array_values(array_map(fn ($l) => ['id' => $l['id'], 'role' => $l['role'], 'kind' => $l['kind'], 'text' => mb_substr($l['text'], 0, 6000), 'result' => $l['result']],
                array_slice($eng['conversation'], -40))),
            'open_problem' => $open === null ? null : $this->problem($open),
            'goal' => $status === 'CLOSED' ? null : $this->goal($eng, $events),
            'allowed_actions' => $this->allowedActions($eng, $pendingOperationId),
            'wrap_up' => (bool) ($eng['wrap_up'] ?? false),
            'csrf_token' => null,
            'pending_operation_id' => $pendingOperationId,
            'error_code' => null,
        ];
        $this->assertPublic($view);
        return $view;
    }

    /** @return list<string> */
    public function allowedActions(array $eng, ?string $pendingOperationId): array
    {
        if ($eng['status'] === 'CLOSED') {
            return [];
        }
        if ($pendingOperationId !== null) {
            return ['END_SESSION'];
        }
        if ($eng['status'] === 'HOLD') {
            return ['RESUME', 'END_SESSION'];
        }
        return $eng['open'] !== null
            ? ['SEND', 'STUCK', 'SKIP', 'CHANGE_TOPIC', 'END_SESSION']
            : ['SEND', 'CHANGE_TOPIC', 'END_SESSION'];
    }

    private function problem(array $open): array
    {
        $prompt = $open['prompt'];
        if ($open['options'] !== null) {
            $prompt = preg_split('/\n(?=[A-D]\) )/u', $prompt)[0];
        }
        // Round 8 §3.1: once help is recorded on a check question, the check becomes practice.
        $purpose = $open['purpose'] === 'CHECK' && ($open['help'] ?? []) !== [] ? 'PRACTICE' : $open['purpose'];
        return [
            'problem_id' => $open['problem_id'],
            'problem_version' => $open['version'],
            'purpose' => strtolower($purpose),
            'label' => match ($purpose) {
                'CHECK' => self::CHECK_LABEL,
                'RETRY' => self::RETRY_LABEL,
                default => self::PRACTICE_LABEL,
            },
            'prompt' => $prompt,
            'response_type' => $open['response_type'],
            'input_help' => match ($open['response_type']) {
                'scalar', 'equation' => 'Type your answer, or a message to the tutor.',
                'expression' => 'Type your expression (for example with x and +), or a message to the tutor.',
                default => 'Choose every option that applies.',
            },
            'options' => $open['options'] === null ? null : array_map(fn ($o) => ['letter' => $o['letter'], 'text' => $o['text']], $open['options']),
            'with_help' => ($open['help'] ?? []) !== [],
        ];
    }

    /**
     * The dots: code's goal status for the skill a slip paused, else the open
     * check question's (or retry's) skill, else the focus skill. A practice
     * question on another skill (a micro-question after a slip) does not move
     * the dots.
     */
    private function goal(array $eng, array $events): ?array
    {
        $open = $eng['open'];
        $skill = $eng['slip']['problem']['skill_id']
            ?? (in_array($open['purpose'] ?? null, ['CHECK', 'RETRY'], true) ? $open['skill_id'] : null)
            ?? $eng['focus'];
        if ($skill === null || !BetaScope::contains($skill)) {
            return null;
        }
        $g = Goals::forSkill(Goals::compute(Gradebook::attempts($events)), $skill);
        $title = $this->curriculum->title($skill);
        if ($g['met']) {
            $lines = ['Goal met in this session for ' . $title . ': ' . Goals::WORDING . '.'];
        } else {
            $lines = [$g['count'] . ' of ' . $g['required'] . ' check questions correct without help (one slip may pause the count)'];
            if ($g['still_needed'] !== []) {
                $lines[] = 'Still needed: ' . BetaScope::kinds($skill, $g['still_needed']) . '.';
            }
            if ($g['paused']) {
                $lines[] = 'Paused after a small slip: the count was kept.';
            }
            if (($eng['open']['purpose'] ?? null) === 'CHECK' && ($eng['open']['help'] ?? []) !== []) {
                $lines[] = 'You had help on this one, so the count starts again after your answer.';
            }
        }
        $label = implode("\n", $lines);
        if (($terms = LearnerText::internalTerms($label)) !== []) {
            throw new \LogicException('Goal label uses internal terms (' . implode(', ', $terms) . ')');
        }
        return ['skill_id' => $skill, 'title' => $title, 'count' => $g['count'], 'required' => $g['required'], 'met' => $g['met'], 'paused' => $g['paused'], 'label' => $label];
    }

    public function assertPublic(array $view): void
    {
        $errors = $this->schemas->errors($view, 'tutor-view.schema.json');
        if ($errors !== []) {
            throw new \LogicException('Public view failed tutor-view.schema.json: ' . implode('; ', $errors));
        }
        self::scan($view);
    }

    public static function scan(mixed $value): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $k => $v) {
            if (is_string($k) && in_array($k, self::FORBIDDEN_KEYS, true)) {
                throw new \LogicException('Private key in public payload: ' . $k);
            }
            self::scan($v);
        }
    }
}
