<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Items\AcceptanceGate;
use AlgebraEngine\Items\StepProposalCheck;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\JsonSyntax;
use AlgebraEngine\Support\LearnerText;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Workflow\ReadingGuard;
use AlgebraEngine\Workflow\TransitionGuard;

/**
 * Runs the provider protocol: a text-mode binding call, then JSON calls
 * validated by schema and by host semantics. Round 6 (architecture 1.3): the
 * model interprets entries (INTERPRET), writes replies with the next item
 * (the envelope) and proposes teaching decisions (DECIDE); each output is
 * accepted only after the host checks. Every call counts toward the
 * per-entry limit the engine's budget enforces, so output repair is bounded
 * by it. A provider timeout or invalid output is a system issue, never a
 * learner failure.
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
        return ['ok' => false, 'failure' => ['type' => 'BINDING_NOT_ESTABLISHED', 'detail' => 'The provider did not return the exact architecture 1.3 activation line after one bounded repair.']];
    }

    /**
     * The reply-and-item call, with bounded output repair. Round 6: every call goes through
     * $budget, which also enforces the per-entry call limit; when it leaves no call for a
     * repair, a rejected output becomes INVALID_OUTPUT (a hold), never a learner failure.
     *
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
                return $blocked === 'ENTRY_CALL_LIMIT' && $repairs !== []
                    ? self::failure('INVALID_OUTPUT', 'The provider output failed host acceptance and the per-entry call limit leaves no repair call.', $repairs)
                    : self::failure('BUDGET', $blocked, $repairs);
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
     * Round 6 INTERPRET: one call, no repair. The host checks the shape here and the
     * reading itself (ReadingGuard, the item's checker) in the engine; learner-visible
     * texts pass the learner-text guards or are not shown.
     *
     * @param array<string,mixed> $request INTERPRET_REQUEST block
     * @param array<string,mixed> $expect identity plus protected_answers and completed_answers for the texts
     * @return array{ok:bool,errors:list<string>,reading:?array,reply_errors:list<string>,clarifying_errors:list<string>,failure:?array}
     */
    public function interpret(array $request, array $expect, callable $budget, callable $log): array
    {
        $out = ['ok' => false, 'errors' => [], 'reading' => null, 'reply_errors' => [], 'clarifying_errors' => [], 'failure' => null];
        $raw = $this->single('interpret', $this->assembler->interpretMessages($this->architecture, $this->handshake, $request), $budget, $log, $out);
        if ($raw === null) {
            return $out;
        }
        [$env, $obj] = $raw;
        $errors = $this->schemas->errors($obj, 'interpretation.schema.json');
        if ($errors === []) {
            $errors = $this->identityErrors($env, $expect);
            if ($env['kind'] === 'answer' && $env['normalized_answer'] === null) {
                $errors[] = 'kind answer needs normalized_answer';
            }
        }
        if ($errors !== []) {
            $out['errors'] = $errors;
            $out['failure'] = ['type' => 'INVALID_OUTPUT', 'detail' => 'interpretation rejected'];
            return $out;
        }
        $protected = $expect['protected_answers'] ?? [];
        $completed = $expect['completed_answers'] ?? [];
        $out['reply_errors'] = trim($env['reply']) === '' ? [] : $this->learnerTextErrors($env['reply'], $protected, $completed, 'reply');
        $out['clarifying_errors'] = $env['clarifying_question'] === null ? [] : $this->learnerTextErrors($env['clarifying_question'], $protected, $completed, 'clarifying_question');
        $out['ok'] = true;
        $out['reading'] = $env;
        return $out;
    }

    /**
     * Round 6 DECIDE: one teaching decision chosen from the options and choices the host supplies.
     *
     * @param array<string,mixed> $request DECISION_REQUEST block: question, options, choices
     * @return array{ok:bool,errors:list<string>,decision:?string,choice:?string,reason:string,failure:?array}
     */
    public function decide(array $request, array $expect, callable $budget, callable $log): array
    {
        $out = ['ok' => false, 'errors' => [], 'decision' => null, 'choice' => null, 'reason' => '', 'failure' => null];
        $raw = $this->single('decision', $this->assembler->decisionMessages($this->architecture, $this->handshake, $request), $budget, $log, $out);
        if ($raw === null) {
            return $out;
        }
        [$env, $obj] = $raw;
        $errors = $this->schemas->errors($obj, 'teaching-decision.schema.json');
        if ($errors === []) {
            $errors = $this->identityErrors($env, $expect);
            if ($env['question'] !== $request['question']) {
                $errors[] = 'question must be ' . $request['question'];
            }
            if (!in_array($env['decision'], $request['options'], true)) {
                $errors[] = 'decision must be one of ' . implode(', ', $request['options']);
            }
            $choices = array_column($request['choices'] ?? [], 'id');
            if (in_array($env['decision'], ['SLIP', 'CHECK_PREREQUISITE'], true) && !in_array($env['choice'], $choices, true)) {
                $errors[] = 'choice must be one of the supplied choices: ' . implode(', ', $choices);
            }
        }
        if ($errors !== []) {
            $out['errors'] = $errors;
            $out['failure'] = ['type' => 'INVALID_OUTPUT', 'detail' => 'decision rejected'];
            return $out;
        }
        return ['ok' => true, 'errors' => [], 'decision' => $env['decision'],
            'choice' => in_array($env['decision'], ['SLIP', 'CHECK_PREREQUISITE'], true) ? $env['choice'] : null,
            'reason' => mb_substr($env['reason'], 0, 400), 'failure' => null];
    }

    /**
     * The learner-text guards for any model text a learner will read: length, state names,
     * mastery/verification/hypothesis claims, internal terms, support claims, and the
     * answer-disclosure guard. Round 6: a question mark is allowed.
     *
     * @return list<string>
     */
    public function learnerTextErrors(string $message, array $protected = [], array $completed = [], string $field = 'student_message'): array
    {
        $errors = [];
        if (mb_strlen($message) > 1200) {
            $errors[] = $field . ' is too long (limit 1200 characters)';
        }
        foreach ($this->guard->states() as $state) {
            if (str_contains($message, $state)) {
                $errors[] = $field . ' must not name internal states';
                break;
            }
        }
        if (preg_match(self::BANNED_WORDS, $message)) {
            $errors[] = $field . ' must not make mastery, verification or hypothesis claims';
        }
        // R5-B: internal vocabulary, as whole words or phrases. Round 5.1 (P3): only the host says when work is unaided.
        $terms = LearnerText::internalTerms($message);
        if ($terms !== []) {
            $errors[] = $field . ' must speak to the learner in plain words; it uses internal terms: ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $terms));
        }
        $claims = array_values(array_diff(LearnerText::modelTextTerms($message), $terms));
        if ($claims !== []) {
            $errors[] = $field . ' must not say whether work is done without help; the host says that. It uses: ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $claims));
        }
        foreach (DisclosureGuard::violations($message, $protected, $completed) as $v) {
            $errors[] = str_replace('student_message', $field, $v);
        }
        return $errors;
    }

    /**
     * One call for a small contract (no repair): the decoded associative and object forms, or null with $out filled.
     *
     * @return array{0:array,1:object}|null
     */
    private function single(string $kind, array $messages, callable $budget, callable $log, array &$out): ?array
    {
        $blocked = $budget();
        if ($blocked !== null) {
            $out['failure'] = ['type' => 'BUDGET', 'detail' => $blocked];
            return null;
        }
        $resp = $this->provider->complete($messages, true);
        if ($resp->error !== null) {
            $log($kind, 'TRANSPORT_' . $resp->error, $resp);
            $out['failure'] = ['type' => 'PROVIDER_UNAVAILABLE', 'detail' => $resp->error];
            return null;
        }
        [$env, $raw, $errors] = $this->parse($resp);
        if ($errors !== []) {
            $log($kind, 'REJECTED', $resp);
            $out['errors'] = $errors;
            $out['failure'] = ['type' => 'INVALID_OUTPUT', 'detail' => $kind . ' output was not one JSON object'];
            return null;
        }
        $log($kind, 'RECEIVED', $resp);
        return [$env, $raw];
    }

    /** @return list<string> */
    private function identityErrors(array $env, array $expect): array
    {
        return $env['session_id'] !== $expect['session_id'] || $env['state_revision'] !== $expect['revision'] || $env['reply_to_event_id'] !== $expect['event_id']
            ? ['identity fields must equal the supplied session_id, state_revision and reply_to_event_id'] : [];
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
        $out = ['errors' => [], 'envelope' => $env, 'record' => null, 'fingerprint' => null, 'flags' => [], 'host_generated' => false, 'student_message' => '', 'proposals' => [], 'step_proposal' => null];
        if ($errors !== []) {
            $out['errors'] = $errors;
            return $out;
        }
        $errors = $this->identityErrors($env, $expect);
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
        // Text that is not shown (no reply is due) is discarded unread, so learner-text rules do not apply to it.
        if ($shown) {
            $errors = array_merge($errors, $this->learnerTextErrors($message, ($expect['disclosure_guard'] ?? true) ? $expect['protected_answers'] : [], $expect['completed_answers'] ?? []));
            // Round 6 (§4): a reply shown before an independent item may only refer back to the previous answer.
            if (($expect['refer_back'] ?? null) !== null) {
                foreach (ReadingGuard::numbers($message) as $n) {
                    if (!isset($expect['refer_back']['allowed_numbers'][$n])) {
                        $errors[] = 'student_message comes before a new problem, so it may only refer back to the previous answer: it uses ' . $n . ', which is not in the previous problem or answer';
                    }
                }
            }
        }
        // Round 6 (§6.1): a proposed smaller step replaces the planned one only after the host's step checks.
        $draft = $env['item'];
        if ($env['step_proposal'] !== null) {
            if (!($allocation['step_proposal_wanted'] ?? false) || !isset($expect['step_check'])) {
                $out['step_proposal'] = ['status' => 'IGNORED', 'proposal' => $env['step_proposal'], 'reasons' => ['no smaller step was requested for this operation']];
            } else {
                $sc = $expect['step_check'];
                $verdict = StepProposalCheck::check($env['step_proposal'], $sc['replaced'], $sc['parent'], $sc['allowed_numbers'], $sc['protected'],
                    fn (string $q) => $this->learnerTextErrors($q, [], [], 'question'));
                if ($verdict['ok'] && $shown && DisclosureGuard::violations($message, [['answer' => $verdict['step']['answer'], 'variables' => $verdict['step']['variables'], 'options' => null, 'active' => true]]) !== []) {
                    $verdict = ['ok' => false, 'reasons' => ['the reply states the answer of the proposed step'], 'step' => null];
                }
                $out['step_proposal'] = ['status' => $verdict['ok'] ? 'ACCEPTED' : 'REJECTED', 'proposal' => $env['step_proposal'], 'reasons' => $verdict['reasons'], 'step' => $verdict['step']];
                if ($verdict['ok']) {
                    $allocation['guided_step'] = $verdict['step'];
                    if (is_array($draft)) {
                        $draft['response_type'] = $verdict['step']['response_type'];
                    }
                }
            }
        }
        if ($errors === [] && $env['message_type'] === 'QUESTION') {
            $accepted = $this->gate->accept($draft, $allocation, $fingerprints, $rng);
            // Round 6: within the per-entry call limit there may be no repair call. When the only fault is the choice of
            // parameters (reused, not varied, structure not kept, or an answer the reply could reveal), the host chooses
            // fresh parameters itself, as it does when the model leaves them empty; the proposal is recorded.
            if (!$accepted['ok'] && is_array($draft) && ($draft['parameters'] ?? []) !== [] && !isset($allocation['fixed_parameters']) && self::parameterChoiceOnly($accepted['errors'])) {
                $replaced = $accepted['errors'];
                $accepted = $this->gate->accept(['parameters' => []] + $draft, $allocation, $fingerprints, $rng);
                if ($accepted['ok']) {
                    $accepted['flags'][] = ['flag' => 'HOST_REPLACED_PARAMETERS', 'reasons' => $replaced, 'model_parameters' => $draft['parameters']];
                }
            }
            if (!$accepted['ok']) {
                $errors = array_merge($errors, $accepted['errors']);
            } else {
                // Round 6 (§4): the answer-leak guard also covers the next item, whose key exists only now.
                if ($shown && ($expect['protect_new_item'] ?? false)) {
                    $record = $accepted['record'];
                    $new = [['answer' => $record['answer_spec'], 'variables' => $record['mathematical_objects']['variables'] ?? ['x'], 'options' => $record['mathematical_objects']['options'] ?? null, 'active' => true]];
                    foreach (DisclosureGuard::violations($message, $new) as $v) {
                        $errors[] = 'student_message contains the answer of the next problem';
                    }
                }
                $out['record'] = $accepted['record'];
                $out['fingerprint'] = $accepted['fingerprint'];
                $out['flags'] = $accepted['flags'];
                $out['host_generated'] = $accepted['host_generated'];
            }
        }
        $out['errors'] = array_values(array_unique($errors));
        if ($out['errors'] === []) {
            $out['student_message'] = $shown ? $message : '';
            $out['discarded_message_chars'] = $shown ? 0 : mb_strlen(trim($message));
            $out['proposals'] = $this->reviewProposals($env, $expect);
        }
        return $out;
    }

    /**
     * Proposals never change state by themselves. Round 6: a transition proposal is
     * adopted when it passes the host checks (permitted by the workflow policy, canonical
     * skill, declared prerequisite) and agrees with the operation the host is carrying out;
     * otherwise it is rejected. Evidence proposals are recorded, never promoted.
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
                $reason = 'a mode change is decided through the host-checked teaching decisions, not by an envelope proposal';
            }
            $out[] = ['kind' => 'transition', 'proposal' => $t, 'status' => $reason === null ? 'ADOPTED_AFTER_HOST_CHECKS' : 'REJECTED', 'reason' => $reason];
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

    /** Gate errors that concern only which parameters were chosen. */
    private static function parameterChoiceOnly(array $errors): bool
    {
        foreach ($errors as $e) {
            if (!preg_match('/^parameters (reuse an item instance|must vary the implicated component|must keep the structure|give the answer )/', $e)) {
                return false;
            }
        }
        return $errors !== [];
    }

    private static function failure(string $type, string $detail, array $repairs, ?array $transport = null): array
    {
        return ['ok' => false, 'failure' => ['type' => $type, 'detail' => $detail] + ($transport === null ? [] : ['transport' => $transport]), 'repairs' => $repairs];
    }
}
