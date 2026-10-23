<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Workflow\EntryInterpreter;

/**
 * Deterministic simulated model. It makes no network calls. Its output goes
 * through exactly the same host acceptance pipeline as a live provider's.
 * Tests queue faults to exercise every rejection path; a fault file can be
 * configured for browser tests only.
 */
final class MockProvider implements ProviderAdapter
{
    /** @var list<array<string,mixed>> */
    public array $faults = [];
    /** Test hook invoked during a call (used to interleave a concurrent request). */
    public ?\Closure $onCall = null;
    public int $calls = 0;
    /** @var list<array{json:bool,messages:list<array>}> */
    public array $log = [];

    public function __construct(private ItemFactory $factory, private string $faultFile = '')
    {
    }

    public function name(): string
    {
        return 'mock';
    }

    public function model(): string
    {
        return 'mock-deterministic-v1';
    }

    public function complete(array $messages, bool $jsonMode): ProviderResponse
    {
        $this->calls++;
        $this->log[] = ['json' => $jsonMode, 'messages' => $messages];
        $kind = $jsonMode ? $this->callKind($messages) : 'binding';
        $fault = $this->nextFault($kind);
        if (($fault['type'] ?? null) === 'CALLBACK' || $this->onCall !== null) {
            $hook = $this->onCall;
            $this->onCall = null;
            if ($hook !== null) {
                $hook();
            }
        }
        if ($fault !== null) {
            switch ($fault['type']) {
                case 'TIMEOUT':
                    return ProviderResponse::failure('TIMEOUT');
                case 'HTTP_500':
                    return ProviderResponse::failure('HTTP_ERROR', 500);
                case 'EMPTY':
                    return new ProviderResponse('', 'stop');
                case 'INVALID_JSON':
                    return new ProviderResponse('Here is the JSON you asked for.', 'stop');
                case 'HALT':
                    return new ProviderResponse('ACE_HALT: CONTRACT_CONFLICT | binding=NOT_ESTABLISHED');
                case 'BAD_HANDSHAKE':
                    return new ProviderResponse('Understood. ' . $this->handshake($messages));
                case 'OLD_HANDSHAKE':
                    return new ProviderResponse(str_replace('architecture=1.3', 'architecture=1.2', $this->handshake($messages)));
            }
        }
        if (!$jsonMode) {
            return new ProviderResponse($this->handshake($messages));
        }
        if ($kind === 'interpret' || $kind === 'decision') {
            $request = $this->request($messages, $kind === 'interpret' ? 'INTERPRET_REQUEST' : 'DECISION_REQUEST');
            $out = $kind === 'interpret' ? $this->interpretEntry($request) : $this->decision($request);
            $out = ['architecture_version' => '1.3', 'session_id' => $request['session_id'], 'state_revision' => $request['state_revision'],
                'reply_to_event_id' => $request['reply_to_event_id'], 'operation' => $kind === 'interpret' ? 'INTERPRET' : 'DECIDE'] + $out;
            if ($fault !== null && in_array($fault['type'], ['READING', 'DECISION'], true)) {
                $out = array_merge($out, $fault['set']);
            }
            return new ProviderResponse(Json::encode($out), 'stop');
        }
        $envelope = $this->envelope($messages);
        if ($fault !== null) {
            $envelope = $this->applyFault($envelope, $fault);
            if ($fault['type'] === 'TRUNCATED') {
                $json = Json::encode($envelope);
                return new ProviderResponse(substr($json, 0, intdiv(strlen($json), 2)), 'length');
            }
        }
        return new ProviderResponse(Json::encode($envelope), 'stop');
    }

    /** envelope, interpret or decision: read from the request block the host sent. */
    private function callKind(array $messages): string
    {
        foreach ($messages as $m) {
            if ($m['role'] === 'user' && str_starts_with($m['content'], 'JSON {"INTERPRET_REQUEST"')) {
                return 'interpret';
            }
            if ($m['role'] === 'user' && str_starts_with($m['content'], 'JSON {"DECISION_REQUEST"')) {
                return 'decision';
            }
        }
        return 'envelope';
    }

    private function request(array $messages, string $label): array
    {
        foreach ($messages as $m) {
            if ($m['role'] === 'user' && str_starts_with($m['content'], 'JSON {"' . $label . '"')) {
                return Json::decode(substr($m['content'], 5), 64)[$label];
            }
        }
        throw new \RuntimeException('Mock received no ' . $label);
    }

    /**
     * A cooperative reader: keeps an answer written before "because", turns number words
     * into digits and "times" into *, reads an x standing between two terms as times, and
     * asks a clarifying question when its reading differs from the host's.
     */
    private function interpretEntry(array $req): array
    {
        $entry = (string) $req['learner_entry'];
        $n = EntryInterpreter::normalise($entry);
        $pending = $req['pending_clarification'] ?? null;
        $answer = fn (string $reading, string $note) => ['kind' => 'answer', 'normalized_answer' => $reading, 'reading_note' => $note, 'clarifying_question' => null, 'reply' => ''];
        if ($pending !== null && preg_match('/^(yes|yeah|yep|y|i did|i meant times|times)$/', $n) === 1) {
            return $answer($pending['model_reading'], 'confirmed the reading in the clarifying question');
        }
        if ($pending !== null && $pending['host_reading'] !== null && preg_match('/^(no|nope|n)$/', $n) === 1) {
            return $answer($pending['host_reading'], 'kept the entry as written');
        }
        if (str_ends_with(trim($entry), '?') && preg_match('/\p{L}{2,}/u', $n) === 1) {
            // A question about the mathematics, or about something else.
            return preg_match('/\b(x|mean|means|solve|expand|multiply|divide|times|equation|parentheses|how|step|sign|negative)\b/u', $n) === 1
                ? ['kind' => 'question', 'normalized_answer' => null, 'reading_note' => 'a question about the problem', 'clarifying_question' => null,
                    'reply' => 'Good question. Look at what the question asks you to find, and start from what you already know about it.']
                : ['kind' => 'off_task', 'normalized_answer' => null, 'reading_note' => 'not about the problem', 'clarifying_question' => null,
                    'reply' => 'Let\'s stay with this problem for now. Type your answer, or type help if you\'re stuck.'];
        }
        $s = rtrim(mb_strtolower(trim($entry), 'UTF-8'), '.!?');
        $notes = [];
        if (preg_match('/^(.*?)\s*\b(because|since|cause|cuz|coz)\b/u', $s, $m) === 1 && trim($m[1]) !== '') {
            $s = trim($m[1]);
            $notes[] = 'kept the answer written before the explanation';
        }
        do {
            $before = $s;
            $s = (string) preg_replace('/^(the answer is|answer is|i think|its|it is|it\'s|it’s|i got|maybe)\s+/u', '', $s);
        } while ($s !== $before);
        $s = (string) preg_replace('/\bequals\b/u', '=', $s);
        $s = (string) preg_replace('/\b(?:negative|minus)\s+(?=\d)/u', '-', $s);
        $s = (string) preg_replace('/\b(\p{L})\s+is\s+/u', '$1 = ', $s);
        foreach (array_reverse(EntryInterpreter::NUMBER_WORDS, true) as $value => $word) {
            $before = $s;
            $s = (string) preg_replace('/\b(?:negative|minus)\s+' . $word . '\b/u', '-' . $value, $s);
            $s = (string) preg_replace('/\b' . $word . '\b/u', (string) $value, $s);
            if ($s !== $before && !in_array('wrote numbers as words', $notes, true)) {
                $notes[] = 'wrote numbers as words';
            }
        }
        if (preg_match('/×|\btimes\b/u', $s) === 1) {
            $s = (string) preg_replace('/\s*(×|\btimes\b)\s*/u', '*', $s);
            $notes[] = 'read times as multiplication';
        }
        $s = (string) preg_replace(['/\bplus\b/u', '/\bminus\b/u'], ['+', '-'], $s);
        $asTimes = false;
        foreach ($req['item']['variables'] as $v) {
            $s = (string) preg_replace('/(?<=[\d\p{L}])\s+' . preg_quote($v, '/') . '\s+(?=[\d\p{L}(])/u', '*', $s, -1, $count);
            $asTimes = $asTimes || $count > 0;
        }
        if ($asTimes) {
            $notes[] = 'used x as a multiplication sign';
        }
        $s = trim($s);
        // A choice question: the option letters the learner named ("A and C").
        if (($req['item']['options'] ?? null) !== null && preg_match_all('/(?<![\p{L}])([a-h])(?![\p{L}])/u', $s, $lm) > 0) {
            $s = strtoupper(implode(',', array_unique($lm[1])));
            $notes[] = 'read the option letters';
        }
        if ($s === '' || preg_match('/[^0-9a-zA-Z+\-*\/=(). ,]/u', $s) === 1 || preg_match('/\p{L}{2,}/u', $s) === 1) {
            return ['kind' => 'unreadable', 'normalized_answer' => null, 'reading_note' => 'could not tell what the answer is', 'clarifying_question' => null,
                'reply' => 'I couldn\'t read that. Please write your answer again with numbers and symbols only.'];
        }
        $out = $answer($s, $notes === [] ? 'read as written' : implode('; ', $notes));
        $host = $req['host_reading'] ?? null;
        if ($host !== null && str_replace(' ', '', $host) !== str_replace(' ', '', $s)) {
            $out['clarifying_question'] = $asTimes
                ? 'When you wrote x between two numbers or letters, did you mean times?'
                : 'Could you write that answer again using only numbers and symbols?';
        }
        return $out;
    }

    /** Mirrors the host's own rules, so mock journeys follow the same path as before round 6. */
    private function decision(array $req): array
    {
        switch ($req['question']) {
            case 'READINESS':
                $f = $req['facts'];
                $ready = ($f['last_problem_faded'] && $f['last_problem_clean']) || $f['problems_completed'] >= 3;
                return ['question' => 'READINESS', 'decision' => $ready ? 'INDEPENDENT_CHECK' : 'ANOTHER_GUIDED_PROBLEM', 'choice' => null,
                    'reason' => $ready ? 'The last problem went smoothly or enough problems were done.' : 'One more guided problem first.'];
            case 'PREREQUISITE':
                return ['question' => 'PREREQUISITE', 'decision' => 'REPAIR_HERE', 'choice' => null, 'reason' => 'No error pattern points to a building block.'];
            default:
                return ['question' => 'SLIP', 'decision' => 'NOT_SLIP', 'choice' => null, 'reason' => 'No slip proposed.'];
        }
    }

    /** The mock "reads" the bound architecture and answers with its Section 18 line. */
    private function handshake(array $messages): string
    {
        $arch = $messages[0]['content'] ?? '';
        if (preg_match('/^(ACE_BOUND: [^\n]+)$/m', $arch, $m)) {
            return trim($m[1]);
        }
        return 'ACE_HALT: CONTRACT_CONFLICT | binding=NOT_ESTABLISHED';
    }

    private function context(array $messages): array
    {
        foreach ($messages as $m) {
            if ($m['role'] === 'user' && str_starts_with($m['content'], 'JSON ')) {
                $data = Json::decode(substr($m['content'], 5), 64);
                if (isset($data['TRUSTED_OUTPUT_ALLOCATION'])) {
                    return $data;
                }
            }
        }
        throw new \RuntimeException('Mock received no trusted context');
    }

    private function envelope(array $messages): array
    {
        $ctx = $this->context($messages);
        $alloc = $ctx['TRUSTED_OUTPUT_ALLOCATION'];
        $item = null;
        if ($alloc['required_message_type'] === 'QUESTION') {
            // Like a cooperative model, propose different numbers after each repair request.
            $repairs = count(array_filter($messages, fn ($m) => $m['role'] === 'user' && str_contains($m['content'], '"REPAIR_REQUEST"')));
            $item = $this->draftItem($alloc, $repairs);
        }
        $planned = $alloc['host_planned_transition'] ?? null;
        return [
            'architecture_version' => '1.3',
            'session_id' => $ctx['TRUSTED_STATE']['session_id'],
            'state_revision' => $ctx['TRUSTED_STATE']['revision'],
            'reply_to_event_id' => $ctx['TRUSTED_EVENT']['event_id'],
            'message_type' => $alloc['required_message_type'],
            'student_message' => $this->message($alloc),
            'item' => $item,
            'proposed_transition' => $planned === null ? null : [
                'to_mode' => $planned['to'],
                'target_skill_id' => $planned['skill_id'],
                'reason' => 'Following the host-selected operation.',
                'evidence_event_ids' => [],
            ],
            'evidence_proposals' => [],
            'resource_requests' => [],
            'teacher_report' => null,
            'step_proposal' => null,
            'issue' => null,
        ];
    }

    private function draftItem(array $alloc, int $repairs = 0): array
    {
        $templateId = $alloc['allowed_templates'][0]['template_id'];
        $expected = null;
        $responseType = $alloc['allowed_templates'][0]['response_type'];
        if (isset($alloc['fixed_parameters'])) {
            $params = $alloc['fixed_parameters'];
        } else {
            $template = $this->factory->get($templateId);
            // Like a cooperative model, it avoids an answer that is a number in the previous problem (round 6 refer-back replies).
            for ($i = 0; $i < 50; $i++) {
                $params = $template->generate(RandomSource::seeded('mock:' . $alloc['item_id'] . ($repairs > 0 ? ':repair' . $repairs : '') . ($i > 0 ? ':' . $i : '')), $alloc['avoid_parameters'] ?? null);
                if (\AlgebraEngine\Items\AcceptanceGate::avoidedAnswer($template->answerSpec($params), $alloc['avoid_answers'] ?? []) === null) {
                    break;
                }
            }
            $expected = $template->answerSpec($params)['expected'];
        }
        return [
            'item_id' => $alloc['item_id'],
            'item_version' => $alloc['item_version'],
            'curriculum_id' => 'ACE-ALGEBRA1',
            'curriculum_version' => '1.1.0',
            'skill_id' => $alloc['skill_id'],
            'role' => $alloc['role'],
            'component_id' => $alloc['component_id'],
            'variant_id' => $alloc['variant_id'],
            'prompt' => 'Rendered by host.',
            'response_type' => $responseType,
            'template_id' => $templateId,
            'parameters' => $params,
            'mathematical_objects' => new \stdClass(),
            'domain' => ['number_system' => 'RATIONAL', 'restrictions' => []],
            'answer_spec' => ['kind' => 'proposal', 'expected' => $expected, 'requested_form' => null, 'units' => null, 'tolerance' => null, 'rubric' => []],
            'checking' => ['requested_route' => 'DETERMINISTIC_TEMPLATE', 'required_capabilities' => [], 'scope' => 'Proposed; host checks.'],
            'representation_requirements' => [],
            'support_already_supplied' => [],
            'independent_eligible_proposal' => $alloc['role'] !== 'GUIDED',
        ];
    }

    /**
     * The simulated tutor's reply (round 6). It follows the allocation: a refer-back reply
     * before an independent item, a reply and lead-in otherwise. When no reply is due it
     * still writes a strategy cue, like the live model in rounds 3–4; the host discards it.
     */
    private function message(array $alloc): string
    {
        $mode = $alloc['reply_mode'] ?? 'REPLY';
        if ($mode === 'NOT_SHOWN') {
            return 'Work out the sizes first, then settle the sign.';
        }
        if ($alloc['operation'] === 'MICRO_CHECK') {
            return 'Most of that was right. Let\'s check one part of it.';
        }
        $parts = [];
        $reply = $alloc['reply_to'] ?? null;
        $facts = $alloc['feedback_facts'] ?? [];
        if ($reply !== null) {
            switch ($reply['moment']) {
                case 'CHECKED_ANSWER':
                    if ($reply['verdict'] === 'CORRECT') {
                        $parts[] = 'You\'re right.' . (($reply['rule'] ?? '') !== '' ? ' ' . $reply['rule'] : '');
                    } elseif ($reply['verdict'] === 'PARTIAL') {
                        $parts[] = 'That is equal, but it is not yet in the form the question asks for.';
                    } else {
                        $parts = array_merge($parts, $facts !== [] ? $facts : ($reply['facts'] ?? []));
                        $facts = [];
                        if ($parts === []) {
                            $parts[] = 'That is not it yet.';
                        }
                    }
                    break;
                case 'ASKED_FOR_ANSWER':
                    $parts[] = match ($reply['follows'] ?? '') {
                        'HINT' => EntryInterpreter::LINE_ASK_HINT,
                        'SMALLER' => EntryInterpreter::LINE_ASK_SMALLER,
                        'ANOTHER_WAY' => EntryInterpreter::LINE_ASK_ANOTHER_WAY,
                        'PREREQUISITE' => EntryInterpreter::LINE_ASK_PREREQUISITE,
                        default => EntryInterpreter::LINE_ASK_DIFFERENT_PROBLEM,
                    };
                    break;
                case 'MICRO_CHECK_CORRECT':
                    $parts[] = 'Yes.';
                    break;
            }
        }
        $parts = array_merge($parts, $facts);
        switch ($alloc['operation']) {
            case 'HINT':
            case 'EXPLANATION':
                $parts[] = $alloc['hint_focus'] ?? 'Look again at the structure of the question.';
                break;
            case 'GUIDED_STEP':
                if (!empty($alloc['representation_hint'])) {
                    $parts[] = $alloc['representation_hint'];
                }
                break;
            case 'SLIP_RETRY':
                $parts[] = 'Okay, try it again.';
                break;
        }
        return implode(' ', array_values(array_unique($parts)));
    }

    private function applyFault(array $env, array $fault): array
    {
        switch ($fault['type']) {
            case 'WRONG_KEY':
                if ($env['item'] !== null) {
                    $env['item']['answer_spec']['expected'] = $fault['expected'] ?? '999';
                }
                break;
            case 'TRANSITION':
                $env['proposed_transition'] = $fault['transition'];
                break;
            case 'EVIDENCE':
                $env['evidence_proposals'] = $fault['evidence'];
                break;
            case 'STUDENT_MESSAGE':
                $env['student_message'] = $fault['text'];
                break;
            case 'ITEM_PATCH':
                if ($env['item'] !== null) {
                    $env['item'] = array_merge($env['item'], $fault['patch']);
                }
                break;
            case 'IDENTITY':
                $env['session_id'] = 'ses_forged';
                break;
            case 'MESSAGE_TYPE':
                $env['message_type'] = $fault['message_type'];
                break;
            case 'STEP_PROPOSAL':
                $env['step_proposal'] = $fault['proposal'];
                break;
        }
        return $env;
    }

    private function nextFault(string $call): ?array
    {
        if ($this->faultFile !== '' && is_file($this->faultFile)) {
            $queued = json_decode((string) file_get_contents($this->faultFile), true) ?: [];
            $fault = self::take($queued, $call);
            if ($fault !== null) {
                file_put_contents($this->faultFile, json_encode($queued));
                return $fault;
            }
        }
        return self::take($this->faults, $call);
    }

    /** A fault names its call (binding, envelope, interpret or decision); without one, handshake faults go to binding and the rest to the envelope. */
    private static function take(array &$queue, string $call): ?array
    {
        foreach ($queue as $i => $f) {
            $target = $f['call'] ?? (in_array($f['type'], ['HALT', 'BAD_HANDSHAKE', 'OLD_HANDSHAKE'], true) ? 'binding' : 'envelope');
            if ($target === $call) {
                array_splice($queue, $i, 1);
                return $f;
            }
        }
        return null;
    }
}
