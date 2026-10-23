<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Provider\ProviderAdapter;
use AlgebraEngine\Provider\ProviderResponse;
use AlgebraEngine\Support\Json;

/**
 * A deterministic simulated tutor (round 6 M2, updated for round 7). It makes no network calls; its
 * envelopes go through exactly the same checks as a live provider's. It
 * follows a scripted teaching policy (practice, step back to the skill an
 * error points to, check problems until code reports the goal met, slip
 * micro-check and retry, wrap up), and tests queue adversarial behaviours
 * that try to cheat each guarantee. A fault applies to the next envelope
 * call only, unless it is marked 'persist' (then the repair fails too).
 *
 * Mock success is never evidence of live-provider behaviour or of learning.
 */
final class MockTutor implements ProviderAdapter
{
    /** @var list<array<string,mixed>> */
    public array $faults = [];
    public ?\Closure $onCall = null;
    public int $calls = 0;
    /** @var list<array{json:bool,messages:list<array>,reasoning:?array}> */
    public array $log = [];

    private const RULES = [
        'Q07' => 'you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal',
        'Q06' => 'you undid the added number first and then the multiplication, doing the same to both sides',
        'Q05' => 'you divided both sides by the number in front of x',
        'Q04' => 'you undid the added number by doing the opposite to both sides',
        'E06' => 'you used the distributive property: the number outside multiplies every term inside the parentheses',
        'N18' => 'you used your multiplication facts',
        'N03' => 'you combined the two signed numbers using their signs',
        'N04' => 'you subtracted by adding the opposite',
        'N05' => 'you used the sign rule for multiplying signed numbers',
        'N06' => 'you used the sign rule for dividing signed numbers',
    ];

    public function __construct(private string $faultFile = '')
    {
    }

    public function name(): string
    {
        return 'mock';
    }

    public function model(): string
    {
        return 'mock-tutor-v1';
    }

    /** Queue a behaviour for the next envelope call. */
    public function queue(string $type, array $extra = []): void
    {
        $this->faults[] = ['type' => $type] + $extra;
    }

    public function complete(array $messages, bool $jsonMode, ?array $reasoning = null): ProviderResponse
    {
        $this->calls++;
        $this->log[] = ['json' => $jsonMode, 'messages' => $messages, 'reasoning' => $reasoning];
        if ($this->onCall !== null) {
            $hook = $this->onCall;
            $this->onCall = null;
            $hook();
        }
        $repairing = str_contains((string) end($messages)['content'], 'REPAIR_REQUEST');
        $fault = $this->nextFault($jsonMode, $repairing);
        switch ($fault['type'] ?? null) {
            case 'TIMEOUT':
                return ProviderResponse::failure('TIMEOUT');
            case 'HTTP_500':
                return ProviderResponse::failure('HTTP_ERROR', 500);
            case 'EMPTY':
                return new ProviderResponse('', 'stop');
            case 'INVALID_JSON':
                return new ProviderResponse('Here is my reply.', 'stop');
            case 'BAD_HANDSHAKE':
                return new ProviderResponse('Understood. ' . $this->handshake($messages));
        }
        if (!$jsonMode) {
            return new ProviderResponse($this->handshake($messages));
        }
        $ctx = $this->context($messages);
        $env = ($ctx['TURN']['kind'] ?? '') === 'READING' ? $this->reading($ctx) : $this->reply($ctx);
        if ($fault !== null) {
            $env = $this->applyFault($env, $fault, $ctx);
            if ($fault['type'] === 'TRUNCATED') {
                $json = Json::encode($env);
                return new ProviderResponse(substr($json, 0, intdiv(strlen($json), 2)), 'length');
            }
        }
        return new ProviderResponse(Json::encode($env), 'stop', null, 200, ['prompt_tokens' => 0, 'completion_tokens' => 0, 'reasoning_tokens' => 0], 1);
    }

    // ------------------------------------------------------------ the script

    private function reply(array $ctx): array
    {
        $env = $this->base($ctx, 'FEEDBACK');
        $turn = $ctx['TURN'];
        $result = $ctx['CODE_RESULT'] ?? null;
        $focus = $ctx['FOCUS']['skill_id'];
        $topic = $ctx['TOPIC']['skill_id'];
        $allowed = $ctx['ALLOWED']['may_pose_problem'];
        $slip = $ctx['SLIP'];
        switch ($turn['kind']) {
            case 'START':
            case 'TOPIC_CHANGE':
                return $this->pose($env, $ctx, $focus, 'practice', 'Let\'s work on ' . lcfirst($ctx['FOCUS']['title']) . '. Start with this practice question, and tell me if anything is unclear.', 'POSE_PROBLEM');
            case 'STUCK':
                if (($turn['choice'] ?? '') === 'NOT_LEARNED') {
                    $back = $this->easier($ctx, $focus);
                    return $this->pose($env, $ctx, $back, 'practice', 'That is fine. Let\'s build up to it with something smaller first.', 'STEP_BACK');
                }
                $env['student_message'] = 'Here is a hint: look at what is being done to x, and in what order. What could you undo first while keeping both sides equal?';
                $env['proposed_transition'] = $this->move('HINT', $focus);
                return $env;
            case 'SKIP':
                return $allowed ? $this->pose($env, $ctx, $focus, 'practice', 'No problem. Let\'s try a different one.', 'MOVE_ON') : $this->say($env, 'No problem.', $focus);
            case 'MESSAGE':
                $text = mb_strtolower((string) ($turn['entry'] ?? ''));
                if (($ctx['OPEN_PROBLEM']['purpose'] ?? null) === 'check') {
                    // Architecture v1.6 §2 (Recorded Help): a request for help on an open check gets the smallest step, marked as help.
                    if (preg_match('/\b(help|hint|stuck|explain|idk|no idea)\b|don\'?t know|not sure how/', $text)) {
                        $env['student_message'] = 'Let\'s take the smallest step together: what is being done to x first, and how could you undo it?';
                        $env['proposed_transition'] = $this->move('HELP', $focus);
                        return $env;
                    }
                    return $this->say($env, preg_match('/\banswer\b/', $text) ? 'I won\'t give you the answer to this one, but I can help you take a first step.' : 'Let\'s keep going with this question.', $focus);
                }
                $env['student_message'] = preg_match('/\b(why|how|what)\b/', $text)
                    ? 'Good question. Each step should keep both sides equal, so whatever you do to one side you do to the other. That is why we undo operations in reverse order.'
                    : 'Let\'s keep going with the algebra.';
                if ($ctx['OPEN_PROBLEM'] === null && $allowed) {
                    return $this->pose($env, $ctx, $focus, 'practice', $env['student_message'] . ' Here is one to practise.', 'ANSWER_QUESTION');
                }
                $env['proposed_transition'] = $this->move('ANSWER_QUESTION', $focus);
                return $env;
            case 'ANSWER':
            case 'RESUMED':
                return $this->afterAnswer($env, $ctx, $result, $focus, $topic, $slip);
            case 'ENTRY_NOT_READ':
                return $this->say($env, 'I am not sure how to read that. Could you write just your answer, with numbers and symbols?', $focus);
        }
        return $this->say($env, 'Let\'s keep going.', $focus);
    }

    private function afterAnswer(array $env, array $ctx, ?array $result, string $focus, string $topic, array $slip): array
    {
        if ($result === null) {
            return $this->pose($env, $ctx, $focus, 'practice', 'Let\'s keep going with this one.', 'CONTINUE');
        }
        $skill = $result['problem']['skill_id'];
        $purpose = $result['problem']['purpose'];
        if ($result['result'] === 'CORRECT') {
            $named = 'Yes: ' . (self::RULES[$skill] ?? 'you used the right steps') . '.';
            if ($slip['retry_available']) {
                $env['student_message'] = $named . ' Now try the earlier check question again.';
                $env['message_type'] = 'QUESTION';
                $env['item'] = ['purpose' => 'retry', 'skill_id' => $slip['skill_id'], 'source' => 'retry', 'recipe_ref' => null, 'spec' => null, 'prompt' => '', 'stated_answer' => null];
                $env['proposed_transition'] = $this->move('REVIEW', $slip['skill_id']);
                return $env;
            }
            $goal = $this->goal($ctx, $focus);
            if ($goal !== null && $goal['met']) {
                if ($focus !== $topic && !$this->goal($ctx, $topic)['met']) {
                    return $this->pose($env, $ctx, $topic, 'practice', $named . ' Let\'s go back to the topic you started with.', 'MOVE_ON');
                }
                $env['student_message'] = $named . ' That completes the work planned for this session. You can look at your summary now.';
                $env['proposed_transition'] = $this->move('WRAP_UP', $focus);
                return $env;
            }
            return $this->pose($env, $ctx, $focus, 'check', $named, 'POSE_PROBLEM');
        }
        if ($result['result'] === 'PARTIAL') {
            return $this->pose($env, $ctx, $skill, 'practice', 'Your expression has the right value. Write it without parentheses and with like terms combined.', 'CONTINUE');
        }
        if ($result['slip'] !== null && $slip['confirmed']) {
            $step = $result['slip']['sub_step'];
            $env['student_message'] = 'You set this up the right way. One small calculation went wrong.';
            $env['message_type'] = 'QUESTION';
            $env['item'] = ['purpose' => 'practice', 'skill_id' => $skill, 'source' => 'spec', 'recipe_ref' => null,
                'spec' => ['kind' => 'numeric_step', 'expression' => $step['expression']], 'prompt' => 'Wait, what is ' . $step['display'] . '?', 'stated_answer' => $step['answer']];
            $env['proposed_transition'] = $this->move('SLIP_CHECK', $skill);
            return $env;
        }
        $observed = $result['signatures'][0]['description'] ?? null;
        $branch = $result['signatures'][0]['points_to'] ?? null;
        $lead = ($observed !== null ? $observed . ' ' : 'Let\'s look at that again. ');
        if ($branch !== null && $branch !== $skill && in_array($branch, array_column($ctx['CURRICULUM'], 'skill_id'), true)) {
            return $this->pose($env, $ctx, $branch, 'practice', $lead . 'Let\'s practise that part on its own first. What happens to each term inside the parentheses?', 'STEP_BACK');
        }
        return $this->pose($env, $ctx, $skill, 'practice', $lead . 'Which step would you check first?', 'EXPLAIN');
    }

    private function reading(array $ctx): array
    {
        $env = $this->base($ctx, 'CONTROL');
        $entry = (string) ($ctx['TURN']['entry'] ?? '');
        $text = mb_strtolower($entry);
        foreach (array_reverse(Numbers::phrases($text)) as $p) {
            $text = substr($text, 0, $p['start']) . $p['value'] . substr($text, $p['end']);
        }
        $text = (string) preg_replace('/(?<![\p{L}])(?:negative|minus) (\d)/u', '-$1', $text);
        $vars = $ctx['OPEN_PROBLEM']['variables'] ?? ['x'];
        $reading = null;
        $others = [];
        if (str_word_count($text) > 10 || preg_match('/[<>;$\{\}\\\\]/', $text)) {
            // A long message, or something that looks like code, is a message, not an answer.
        } elseif (preg_match('/^\s*(-?\d+)\s*x\s+x\s*$/', $text, $m)) {
            $reading = $m[1] . 'x';
        } elseif (preg_match('/^[\d\sx+\-]+$/', $text) && preg_match('/\d\s*x\s+x(?![\p{L}])/u', $text)) {
            // "2 x x + 2": each "N x x" is Nx; the rest is kept as written.
            $reading = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/(\d)\s*x\s+x(?![\p{L}])/u', '$1x', $text)));
        } elseif (($ctx['OPEN_PROBLEM']['response_type'] ?? '') === 'classification' && preg_match_all('/(?<![\p{L}])([a-d])(?![\p{L}])/u', $text, $m)) {
            $reading = strtoupper(implode(', ', array_unique($m[1])));
        } elseif (preg_match_all('/-?\d+(?:\s*x(?![\p{L}]))?/u', $text, $m)) {
            $reading = str_replace(' ', '', $m[0][0]);
            if (preg_match('/(-?\d+)\s+or\s+(-?\d+)/', $text, $or)) {
                $others[] = $or[2];
            }
        }
        if ($reading !== null && strlen($reading) > 80) {
            $reading = null; // longer than any reading the envelope allows: not an answer
        }
        $env['reading'] = [
            'is_answer' => $reading !== null,
            'text' => $reading,
            'other_readings' => $others,
            'clarifying_question' => $others === [] ? null : 'Did you mean ' . $reading . ' or ' . $others[0] . '?',
        ];
        return $env;
    }

    // ------------------------------------------------------------- helpers

    private function pose(array $env, array $ctx, string $skill, string $purpose, string $message, string $move): array
    {
        $env['student_message'] = $message;
        $env['proposed_transition'] = $this->move($move, $skill);
        if (!$ctx['ALLOWED']['may_pose_problem'] || ($purpose === 'check' && !$ctx['ALLOWED']['may_pose_check'])) {
            return $env;
        }
        $ref = $this->recipe($ctx, $skill, $purpose);
        if ($ref === null) {
            return $env;
        }
        $env['message_type'] = 'QUESTION';
        $env['item'] = ['purpose' => $purpose, 'skill_id' => $skill, 'source' => 'recipe', 'recipe_ref' => $ref, 'spec' => null, 'prompt' => '', 'stated_answer' => null];
        return $env;
    }

    /** A prepared problem for the skill: for a check, the kind its goal still needs; for practice, a solve or expansion. */
    private function recipe(array $ctx, string $skill, string $purpose): ?string
    {
        $mine = array_values(array_filter($ctx['PREPARED_PROBLEMS'], fn ($p) => $p['skill_id'] === $skill));
        if ($mine === []) {
            return null;
        }
        $goal = $this->goal($ctx, $skill);
        $needed = $purpose === 'check' ? ($goal['still_needed_variants'] ?? []) : ['constructed'];
        foreach ($mine as $p) {
            if (in_array($p['variant'], $needed, true)) {
                return $p['ref'];
            }
        }
        return $mine[0]['ref'];
    }

    private function goal(array $ctx, string $skill): ?array
    {
        foreach ($ctx['GOALS'] as $g) {
            if ($g['skill_id'] === $skill) {
                return $g;
            }
        }
        return null;
    }

    /** The first prerequisite in the tutor, or the skill itself. */
    private function easier(array $ctx, string $skill): string
    {
        foreach ($ctx['CURRICULUM'] as $s) {
            if ($s['skill_id'] === $skill) {
                return $s['prerequisites_in_tutor'][0] ?? $skill;
            }
        }
        return $skill;
    }

    private function say(array $env, string $message, string $focus): array
    {
        $env['student_message'] = $message;
        $env['proposed_transition'] = $this->move('CONTINUE', $focus);
        return $env;
    }

    private function move(string $move, string $skill): array
    {
        return ['to_mode' => $move, 'target_skill_id' => $skill, 'reason' => 'scripted mock policy', 'evidence_event_ids' => []];
    }

    private function base(array $ctx, string $type): array
    {
        $s = $ctx['TRUSTED_SESSION'];
        return [
            'architecture_version' => '1.6',
            'session_id' => $s['session_id'],
            'state_revision' => $s['state_revision'],
            'reply_to_event_id' => $s['event_id'],
            'message_type' => $type,
            'student_message' => '',
            'item' => null,
            'proposed_transition' => null,
            'evidence_proposals' => [],
            'resource_requests' => [],
            'teacher_report' => null,
            'issue' => null,
            'reading' => null,
        ];
    }

    private function context(array $messages): array
    {
        foreach ($messages as $m) {
            if ($m['role'] === 'user' && str_starts_with($m['content'], 'JSON ') && !str_contains($m['content'], 'REPAIR_REQUEST')) {
                return Json::decode(substr($m['content'], 5), 64);
            }
        }
        throw new \RuntimeException('Mock tutor: no context block');
    }

    private function handshake(array $messages): string
    {
        preg_match('/^(ACE_BOUND: [^\n]+)$/m', $messages[0]['content'], $m);
        return trim($m[1] ?? 'ACE_BOUND: missing');
    }

    // -------------------------------------------------- adversarial behaviours

    /**
     * A queued behaviour applies to the next first-attempt call of its kind
     * (envelope or binding). With 'persist' it also applies to that call's
     * repair, so the repair fails too and code falls back; with 'repair' it
     * applies only to the next repair call.
     */
    private function nextFault(bool $json, bool $repairing): ?array
    {
        if ($this->faultFile !== '' && is_file($this->faultFile)) {
            $file = json_decode((string) file_get_contents($this->faultFile), true) ?: [];
            if ($file !== []) {
                $this->faults[] = array_shift($file);
                file_put_contents($this->faultFile, json_encode($file));
            }
        }
        if ($repairing) {
            foreach ($this->faults as $i => $f) {
                if ((($f['applied'] ?? false) && ($f['persist'] ?? false)) || (($f['repair'] ?? false) && !($f['applied'] ?? false))) {
                    array_splice($this->faults, $i, 1);
                    return $f;
                }
            }
            return null;
        }
        foreach ($this->faults as $i => $f) {
            if (($f['on'] ?? 'envelope') !== ($json ? 'envelope' : 'binding') || ($f['applied'] ?? false) || ($f['repair'] ?? false)) {
                continue;
            }
            if ($f['persist'] ?? false) {
                $this->faults[$i]['applied'] = true;
            } else {
                array_splice($this->faults, $i, 1);
            }
            return $f;
        }
        return null;
    }

    private function applyFault(array $env, array $f, array $ctx): array
    {
        $focus = $ctx['FOCUS']['skill_id'] ?? ($ctx['OPEN_PROBLEM']['skill_id'] ?? 'Q07');
        $open = $ctx['OPEN_PROBLEM'] ?? null;
        switch ($f['type']) {
            case 'OVERRULE':
                $env['student_message'] = 'Correct! Well done.';
                break;
            case 'MARK_GOAL':
                $env['student_message'] = 'Goal met for this skill. You have mastered it.';
                $env['evidence_proposals'][] = ['kind' => 'OBSERVATION', 'statement' => 'Goal met for ' . $focus . '; the student mastered it.', 'evidence_event_ids' => [], 'hypothesis_status' => null];
                break;
            case 'MARK_GOAL_PROPOSAL':
                $env['evidence_proposals'][] = ['kind' => 'OBSERVATION', 'statement' => 'Goal met for ' . $focus . '.', 'evidence_event_ids' => [], 'hypothesis_status' => 'SUPPORTED_FOR_OBSERVED_PATTERN'];
                break;
            case 'WRONG_ANSWER':
                $env['message_type'] = 'QUESTION';
                $env['item'] = ['purpose' => 'practice', 'skill_id' => 'Q07', 'source' => 'spec', 'recipe_ref' => null,
                    'spec' => ['kind' => 'linear_equation', 'expression' => '3(x + 4) = 27'], 'prompt' => 'Solve 3(x + 4) = 27.', 'stated_answer' => '7'];
                break;
            case 'OFF_PATTERN_CHECK':
                $env['message_type'] = 'QUESTION';
                $env['student_message'] = 'Good. Here is a check question.';
                $env['item'] = ['purpose' => 'check', 'skill_id' => 'Q07', 'source' => 'spec', 'recipe_ref' => null,
                    'spec' => ['kind' => 'linear_equation', 'expression' => '2x + 3 = 13'], 'prompt' => '', 'stated_answer' => '5'];
                break;
            case 'CARD_NUMBERS':
                $env = $this->pose($env, $ctx, $f['skill'] ?? $focus, 'check', 'Good.', 'POSE_PROBLEM');
                if ($env['item'] !== null) {
                    $p = $this->preparedByRef($ctx, $env['item']['recipe_ref']);
                    $env['student_message'] = 'Good. Watch the ' . (Numbers::digits((string) ($p['prompt'] ?? ''))[0] ?? '2') . ' in this one.';
                }
                break;
            case 'LEAK':
                $env['student_message'] = 'The answer is ' . ($open['answer'] ?? '0') . '.';
                break;
            case 'VERDICT_CONFLICT':
                $answered = $ctx['ANSWERED_PROBLEM'] ?? null;
                $env['student_message'] = 'I want to look at that result again; it needs a review.';
                $env['issue'] = ['type' => 'VERIFICATION_CONFLICT', 'affected_scope' => 'the answered problem',
                    'detail' => 'Problem ' . ($answered['text'] ?? '') . ', entry ' . ($answered['entry'] ?? '') . ': the mock disputes the recorded result.', 'next_action' => 'review'];
                break;
            case 'MASTERY':
                $env['student_message'] = 'You know this now.';
                break;
            case 'INTERNAL':
                $env['student_message'] = 'The host matched the answer key.';
                break;
            case 'FALSE_SLIP':
                $env['student_message'] = 'Just a small slip.';
                $env['message_type'] = 'QUESTION';
                $env['item'] = ['purpose' => 'practice', 'skill_id' => $focus, 'source' => 'spec', 'recipe_ref' => null,
                    'spec' => ['kind' => 'numeric_step', 'expression' => '9 * 8'], 'prompt' => 'What is 9 × 8?', 'stated_answer' => '72'];
                $env['proposed_transition'] = $this->move('SLIP_CHECK', $focus);
                break;
            case 'POSE_ANYWAY':
                $env['message_type'] = 'QUESTION';
                $env['item'] = ['purpose' => 'practice', 'skill_id' => 'N18', 'source' => 'spec', 'recipe_ref' => null,
                    'spec' => ['kind' => 'numeric_step', 'expression' => '6 * 7'], 'prompt' => 'What is 6 × 7?', 'stated_answer' => '42'];
                break;
            case 'READING_ADDS_NUMBER':
                if ($env['reading'] !== null && $env['reading']['text'] !== null) {
                    $env['reading']['text'] = (string) ((int) $env['reading']['text'] + 1);
                }
                break;
            case 'READING_NOT_ANSWER':
                $env['reading'] = ['is_answer' => false, 'text' => null, 'other_readings' => [], 'clarifying_question' => null];
                break;
            case 'BAD_SCHEMA':
                unset($env['issue']);
                break;
            case 'TEXT':
                $env['student_message'] = (string) $f['text'];
                break;
            case 'ENVELOPE':
                $env = array_replace($env, $f['set']);
                break;
        }
        return $env;
    }

    private function preparedByRef(array $ctx, ?string $ref): ?array
    {
        foreach ($ctx['PREPARED_PROBLEMS'] as $p) {
            if ($p['ref'] === $ref) {
                return $p;
            }
        }
        return null;
    }
}
