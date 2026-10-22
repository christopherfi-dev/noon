<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Items\AcceptanceGate;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\JsonSyntax;
use AlgebraEngine\Support\LearnerText;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Workflow\TransitionGuard;

/**
 * Runs the provider protocol: a text-mode binding call, then JSON envelope
 * calls validated by schema and by host semantics, with at most two bounded
 * repair calls per operation. A provider timeout or invalid output is a
 * system issue, never a learner failure.
 */
final class ModelCoordinator
{
    public const MAX_REPAIRS = 2;
    private const BANNED_WORDS = '/\b(master(ed|y|ing)?|hypothes[ie]s|verified|criterion met|passed)\b/i';

    private string $handshake;

    public function __construct(
        private ProviderAdapter $provider,
        private PromptAssembler $assembler,
        private SchemaValidator $schemas,
        private AcceptanceGate $gate,
        private CurriculumRepository $curriculum,
        private TransitionGuard $guard,
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

    public function expectedHandshake(): string
    {
        return $this->handshake;
    }

    public function architectureSha256(): string
    {
        return hash('sha256', $this->architecture);
    }

    /**
     * Binding: separate text-mode call, exact line required, one bounded
     * activation repair. The acknowledgement is not enforcement evidence.
     *
     * @param callable():?string $budget returns null when a call may proceed, else a failure code
     * @param callable(string,string,?ProviderResponse):void $log kind, outcome, response metadata
     */
    public function bind(callable $budget, callable $log): array
    {
        $messages = $this->assembler->bindingMessages($this->architecture);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $blocked = $budget();
            if ($blocked !== null) {
                return ['ok' => false, 'failure' => ['type' => 'BUDGET', 'detail' => $blocked]];
            }
            $resp = $this->provider->complete($messages, false);
            if ($resp->error !== null) {
                $log('binding', 'TRANSPORT_' . $resp->error, $resp);
                return ['ok' => false, 'failure' => ['type' => 'PROVIDER_UNAVAILABLE', 'detail' => $resp->error] + ($resp->diagnostics === null ? [] : ['transport' => $resp->diagnostics])];
            }
            $text = trim((string) $resp->content);
            if ($text === $this->handshake) {
                $log('binding', 'ACCEPTED', $resp);
                return ['ok' => true, 'binding' => [
                    'provider' => $this->provider->name(),
                    'model' => $this->provider->model(),
                    'architecture_sha256' => $this->architectureSha256(),
                    'acknowledged' => true,
                ], 'attempts' => $attempt + 1];
            }
            $log('binding', 'REJECTED', $resp);
            $messages[] = ['role' => 'assistant', 'content' => mb_substr($text, 0, 2000)];
            $messages[] = ['role' => 'user', 'content' => 'CONTROL: That reply is not the exact activation line. Respond with only the exact line from Section 18, or the exact halt line.'];
        }
        return ['ok' => false, 'failure' => ['type' => 'BINDING_NOT_ESTABLISHED', 'detail' => 'The provider did not return the exact architecture 1.2 activation line after one bounded repair.']];
    }

    /**
     * @param array<string,mixed> $context trusted blocks (already including TRUSTED_OUTPUT_ALLOCATION)
     * @param array<string,mixed> $expect identity, mode, guard data and planned transition
     * @param list<string> $fingerprints
     */
    public function generate(array $context, array $expect, callable $budget, callable $log, RandomSource $rng, array $fingerprints): array
    {
        $allocation = $context['TRUSTED_OUTPUT_ALLOCATION'];
        $messages = $this->assembler->envelopeMessages($this->architecture, $this->handshake, $context);
        $repairs = [];
        for ($attempt = 0; $attempt <= self::MAX_REPAIRS; $attempt++) {
            $blocked = $budget();
            if ($blocked !== null) {
                return self::failure('BUDGET', $blocked, $repairs);
            }
            $resp = $this->provider->complete($messages, true);
            if ($resp->error !== null) {
                $log('envelope', 'TRANSPORT_' . $resp->error, $resp);
                return self::failure('PROVIDER_UNAVAILABLE', $resp->error, $repairs, $resp->diagnostics);
            }
            [$envelope, $raw, $errors] = $this->parse($resp);
            $result = null;
            if ($errors === []) {
                $result = $this->check($envelope, $raw, $allocation, $expect, $rng, $fingerprints);
                $errors = $result['errors'];
            }
            if ($errors === []) {
                $log('envelope', $attempt === 0 ? 'ACCEPTED' : 'ACCEPTED_AFTER_REPAIR', $resp);
                return ['ok' => true, 'repairs' => $repairs] + $result;
            }
            $log('envelope', 'REJECTED', $resp);
            $repairs[] = ['attempt' => $attempt, 'errors' => $errors];
            if ($attempt < self::MAX_REPAIRS) {
                $messages = $this->assembler->repairMessages($messages, $resp->content, $errors, self::MAX_REPAIRS - $attempt - 1);
            }
        }
        return self::failure('INVALID_OUTPUT', 'The provider output failed host acceptance after ' . self::MAX_REPAIRS . ' repair attempts.', $repairs);
    }

    /**
     * R4-A: a guided step presented by the host alone (a re-ask). The host builds
     * the item through the same gate; no provider call, no model text.
     */
    public function hostOnly(array $allocation, RandomSource $rng, array $fingerprints): array
    {
        $draft = [];
        foreach (['item_id', 'item_version', 'skill_id', 'role', 'variant_id', 'component_id'] as $k) {
            $draft[$k] = $allocation[$k];
        }
        $draft += ['template_id' => AcceptanceGate::GUIDED_TEMPLATE, 'response_type' => $allocation['guided_step']['response_type'], 'parameters' => $allocation['fixed_parameters']];
        $accepted = $this->gate->accept($draft, $allocation, $fingerprints, $rng);
        if (!$accepted['ok']) {
            throw new \LogicException('Host-built guided step failed the gate: ' . implode('; ', $accepted['errors']));
        }
        return ['ok' => true, 'repairs' => [], 'proposals' => [], 'student_message' => ''] + $accepted;
    }

    /** @return array{0:?array,1:mixed,2:list<string>} associative form, raw object form, errors */
    private function parse(ProviderResponse $resp): array
    {
        $content = $resp->content;
        // Check truncation first: a reasoning model that hits the token limit returns empty content.
        if ($resp->finishReason === 'length') {
            return [null, null, ['output was truncated (finish_reason=length); return a shorter complete JSON object']];
        }
        if ($content === null || trim($content) === '') {
            return [null, null, ['empty content']];
        }
        if (strlen($content) > $this->maxBytes) {
            return [null, null, ['output exceeds the size limit']];
        }
        try {
            $decoded = Json::decode(trim($content), 32);
            $raw = Json::decodeObject(trim($content), 32);
        } catch (\JsonException $e) {
            // Round 4 ruling 2: name the parser's error and the character position.
            $at = JsonSyntax::firstError(trim($content));
            return [null, null, ['output is not valid JSON: ' . $e->getMessage() . ($at === null ? '' : ' (character ' . $at['position'] . ': ' . $at['message'] . ')')]];
        }
        if (!is_array($decoded) || !is_object($raw)) {
            return [null, null, ['output must be one JSON object']];
        }
        return [$decoded, $raw, []];
    }

    private function check(array $env, object $raw, array $allocation, array $expect, RandomSource $rng, array $fingerprints): array
    {
        $errors = $this->schemas->errors($raw, 'model-envelope.schema.json');
        $out = ['errors' => [], 'envelope' => $env, 'record' => null, 'fingerprint' => null, 'flags' => [], 'host_generated' => false, 'student_message' => '', 'proposals' => []];
        if ($errors !== []) {
            $out['errors'] = $errors;
            return $out;
        }
        if ($env['session_id'] !== $expect['session_id'] || $env['state_revision'] !== $expect['revision'] || $env['reply_to_event_id'] !== $expect['event_id']) {
            $errors[] = 'identity fields must equal the supplied session_id, state_revision and reply_to_event_id';
        }
        if ($env['message_type'] !== $allocation['required_message_type']) {
            $errors[] = 'message_type must be ' . $allocation['required_message_type'];
        }
        if ($env['message_type'] === 'QUESTION' && $env['item'] === null) {
            $errors[] = 'QUESTION requires an item';
        }
        if ($env['message_type'] !== 'QUESTION' && $env['item'] !== null) {
            $errors[] = 'item must be null unless message_type is QUESTION';
        }
        if ($env['teacher_report'] !== null) {
            $errors[] = 'teacher_report must be null for this operation';
        }
        $message = $env['student_message'];
        $shown = $expect['learner_text_shown'] ?? true;
        // R5-A: text proposed with an independent item is discarded unread, so learner-text rules do not apply to it.
        if ($shown && str_contains($message, '?')) {
            $errors[] = 'student_message must not contain a question; the host shows the single question from the accepted item';
        }
        if ($shown && mb_strlen($message) > 1200) {
            $errors[] = 'student_message is too long (limit 1200 characters)';
        }
        foreach ($shown ? $this->guard->states() : [] as $state) {
            if (str_contains($message, $state)) {
                $errors[] = 'student_message must not name internal states';
                break;
            }
        }
        if ($shown && preg_match(self::BANNED_WORDS, $message)) {
            $errors[] = 'student_message must not make mastery, verification or hypothesis claims';
        }
        // R5-B: internal vocabulary, as whole words or phrases. Round 5.1 (P3): only the host says when work is unaided.
        $terms = $shown ? LearnerText::internalTerms($message) : [];
        if ($terms !== []) {
            $errors[] = 'student_message must speak to the learner in plain words; it uses internal terms: ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $terms));
        }
        $claims = $shown ? array_values(array_diff(LearnerText::modelTextTerms($message), $terms)) : [];
        if ($claims !== []) {
            $errors[] = 'student_message must not say whether work is done without help; the host says that. It uses: ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $claims));
        }
        if ($shown && $expect['disclosure_guard']) {
            foreach (DisclosureGuard::violations($message, $expect['protected_answers'], $expect['completed_answers'] ?? []) as $v) {
                $errors[] = $v;
            }
        }
        if ($errors === [] && $env['message_type'] === 'QUESTION') {
            $accepted = $this->gate->accept($env['item'], $allocation, $fingerprints, $rng);
            if (!$accepted['ok']) {
                $errors = array_merge($errors, $accepted['errors']);
            } else {
                $out['record'] = $accepted['record'];
                $out['fingerprint'] = $accepted['fingerprint'];
                $out['flags'] = $accepted['flags'];
                $out['host_generated'] = $accepted['host_generated'];
            }
        }
        $out['errors'] = $errors;
        if ($errors === []) {
            $out['student_message'] = $shown ? $message : '';
            $out['discarded_message_chars'] = $shown ? 0 : mb_strlen(trim($message));
            $out['proposals'] = $this->reviewProposals($env, $expect);
        }
        return $out;
    }

    /**
     * Proposals never change state by themselves. Each is recorded as
     * adopted (it matches the host plan), accepted-as-proposal or rejected.
     *
     * @return list<array<string,mixed>>
     */
    private function reviewProposals(array $env, array $expect): array
    {
        $out = [];
        $t = $env['proposed_transition'];
        if ($t !== null) {
            $planned = $expect['planned_transition'];
            $reason = null;
            if (!$this->guard->allowed($expect['mode'], $t['to_mode'])) {
                $reason = 'transition ' . $expect['mode'] . ' -> ' . $t['to_mode'] . ' is not permitted by the workflow policy';
            } elseif ($t['target_skill_id'] !== null && !$this->curriculum->has($t['target_skill_id'])) {
                $reason = 'target skill ' . $t['target_skill_id'] . ' is not a canonical curriculum record';
            } elseif ($t['to_mode'] === 'PREREQUISITE_CHECK' && $t['target_skill_id'] !== null && $expect['current_skill'] !== null
                && !$this->curriculum->isDeclaredPrerequisite($expect['current_skill'], $t['target_skill_id'])) {
                $reason = $t['target_skill_id'] . ' is not a declared prerequisite of ' . $expect['current_skill'];
            } elseif ($planned === null || $planned['to'] !== $t['to_mode'] || ($t['target_skill_id'] !== null && $t['target_skill_id'] !== $planned['skill_id'])) {
                $reason = 'proposal differs from the host-selected operation and lacks host-accepted evidence';
            }
            $out[] = ['kind' => 'transition', 'proposal' => $t, 'status' => $reason === null ? 'ADOPTED_MATCHES_HOST_PLAN' : 'REJECTED', 'reason' => $reason];
        }
        foreach ($env['evidence_proposals'] as $p) {
            $reason = null;
            foreach ($p['evidence_event_ids'] as $id) {
                if (!in_array($id, $expect['known_event_ids'], true)) {
                    $reason = 'references an event that is not in the accepted ledger';
                    break;
                }
            }
            if ($reason === null && preg_match(self::BANNED_WORDS, $p['statement'])) {
                $reason = 'promotion or verification claims are host-produced only';
            }
            if ($reason === null && in_array($p['hypothesis_status'], ['SUPPORTED_FOR_OBSERVED_PATTERN', 'CONTRADICTED'], true)) {
                $reason = 'hypothesis status changes require host-accepted evidence';
            }
            $out[] = ['kind' => 'evidence', 'proposal' => $p, 'status' => $reason === null ? 'RECORDED_AS_MODEL_PROPOSAL' : 'REJECTED', 'reason' => $reason];
        }
        foreach ($env['resource_requests'] as $r) {
            $ok = $this->curriculum->has($r['skill_id']);
            $out[] = ['kind' => 'resource', 'proposal' => $r, 'status' => $ok ? 'RECORDED_NOT_ACTED_ON' : 'REJECTED', 'reason' => $ok ? 'beta assembles a bounded frontier itself' : 'unknown skill id'];
        }
        if ($env['issue'] !== null) {
            $out[] = ['kind' => 'issue', 'proposal' => $env['issue'], 'status' => 'RECORDED_NOT_ACTED_ON', 'reason' => 'model-raised issue recorded for review'];
        }
        return $out;
    }

    private static function failure(string $type, string $detail, array $repairs, ?array $transport = null): array
    {
        return ['ok' => false, 'failure' => ['type' => $type, 'detail' => $detail] + ($transport === null ? [] : ['transport' => $transport]), 'repairs' => $repairs];
    }
}
