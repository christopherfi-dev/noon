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
 * The AI calls (round 6 §3, round 7 §4). A text-mode binding call once per
 * session; then per learner turn one reply call (and, for an entry only the AI
 * can read, a reading call first). Every envelope is checked after the call:
 * schema, identity, the output contract's field rules, and the proposed
 * problem through ProblemVerifier. Only those failures get a repair: exactly
 * one repair call, and if that fails too the caller holds the turn. The text
 * guards run on the accepted envelope and are returned as findings to log;
 * they never cause a repair. A problem or envelope that still fails after
 * its repair holds the turn (round 9 §3.1). The provider's reasoning text is
 * never read.
 */
final class TutorCoordinator
{
    public const MAX_REPAIRS = 1;
    /** Round 9 §4.1: no longer returned by the model; code attaches them. */
    public const IDENTITY_FIELDS = ['session_id', 'state_revision', 'reply_to_event_id'];
    /** Round 10 §4.1: fields code sets; a reply that still carries them has them ignored. */
    public const CODE_FIELDS = ['message_type'];
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
        return ['ok' => false, 'failure' => ['type' => 'BINDING_NOT_ESTABLISHED', 'detail' => 'The provider did not return the exact architecture 1.8 activation line after one repair.']];
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
     * @return array{ok:bool,failure?:array,invalid?:bool,repairs:list<array>,message?:string,problem?:?array,move?:?array,proposals?:list<array>,
     *   findings?:list<array>,card?:?array,issue?:?array,ai_prompt?:string,dropped_prompt_chars?:int,discarded_chars?:int}
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
        return ['ok' => false, 'invalid' => true, 'repairs' => $repairs, 'discarded_chars' => $discarded];
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
        // Round 9 §4.2: code attaches the identity captured with the request; any identity fields the model still sends are ignored (owner's decision).
        $out = ['errors' => [], 'message' => '', 'message_type' => null, 'problem' => null, 'move' => null, 'proposals' => [], 'findings' => [], 'card' => null,
            'issue' => null, 'ai_prompt' => '', 'dropped_prompt_chars' => 0, 'reading' => null,
            'identity' => ['session_id' => $x['session_id'], 'state_revision' => $x['revision'], 'reply_to_event_id' => $x['event_id'], 'open_problem' => $x['open_problem'] ?? null]];
        foreach (array_merge(self::IDENTITY_FIELDS, self::CODE_FIELDS) as $field) {
            unset($raw->{$field}, $env[$field]);
        }
        $errors = $this->schemas->errors($raw, 'tutor-envelope.schema.json');
        if ($errors !== []) {
            $out['errors'] = $errors;
            return $out;
        }
        if ($x['mode'] === 'READING') {
            if ($env['reading'] === null || $env['item'] !== null) {
                $errors[] = 'for a READING turn return item null and a reading object';
            }
            $out['message_type'] = 'CONTROL';
            $out['errors'] = $errors;
            $out['reading'] = $env['reading'];
            return $out;
        }
        if ($env['reading'] !== null) {
            $errors[] = 'reading must be null unless TURN.kind is READING';
        }
        $item = $env['item'];
        $message = trim($env['student_message']);
        if ($message === '' && $item === null) {
            $errors[] = 'student_message must not be empty when no problem is posed';
        }
        $problem = null;
        $move = $env['proposed_transition'];
        $helpMove = $move !== null && in_array($move['to_mode'], ['HELP', 'HINT'], true);
        if ($item !== null) {
            $practiceWithHelp = ($x['practice_with_help'] ?? false) && $helpMove && strtolower((string) ($item['purpose'] ?? '')) === 'practice';
            if (!($x['allow_item'] ?? true) && !$practiceWithHelp) {
                $errors[] = 'no problem may be posed this turn: a check question is still open (ALLOWED.may_pose_problem is false); during an open check problem only a reply whose to_mode is HELP or HINT may carry an item, with purpose practice';
            } elseif (strtolower((string) ($item['purpose'] ?? '')) === 'check' && !($x['allow_check'] ?? true)) {
                $errors[] = 'a check item is valid only when ALLOWED.may_pose_check is true';
            } else {
                $ctx = [
                    'recipes' => $x['recipes'] ?? [], 'used_fingerprints' => $x['used_fingerprints'] ?? [],
                    'retry_target' => $x['retry_target'] ?? null, 'limits' => $x['limits'] ?? [], 'used_number_sets' => $x['used_number_sets'] ?? [],
                    'original' => $x['original'] ?? null,
                ];
                $v = $this->verifier->verify($item, $ctx);
                if (!$v['ok']) {
                    foreach ($v['errors'] as $e) {
                        $errors[] = 'item: ' . $e;
                    }
                } else {
                    $problem = $v['problem'];
                    $out['dropped_prompt_chars'] = $v['dropped_prompt_chars'];
                    $out['ai_prompt'] = mb_substr(trim($item['prompt']), 0, 600);
                }
            }
        }
        if ($move !== null) {
            if ($move['target_skill_id'] !== null && !BetaScope::contains($move['target_skill_id'])) {
                $errors[] = 'proposed_transition.target_skill_id must be one of the ten skills in CURRICULUM';
            }
            if ($move['to_mode'] === 'SLIP_CHECK' && !($x['slip_allowed'] ?? false)) {
                $errors[] = 'SLIP_CHECK is only possible right after an answer that code found eligible for the slip pause (SLIP.eligible_for_pause is false)';
            }
            if ($move['to_mode'] === 'SLIP_CHECK' && $problem !== null && $problem['purpose'] !== 'PRACTICE') {
                $errors[] = 'a slip check is one short practice question, not a check question';
            }
        }
        $out['errors'] = $errors;
        if ($errors !== []) {
            return $out;
        }
        // Accepted. The detectors log; nothing they find changes the text (round 7 §4).
        $protected = $x['protected'] ?? [];
        if ($problem !== null) {
            $protected[] = $problem;
        }
        // A helped check brought back as the original problem is practice (round 9 §4.3): no check card follows.
        $card = $problem !== null && $problem['purpose'] === 'CHECK' && ($problem['source'] ?? null) !== 'ORIGINAL';
        $out['findings'] = TextGuard::findings($message, $protected, $x['verdict'] ?? null, $card);
        if ($card) {
            $out['card'] = TextGuard::beforeCheckCard($message, $problem, $x['previous'] ?? []);
            $out['findings'] = array_merge($out['findings'], $out['card']['findings']);
        }
        $out['message'] = $message;
        $out['problem'] = $problem;
        // Round 10 §4.1: code sets message_type on every accepted reply.
        $out['message_type'] = $problem !== null ? 'QUESTION' : 'FEEDBACK';
        $out['move'] = $move;
        $out['issue'] = $env['issue'];
        $out['proposals'] = $this->reviewProposals($env, $x['known_event_ids'] ?? []);
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
            $conflict = $env['issue']['type'] === 'VERIFICATION_CONFLICT';
            $out[] = ['kind' => 'issue', 'proposal' => $env['issue'], 'status' => $conflict ? 'RECORDED_ATTEMPT_HELD' : 'RECORDED_NOT_ACTED_ON',
                'reason' => $conflict ? 'a verdict conflict: the answered attempt is held for review (round 7 §5)' : 'model-raised issue recorded for review'];
        }
        return $out;
    }
}
