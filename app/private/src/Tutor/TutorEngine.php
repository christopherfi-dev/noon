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
 * Round 6: the tutor loop. The AI leads the conversation and decides every
 * move; this engine holds the instruments and the guarantees.
 *
 * One learner turn (an answer, a free-text message, or an "I'm stuck"
 * choice): code does what it can first (safe normalisation, parsing,
 * checking, signatures, slips, recording the attempt and any help), then one
 * AI call builds the reply from a context assembled anew, and code verifies
 * the envelope after the call. An entry only the AI can read takes a reading
 * call first. While a check problem is open the conversation pauses.
 *
 * Mutation algorithm (unchanged from the beta): validate and claim a lease in
 * a short transaction; call the provider with no transaction or lock held;
 * then verify the lease and base revision and commit everything atomically,
 * advancing the revision exactly once.
 */
final class TutorEngine
{
    public const PAUSE_LINE = 'Let\'s finish this check question first. Answer it, or tap I\'m stuck.';
    public const REWRITE_LINE = 'I couldn\'t read that as an answer. Please write it again, using numbers and symbols.';
    public const CLARIFY_AGAIN_LINE = 'Please type just your answer, using numbers and symbols.';
    public const HOLD_LINE = 'Your work is saved, but the tutor\'s reply could not be prepared right now. You can try again or end the session.';
    public const FALLBACK_LINE = 'Let\'s practise with this one.';
    public const FALLBACK_CHECK_LINE = 'Take another look at the check question. You can answer it, skip it, or tap I\'m stuck.';
    public const FALLBACK_PLAIN_LINE = 'Let\'s keep going.';
    public const STUCK_LABELS = ['HINT' => 'I\'m stuck: give me a hint.', 'EXPLAIN' => 'I\'m stuck: explain the idea.', 'NOT_LEARNED' => 'I\'m stuck: I haven\'t learned this yet.'];
    public const SKIP_LABEL = 'Skip this question.';
    public const RESULT_WORDS = ['CORRECT' => 'Correct.', 'INCORRECT' => 'Not yet.', 'PARTIAL' => 'Equal, but not in the form asked.'];
    public const HISTORY_LIMIT = 12;
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
            'architecture_version' => '1.3', 'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
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
                $this->stuck($t, $req['payload']['choice'], false, $committed);
                return;
            case 'SKIP':
                $t->line('student', 'message', self::SKIP_LABEL);
                $this->close($t, 'SKIPPED');
                $this->reply($t, ['kind' => 'SKIP'], $committed);
                return;
            case 'CHANGE_TOPIC':
                $topic = $req['payload']['topic_skill_id'];
                $t->event('TOPIC_CHANGED', ['from' => $t->eng['topic'], 'to' => $topic]);
                $this->close($t, 'SET_ASIDE');
                $t->eng['slip'] = null;
                $t->eng['topic'] = $topic;
                $t->eng['focus'] = $topic;
                $t->line('app', 'notice', 'New topic: ' . $this->curriculum->title($topic) . '.');
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
            case 'STUCK':
                $this->stuck($t, 'HINT', true, $committed);
                return;
            case 'MESSAGE':
                if ($open !== null && $open['purpose'] === 'CHECK') {
                    $this->pause($t);
                    return;
                }
                $this->reply($t, ['kind' => 'MESSAGE', 'text' => $text], $committed);
                return;
            case 'UNREADABLE':
                $t->event('ENTRY_UNREADABLE', ['problem_id' => $open['problem_id'], 'entry' => $text, 'note' => 'Not recorded as an attempt; the student is asked to rewrite it.']);
                $t->line('app', 'notice', $c['clarification'] ?? self::REWRITE_LINE);
                return;
            case 'AMBIGUOUS':
                $this->readEntry($t, $text, $committed);
                return;
            default:
                $this->answer($t, $text, $c['read_as'], $c['note'], $committed);
        }
    }

    /** §5.3: a free-text message while a check problem is open gets the code line; no AI call. */
    private function pause(TutorTurn $t): void
    {
        $t->event('CONVERSATION_PAUSED', ['problem_id' => $t->eng['open']['problem_id'], 'note' => 'A message during an open check question; code line shown, no AI call.']);
        $t->line('app', 'notice', self::PAUSE_LINE);
    }

    /** "I'm stuck" (or a typed stuck phrase): help is recorded before any AI text is shown. */
    private function stuck(TutorTurn $t, string $choice, bool $typed, array $committed): void
    {
        $open = $t->eng['open'];
        if ($open === null) {
            $this->reply($t, ['kind' => 'MESSAGE', 'text' => $typed ? 'I am stuck.' : self::STUCK_LABELS[$choice]], $committed);
            return;
        }
        $recorded = $typed ? 'TYPED_STUCK' : $choice;
        $t->event('HELP_GIVEN', ['problem_id' => $open['problem_id'], 'version' => $open['version'], 'choice' => $recorded, 'purpose' => $open['purpose'],
            'note' => 'Recorded before the AI text is generated or shown; an answer to this problem after help does not count toward a goal.']);
        if ($choice === 'NOT_LEARNED') {
            $this->close($t, 'NOT_LEARNED');
        } else {
            $t->eng['open']['help'][] = $recorded;
        }
        $this->reply($t, ['kind' => 'STUCK', 'choice' => $choice, 'typed' => $typed], $committed);
    }

    /** An entry code can read: check it, record the attempt, show code's result, then the AI replies. */
    private function answer(TutorTurn $t, string $raw, string $readAs, ?string $note, array $committed): void
    {
        $open = $t->eng['open'];
        $r = $this->checker->check($open, $readAs);
        if ($r['verdict'] === 'UNSCORABLE') {
            $t->line('app', 'notice', $r['clarification'] ?? self::REWRITE_LINE);
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
        ]);
        $t->results[] = ['checker_result_id' => Ids::make('chk'), 'item_id' => $open['problem_id'], 'item_version' => $open['version'], 'event_id' => $attemptId,
            'response_sha256' => $sha, 'verdict' => $r['verdict'], 'verification_scope' => $r['scope'], 'created_at' => Time::iso($t->now)];
        $this->close($t, 'ANSWERED');
        $lines = [self::RESULT_WORDS[$r['verdict']]];
        $echo = ProblemText::answerLine($open['prompt'], $open['response_type'], $raw);
        if ($echo !== null) {
            $lines[] = $echo;
        }
        if ($note === EntryReader::NOTE_AI_READING || $note === EntryReader::NOTE_CAPITAL) {
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
            'kind' => 'ANSWER', 'verdict' => $r['verdict'], 'signatures' => $signatures, 'slip' => $slip, 'attempt_id' => $attemptId,
            'problem' => ['purpose' => strtolower($open['purpose']), 'skill_id' => $open['skill_id'], 'prompt' => $open['prompt']],
            'entry' => $raw, 'read_as' => $readAs, 'reading_note' => $note, 'form_issue' => $r['form_issue'], 'result_line' => self::RESULT_WORDS[$r['verdict']],
        ], $committed);
    }

    /** §3.4 and §4.1: an entry only the AI can read. */
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
        if (!$res['ok'] && isset($res['failure'])) {
            $this->hold($t, ['kind' => 'READING', 'entry' => $text], $res['failure']);
            return;
        }
        if (!$res['ok']) {
            $t->event('READING_REJECTED', ['problem_id' => $open['problem_id'], 'entry' => $text, 'reading' => null, 'reasons' => ['the reading output failed its checks after one repair']]);
            $t->line('app', 'notice', self::REWRITE_LINE);
            return;
        }
        $reading = $res['reading'];
        if (!$reading['is_answer'] || $reading['text'] === null || trim($reading['text']) === '') {
            $t->event('READING_NOT_AN_ANSWER', ['problem_id' => $open['problem_id'], 'entry' => $text]);
            if ($open['purpose'] === 'CHECK') {
                $this->pause($t);
                return;
            }
            $this->reply($t, ['kind' => 'MESSAGE', 'text' => $text], $committed);
            return;
        }
        $reasons = ReadingCheck::check($text, $reading['text'], $open['variables'] ?: ['x']);
        if ($reasons === [] && AnswerCheck::outcome($open, $reading['text'])->verdict === 'UNSCORABLE') {
            $reasons[] = 'code cannot read the reading as an answer to this problem';
        }
        if ($reasons !== []) {
            $t->event('READING_REJECTED', ['problem_id' => $open['problem_id'], 'entry' => $text, 'reading' => $reading['text'], 'reasons' => $reasons]);
            $t->line('app', 'notice', self::REWRITE_LINE);
            return;
        }
        if ($open['purpose'] === 'CHECK') {
            $others = $this->differentReadings($open, $text, $reading);
            if ($others !== []) {
                if (!($open['clarified'] ?? false)) {
                    $question = trim((string) ($reading['clarifying_question'] ?? ''));
                    $source = 'AI';
                    if ($question === '' || TextGuard::violations($question, [$open]) !== []) {
                        $question = 'Did you mean ' . $reading['text'] . ' or ' . $others[0] . '?';
                        $source = 'CODE';
                    }
                    $t->eng['open']['clarified'] = true;
                    $t->event('CLARIFICATION_ASKED', ['problem_id' => $open['problem_id'], 'entry' => $text, 'readings' => array_merge([$reading['text']], $others), 'question_source' => $source,
                        'note' => 'A check question with another plausible reading: nothing is recorded until the student clarifies.']);
                    $t->line($source === 'AI' ? 'tutor' : 'app', 'clarify', $question);
                    return;
                }
                $t->event('CLARIFICATION_NOT_RESOLVED', ['problem_id' => $open['problem_id'], 'entry' => $text]);
                $t->line('app', 'notice', self::CLARIFY_AGAIN_LINE);
                return;
            }
        }
        $t->event('READING_ACCEPTED', ['problem_id' => $open['problem_id'], 'entry' => $text, 'reading' => $reading['text'], 'other_readings' => $reading['other_readings']]);
        $this->answer($t, $text, $reading['text'], EntryReader::NOTE_AI_READING, $committed);
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
        $allowItem = !($open !== null && $open['purpose'] === 'CHECK');
        try {
            $context = $this->context($t, $info, $attempts, $goals, $recipes, $skills, $slipAllowed, $retryTarget, $allowItem);
        } catch (MissingCurriculum $e) {
            // The API loads canonical records only; a missing one blocks the call instead of inventing a record.
            $this->hold($t, $info, ['type' => 'MISSING_CURRICULUM', 'detail' => 'The curriculum record for ' . $e->skillId . ' is not available.'], 'MISSING_CURRICULUM');
            return;
        }
        if (!$this->ensureBinding($t, $budget, $log, $info)) {
            return;
        }
        $expect = [
            'session_id' => $t->snap['session_id'], 'revision' => $t->snap['revision'], 'event_id' => $t->triggerEventId,
            'allow_item' => $allowItem,
            'protected' => array_values(array_filter([$open === null ? null : self::stripOpen($open), $retryTarget])),
            'verdict' => $info['verdict'] ?? null, 'slip_allowed' => $slipAllowed, 'retry_target' => $retryTarget,
            'recipes' => $recipes, 'used_fingerprints' => $used, 'used_number_sets' => $usedSets, 'limits' => $this->limits(),
            'known_event_ids' => array_column($events, 'event_id'),
        ];
        $reasoning = ReasoningPolicy::decide($this->config, 'REPLY', $info);
        $res = $this->ai->reply($context, $expect, $reasoning, $budget, $log);
        $this->recordRejections($t, $res['repairs'], 'REPLY');
        if (!$res['ok'] && isset($res['failure'])) {
            $this->hold($t, $info, $res['failure']);
            return;
        }
        if (!$res['ok']) {
            $this->fallback($t, $info, $recipes, $res);
            return;
        }
        $t->event('MODEL_OUTPUT_ACCEPTED', ['kind' => $info['kind'], 'attempts' => count($res['repairs']) + 1]);
        foreach ($res['proposals'] as $p) {
            $t->event($p['status'] === 'REJECTED' ? 'MODEL_PROPOSAL_REJECTED' : 'MODEL_PROPOSAL', $p);
        }
        $move = $res['move'];
        if ($move !== null) {
            $t->event('AI_MOVE', ['move' => $move['to_mode'], 'target_skill_id' => $move['target_skill_id'], 'from_focus' => $t->eng['focus'], 'reason' => $move['reason']]);
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
        $t->line('tutor', 'message', $res['message']);
        $p = $res['problem'];
        if ($p !== null) {
            if ($p['purpose'] === 'CHECK' && $res['forward'] !== []) {
                $t->event('CHECK_CARD_AUDIT', ['problem_id' => $p['problem_id'], 'phrases' => $res['forward'], 'text' => $res['message'],
                    'note' => 'Forward-looking phrases in the text before a check question (audit list; not rejected).']);
            }
            if ($res['dropped_prompt_chars'] > 0) {
                $t->event('MODEL_TEXT_DISCARDED', ['what' => 'problem wording; code writes check and choice wording', 'characters' => $res['dropped_prompt_chars']]);
            }
            $this->present($t, $p);
        }
    }

    /** §3.5: after a failed repair, code shows a prepared problem with a code line and drops the AI's text. */
    private function fallback(TutorTurn $t, array $info, array $recipes, array $res): void
    {
        $last = end($res['repairs']) ?: ['errors' => []];
        $t->event('FALLBACK_USED', ['kind' => $info['kind'], 'errors' => $last['errors'], 'discarded_characters' => $res['discarded_chars'] ?? 0,
            'note' => 'The AI output failed its checks after one repair; its text for this turn was dropped and code wrote the line.']);
        $open = $t->eng['open'];
        if ($open !== null && $open['purpose'] === 'CHECK') {
            $t->line('app', 'notice', self::FALLBACK_CHECK_LINE);
            return;
        }
        $pick = null;
        foreach ($recipes as $p) {
            if ($p['skill_id'] === $t->eng['focus'] && $p['response_type'] !== 'classification') {
                $pick = $p;
                break;
            }
        }
        if ($pick === null) {
            $t->line('app', 'notice', self::FALLBACK_PLAIN_LINE);
            return;
        }
        $pick['purpose'] = 'PRACTICE';
        $t->line('app', 'notice', self::FALLBACK_LINE);
        $this->present($t, $pick);
    }

    private function present(TutorTurn $t, array $p): void
    {
        if ($t->eng['open'] !== null) {
            $this->close($t, 'SET_ASIDE');
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
        $t->eng['open'] = $p + ['help' => [], 'clarified' => false];
        $t->line($p['purpose'] === 'CHECK' ? 'app' : 'tutor', $p['purpose'] === 'CHECK' ? 'check' : 'problem', $p['prompt']);
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
        $t->event('PROVIDER_FAILURE', ['failure' => $failure, 'kind' => $info['kind']]);
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
        $t->snap['binding'] = $bound['binding'];
        $t->event('BINDING_ACCEPTED', $bound['binding'] + ['handshake' => $this->ai->handshake(),
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

    private function context(TutorTurn $t, array $info, array $attempts, array $goals, array $recipes, array $skills, bool $slipAllowed, ?array $retryTarget, bool $allowItem): array
    {
        $open = $t->eng['open'];
        $skillBlock = fn (string $id) => ['skill_id' => $id, 'title' => $this->curriculum->title($id)];
        $goalSkills = array_values(array_unique(array_merge($skills, array_column($attempts, 'skill_id'))));
        $code = null;
        if ($info['kind'] === 'ANSWER') {
            $code = ['problem' => $info['problem'], 'entry' => $info['entry'], 'read_as' => $info['read_as'], 'reading_note' => $info['reading_note'],
                'result' => $info['verdict'], 'result_line' => $info['result_line'], 'form_issue' => $info['form_issue'],
                'signatures' => $info['signatures'], 'slip' => $info['slip']];
        }
        $slip = $t->eng['slip'];
        return [
            'TRUSTED_SESSION' => ['session_id' => $t->snap['session_id'], 'state_revision' => $t->snap['revision'], 'event_id' => $t->triggerEventId],
            'TURN' => ['kind' => ($info['resumed'] ?? false) && $info['kind'] === 'ANSWER' ? 'RESUMED' : $info['kind'], 'entry' => $info['text'] ?? ($info['entry'] ?? null),
                'choice' => $info['choice'] ?? null, 'typed_stuck_phrase' => $info['typed'] ?? false],
            'CODE_RESULT' => $code,
            'OPEN_PROBLEM' => $open === null ? null : self::openBlock($open),
            'SLIP' => [
                'confirmed' => $slipAllowed,
                'skill_id' => ($info['slip'] ?? null) !== null ? $info['problem']['skill_id'] : ($slip['problem']['skill_id'] ?? null),
                'sub_step' => $info['slip']['sub_step'] ?? null,
                'retry_available' => $retryTarget !== null && ($slip['micro_asked'] ?? false),
                'note' => 'A confirmed slip on a check question pauses its count once: the slipped answer and its retry do not count.',
            ],
            'ALLOWED' => ['may_pose_problem' => $allowItem, 'may_pose_check' => $allowItem],
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
                'answer_for_your_teaching_never_state' => is_array($p['answer']['expected']) ? implode(', ', $p['answer']['expected']) : $p['answer']['expected'],
            ], array_keys($recipes), array_values($recipes)),
            'CHECK_PATTERNS' => ['typed' => array_values(PatternMatcher::DESCRIPTIONS), 'choice' => 'Q07 and E06 choice questions: use a prepared problem whose kind is choice question.'],
            'PROBLEM_RULES' => ['spec_kinds' => SpecMath::KINDS, 'number_limit' => $this->config->int('spec_number_limit'),
                'answer_limit' => $this->config->int('spec_answer_limit'), 'denominator_limit' => $this->config->int('spec_denominator_limit')],
            'EARLIER_SESSIONS' => ($t->eng['progress']['earlier'] ?? []) === [] ? null : array_map(fn ($r) => ['status' => $r['status'], 'date' => $r['date']], $t->eng['progress']['earlier']),
        ];
    }

    private function readingContext(TutorTurn $t, string $text): array
    {
        return [
            'TRUSTED_SESSION' => ['session_id' => $t->snap['session_id'], 'state_revision' => $t->snap['revision'], 'event_id' => $t->triggerEventId],
            'TURN' => ['kind' => 'READING', 'entry' => $text],
            'OPEN_PROBLEM' => self::openBlock($t->eng['open']),
            'RECENT_CONVERSATION' => array_map(fn ($l) => ['role' => $l['role'], 'text' => mb_substr($l['text'], 0, 600)], array_slice($t->eng['conversation'], -6)),
        ];
    }

    private static function openBlock(array $open): array
    {
        return ['purpose' => strtolower($open['purpose']), 'skill_id' => $open['skill_id'], 'prompt' => $open['prompt'], 'response_type' => $open['response_type'],
            'variables' => $open['variables'], 'options' => $open['options'] === null ? null : array_map(fn ($o) => $o['letter'] . ') ' . $o['text'], $open['options']),
            'answer_for_your_teaching_never_state' => is_array($open['answer']['expected']) ? implode(', ', $open['answer']['expected']) : $open['answer']['expected'],
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
            unset($b);
        }
        return ['by_skill' => $by, 'recent_attempts' => array_map(fn ($a) => [
            'skill_id' => $a['skill_id'], 'purpose' => strtolower($a['purpose']), 'problem' => $a['prompt'], 'entry' => $a['entry'], 'result' => $a['verdict'],
            'help' => $a['help'], 'signatures' => $a['signatures'], 'slip' => $a['slip']['code'] ?? null,
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
            'probe_focus' => $s['diagnostic_guidance']['probe_focus'] ?? null,
            'bridge' => $s['instructional_guidance']['bridge'] ?? null,
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
        unset($open['help'], $open['clarified']);
        return $open;
    }

    // ============================================================ transactions

    private function initialState(string $sessionId, string $topic, string $focus, \DateTimeImmutable $now): array
    {
        $snap = [
            'session_id' => $sessionId, 'revision' => 0, 'architecture_version' => '1.3',
            'curriculum_id' => CurriculumRepository::CURRICULUM_ID, 'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'status' => 'READY', 'topic_skill_id' => $topic, 'focus_skill_id' => $focus, 'next_focus_skill_id' => null,
            'open_problem_id' => null, 'open_problem_version' => null, 'pending_operation_id' => null,
            'binding' => ['provider' => '', 'model' => '', 'architecture_sha256' => '', 'acknowledged' => false],
            'goals' => [], 'issue' => null,
            'created_at' => Time::iso($now), 'last_activity_at' => Time::iso($now),
            'expires_at' => Time::iso($now->modify('+' . $this->config->int('session_idle_ttl') . ' seconds')),
        ];
        $eng = ['status' => 'READY', 'topic' => $topic, 'focus' => $focus, 'next_focus' => null, 'open' => null, 'slip' => null,
            'conversation' => [], 'line_seq' => 0, 'wrap_up' => false, 'pending' => null, 'progress' => null];
        return [$snap, $eng];
    }

    private function loadForMutation(string $sessionId, \DateTimeImmutable $now): array
    {
        $row = $this->store->session($sessionId);
        if ($row === null || $this->expired($row, $now)) {
            throw new EngineError(404, 'SESSION_NOT_FOUND', 'This session is no longer available. Start a new session.');
        }
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
            $events = $this->store->events($sessionId);
            $goals = Goals::compute(Gradebook::attempts($events));
            $recorded = [];
            foreach ($events as $e) {
                if ($e['type'] === 'GOAL_MET') {
                    $recorded[$e['data']['skill_id']] = true;
                }
            }
            foreach ($goals as $g) {
                if ($g['met'] && !isset($recorded[$g['skill_id']])) {
                    $this->store->appendEvent($sessionId, 'GOAL_MET', $revision, ['skill_id' => $g['skill_id'], 'attempt_ids' => $g['met_attempt_ids'],
                        'scope' => 'Three check questions correct in a row without help in this session' . ($g['three_variant'] ? ', covering a solve or expansion, a choice question and a new form' : '') . '. An in-session goal only; no retention or long-term learning claim.'], $now);
                }
            }
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
            $t->line('app', 'notice', 'This session has ended. Your summary is ready.');
            $t->reportNeeded = true;
            $this->metrics($t);
            $op = $this->store->claimOperation($sessionId, $row['revision'], $requestHash, $now, $this->config->int('lease_seconds'));
            return $this->commit($sessionId, $op, $t, $row['revision'], $sessionId, $req['idempotency_key']);
        });
    }
}
