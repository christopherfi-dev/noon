<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Config;
use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Curriculum\MissingCurriculum;
use AlgebraEngine\Progress\ProgressCode;
use AlgebraEngine\Progress\ProgressRecord;
use AlgebraEngine\Provider\ProviderResponse;
use AlgebraEngine\Provider\ReasoningPolicy;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Report\TutorReport;
use AlgebraEngine\Storage\Store;
use AlgebraEngine\Support\Clock;
use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\ProblemText;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Support\Time;

/**
 * The tutor loop (round 6, as changed by rounds 7 and 8). The AI leads the
 * conversation and decides every move under architecture v1.6; this engine
 * holds the instruments and the guarantees.
 *
 * One learner turn (an answer, a free-text message, or an "I'm stuck"
 * choice): code does what it can first (safe normalisation, parsing,
 * checking, signatures, slips, recording the attempt and any help), then one
 * AI call builds the reply from a context assembled anew, and code verifies
 * the envelope after the call. An entry only the AI can read takes a reading
 * call first. Messages during an open check reach the AI; the check stays
 * open. A reply during it that carries a help move records the help before
 * it is shown, and text that uses the check's expression or numbers without
 * one holds the check (round 8 §3.1–§3.2). Text guards log findings and never
 * change the text; an envelope or problem that still fails after one repair
 * holds the turn, except a check problem that shows its own answer, which a
 * prepared check problem replaces (round 8 §3.6). HOLD_LINE is the only line
 * code writes into the conversation.
 *
 * Mutation algorithm (unchanged from the beta): validate and claim a lease in
 * a short transaction; call the provider with no transaction or lock held;
 * then verify the lease and base revision and commit everything atomically,
 * advancing the revision exactly once.
 */
final class TutorEngine
{
    public const HOLD_LINE = 'Your work is saved, but the tutor\'s reply could not be prepared right now. You can try again or end the session.';
    /** Round 7 §6.4: a hint, or "I haven't learned this yet". */
    public const STUCK_LABELS = ['HINT' => 'I\'m stuck: give me a hint.', 'NOT_LEARNED' => 'I\'m stuck: I haven\'t learned this yet.'];
    public const SKIP_LABEL = 'Skip this question.';
    public const RESULT_WORDS = ['CORRECT' => 'Correct.', 'INCORRECT' => 'Not yet.', 'PARTIAL' => 'Equal, but not in the form asked.'];
    public const HISTORY_LIMIT = 12;
    /** Round 9 §4.5: the reason code sends the tutor when an entry for an equation_step problem is not an equation. */
    public const NOT_EQUATION_REASON = 'the entry is not an equation; the open problem asks for the equation after the step';
    /** Round 9 §3.3: the moves that count as help when a reply carries one while a check question is open. */
    public const HELP_MOVES = ['HELP', 'HINT'];
    private const MAX_RELEVANT = 4;

    private RandomSource $rng;
    private ?string $issuedCode = null;
    private ?string $pendingCode = null;

    public function __construct(
        private Config $config,
        private Store $store,
        private CurriculumRepository $curriculum,
        private ProblemFactory $problems,
        private Recipes $recipes,
        private AnswerCheck $checker,
        private TutorCoordinator $ai,
        private SchemaValidator $schemas,
        private TutorProjection $projection,
        private TutorReport $reports,
        private Clock $clock,
        ?RandomSource $rng = null,
    ) {
        $this->rng = $rng ?? RandomSource::secure();
    }

    public function takeIssuedCode(): ?string
    {
        $code = $this->issuedCode;
        $this->issuedCode = null;
        return $code;
    }

    // ================================================================= API

    /**
     * @param array{progress_id:string,record:array,route_target:?string}|null $progress continuing a progress code
     * @return array{session_id:string,view:array}
     */
    public function createSession(string $containerId, string $topic, string $idemKey, string $requestHash, ?array $progress = null, ?string $focus = null): array
    {
        $this->assertSelectable($topic);
        $focus ??= $topic;
        $this->assertSelectable($focus);
        $now = $this->clock->now();
        $sessionId = Ids::make('ses');
        $op = $this->store->db->transaction(function () use ($containerId, $sessionId, $now, $idemKey, $requestHash, $progress, $topic, $focus) {
            $this->store->idempotencyPending($containerId, $idemKey, $requestHash, $now);
            [$snap, $eng] = $this->initialState($sessionId, $topic, $focus, $now);
            if ($progress !== null) {
                $eng['progress'] = ['earlier' => $progress['record']['skills'] ?? [], 'route_target' => $progress['route_target']];
                $this->store->linkProgress($progress['progress_id'], $sessionId);
                $this->store->updateProgress($progress['progress_id'], null, $now, $this->config->int('progress_retention_days'));
            }
            $this->store->insertSession($sessionId, $containerId, $snap, $eng, $now, $this->config->int('session_idle_ttl'), $this->config->int('session_absolute_ttl'));
            $this->store->setCurrentSession($containerId, $sessionId);
            return $this->store->claimOperation($sessionId, 0, $requestHash, $now, $this->config->int('lease_seconds'));
        });
        $row = $this->store->session($sessionId);
        $t = new TutorTurn($row['snapshot'], $row['engine'], $now, 'START');
        $t->event('SESSION_STARTED', [
            'provider' => $this->ai->provider()->name(), 'model' => $this->ai->provider()->model(),
            'architecture_version' => Store::ARCHITECTURE_VERSION, 'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'topic_skill_id' => $topic, 'focus_skill_id' => $focus, 'continued_from_code' => $progress !== null,
        ], $t->triggerEventId);
        try {
            $this->reply($t, ['kind' => 'START'], []);
        } catch (\Throwable $e) {
            $this->abandon($sessionId, $op, $idemKey, $containerId);
            throw $e;
        }
        $this->metrics($t);
        $view = $this->commit($sessionId, $op, $t, 0, $containerId, $idemKey);
        return ['session_id' => $sessionId, 'view' => $view];
    }

    public function handle(string $sessionId, array $req, string $requestHash): array
    {
        $now = $this->clock->now();
        if ($req['action'] === 'END_SESSION') {
            return $this->endSession($sessionId, $req, $requestHash, $now);
        }
        [$row, $op] = $this->store->db->transaction(function () use ($sessionId, $req, $requestHash, $now) {
            $row = $this->loadForMutation($sessionId, $now);
            $this->validateEvent($row, $req);
            $this->store->idempotencyPending($sessionId, $req['idempotency_key'], $requestHash, $now);
            $op = $this->store->claimOperation($sessionId, $row['revision'], $requestHash, $now, $this->config->int('lease_seconds'));
            if ($req['action'] === 'SEND') {
                $this->store->setPendingInput($op['operation_id'], ['action' => 'SEND', 'problem_id' => $req['problem_id'], 'problem_version' => $req['problem_version'], 'text' => $req['payload']['text']]);
            }
            return [$row, $op];
        });
        $t = new TutorTurn($row['snapshot'], $row['engine'], $now, $req['action']);
        $committed = $this->store->events($sessionId);
        try {
            $this->learnerTurn($t, $req, $committed);
        } catch (\Throwable $e) {
            $this->abandon($sessionId, $op, $req['idempotency_key'], $sessionId);
            throw $e;
        }
        $this->metrics($t);
        return $this->commit($sessionId, $op, $t, $row['revision'], $sessionId, $req['idempotency_key']);
    }

    public function view(string $sessionId): ?array
    {
        $row = $this->store->session($sessionId);
        if ($row === null || $this->expired($row, $this->clock->now())) {
            return null;
        }
        return $this->projection->build($row, $this->pendingActive($row), $this->store->events($sessionId));
    }

    public function report(string $sessionId): ?array
    {
        $row = $this->store->session($sessionId);
        if ($row === null || $this->expired($row, $this->clock->now())) {
            return null;
        }
        $closed = $row['engine']['status'] === 'CLOSED';
        return ($closed ? $this->store->latestReport($sessionId) : null)
            ?? $this->reports->build($row, $this->store->events($sessionId), $closed ? 'COMPLETED' : 'IN_PROGRESS', $this->ai->provider()->name(), $this->clock->now());
    }

    /** Progress code entered on another device: that device takes the live session over. */
    public function takeOver(string $sessionId, string $containerId): array
    {
        $now = $this->clock->now();
        return $this->store->db->transaction(function () use ($sessionId, $containerId, $now) {
            $row = $this->loadForMutation($sessionId, $now);
            $snap = $row['snapshot'];
            $snap['revision'] = $row['revision'] + 1;
            $snap['last_activity_at'] = Time::iso($now);
            $this->store->appendEvent($sessionId, 'SESSION_CONTINUED_ELSEWHERE', $snap['revision'],
                ['note' => 'Continued with a progress code on another device; the earlier device can no longer answer.'], $now);
            $this->store->appendEvent($sessionId, 'STATE_COMMITTED', $snap['revision'], ['snapshot' => $snap], $now);
            $this->store->saveSession($sessionId, $snap, $row['engine'], $now, $this->config->int('session_idle_ttl'));
            $this->store->db->exec('UPDATE sessions SET container_id = ? WHERE session_id = ?', [$containerId, $sessionId]);
            $this->store->setCurrentSession($containerId, $sessionId);
            return $this->projection->build($this->store->session($sessionId), null, $this->store->events($sessionId));
        });
    }

    public function live(?array $row): bool
    {
        return $row !== null && !$this->expired($row, $this->clock->now()) && $row['engine']['status'] !== 'CLOSED';
    }

    public function expired(array $row, \DateTimeImmutable $now): bool
    {
        return Time::iso($now) > $row['idle_expires_at'] || Time::iso($now) > $row['absolute_expires_at'];
    }

    public function assertSelectable(string $skillId): void
    {
        if (!$this->curriculum->has($skillId)) {
            throw new EngineError(422, 'UNKNOWN_SKILL', 'That skill is not in the curriculum.');
        }
        if (!BetaScope::contains($skillId)) {
            throw new EngineError(422, 'SKILL_NOT_IN_BETA', 'That skill is in the curriculum but is not available in this tutor yet.');
        }
    }

    // ========================================================= the learner turn

    private function learnerTurn(TutorTurn $t, array $req, array $committed): void
    {
        $t->event('ACTION_RECEIVED', ['action' => $req['action']], $t->triggerEventId);
        switch ($req['action']) {
            case 'SEND':
                $this->send($t, $req['payload']['text'], $committed);
                return;
            case 'STUCK':
                $t->line('student', 'message', self::STUCK_LABELS[$req['payload']['choice']]);
                $this->stuck($t, $req['payload']['choice'], $committed);
                return;
            case 'SKIP':
                $t->line('student', 'message', self::SKIP_LABEL);
                $this->close($t, 'SKIPPED');
                $this->dropOriginal($t, 'the student skipped');
                $this->reply($t, ['kind' => 'SKIP'], $committed);
                return;
            case 'CHANGE_TOPIC':
                $topic = $req['payload']['topic_skill_id'];
                $t->event('TOPIC_CHANGED', ['from' => $t->eng['topic'], 'to' => $topic]);
                $this->close($t, 'SET_ASIDE');
                $this->dropOriginal($t, 'the topic changed');
                $t->eng['slip'] = null;
                $t->eng['topic'] = $topic;
                $t->eng['focus'] = $topic;
                $this->reply($t, ['kind' => 'TOPIC_CHANGE'], $committed);
                return;
            case 'RESUME':
                $info = $t->eng['pending'] ?? ['kind' => 'MESSAGE', 'text' => ''];
                $t->eng['pending'] = null;
                $t->eng['status'] = 'READY';
                $t->snap['issue'] = null;
                $t->event('RESUMED', ['kind' => $info['kind']]);
                if ($info['kind'] === 'READING') {
                    $this->readEntry($t, $info['entry'], $committed);
                } else {
                    $this->reply($t, $info + ['resumed' => true], $committed);
                }
                return;
        }
        throw new EngineError(400, 'UNKNOWN_ACTION', 'Unknown action.');
    }

    private function send(TutorTurn $t, string $text, array $committed): void
    {
        $t->line('student', 'message', $text);
        $open = $t->eng['open'];
        $c = EntryReader::classify($text, $open);
        switch ($c['kind']) {
            case 'MESSAGE':
                // Round 7 §6.1 and §6.3: a message reaches the AI, during an open check too; round 8 §3.1–§3.2 decide what the reply records.
                $this->reply($t, ['kind' => 'MESSAGE', 'text' => $text], $committed);
                return;
            case 'NOT_EQUATION':
                // Round 9 §4.5: not graded; the tutor asks for the equation (no code-written line).
                $t->event('ENTRY_NOT_AN_EQUATION', ['problem_id' => $t->eng['open']['problem_id'], 'version' => $t->eng['open']['version'], 'entry' => $text,
                    'note' => 'The open problem asks for an equation; this entry is not one. Not recorded as an attempt.']);
                $this->reply($t, ['kind' => 'ENTRY_NOT_READ', 'entry' => $text, 'reading' => null, 'reasons' => [self::NOT_EQUATION_REASON]], $committed);
                return;
            case 'AMBIGUOUS':
            case 'UNREADABLE':
                // Round 7 §7.4: anything code cannot read goes to the AI for a reading.
                $this->readEntry($t, $text, $committed);
                return;
            default:
                $this->answer($t, $text, $c['read_as'], $c['note'], $committed);
        }
    }

    /** "I'm stuck": help is recorded before any AI text is generated or shown (round 7 §6.2). */
    private function stuck(TutorTurn $t, string $choice, array $committed): void
    {
        $open = $t->eng['open'];
        if ($open === null) {
            $this->reply($t, ['kind' => 'MESSAGE', 'text' => self::STUCK_LABELS[$choice]], $committed);
            return;
        }
        $t->event('HELP_GIVEN', ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'choice' => $choice, 'purpose' => $open['purpose'],
            'note' => 'Recorded before the AI text is generated or shown; an answer to this problem after help does not count toward a goal.']);
        if ($choice === 'NOT_LEARNED') {
            $this->close($t, 'NOT_LEARNED');
            $this->dropOriginal($t, 'the student chose "I haven\'t learned this yet"');
        } else {
            $t->eng['open']['help'][] = $choice;
        }
        $this->reply($t, ['kind' => 'STUCK', 'choice' => $choice], $committed);
    }

    /** An entry code can read: check it, record the attempt, show code's result, then the AI replies. */
    private function answer(TutorTurn $t, string $raw, string $readAs, ?string $note, array $committed): void
    {
        $open = $t->eng['open'];
        $r = $this->checker->check($open, $readAs);
        if ($r['verdict'] === 'UNSCORABLE') {
            // Not reachable from send(), which passes only readings code can score; never a code-written line.
            $this->reply($t, ['kind' => 'ENTRY_NOT_READ', 'entry' => $raw, 'reading' => $readAs, 'reasons' => ['code cannot score this reading as an answer']], $committed);
            return;
        }
        $sha = hash('sha256', $readAs);
        $attemptId = $t->event('ATTEMPT_RECORDED', [
            'problem_id' => $open['problem_id'], 'version' => $open['version'], 'skill_id' => $open['skill_id'], 'purpose' => $open['purpose'],
            'entry' => $raw, 'read_as' => $readAs, 'reading_note' => $note, 'verdict' => $r['verdict'], 'normalized' => $r['normalized'],
            'signatures' => array_column($r['signatures'], 'code'),
            'points_to' => array_values(array_unique(array_filter(array_column($r['signatures'], 'branch')))),
            'slip' => $r['slip'] === null ? null : ['code' => $r['slip']['code'], 'detail' => $r['slip']['detail']],
            'help' => $open['help'] ?? [], 'response_sha256' => $sha, 'scope' => $r['scope'], 'form_issue' => $r['form_issue'],
        ] + ($open['answer']['kind'] === 'equation_step' ? ['step_verdict' => $r['observation']] : []));
        $t->results[] = ['checker_result_id' => Ids::make('chk'), 'item_id' => $open['problem_id'], 'item_version' => $open['version'], 'event_id' => $attemptId,
            'response_sha256' => $sha, 'verdict' => $r['verdict'], 'verification_scope' => $r['scope'], 'created_at' => Time::iso($t->now)];
        // Round 7 §8.2: the answered problem's identity, carried in every later request until the next answer.
        $t->eng['answered'] = ['attempt_id' => $attemptId, 'problem_id' => $open['problem_id'], 'version' => $open['version'], 'response_sha256' => $sha,
            'purpose' => $open['purpose'], 'skill_id' => $open['skill_id'], 'prompt' => $open['prompt'], 'entry' => $raw, 'read_as' => $readAs, 'verdict' => $r['verdict']];
        $this->close($t, 'ANSWERED');
        if ($open['purpose'] === 'CHECK' && ($open['help'] ?? []) !== [] && $r['verdict'] !== 'CORRECT') {
            // Owner's decision on round 9 §4.3: the original waits until it is answered correctly or the tutor moves on.
            $this->wait($t, $open);
        }
        $lines = [self::RESULT_WORDS[$r['verdict']]];
        $echo = ProblemText::answerLine($open['prompt'], $open['response_type'], $raw);
        if ($echo !== null) {
            $lines[] = $echo;
        }
        if (in_array($note, [EntryReader::NOTE_AI_READING, EntryReader::NOTE_CAPITAL, EntryReader::NOTE_NUMBER_WORDS], true)) {
            $lines[] = 'Read as ' . $readAs . '.';
        }
        $t->line('app', 'result', implode("\n", $lines), ['CORRECT' => 'correct', 'INCORRECT' => 'notyet', 'PARTIAL' => 'partial'][$r['verdict']]);
        if ($open['purpose'] === 'CHECK' && $r['slip'] !== null) {
            $t->eng['slip'] = ['problem' => self::stripOpen($open), 'micro_asked' => false, 'retried' => false, 'code' => $r['slip']['code']];
        } elseif ($open['purpose'] === 'RETRY') {
            $t->eng['slip'] = null;
        }
        $template = $open['template_id'] !== null ? $this->problems->items()->get($open['template_id']) : null;
        $signatures = array_map(fn ($s) => [
            'code' => $s['code'], 'observation' => $s['label'], 'points_to' => $s['branch'],
            'description' => $template?->signatureFeedback($open['params'], $s['code']),
        ], $r['signatures']);
        $slip = $r['slip'] === null ? null : ['code' => $r['slip']['code'], 'detail' => $r['slip']['detail'], 'sub_step' => $r['slip']['micro'] + [
            'display' => SpecMath::compute('numeric_step', $r['slip']['micro']['expression'])['display']]];
        $this->reply($t, [
            'kind' => 'ANSWER', 'verdict' => $r['verdict'], 'signatures' => $signatures, 'slip' => $slip, 'attempt_id' => $attemptId, 'response_sha256' => $sha,
            'problem' => ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'purpose' => strtolower($open['purpose']), 'skill_id' => $open['skill_id'], 'prompt' => $open['prompt']],
            'entry' => $raw, 'read_as' => $readAs, 'reading_note' => $note, 'form_issue' => $r['form_issue'], 'result_line' => self::RESULT_WORDS[$r['verdict']],
        ], $committed);
    }

    /**
     * An entry only the AI can read (round 6 §3.4; round 7 §7). Code checks
     * the reading; when it fails, or plausible readings would be scored
     * differently, nothing is graded and the student sees the AI's own
     * clarifying question. With no question from the AI, the reply call
     * answers the student instead; code never writes the line.
     */
    private function readEntry(TutorTurn $t, string $text, array $committed): void
    {
        $open = $t->eng['open'];
        [$budget, $log] = $this->tools($t);
        if (!$this->ensureBinding($t, $budget, $log, ['kind' => 'READING', 'entry' => $text])) {
            return;
        }
        $context = $this->readingContext($t, $text);
        $expect = ['session_id' => $t->snap['session_id'], 'revision' => $t->snap['revision'], 'event_id' => $t->triggerEventId];
        $reasoning = ReasoningPolicy::decide($this->config, 'READING', ['kind' => 'READING']);
        $res = $this->ai->read($context, $expect, $reasoning, $budget, $log);
        $this->recordRejections($t, $res['repairs'], 'READING');
        if (!$res['ok']) {
            $this->hold($t, ['kind' => 'READING', 'entry' => $text], $res['failure'] ?? self::repairFailure($res), isset($res['failure']) ? 'PROVIDER_UNAVAILABLE' : 'REPAIR_LIMIT_REACHED');
            return;
        }
        $reading = $res['reading'];
        if (!$reading['is_answer'] || $reading['text'] === null || trim($reading['text']) === '') {
            $t->event('READING_NOT_AN_ANSWER', ['problem_id' => $open['problem_id'], 'entry' => $text, 'clarifying_question' => $reading['clarifying_question']]);
            $this->reply($t, ['kind' => 'MESSAGE', 'text' => $text], $committed);
            return;
        }
        $reasons = ReadingCheck::check($text, $reading['text'], $open['variables'] ?: ['x']);
        if ($reasons === [] && AnswerCheck::outcome($open, $reading['text'])->verdict === 'UNSCORABLE') {
            $reasons[] = 'code cannot score the reading as an answer to this problem';
        }
        if ($reasons !== []) {
            $t->event('READING_REJECTED', ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'entry' => $text, 'reading' => $reading['text'], 'reasons' => $reasons,
                'note' => 'Not recorded as an attempt.']);
            $this->clarify($t, $text, $reading, ['kind' => 'ENTRY_NOT_READ', 'entry' => $text, 'reading' => $reading['text'], 'reasons' => $reasons], $committed);
            return;
        }
        $others = $this->differentReadings($open, $text, $reading);
        if ($others !== []) {
            // Architecture v1.6 §7: plausible readings that would change the verdict are clarified before anything is graded.
            $t->event('READINGS_DIFFER', ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'entry' => $text, 'readings' => array_merge([$reading['text']], $others),
                'note' => 'Plausible readings would be scored differently; nothing is recorded until the student clarifies.']);
            $this->clarify($t, $text, $reading, ['kind' => 'ENTRY_NOT_READ', 'entry' => $text, 'reading' => $reading['text'], 'other_readings' => $others,
                'reasons' => ['plausible readings would be scored differently']], $committed);
            return;
        }
        $t->event('READING_ACCEPTED', ['problem_id' => $open['problem_id'], 'entry' => $text, 'reading' => $reading['text'], 'other_readings' => $reading['other_readings']]);
        $this->answer($t, $text, $reading['text'], EntryReader::NOTE_AI_READING, $committed);
    }

    /** The AI's clarifying question, shown as the AI wrote it (findings logged); without one, the reply call answers. */
    private function clarify(TutorTurn $t, string $entry, array $reading, array $info, array $committed): void
    {
        $question = trim((string) ($reading['clarifying_question'] ?? ''));
        if ($question === '') {
            $this->reply($t, $info, $committed);
            return;
        }
        $open = $t->eng['open'];
        $t->event('CLARIFICATION_ASKED', ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'entry' => $entry, 'question' => $question, 'question_source' => 'AI']);
        $this->logFindings($t, TextGuard::findings($question, [self::stripOpen($open)]), $question, 'clarifying_question');
        $this->identity($t, $question, 'clarifying_question');
        $t->line('tutor', 'clarify', $question);
    }

    /** Other readings that pass the reading check and would be scored differently. @return list<string> */
    private function differentReadings(array $open, string $entry, array $reading): array
    {
        $main = AnswerCheck::outcome($open, $reading['text']);
        $out = [];
        foreach ($reading['other_readings'] as $other) {
            if (ReadingCheck::check($entry, $other, $open['variables'] ?: ['x']) !== []) {
                continue;
            }
            $o = AnswerCheck::outcome($open, $other);
            if ($o->verdict !== 'UNSCORABLE' && ($o->normalizedResponse !== $main->normalizedResponse || $o->verdict !== $main->verdict)) {
                $out[] = $other;
            }
        }
        return $out;
    }

    // ============================================================ the reply call

    private function reply(TutorTurn $t, array $info, array $committed): void
    {
        $events = $t->allEvents($committed);
        $attempts = Gradebook::attempts($events);
        $goals = Goals::compute($attempts);
        $skills = $this->relevantSkills($t);
        $needed = [];
        foreach ($skills as $s) {
            $needed[$s] = Goals::forSkill($goals, $s)['still_needed'];
        }
        $used = array_merge($this->store->fingerprints($t->snap['session_id']), array_column($t->problems, 'fingerprint'));
        $usedSets = [];
        foreach (Gradebook::problems($events) as $p) {
            $usedSets[$p['skill_id']][] = $p['number_set'] ?? '';
        }
        $recipes = $this->recipes->pregenerate($skills, $this->rng, $used, $needed, $usedSets);
        [$budget, $log] = $this->tools($t);
        $open = $t->eng['open'];
        $slip = $t->eng['slip'];
        $retryTarget = $slip !== null && !$slip['retried'] ? $slip['problem'] : null;
        $slipAllowed = ($info['slip'] ?? null) !== null || ($slip !== null && !$slip['micro_asked']);
        $original = $t->eng['original'] ?? null;
        $uncheckedOpen = $open !== null && $open['purpose'] === 'CHECK' && ($open['help'] ?? []) === [];
        // Round 9 §4.3: after help, the helped check is practice for the tutor; it may pose a problem, but no check while it is open or waiting.
        $allowItem = !$uncheckedOpen;
        $allowCheck = !($open !== null && $open['purpose'] === 'CHECK') && $original === null;
        try {
            $context = $this->context($t, $info, $attempts, $goals, $recipes, $skills, $slipAllowed, $retryTarget, $allowItem, $allowCheck);
        } catch (MissingCurriculum $e) {
            // The API loads canonical records only; a missing one blocks the call instead of inventing a record.
            $this->hold($t, $info, ['type' => 'MISSING_CURRICULUM', 'detail' => 'The curriculum record for ' . $e->skillId . ' is not available.'], 'MISSING_CURRICULUM');
            return;
        }
        if (!$this->ensureBinding($t, $budget, $log, $info)) {
            return;
        }
        // Round 9 §4.2: the request's identity is captured here; code attaches it to the reply, and the reply is committed only against it.
        $expect = [
            'session_id' => $t->snap['session_id'], 'revision' => $t->snap['revision'], 'event_id' => $t->triggerEventId,
            'open_problem' => $open === null ? null : ['problem_id' => $open['problem_id'], 'version' => $open['version']],
            'allow_item' => $allowItem, 'allow_check' => $allowCheck, 'practice_with_help' => $uncheckedOpen, 'original' => $original === null ? null : self::stripOpen($original),
            'protected' => array_values(array_filter([$open === null ? null : self::stripOpen($open), $retryTarget])),
            'verdict' => $info['verdict'] ?? null, 'slip_allowed' => $slipAllowed, 'retry_target' => $retryTarget,
            'recipes' => $recipes, 'used_fingerprints' => $used, 'used_number_sets' => $usedSets, 'limits' => $this->limits(),
            'known_event_ids' => array_column($events, 'event_id'),
            // Round 8 §3.5: what a number before a check card may come from (logged as a signal since round 9).
            'previous' => $this->previous($t, $info, $open),
        ];
        $reasoning = ReasoningPolicy::decide($this->config, 'REPLY', $info);
        $res = $this->ai->reply($context, $expect, $reasoning, $budget, $log);
        $this->recordRejections($t, $res['repairs'], 'REPLY');
        if (!$res['ok']) {
            // Round 7 §4.2: a provider failure, or an envelope that still fails after one repair, holds the turn; there is no code-written reply.
            $this->hold($t, $info, $res['failure'] ?? self::repairFailure($res), isset($res['failure']) ? 'PROVIDER_UNAVAILABLE' : 'REPAIR_LIMIT_REACHED');
            return;
        }
        $t->event('MODEL_OUTPUT_ACCEPTED', ['kind' => $info['kind'], 'attempts' => count($res['repairs']) + 1]);
        foreach ($res['proposals'] as $p) {
            $t->event($p['status'] === 'REJECTED' ? 'MODEL_PROPOSAL_REJECTED' : 'MODEL_PROPOSAL', $p);
        }
        $move = $res['move'];
        // Round 9 §4.2: a help move binds to the open problem captured with its request.
        $bound = $res['identity']['open_problem'] ?? null;
        $checkOpen = $open !== null && $open['purpose'] === 'CHECK' && $bound !== null && $bound['problem_id'] === $open['problem_id']
            && ($t->eng['open']['problem_id'] ?? null) === $open['problem_id'];
        $helpMove = $checkOpen && $move !== null && in_array($move['to_mode'], self::HELP_MOVES, true);
        if ($move !== null) {
            $t->event('AI_MOVE', ['move' => $move['to_mode'], 'target_skill_id' => $move['target_skill_id'], 'from_focus' => $t->eng['focus'], 'reason' => $move['reason'],
                'help_on_open_check' => $helpMove, 'with_practice_item' => $helpMove && $res['problem'] !== null]);
            if ($move['target_skill_id'] !== null) {
                $t->eng['focus'] = $move['target_skill_id'];
                $t->eng['next_focus'] = $move['target_skill_id'];
            }
            if ($move['to_mode'] === 'SLIP_CHECK' && $t->eng['slip'] !== null) {
                $t->eng['slip']['micro_asked'] = true;
            }
            if ($move['to_mode'] === 'WRAP_UP') {
                $t->eng['wrap_up'] = true;
            }
        }
        $findings = $res['findings'];
        if ($checkOpen && !$helpMove && ($open['help'] ?? []) === []) {
            $findings = array_merge($findings, $this->duringCheckSignals($open, $res['message'], $move));
        }
        $this->logFindings($t, $findings, $res['message'], 'student_message');
        $this->heldAttempts($t, $info, $res, $open);
        if ($helpMove) {
            $this->helpMove($t, $move['to_mode'], $info);
        }
        $this->counters($t, $info, $res);
        $this->identity($t, $res['message'], 'student_message');
        $t->line('tutor', 'message', $res['message']);
        $p = $res['problem'];
        if ($p !== null) {
            if ($res['dropped_prompt_chars'] > 0) {
                $t->event('MODEL_TEXT_DISCARDED', ['what' => 'problem wording: code renders every problem from its verified spec (round 7 §8.1)',
                    'characters' => $res['dropped_prompt_chars'], 'text' => $res['ai_prompt'], 'problem_id' => $p['problem_id']]);
            }
            $this->present($t, $p);
            if ($res['card'] !== null) {
                $card = $res['card'];
                $t->event('CHECK_CARD_AUDIT', ['problem_id' => $p['problem_id'], 'version' => $p['version'], 'phrases' => $card['phrases'], 'invitations' => $card['invitations'],
                    'shared_numbers' => $card['shared'], 'answer_in_text' => $card['answer_in_text'], 'answer_source' => $card['answer_source'],
                    'handover_support_words' => TextGuard::handoverSupportWords($res['message']), 'text' => $res['message'],
                    'note' => 'Text before a check question. Signals only (round 9 §3.2): nothing is held and no count changes.']);
            }
        }
    }

    /**
     * Round 9 §4.3: a reply during an open check that carries HELP or HINT
     * records HELP_GIVEN for that check before the reply is shown. The ledger
     * keeps the problem as a check answered with help, so the goal policy
     * applies; from then on the tutor sees it as practice and may pose a
     * problem. The reply to "I'm stuck" gives the help already recorded for
     * that request before the call, so it is not recorded twice.
     */
    private function helpMove(TutorTurn $t, string $mode, array $info): void
    {
        $open = $t->eng['open'];
        if ($info['kind'] === 'STUCK') {
            return;
        }
        $t->event('HELP_GIVEN', ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'choice' => $mode, 'source' => 'TUTOR_MOVE', 'purpose' => $open['purpose'],
            'note' => 'The tutor\'s reply during the open check carries a help move; recorded before the reply is shown. The check becomes practice: an answer to it does not count toward a goal.']);
        $t->eng['open']['help'][] = $mode;
    }

    /**
     * Round 9 §3.2 and §5.4, signals only: during an open check that has no
     * help, a reply with no help move whose text uses the check's expression,
     * a number from it or its answer (the round 8 backstop), and a reply
     * whose move is ANSWER_QUESTION or EXPLAIN, are logged. Nothing is held.
     *
     * @return list<array>
     */
    private function duringCheckSignals(array $open, string $text, ?array $move): array
    {
        $out = [];
        $b = TextGuard::duringCheck($text, $open);
        $causes = array_values(array_filter([$b['expression'] ? 'expression' : null, $b['numbers'] !== [] ? 'numbers' : null, $b['answer'] ? 'answer' : null]));
        $ref = ['problem_id' => $open['problem_id'], 'problem_version' => $open['version']];
        if ($causes !== []) {
            $out[] = ['guard' => 'DURING_CHECK', 'detail' => 'uses the open check\'s ' . implode(' and ', $causes) . ' with no help move', 'causes' => $causes, 'numbers' => $b['numbers']] + $ref;
        }
        if ($move !== null && in_array($move['to_mode'], ['ANSWER_QUESTION', 'EXPLAIN'], true)) {
            $out[] = ['guard' => 'MOVE_DURING_CHECK', 'detail' => 'to_mode ' . $move['to_mode'] . ' during an open check; not recorded as help', 'move' => $move['to_mode']] + $ref;
        }
        return $out;
    }

    /** Round 8 §3.7: the two counters. Logged only; they never change anything. */
    private function counters(TutorTurn $t, array $info, array $res): void
    {
        $hits = [];
        if ($info['kind'] === 'ANSWER' && ($info['verdict'] ?? null) === 'CORRECT') {
            $record = $this->problemRecord($t, $info['problem']['problem_id'], $info['problem']['version']);
            if ($record !== null) {
                $student = array_merge([$info['entry'], $info['read_as']], array_column(array_filter(array_slice($t->eng['conversation'], -6), fn ($l) => $l['role'] === 'student'), 'text'));
                $hits['RESOLVE_AFTER_CORRECT'] = Counters::resolveAfterCorrect($res['message'], $record, array_values(array_unique($student)));
            }
        }
        if ($res['problem'] === null) {
            $hits['VALUE_QUESTION_IN_TEXT'] = Counters::valueQuestions($res['message']);
        }
        foreach (array_filter($hits) as $counter => $found) {
            $t->event('COUNTER', ['counter' => $counter, 'hits' => $found, 'field' => 'student_message', 'text' => $res['message'], 'reply_to_event_id' => $t->triggerEventId,
                'answered' => $this->answeredIdentity($t),
                'open_problem' => $t->eng['open'] === null ? null : ['problem_id' => $t->eng['open']['problem_id'], 'version' => $t->eng['open']['version']],
                'note' => 'Logged only; the text was shown unchanged.']);
        }
    }

    /** A problem record presented this turn or earlier in the session. */
    private function problemRecord(TutorTurn $t, string $problemId, int $version): ?array
    {
        foreach ($t->problems as $p) {
            if ($p['problem_id'] === $problemId && $p['version'] === $version) {
                return $p;
            }
        }
        return $this->store->item($t->snap['session_id'], $problemId, $version);
    }

    /**
     * Round 8 §3.5: the previous problem (the last answered one, and the one
     * open before this reply) and the student's entries, which a number before
     * a check card may come from.
     *
     * @return array{problems:list<array>,entries:list<string>}
     */
    private function previous(TutorTurn $t, array $info, ?array $open): array
    {
        $problems = [];
        $answered = $t->eng['answered'] ?? null;
        if ($answered !== null && ($record = $this->problemRecord($t, $answered['problem_id'], $answered['version'])) !== null) {
            $problems[] = $record;
        }
        if ($open !== null) {
            $problems[] = self::stripOpen($open);
        }
        $entries = array_values(array_filter([$answered['entry'] ?? null, $answered['read_as'] ?? null, $info['kind'] === 'MESSAGE' ? ($info['text'] ?? null) : null],
            fn ($e) => is_string($e) && $e !== ''));
        return ['problems' => $problems, 'entries' => $entries];
    }

    /**
     * Round 7 §4.1: each guard finding is recorded with the exact text, the
     * guard's name, and the problem and response it relates to. The student
     * sees the text unchanged.
     */
    private function logFindings(TutorTurn $t, array $findings, string $text, string $field): void
    {
        $answered = $this->answeredIdentity($t);
        foreach ($findings as $f) {
            $t->event('GUARD_FINDING', $f + [
                'field' => $field, 'text' => $text, 'reply_to_event_id' => $t->triggerEventId,
                'open_problem' => $t->eng['open'] === null ? null : ['problem_id' => $t->eng['open']['problem_id'], 'version' => $t->eng['open']['version']],
                'answered' => $answered, 'signal' => true,
                'note' => 'Logged as a signal only (round 9 §3.2); the text was shown unchanged, nothing is held and no count changes.',
            ]);
        }
    }

    /**
     * Round 7 §5: the AI raises a verdict conflict in issue, and the answered
     * attempt is held ("result under review"). Since round 9 §3.2 this is the
     * only hold; every text detector logs a signal only.
     */
    private function heldAttempts(TutorTurn $t, array $info, array $res, ?array $openBefore): void
    {
        if (($res['issue']['type'] ?? null) === 'VERIFICATION_CONFLICT') {
            $answered = $this->answeredIdentity($t);
            if ($answered !== null) {
                $t->event('ATTEMPT_HELD', ['attempt_id' => $answered['attempt_id'], 'problem_id' => $answered['problem_id'], 'version' => $answered['version'],
                    'reason' => 'VERDICT_CONFLICT', 'issue' => $res['issue'],
                    'note' => 'Result under review: the AI raised a conflict with code\'s verdict. The verdict stays in the record; the attempt counts neither for nor against the goal until reviewed.']);
            } else {
                $t->event('ISSUE_WITHOUT_ATTEMPT', ['issue' => $res['issue'], 'note' => 'A verdict conflict was raised, but no answered problem exists in this session; nothing is held.']);
            }
        }
    }

    /** The last answered problem and its attempt (round 7 §8.2). */
    private function answeredIdentity(TutorTurn $t): ?array
    {
        $a = $t->eng['answered'] ?? null;
        return $a === null ? null : ['attempt_id' => $a['attempt_id'], 'problem_id' => $a['problem_id'], 'version' => $a['version'], 'response_sha256' => $a['response_sha256']];
    }

    /** Round 7 §8.2: the answered and the open problem's identities, logged with each emitted AI message. */
    private function identity(TutorTurn $t, string $text, string $field): void
    {
        $open = $t->eng['open'];
        $t->event('TUTOR_MESSAGE', ['field' => $field, 'text' => $text, 'reply_to_event_id' => $t->triggerEventId,
            'answered' => $this->answeredIdentity($t),
            'open_problem' => $open === null ? null : ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'purpose' => $open['purpose']]]);
    }

    private static function repairFailure(array $res): array
    {
        $last = end($res['repairs']) ?: ['errors' => []];
        return ['type' => 'REPAIR_LIMIT_REACHED', 'detail' => 'The tutor\'s reply failed its checks after one repair.', 'errors' => array_slice($last['errors'], 0, 12)];
    }

    private function present(TutorTurn $t, array $p): void
    {
        if ($p['source'] === 'ORIGINAL') {
            $this->returnOriginal($t, $p);
            return;
        }
        $open = $t->eng['open'];
        if ($open !== null && $open['purpose'] === 'CHECK' && ($open['help'] ?? []) !== []) {
            // Round 9 §4.3 (owner's decision): the helped check waits as the original while a smaller step is open.
            $this->wait($t, $open);
            $t->eng['open'] = null;
        } elseif ($open !== null) {
            $this->close($t, 'SET_ASIDE');
        }
        if (($t->eng['original'] ?? null) !== null && $t->eng['original']['skill_id'] !== $p['skill_id']) {
            $this->dropOriginal($t, 'a problem on another skill was posed');
        }
        if ($p['purpose'] === 'RETRY' && $t->eng['slip'] !== null) {
            $t->eng['slip']['retried'] = true;
        }
        if ($p['purpose'] === 'CHECK') {
            $t->eng['slip'] = null;
        }
        $t->problems[] = $p;
        $t->event('PROBLEM_PRESENTED', [
            'problem_id' => $p['problem_id'], 'version' => $p['version'], 'purpose' => $p['purpose'], 'skill_id' => $p['skill_id'],
            'source' => $p['source'], 'kind' => $p['kind'], 'template_id' => $p['template_id'], 'variant_id' => $p['variant_id'],
            'prompt' => $p['prompt'], 'fingerprint' => $p['fingerprint'], 'retry_of' => $p['retry_of'], 'response_type' => $p['response_type'],
            'number_set' => ProblemFactory::numberSet($p),
        ]);
        $t->eng['open'] = $p + ['help' => []];
        $t->line($p['purpose'] === 'CHECK' ? 'app' : 'tutor', $p['purpose'] === 'CHECK' ? 'check' : 'problem', $p['prompt']);
    }

    /** The helped check waits as the original (owner's decision on round 9 §4.3). */
    private function wait(TutorTurn $t, array $problem): void
    {
        $t->event('PROBLEM_WAITING', ['problem_id' => $problem['problem_id'], 'version' => $problem['version'], 'purpose' => $problem['purpose'], 'help' => $problem['help'] ?? [],
            'note' => 'A check with help recorded waits as the original problem; the tutor may bring it back (item source original). Not closed.']);
        $t->eng['original'] = $problem;
    }

    /**
     * The original is dropped and closed as set aside: a problem on another
     * skill, a topic change, a skip or "I haven't learned this yet" (owner's
     * decision on round 9 §4.3).
     */
    private function dropOriginal(TutorTurn $t, string $why): void
    {
        $o = $t->eng['original'] ?? null;
        if ($o === null) {
            return;
        }
        $t->event('PROBLEM_CLOSED', ['problem_id' => $o['problem_id'], 'version' => $o['version'], 'reason' => 'SET_ASIDE', 'purpose' => $o['purpose'], 'original_dropped' => $why]);
        $t->eng['original'] = null;
    }

    /** The tutor brings the original back: the same problem, shown again (as practice, since it has help); its help stays recorded. */
    private function returnOriginal(TutorTurn $t, array $p): void
    {
        $original = $t->eng['original'];
        if ($t->eng['open'] !== null) {
            $this->close($t, 'SET_ASIDE');
        }
        $t->event('PROBLEM_PRESENTED', [
            'problem_id' => $original['problem_id'], 'version' => $original['version'], 'purpose' => $original['purpose'], 'skill_id' => $original['skill_id'],
            'source' => $original['source'], 'kind' => $original['kind'], 'template_id' => $original['template_id'], 'variant_id' => $original['variant_id'],
            'prompt' => $original['prompt'], 'fingerprint' => $original['fingerprint'], 'retry_of' => $original['retry_of'], 'response_type' => $original['response_type'],
            'number_set' => ProblemFactory::numberSet($original), 'returned_original' => true,
        ]);
        $t->eng['open'] = $original;
        $t->eng['original'] = null;
        $t->line('tutor', 'problem', $original['prompt']);
    }

    private function close(TutorTurn $t, string $reason): void
    {
        $o = $t->eng['open'];
        if ($o === null) {
            return;
        }
        $t->event('PROBLEM_CLOSED', ['problem_id' => $o['problem_id'], 'version' => $o['version'], 'reason' => $reason, 'purpose' => $o['purpose']]);
        $t->eng['open'] = null;
    }

    private function hold(TutorTurn $t, array $info, array $failure, string $type = 'PROVIDER_UNAVAILABLE'): void
    {
        $issue = [
            'type' => $type,
            'affected_scope' => 'The tutor reply for this turn.',
            'detail' => ($type === 'PROVIDER_UNAVAILABLE' ? 'Model provider: ' . $failure['type'] . ' (' . $failure['detail'] . ').' : $failure['detail']) . ' Recorded work is preserved; this is not a learner failure.',
            'next_action' => 'Retry, or end the session for a partial summary.',
        ];
        $t->event($type === 'PROVIDER_UNAVAILABLE' ? 'PROVIDER_FAILURE' : 'TURN_HELD', ['failure' => $failure, 'kind' => $info['kind'], 'hold_type' => $type]);
        $t->event('ISSUE', $issue);
        $t->snap['issue'] = $issue;
        $t->eng['status'] = 'HOLD';
        $t->eng['pending'] = $info;
        $t->line('app', 'error', self::HOLD_LINE);
    }

    private function recordRejections(TutorTurn $t, array $repairs, string $purpose): void
    {
        foreach ($repairs as $r) {
            $t->event('MODEL_OUTPUT_REJECTED', ['purpose' => $purpose, 'attempt' => $r['attempt'], 'errors' => $r['errors'], 'text' => $r['text'] ?? null,
                'note' => 'Rejected before display; never shown to the student. Not a learner failure.']);
        }
    }

    /** @return array{0:callable,1:callable} the call budget and the call log for this turn */
    private function tools(TutorTurn $t): array
    {
        $sessionId = $t->snap['session_id'];
        $leaseEnds = $t->now->getTimestamp() + $this->config->int('lease_seconds') - 5;
        $budget = function () use ($sessionId, $leaseEnds): ?string {
            if ($this->clock->now()->getTimestamp() + $this->config->int('provider_timeout') > $leaseEnds) {
                return 'LEASE_TIME_INSUFFICIENT';
            }
            if ($this->store->modelCallCount($sessionId) >= $this->config->int('max_model_calls_per_session')) {
                return 'SESSION_MODEL_CALL_BUDGET';
            }
            $day = 'provider-day-' . gmdate('Y-m-d', $this->clock->now()->getTimestamp());
            if ($this->store->counter($day) >= $this->config->int('global_provider_calls_per_day')) {
                return 'GLOBAL_PROVIDER_BUDGET';
            }
            $this->store->bump($day, $this->clock->now()->modify('+2 days'));
            return null;
        };
        $provider = $this->ai->provider();
        $log = function (string $purpose, string $outcome, ?ProviderResponse $resp, array $reasoning) use ($sessionId, $provider, $t): void {
            $t->calls[] = ['purpose' => $purpose, 'outcome' => $outcome, 'thinking' => $reasoning['thinking'], 'effort' => $reasoning['effort'] ?? null,
                'reason' => $reasoning['reason'] ?? '', 'duration_ms' => $resp?->durationMs,
                'prompt_tokens' => $resp?->usage['prompt_tokens'] ?? null, 'completion_tokens' => $resp?->usage['completion_tokens'] ?? null];
            $this->store->logModelCall($sessionId, null, $purpose === 'BINDING' ? 'binding' : 'envelope', $provider->name(), $provider->model(), $outcome, $this->clock->now(), $resp === null ? [] : [
                'finish_reason' => $resp->finishReason, 'duration_ms' => $resp->durationMs,
                'prompt_tokens' => $resp->usage['prompt_tokens'] ?? null, 'completion_tokens' => $resp->usage['completion_tokens'] ?? null, 'reasoning_tokens' => $resp->usage['reasoning_tokens'] ?? null,
                'cached_prompt_tokens' => $resp->usage['cached_prompt_tokens'] ?? null,
                'transport_detail' => $resp->diagnostics,
                'thinking' => $reasoning['thinking'], 'reasoning_effort' => $reasoning['effort'] ?? null, 'call_purpose' => $purpose, 'turn_id' => $t->triggerEventId,
            ]);
        };
        return [$budget, $log];
    }

    private function ensureBinding(TutorTurn $t, callable $budget, callable $log, array $info): bool
    {
        $b = $t->snap['binding'];
        $p = $this->ai->provider();
        if ($b['acknowledged'] && $b['provider'] === $p->name() && $b['model'] === $p->model() && $b['architecture_sha256'] === $this->ai->architectureSha256()) {
            return true;
        }
        $reasoning = ReasoningPolicy::decide($this->config, 'BINDING', ['kind' => 'BINDING']);
        $bound = $this->ai->bind($budget, $log, $reasoning);
        if (!$bound['ok']) {
            $this->hold($t, $info, $bound['failure']);
            return false;
        }
        $previous = $b['acknowledged'] ? $b['architecture_sha256'] : null;
        $t->snap['binding'] = $bound['binding'];
        $t->event('BINDING_ACCEPTED', $bound['binding'] + ['handshake' => $this->ai->handshake(), 'rebound_from_architecture_sha256' => $previous,
            'note' => 'Exact activation line received in text mode. This acknowledges the contract; it is not evidence of enforcement.']);
        return true;
    }

    /** §3.2 and §3.7: calls, reasoning settings and outcomes for this turn, for the calls-per-turn and latency reports. */
    private function metrics(TutorTurn $t): void
    {
        $t->event('TURN_METRICS', [
            'action' => $t->action,
            'calls' => count($t->calls),
            'purposes' => array_column($t->calls, 'purpose'),
            'reasoning' => array_map(fn ($c) => $c['thinking'] . ($c['effort'] !== null ? ':' . $c['effort'] : '') . ' (' . $c['reason'] . ')', $t->calls),
            'outcomes' => array_column($t->calls, 'outcome'),
            'call_ms' => array_column($t->calls, 'duration_ms'),
        ]);
    }

    // ================================================================ context

    /** §3.2: the skills that matter now: the focus, the topic, their prerequisites in this tutor, and a slipped skill. @return list<string> */
    private function relevantSkills(TutorTurn $t): array
    {
        $out = [$t->eng['focus'], $t->eng['topic']];
        if ($t->eng['slip'] !== null) {
            $out[] = $t->eng['slip']['problem']['skill_id'];
        }
        foreach ([$t->eng['focus'], $t->eng['topic']] as $s) {
            foreach ($this->curriculum->declaredPrerequisites($s) as $p) {
                if (BetaScope::contains($p)) {
                    $out[] = $p;
                }
            }
        }
        return array_slice(array_values(array_unique($out)), 0, self::MAX_RELEVANT);
    }

    private function limits(): array
    {
        return ['number' => $this->config->int('spec_number_limit'), 'answer' => $this->config->int('spec_answer_limit'), 'denominator' => $this->config->int('spec_denominator_limit')];
    }

    private function context(TutorTurn $t, array $info, array $attempts, array $goals, array $recipes, array $skills, bool $slipAllowed, ?array $retryTarget, bool $allowItem, bool $allowCheck): array
    {
        $open = $t->eng['open'];
        $skillBlock = fn (string $id) => ['skill_id' => $id, 'title' => $this->curriculum->title($id)];
        $goalSkills = array_values(array_unique(array_merge($skills, array_column($attempts, 'skill_id'))));
        $code = null;
        if ($info['kind'] === 'ANSWER') {
            $code = ['problem' => $info['problem'], 'attempt_id' => $info['attempt_id'], 'entry' => $info['entry'], 'read_as' => $info['read_as'], 'reading_note' => $info['reading_note'],
                'result' => $info['verdict'], 'result_line' => $info['result_line'], 'form_issue' => $info['form_issue'],
                'signatures' => $info['signatures'], 'slip' => $info['slip']];
        }
        $slip = $t->eng['slip'];
        $answered = $t->eng['answered'] ?? null;
        // Round 9 §4.1 (owner's decision): no TRUSTED_SESSION block; code attaches the identity fields to the reply.
        return [
            'TURN' => ['kind' => ($info['resumed'] ?? false) && $info['kind'] === 'ANSWER' ? 'RESUMED' : $info['kind'], 'entry' => $info['text'] ?? ($info['entry'] ?? null),
                'choice' => $info['choice'] ?? null]
                + ($info['kind'] === 'ENTRY_NOT_READ' ? ['reading' => $info['reading'] ?? null, 'other_readings' => $info['other_readings'] ?? [], 'not_read_because' => $info['reasons'] ?? []] : []),
            'CODE_RESULT' => $code,
            // Round 7 §8.2: the problem the last answer was for, and the open problem, each with its identity.
            'ANSWERED_PROBLEM' => $answered === null ? null : [
                'problem_id' => $answered['problem_id'], 'version' => $answered['version'], 'attempt_id' => $answered['attempt_id'],
                'purpose' => strtolower($answered['purpose']), 'skill_id' => $answered['skill_id'], 'text' => $answered['prompt'],
                'entry' => $answered['entry'], 'read_as' => $answered['read_as'], 'result' => $answered['verdict'], 'answered_in_this_turn' => $info['kind'] === 'ANSWER',
            ],
            'OPEN_PROBLEM' => $open === null ? null : self::openBlock($open),
            // Round 9 §4.3: the helped check waiting while a smaller step is open (owner's decision: the tutor brings it back with source original).
            'ORIGINAL_PROBLEM' => ($t->eng['original'] ?? null) === null ? null : self::openBlock($t->eng['original'])
                + ['spec' => ['kind' => $t->eng['original']['kind'], 'expression' => ProblemText::main($t->eng['original']['prompt']) ?: $t->eng['original']['spec']]],
            'SLIP' => [
                'eligible_for_pause' => $slipAllowed,
                'skill_id' => ($info['slip'] ?? null) !== null ? $info['problem']['skill_id'] : ($slip['problem']['skill_id'] ?? null),
                'sub_step' => $info['slip']['sub_step'] ?? null,
                'retry_available' => $retryTarget !== null && ($slip['micro_asked'] ?? false),
            ],
            'ALLOWED' => ['may_pose_problem' => $allowItem, 'may_pose_check' => $allowCheck],
            'TOPIC' => $skillBlock($t->eng['topic']),
            'FOCUS' => $skillBlock($t->eng['focus']),
            'GOALS' => array_map(function ($s) use ($goals) {
                $g = Goals::forSkill($goals, $s);
                return ['skill_id' => $s, 'title' => $this->curriculum->title($s), 'correct_in_a_row' => $g['count'], 'required' => $g['required'], 'met' => $g['met'],
                    'still_needed' => $g['still_needed'] === [] ? null : BetaScope::kinds($s, $g['still_needed']), 'still_needed_variants' => $g['still_needed'],
                    'slip_pause_available' => !$g['pause_used'], 'goal' => Goals::WORDING . ($g['three_variant'] ? ', including ' . BetaScope::kinds($s) : '')];
            }, $goalSkills),
            'GRADEBOOK' => self::gradebookBlock($attempts),
            'RECENT_CONVERSATION' => array_map(fn ($l) => ['role' => $l['role'], 'text' => mb_substr($l['text'], 0, 600)], array_slice($t->eng['conversation'], -self::HISTORY_LIMIT)),
            'CURRICULUM' => array_map(fn ($id) => $this->curriculumRecord($id), BetaScope::SKILLS),
            'PREPARED_PROBLEMS' => array_map(fn ($ref, $p) => [
                'ref' => $ref, 'skill_id' => $p['skill_id'], 'variant' => $p['variant_id'], 'kind' => self::kindWords($p),
                'prompt' => $p['options'] === null ? $p['prompt'] : explode("\n", $p['prompt'])[0], 'options' => $p['options'] === null ? null : array_map(fn ($o) => $o['letter'] . ') ' . $o['text'], $p['options']),
                'answer' => is_array($p['answer']['expected']) ? implode(', ', $p['answer']['expected']) : $p['answer']['expected'],
            ], array_keys($recipes), array_values($recipes)),
            'CHECK_PATTERNS' => ['typed' => array_values(PatternMatcher::DESCRIPTIONS), 'choice' => 'Q07 and E06 choice check questions are the prepared problems whose kind is choice question.'],
            'PROBLEM_LIMITS' => ['spec_kinds' => SpecMath::KINDS, 'number_limit' => $this->config->int('spec_number_limit'),
                'answer_limit' => $this->config->int('spec_answer_limit'), 'denominator_limit' => $this->config->int('spec_denominator_limit')],
            'EARLIER_SESSIONS' => ($t->eng['progress']['earlier'] ?? []) === [] ? null : array_map(fn ($r) => ['status' => $r['status'], 'date' => $r['date']], $t->eng['progress']['earlier']),
        ];
    }

    private function readingContext(TutorTurn $t, string $text): array
    {
        return [
            'TURN' => ['kind' => 'READING', 'entry' => $text, 'choice' => null],
            'OPEN_PROBLEM' => self::openBlock($t->eng['open']),
            'RECENT_CONVERSATION' => array_map(fn ($l) => ['role' => $l['role'], 'text' => mb_substr($l['text'], 0, 600)], array_slice($t->eng['conversation'], -6)),
        ];
    }

    /** Round 9 §4.3: a check with help recorded is presented to the tutor as practice. */
    private static function openBlock(array $open): array
    {
        $purpose = $open['purpose'] === 'CHECK' && ($open['help'] ?? []) !== [] ? 'PRACTICE' : $open['purpose'];
        return ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'purpose' => strtolower($purpose), 'skill_id' => $open['skill_id'],
            'text' => $open['prompt'], 'response_type' => $open['response_type'],
            'variables' => $open['variables'], 'options' => $open['options'] === null ? null : array_map(fn ($o) => $o['letter'] . ') ' . $o['text'], $open['options']),
            'answer' => is_array($open['answer']['expected']) ? implode(', ', $open['answer']['expected']) : $open['answer']['expected'],
            'help_given' => $open['help'] ?? []];
    }

    private static function gradebookBlock(array $attempts): array
    {
        $by = [];
        foreach ($attempts as $a) {
            $k = strtolower($a['purpose']);
            $b = &$by[$a['skill_id']];
            $b[$k . '_correct'] = ($b[$k . '_correct'] ?? 0) + ($a['verdict'] === 'CORRECT' ? 1 : 0);
            $b[$k . '_not_yet'] = ($b[$k . '_not_yet'] ?? 0) + ($a['verdict'] !== 'CORRECT' ? 1 : 0);
            $b['answered_after_help'] = ($b['answered_after_help'] ?? 0) + ($a['help'] !== [] ? 1 : 0);
            $b['held'] = ($b['held'] ?? 0) + (($a['held'] ?? null) !== null ? 1 : 0);
            unset($b);
        }
        return ['by_skill' => $by, 'recent_attempts' => array_map(fn ($a) => [
            'attempt_id' => $a['attempt_id'], 'problem_id' => $a['problem_id'], 'skill_id' => $a['skill_id'], 'purpose' => strtolower($a['purpose']), 'problem' => $a['prompt'],
            'entry' => $a['entry'], 'result' => $a['verdict'], 'help' => $a['help'], 'signatures' => $a['signatures'], 'slip' => $a['slip']['code'] ?? null,
            'held' => $a['held'] ?? null,
        ], array_slice($attempts, -6))];
    }

    private function curriculumRecord(string $id): array
    {
        $s = $this->curriculum->skill($id);
        $prereq = $this->curriculum->declaredPrerequisites($id);
        return [
            'skill_id' => $id, 'statement' => $s['statement'], 'observable_performance' => $s['observable_performance'], 'scope' => $s['scope']['included'] ?? null,
            'prerequisites_in_tutor' => array_values(array_filter($prereq, fn ($p) => BetaScope::contains($p))),
            'prerequisites_outside_tutor' => array_values(array_map(fn ($p) => $p . ' ' . ($this->curriculum->has($p) ? $this->curriculum->title($p) : ''), array_filter($prereq, fn ($p) => !BetaScope::contains($p)))),
        ];
    }

    private static function kindWords(array $p): string
    {
        if ($p['response_type'] === 'classification') {
            return 'choice question';
        }
        if ($p['variant_id'] === 'transfer') {
            return 'new form';
        }
        return match ($p['kind']) {
            'linear_equation' => 'solve',
            'expand' => 'expansion',
            default => 'arithmetic',
        };
    }

    private static function stripOpen(array $open): array
    {
        unset($open['help'], $open['clarified'], $open['held']); // 'clarified' exists only in round 6 records
        return $open;
    }

    // ============================================================ transactions

    private function initialState(string $sessionId, string $topic, string $focus, \DateTimeImmutable $now): array
    {
        $snap = [
            'session_id' => $sessionId, 'revision' => 0, 'architecture_version' => Store::ARCHITECTURE_VERSION,
            'curriculum_id' => CurriculumRepository::CURRICULUM_ID, 'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'status' => 'READY', 'topic_skill_id' => $topic, 'focus_skill_id' => $focus, 'next_focus_skill_id' => null,
            'open_problem_id' => null, 'open_problem_version' => null, 'pending_operation_id' => null,
            'binding' => ['provider' => '', 'model' => '', 'architecture_sha256' => '', 'acknowledged' => false],
            'goals' => [], 'issue' => null,
            'created_at' => Time::iso($now), 'last_activity_at' => Time::iso($now),
            'expires_at' => Time::iso($now->modify('+' . $this->config->int('session_idle_ttl') . ' seconds')),
        ];
        $eng = ['status' => 'READY', 'topic' => $topic, 'focus' => $focus, 'next_focus' => null, 'open' => null, 'original' => null, 'slip' => null,
            'conversation' => [], 'line_seq' => 0, 'wrap_up' => false, 'pending' => null, 'progress' => null];
        return [$snap, $eng];
    }

    private function loadForMutation(string $sessionId, \DateTimeImmutable $now): array
    {
        $row = $this->store->session($sessionId);
        if ($row === null || $this->expired($row, $now)) {
            throw new EngineError(404, 'SESSION_NOT_FOUND', 'This session is no longer available. Start a new session.');
        }
        $row = self::upgrade($row);
        if ($row['pending_operation_id'] !== null) {
            $op = $this->store->operation($row['pending_operation_id']);
            if ($op !== null && $op['status'] === 'ACTIVE' && Time::iso($now) < $op['expires_at']) {
                throw new EngineError(409, 'OPERATION_PENDING', 'The previous request is still being processed. Please wait.');
            }
            if ($op !== null && $op['status'] === 'ACTIVE') {
                $this->store->finishOperation($op['operation_id'], 'EXPIRED', $now);
            }
            $this->store->setPendingOperation($sessionId, null);
            $row['pending_operation_id'] = null;
        }
        return $row;
    }

    /**
     * Round 7 §2.3 and round 8 §2.1: a session stored under an earlier
     * architecture (1.3 or 1.5) continues under 1.6. Its snapshot takes the
     * new version, and because the binding's architecture hash no longer
     * matches, ensureBinding() rebinds it to v1.6 before the next AI call.
     */
    private static function upgrade(array $row): array
    {
        if (($row['snapshot']['architecture_version'] ?? null) !== Store::ARCHITECTURE_VERSION) {
            $row['snapshot']['architecture_version'] = Store::ARCHITECTURE_VERSION;
        }
        return $row;
    }

    private function pendingActive(array $row): ?string
    {
        $id = $row['pending_operation_id'];
        if ($id === null) {
            return null;
        }
        $op = $this->store->operation($id);
        return ($op !== null && $op['status'] === 'ACTIVE' && Time::iso($this->clock->now()) < $op['expires_at']) ? $id : null;
    }

    private function validateEvent(array $row, array $req): void
    {
        $eng = $row['engine'];
        if ($row['revision'] !== $req['expected_revision']) {
            throw new EngineError(409, 'STALE_REVISION', 'This page is out of date. Reload to continue.');
        }
        if ($eng['status'] === 'CLOSED') {
            throw new EngineError(409, 'SESSION_CLOSED', 'This session has ended. Start a new session to continue.');
        }
        if (!in_array($req['action'], $this->projection->allowedActions($eng, null), true)) {
            throw new EngineError(409, 'ACTION_NOT_ALLOWED', 'That action is not available right now.');
        }
        $open = $eng['open'];
        $refs = $req['problem_id'] !== null || $req['problem_version'] !== null;
        if (in_array($req['action'], ['SEND', 'STUCK', 'SKIP'], true)) {
            $matches = $open !== null && $req['problem_id'] === $open['problem_id'] && $req['problem_version'] === $open['version'];
            if (($open === null && $refs) || ($open !== null && !$matches)) {
                throw new EngineError(409, 'ITEM_CONFLICT', 'That question is no longer open. Reload to see the current one.');
            }
        } elseif ($refs) {
            throw new EngineError(400, 'INVALID_ITEM_REFERENCE', 'This action does not take a question.');
        }
        $payload = $req['payload'];
        $keys = array_keys($payload);
        $need = match ($req['action']) {
            'SEND' => ['text'], 'STUCK' => ['choice'], 'CHANGE_TOPIC' => ['topic_skill_id'], default => [],
        };
        if ($keys !== $need && !(count($keys) === count($need) && array_diff($keys, $need) === [])) {
            throw new EngineError(400, 'INVALID_PAYLOAD', 'This action has missing or unexpected fields.');
        }
        if ($req['action'] === 'SEND' && (trim($payload['text']) === '' || mb_strlen($payload['text']) > $this->config->int('answer_max'))) {
            throw new EngineError(413, 'FIELD_TOO_LONG', 'That entry is empty or too long.');
        }
        if ($req['action'] === 'CHANGE_TOPIC') {
            $this->assertSelectable($payload['topic_skill_id']);
        }
    }

    /** Verify the lease and base revision, then commit the whole turn atomically. */
    private function commit(string $sessionId, array $op, TutorTurn $t, int $baseRevision, string $idemScope, string $idemKey): array
    {
        $now = $t->now;
        $this->pendingCode = null;
        $view = $this->store->db->transaction(function () use ($sessionId, $op, $t, $baseRevision, $idemScope, $idemKey, $now) {
            $current = $this->store->operation($op['operation_id']);
            $row = $this->store->session($sessionId);
            if ($row === null || $current === null || $current['status'] !== 'ACTIVE' || !hash_equals($current['lease_token'], $op['lease_token'])
                || Time::iso($this->clock->now()) >= $current['expires_at'] || $row['revision'] !== $baseRevision) {
                if ($current !== null && $current['status'] === 'ACTIVE') {
                    $this->store->finishOperation($op['operation_id'], 'DISCARDED', $now);
                }
                if ($this->store->idempotency($idemScope, $idemKey) !== null) {
                    $this->store->idempotencyComplete($idemScope, $idemKey, 409, ['error_code' => 'OPERATION_CANCELLED', 'message' => 'This request was cancelled or superseded. Reload to see the current state.']);
                }
                return null; // late or cancelled: discard, never overwrite current state
            }
            $revision = $baseRevision + 1;
            foreach ($t->problems as $p) {
                $this->store->insertItem($sessionId, ['item_id' => $p['problem_id'], 'item_version' => $p['version']] + $p, $p['fingerprint'], $now);
            }
            foreach ($t->results as $r) {
                $this->store->insertCheckerResult($sessionId, $r);
            }
            foreach ($t->events as $e) {
                $this->store->appendEvent($sessionId, $e['type'], $revision, $e['data'], $now, $e['event_id']);
            }
            // Goals are always recomputed from the whole accepted ledger.
            $goals = $this->reconcileGoals($sessionId, $revision, $now, 'An attempt that met this goal is held for review; the goal is recomputed without it.');
            $events = $this->store->events($sessionId);
            $snap = $t->snap;
            $snap['revision'] = $revision;
            $snap['status'] = $t->eng['status'];
            $snap['topic_skill_id'] = $t->eng['topic'];
            $snap['focus_skill_id'] = $t->eng['focus'];
            $snap['next_focus_skill_id'] = $t->eng['next_focus'];
            $snap['open_problem_id'] = $t->eng['open']['problem_id'] ?? null;
            $snap['open_problem_version'] = $t->eng['open']['version'] ?? null;
            $snap['pending_operation_id'] = null;
            $snap['last_activity_at'] = Time::iso($now);
            $snap['expires_at'] = Time::iso($now->modify('+' . $this->config->int('session_idle_ttl') . ' seconds'));
            $snap['goals'] = array_values(array_map(fn ($g) => ['skill_id' => $g['skill_id'], 'count' => $g['count'], 'met' => $g['met'], 'pause_used' => $g['pause_used']], $goals));
            $t->snap = $snap;
            $this->syncProgress($sessionId, $t, $events, $goals, $now);
            if ($t->reportNeeded) {
                $report = $this->reports->build(['snapshot' => $snap, 'engine' => $t->eng, 'session_id' => $sessionId], $events, 'COMPLETED', $this->ai->provider()->name(), $now);
                $this->store->insertReport($sessionId, $revision, $report, $now);
            }
            $errors = $this->schemas->errors($snap, 'tutor-session.schema.json');
            if ($errors !== []) {
                throw new \LogicException('Snapshot failed tutor-session.schema.json: ' . implode('; ', $errors));
            }
            $this->store->appendEvent($sessionId, 'STATE_COMMITTED', $revision, ['snapshot' => $snap], $now);
            $this->store->saveSession($sessionId, $snap, $t->eng, $now, $this->config->int('session_idle_ttl'));
            $this->store->finishOperation($op['operation_id'], 'COMPLETED', $now);
            $view = $this->projection->build($this->store->session($sessionId), null, $events);
            $this->store->idempotencyComplete($idemScope, $idemKey, 200, $view);
            return $view;
        });
        if ($view === null) {
            throw new EngineError(409, 'OPERATION_CANCELLED', 'This request was cancelled or superseded. Reload to see the current state.');
        }
        $this->issuedCode = $this->pendingCode;
        $this->pendingCode = null;
        return $view;
    }

    /**
     * Recompute goals from the whole ledger and record each change: GOAL_MET
     * for a goal newly met, GOAL_SUSPENDED for a recorded goal no longer met
     * (the earlier record stays).
     *
     * @return array<string,array> the goals
     */
    private function reconcileGoals(string $sessionId, int $revision, \DateTimeImmutable $now, string $suspendReason): array
    {
        $events = $this->store->events($sessionId);
        $goals = Goals::compute(Gradebook::attempts($events));
        $recorded = [];
        foreach ($events as $e) {
            if ($e['type'] === 'GOAL_MET' || $e['type'] === 'GOAL_SUSPENDED') {
                $recorded[$e['data']['skill_id']] = $e['type'] === 'GOAL_MET';
            }
        }
        foreach ($recorded as $skill => $met) {
            if ($met && !($goals[$skill]['met'] ?? false)) {
                $this->store->appendEvent($sessionId, 'GOAL_SUSPENDED', $revision, ['skill_id' => $skill, 'reason' => $suspendReason], $now);
                $recorded[$skill] = false;
            }
        }
        foreach ($goals as $g) {
            if ($g['met'] && !($recorded[$g['skill_id']] ?? false)) {
                $this->store->appendEvent($sessionId, 'GOAL_MET', $revision, ['skill_id' => $g['skill_id'], 'attempt_ids' => $g['met_attempt_ids'],
                    'scope' => 'Three check questions correct in a row without help in this session' . ($g['three_variant'] ? ', covering a solve or expansion, a choice question and a new form' : '') . '. An in-session goal only; no retention or long-term learning claim.'], $now);
            }
        }
        return $goals;
    }

    // ============================================================ corrections

    public const CORRECTION_VALUES = ['help' => ['with_help', 'without_help'], 'verdict' => ['CORRECT', 'INCORRECT', 'PARTIAL']];

    /**
     * Round 9 §4.7: the owner's correction of one recorded attempt, run from
     * tools/correct_attempt.php. A CORRECTION event is appended; no earlier
     * event is edited or deleted. The gradebook applies the latest correction
     * of each field, and goals, the progress record and (when the session has
     * a report) the report are recomputed in the same transaction, advancing
     * the revision once. Refused while a provider call is in flight.
     *
     * @return array{event_id:string,revision:int,previous:string,goals:array<string,array>,report_rebuilt:bool}
     */
    public function applyCorrection(string $sessionId, string $attemptId, string $field, string $value, string $reason): array
    {
        if (!isset(self::CORRECTION_VALUES[$field]) || !in_array($value, self::CORRECTION_VALUES[$field], true)) {
            throw new EngineError(400, 'INVALID_CORRECTION', 'field is help (with_help | without_help) or verdict (CORRECT | INCORRECT | PARTIAL)');
        }
        if (trim($reason) === '') {
            throw new EngineError(400, 'INVALID_CORRECTION', 'a correction needs a reason');
        }
        $now = $this->clock->now();
        return $this->store->db->transaction(function () use ($sessionId, $attemptId, $field, $value, $reason, $now) {
            $row = $this->store->session($sessionId);
            if ($row === null) {
                throw new EngineError(404, 'SESSION_NOT_FOUND', 'No session with that id.');
            }
            if ($this->pendingActive($row) !== null) {
                throw new EngineError(409, 'OPERATION_PENDING', 'A provider call is in flight for this session; run the correction again when it has finished.');
            }
            $attempt = null;
            foreach (Gradebook::attempts($this->store->events($sessionId)) as $a) {
                if ($a['attempt_id'] === $attemptId) {
                    $attempt = $a;
                }
            }
            if ($attempt === null) {
                throw new EngineError(404, 'ATTEMPT_NOT_FOUND', 'No recorded attempt with that id in this session.');
            }
            $previous = $field === 'help' ? ($attempt['help'] === [] ? 'without_help' : 'with_help') : $attempt['verdict'];
            $revision = $row['revision'] + 1;
            $eventId = $this->store->appendEvent($sessionId, 'CORRECTION', $revision, [
                'attempt_id' => $attemptId, 'problem_id' => $attempt['problem_id'], 'version' => $attempt['version'], 'field' => $field, 'value' => $value,
                'previous' => $previous, 'reason' => trim($reason), 'source' => 'OWNER_CLI',
                'note' => 'Appended by tools/correct_attempt.php; the original attempt record is unchanged. The latest correction of each field applies.',
            ], $now);
            $goals = $this->reconcileGoals($sessionId, $revision, $now, 'A correction changed an attempt this goal counted; the goal is recomputed with the correction.');
            $events = $this->store->events($sessionId);
            $snap = $row['snapshot'];
            $snap['revision'] = $revision;
            $snap['goals'] = array_values(array_map(fn ($g) => ['skill_id' => $g['skill_id'], 'count' => $g['count'], 'met' => $g['met'], 'pause_used' => $g['pause_used']], $goals));
            $t = new TutorTurn($snap, $row['engine'], $now, 'CORRECTION');
            $this->syncProgress($sessionId, $t, $events, $goals, $now);
            $old = $this->store->latestReport($sessionId);
            if ($old !== null) {
                $report = $this->reports->build(['snapshot' => $snap, 'engine' => $row['engine'], 'session_id' => $sessionId], $events,
                    $row['engine']['status'] === 'CLOSED' ? 'COMPLETED' : 'IN_PROGRESS', (string) $old['provider'], $now);
                $this->store->insertReport($sessionId, $revision, $report, $now);
            }
            $this->store->appendEvent($sessionId, 'STATE_COMMITTED', $revision, ['snapshot' => $snap], $now);
            $this->store->saveSnapshot($sessionId, $snap);
            return ['event_id' => $eventId, 'revision' => $revision, 'previous' => $previous, 'goals' => $goals, 'report_rebuilt' => $old !== null];
        });
    }

    /**
     * Round UI v3 (U10), kept: the progress record is created at the session's
     * first recorded answer and updated in the same transaction as every commit
     * after it. Round 6 §8: it stores gradebook statuses and the AI's stated
     * next focus.
     */
    private function syncProgress(string $sessionId, TutorTurn $t, array $events, array $goals, \DateTimeImmutable $now): void
    {
        $row = $this->store->progressBySession($sessionId);
        $answered = (bool) array_filter($t->events, fn ($e) => $e['type'] === 'ATTEMPT_RECORDED');
        if ($row === null && !$answered) {
            return;
        }
        $p = $t->eng['progress'] ?? [];
        $route = $p['route_target'] ?? $t->eng['topic'];
        $record = [
            'route_target' => $route,
            'next_focus' => $t->eng['next_focus'],
            'next_skill' => ProgressRecord::nextSkill($t->eng['next_focus'], $t->eng['focus'], $t->eng['topic'], $route, $goals),
            'skills' => ProgressRecord::merge($p['earlier'] ?? [], ProgressRecord::sessionSkills($events, $goals, $now->format('Y-m-d'))),
        ];
        $days = $this->config->int('progress_retention_days');
        if ($row !== null) {
            $this->store->updateProgress($row['progress_id'], $record, $now, $days);
            return;
        }
        do {
            $code = ProgressCode::generate();
        } while ($this->store->progressHashTaken(ProgressCode::hash($code)));
        $this->store->insertProgress(ProgressCode::hash($code), $record, $sessionId, $now, $days);
        $this->pendingCode = $code;
    }

    private function abandon(string $sessionId, array $op, string $idemKey, string $idemScope): void
    {
        $this->store->db->transaction(function () use ($sessionId, $op, $idemKey, $idemScope) {
            $this->store->finishOperation($op['operation_id'], 'FAILED', $this->clock->now());
            $this->store->setPendingOperation($sessionId, null);
            $this->store->db->exec('DELETE FROM idempotency WHERE scope_id = ? AND idem_key = ?', [$idemScope, $idemKey]);
        });
    }

    /**
     * END_SESSION stays usable while a provider call is pending: it cancels the
     * lease and closes with a report in one transaction. A late provider
     * result is then discarded at its commit.
     */
    private function endSession(string $sessionId, array $req, string $requestHash, \DateTimeImmutable $now): array
    {
        return $this->store->db->transaction(function () use ($sessionId, $req, $requestHash, $now) {
            $row = $this->store->session($sessionId);
            if ($row === null || $this->expired($row, $now)) {
                throw new EngineError(404, 'SESSION_NOT_FOUND', 'This session is no longer available.');
            }
            $row = self::upgrade($row);
            if ($row['revision'] !== $req['expected_revision']) {
                throw new EngineError(409, 'STALE_REVISION', 'This page is out of date. Reload to continue.');
            }
            if ($row['engine']['status'] === 'CLOSED') {
                throw new EngineError(409, 'SESSION_CLOSED', 'This session has already ended.');
            }
            if ($req['problem_id'] !== null || $req['problem_version'] !== null || $req['payload'] !== []) {
                throw new EngineError(400, 'INVALID_ITEM_REFERENCE', 'This action does not take a question or payload.');
            }
            $this->store->idempotencyPending($sessionId, $req['idempotency_key'], $requestHash, $now);
            $t = new TutorTurn($row['snapshot'], $row['engine'], $now, 'END_SESSION');
            $t->event('ACTION_RECEIVED', ['action' => 'END_SESSION'], $t->triggerEventId);
            $pending = $row['pending_operation_id'];
            if ($pending !== null) {
                $op = $this->store->operation($pending);
                if ($op !== null && $op['status'] === 'ACTIVE') {
                    $this->store->finishOperation($pending, 'CANCELLED', $now);
                    $t->event('OPERATION_CANCELLED', ['operation_id' => $pending, 'reason' => 'END_SESSION while a provider call was pending; any late output is discarded.']);
                    if ($op['pending_input_json'] !== null) {
                        $input = json_decode($op['pending_input_json'], true);
                        $t->event('PENDING_INPUT_UNSCORED', ['problem_id' => $input['problem_id'], 'problem_version' => $input['problem_version'],
                            'response_sha256' => hash('sha256', (string) $input['text']), 'text' => $input['text'],
                            'note' => 'Sent while a provider call was pending; no verdict exists, so it is not recorded as an attempt.']);
                    }
                }
            }
            $this->close($t, 'SESSION_ENDED');
            $t->eng['status'] = 'CLOSED';
            $t->eng['pending'] = null;
            $t->snap['issue'] = null;
            $t->event('SESSION_ENDED', ['reason' => 'Learner ended the session.']);
            $t->reportNeeded = true;
            $this->metrics($t);
            $op = $this->store->claimOperation($sessionId, $row['revision'], $requestHash, $now, $this->config->int('lease_seconds'));
            return $this->commit($sessionId, $op, $t, $row['revision'], $sessionId, $req['idempotency_key']);
        });
    }
}
