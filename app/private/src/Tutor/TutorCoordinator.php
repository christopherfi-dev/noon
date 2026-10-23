<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Provider\ProviderAdapter;
use AlgebraEngine\Provider\ProviderResponse;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\JsonSyntax;

/**
 * Round 6 §3: the AI calls. A text-mode binding call once per session; then
 * per learner turn one reply call (and, for an entry only the AI can read, a
 * reading call first). Every envelope is checked after the call: schema,
 * identity, the text guards, the verdict rule, the check-card rule, and the
 * proposed problem through ProblemVerifier. A failing envelope gets exactly
 * one repair call; if that fails too, the caller uses the code fallback. The
 * provider's reasoning text is never read.
 */
final class TutorCoordinator
{
    public const MAX_REPAIRS = 1;
    private const PROPOSAL_CLAIMS = '/\b(master(ed|y|ing)?|goal\s+met|met\s+the\s+goal|criterion\s+met|verified|passed|learned\s+this)\b/i';

    private string $handshake;

    public function __construct(
        private ProviderAdapter $provider,
        private TutorPrompt $prompt,
        private SchemaValidator $schemas,
        private ProblemVerifier $verifier,
        private string $architecture,
        private int $maxBytes = 65536,
    ) {
        if (!preg_match('/^(ACE_BOUND: [^\n]+)$/m', $architecture, $m)) {
            throw new \RuntimeException('Architecture text has no activation line');
        }
        $this->handshake = trim($m[1]);
    }

    public function provider(): ProviderAdapter
    {
        return $this->provider;
    }

    public function handshake(): string
    {
        return $this->handshake;
    }

    public function architectureSha256(): string
    {
        return hash('sha256', $this->architecture);
    }

    /**
     * @param callable():?string $budget null when a call may proceed, else a failure code
     * @param callable(string,string,?ProviderResponse,array):void $log purpose, outcome, response, reasoning setting
     */
    public function bind(callable $budget, callable $log, array $reasoning): array
    {
        $messages = $this->prompt->bindingMessages($this->architecture);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if (($blocked = $budget()) !== null) {
                return ['ok' => false, 'failure' => ['type' => 'BUDGET', 'detail' => $blocked]];
            }
            $resp = $this->provider->complete($messages, false, $reasoning);
            if ($resp->error !== null) {
                $log('BINDING', 'TRANSPORT_' . $resp->error, $resp, $reasoning);
                return ['ok' => false, 'failure' => ['type' => 'PROVIDER_UNAVAILABLE', 'detail' => $resp->error] + ($resp->diagnostics === null ? [] : ['transport' => $resp->diagnostics])];
            }
            $text = trim((string) $resp->content);
            if ($text === $this->handshake) {
                $log('BINDING', 'ACCEPTED', $resp, $reasoning);
                return ['ok' => true, 'binding' => ['provider' => $this->provider->name(), 'model' => $this->provider->model(),
                    'architecture_sha256' => $this->architectureSha256(), 'acknowledged' => true]];
            }
            $log('BINDING', 'REJECTED', $resp, $reasoning);
            $messages[] = ['role' => 'assistant', 'content' => mb_substr($text, 0, 2000)];
            $messages[] = ['role' => 'user', 'content' => 'CONTROL: That reply is not the exact activation line. Respond with only the exact line from Section 16, or the exact halt line.'];
        }
        return ['ok' => false, 'failure' => ['type' => 'BINDING_NOT_ESTABLISHED', 'detail' => 'The provider did not return the exact architecture 1.3 activation line after one repair.']];
    }

    /**
     * The reading call (§3.4): the AI's reading of an entry code would not parse.
     *
     * @return array{ok:bool,reading?:array,failure?:array,invalid?:bool,repairs:list<array>}
     */
    public function read(array $context, array $expect, array $reasoning, callable $budget, callable $log): array
    {
        return $this->run($context, $expect + ['mode' => 'READING'], $reasoning, $budget, $log);
    }

    /**
     * The reply call: one envelope, at most one next problem, verified by code.
     *
     * @return array{ok:bool,failure?:array,fallback?:bool,repairs:list<array>,message?:string,problem?:?array,move?:?array,proposals?:list<array>,forward?:list<string>,dropped_prompt_chars?:int,discarded_chars?:int}
     */
    public function reply(array $context, array $expect, array $reasoning, callable $budget, callable $log): array
    {
        return $this->run($context, $expect + ['mode' => 'REPLY'], $reasoning, $budget, $log);
    }

    private function run(array $context, array $expect, array $reasoning, callable $budget, callable $log): array
    {
        $messages = $this->prompt->messages($this->architecture, $this->handshake, $context);
        $repairs = [];
        $purpose = $expect['mode'];
        $discarded = 0;
        for ($attempt = 0; $attempt <= self::MAX_REPAIRS; $attempt++) {
            if (($blocked = $budget()) !== null) {
                return ['ok' => false, 'failure' => ['type' => 'BUDGET', 'detail' => $blocked], 'repairs' => $repairs];
            }
            $callPurpose = $attempt === 0 ? $purpose : 'REPAIR';
            $resp = $this->provider->complete($messages, true, $reasoning);
            if ($resp->error !== null) {
                $log($callPurpose, 'TRANSPORT_' . $resp->error, $resp, $reasoning);
                return ['ok' => false, 'failure' => ['type' => 'PROVIDER_UNAVAILABLE', 'detail' => $resp->error] + ($resp->diagnostics === null ? [] : ['transport' => $resp->diagnostics]), 'repairs' => $repairs];
            }
            [$env, $raw, $errors] = $this->parse($resp);
            $result = null;
            if ($errors === []) {
                $result = $this->check($env, $raw, $expect);
                $errors = $result['errors'];
            }
            if ($errors === []) {
                $log($callPurpose, $attempt === 0 ? 'ACCEPTED' : 'ACCEPTED_AFTER_REPAIR', $resp, $reasoning);
                return ['ok' => true, 'repairs' => $repairs] + $result;
            }
            $log($callPurpose, 'REJECTED', $resp, $reasoning);
            $repairs[] = ['attempt' => $attempt, 'errors' => $errors, 'text' => is_array($env) ? mb_substr((string) ($env['student_message'] ?? ''), 0, 1000) : null];
            $discarded += is_array($env) ? mb_strlen((string) ($env['student_message'] ?? '')) : 0;
            if ($attempt < self::MAX_REPAIRS) {
                $messages = $this->prompt->repair($messages, $resp->content, $errors);
            }
        }
        return ['ok' => false, 'fallback' => true, 'invalid' => true, 'repairs' => $repairs, 'discarded_chars' => $discarded];
    }

    /** @return array{0:?array,1:mixed,2:list<string>} */
    private function parse(ProviderResponse $resp): array
    {
        if ($resp->finishReason === 'length') {
            return [null, null, ['output was truncated (finish_reason=length); return a shorter complete JSON object']];
        }
        $content = trim((string) $resp->content);
        if ($content === '') {
            return [null, null, ['empty content']];
        }
        if (strlen($content) > $this->maxBytes) {
            return [null, null, ['output exceeds the size limit']];
        }
        try {
            $decoded = Json::decode($content, 32);
            $raw = Json::decodeObject($content, 32);
        } catch (\JsonException $e) {
            $at = JsonSyntax::firstError($content);
            return [null, null, ['output is not valid JSON: ' . $e->getMessage() . ($at === null ? '' : ' (character ' . $at['position'] . ': ' . $at['message'] . ')')]];
        }
        if (!is_array($decoded) || !is_object($raw)) {
            return [null, null, ['output must be one JSON object']];
        }
        return [$decoded, $raw, []];
    }

    /**
     * @param array{mode:string,session_id:string,revision:int,event_id:string,allow_item?:bool,protected?:list<array>,
     *   verdict?:?string,slip_allowed?:bool,retry_target?:?array,recipes?:array,used_fingerprints?:list<string>,limits?:array,known_event_ids?:list<string>,entry_variables?:list<string>} $x
     */
    private function check(array $env, object $raw, array $x): array
    {
        $out = ['errors' => [], 'message' => '', 'problem' => null, 'move' => null, 'proposals' => [], 'forward' => [], 'dropped_prompt_chars' => 0, 'reading' => null];
        $errors = $this->schemas->errors($raw, 'tutor-envelope.schema.json');
        if ($errors !== []) {
            $out['errors'] = $errors;
            return $out;
        }
        if ($env['session_id'] !== $x['session_id'] || $env['state_revision'] !== $x['revision'] || $env['reply_to_event_id'] !== $x['event_id']) {
            $errors[] = 'session_id, state_revision and reply_to_event_id must equal the values in TRUSTED_SESSION';
        }
        if ($x['mode'] === 'READING') {
            if ($env['message_type'] !== 'CONTROL' || $env['reading'] === null || $env['item'] !== null) {
                $errors[] = 'for a READING turn return message_type CONTROL, item null and a reading object';
            }
            $out['errors'] = $errors;
            $out['reading'] = $env['reading'];
            return $out;
        }
        if ($env['reading'] !== null) {
            $errors[] = 'reading must be null unless TURN.kind is READING';
        }
        $item = $env['item'];
        if (!in_array($env['message_type'], ['QUESTION', 'FEEDBACK'], true) || ($env['message_type'] === 'QUESTION') !== ($item !== null)) {
            $errors[] = 'message_type must be QUESTION when item is present and FEEDBACK otherwise';
        }
        $message = trim($env['student_message']);
        if ($message === '' && $item === null) {
            $errors[] = 'student_message must not be empty when no problem is posed';
        }
        $problem = null;
        if ($item !== null) {
            if (!($x['allow_item'] ?? true)) {
                $errors[] = 'no problem may be posed this turn: a check question is still open (ALLOWED.may_pose_problem is false)';
            } else {
                $v = $this->verifier->verify($item, [
                    'recipes' => $x['recipes'] ?? [], 'used_fingerprints' => $x['used_fingerprints'] ?? [],
                    'retry_target' => $x['retry_target'] ?? null, 'limits' => $x['limits'] ?? [], 'used_number_sets' => $x['used_number_sets'] ?? [],
                ]);
                if (!$v['ok']) {
                    foreach ($v['errors'] as $e) {
                        $errors[] = 'item: ' . $e;
                    }
                } else {
                    $problem = $v['problem'];
                    $out['dropped_prompt_chars'] = $v['dropped_prompt_chars'];
                }
            }
        }
        $protected = $x['protected'] ?? [];
        if ($problem !== null) {
            $protected[] = $problem;
        }
        foreach (TextGuard::violations($message, $protected) as $v) {
            $errors[] = 'student_message: ' . $v;
        }
        if (($x['verdict'] ?? null) !== null) {
            foreach (TextGuard::contradicts($message, $x['verdict']) as $v) {
                $errors[] = 'student_message: ' . $v;
            }
        }
        if ($problem !== null && $problem['purpose'] === 'CHECK') {
            $card = TextGuard::beforeCheckCard($message, $problem);
            foreach ($card['violations'] as $v) {
                $errors[] = 'student_message: ' . $v;
            }
            $out['forward'] = $card['forward'];
        }
        $move = $env['proposed_transition'];
        if ($move !== null) {
            if ($move['target_skill_id'] !== null && !BetaScope::contains($move['target_skill_id'])) {
                $errors[] = 'proposed_transition.target_skill_id must be one of the ten skills in CURRICULUM';
            }
            if ($move['to_mode'] === 'SLIP_CHECK' && !($x['slip_allowed'] ?? false)) {
                $errors[] = 'SLIP_CHECK is only possible right after a slip that code confirmed (SLIP.confirmed is false); code found no slip here';
            }
            if ($move['to_mode'] === 'SLIP_CHECK' && $problem !== null && $problem['purpose'] !== 'PRACTICE') {
                $errors[] = 'a slip check is one short practice question, not a check question';
            }
        }
        $out['errors'] = $errors;
        if ($errors === []) {
            $out['message'] = $message;
            $out['problem'] = $problem;
            $out['move'] = $move;
            $out['proposals'] = $this->reviewProposals($env, $x['known_event_ids'] ?? []);
        }
        return $out;
    }

    /** Proposals are recorded as the AI's, never acted on; claims about goals or mastery are rejected. */
    private function reviewProposals(array $env, array $known): array
    {
        $out = [];
        foreach ($env['evidence_proposals'] as $p) {
            $reason = null;
            foreach ($p['evidence_event_ids'] as $id) {
                if (!in_array($id, $known, true)) {
                    $reason = 'references an event that is not in the accepted ledger';
                    break;
                }
            }
            if ($reason === null && preg_match(self::PROPOSAL_CLAIMS, $p['statement'])) {
                $reason = 'goal, mastery and verification claims are code-produced only';
            }
            if ($reason === null && in_array($p['hypothesis_status'], ['SUPPORTED_FOR_OBSERVED_PATTERN', 'CONTRADICTED'], true)) {
                $reason = 'a hypothesis status change needs code-accepted evidence';
            }
            $out[] = ['kind' => 'evidence', 'proposal' => $p, 'status' => $reason === null ? 'RECORDED_AS_MODEL_PROPOSAL' : 'REJECTED', 'reason' => $reason];
        }
        foreach ($env['resource_requests'] as $r) {
            $out[] = ['kind' => 'resource', 'proposal' => $r, 'status' => 'RECORDED_NOT_ACTED_ON', 'reason' => 'the curriculum slice is supplied with every request'];
        }
        if ($env['issue'] !== null) {
            $out[] = ['kind' => 'issue', 'proposal' => $env['issue'], 'status' => 'RECORDED_NOT_ACTED_ON', 'reason' => 'model-raised issue recorded for review'];
        }
        return $out;
    }
}
