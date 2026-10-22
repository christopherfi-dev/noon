<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Checking\ClassificationChecker;
use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Config;
use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Curriculum\MissingCurriculum;
use AlgebraEngine\Evidence\CriteriaCalculator;
use AlgebraEngine\Items\AcceptanceGate;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Items\SocraticPlanner;
use AlgebraEngine\Items\StructuralFeatures;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Provider\ModelCoordinator;
use AlgebraEngine\Provider\ProviderResponse;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Report\ReportBuilder;
use AlgebraEngine\Storage\Store;
use AlgebraEngine\Support\Clock;
use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Support\Time;

/**
 * The host workflow. It owns every state change: the model only drafts
 * items and wording inside an operation the host has already chosen.
 *
 * Mutation algorithm (spec 02): validate and claim a lease in a short
 * transaction; decide and call the provider with no transaction or lock
 * held; then verify the lease and commit everything atomically, advancing
 * the revision exactly once.
 */
final class Engine
{
    public const PARENT_REVERIFY_REQUIRED = 1;
    public const MAX_FAILED_CYCLES = 3;
    public const MAX_DEPTH = 8;
    public const MAX_GUIDED_PROBLEMS = 3;
    /** Round 5.1 P4: no more than two probes per diagnosis (the owner's slip check of one or two extra problems). */
    public const MAX_PROBES_PER_DECISION = 2;
    public const HISTORY_LIMIT = 12;

    private const ITEM_ACTIONS = ['SUBMIT_RESPONSE', 'REQUEST_HINT', 'REQUEST_EXPLANATION', 'CLARIFY_RESPONSE', 'SKIP_ITEM', 'NOT_LEARNED'];

    /** R5-A: the only text shown with an independent item. It states what is asked, never a method. */
    public const INDEPENDENT_LEAD = 'Here is one to try on your own.';

    private RandomSource $rng;

    public function __construct(
        private readonly Config $config,
        private readonly Store $store,
        private readonly CurriculumRepository $curriculum,
        private readonly ItemFactory $factory,
        private readonly SocraticPlanner $planner,
        private readonly ModelCoordinator $model,
        private readonly TransitionGuard $guard,
        private readonly ReportBuilder $reports,
        private readonly SchemaValidator $schemas,
        private readonly PublicProjection $projection,
        private readonly Clock $clock,
        ?RandomSource $rng = null,
    ) {
        $this->rng = $rng ?? RandomSource::secure();
    }

    // =================================================================== API

    /**
     * Create a session for a browser container and run its first turn.
     *
     * @return array{session_id:string,view:array}
     */
    public function createSession(string $containerId, string $entryRoute, ?string $target, string $idemKey, string $requestHash): array
    {
        if ($target !== null) {
            $this->assertSelectable($target);
        }
        $now = $this->clock->now();
        $sessionId = Ids::make('ses');
        $op = $this->store->db->transaction(function () use ($containerId, $entryRoute, $sessionId, $now, $idemKey, $requestHash) {
            $this->store->idempotencyPending($containerId, $idemKey, $requestHash, $now);
            [$snap, $eng] = $this->initialState($sessionId, $entryRoute, $now);
            $this->store->insertSession($sessionId, $containerId, $snap, $eng, $now, $this->config->int('session_idle_ttl'), $this->config->int('session_absolute_ttl'));
            $this->store->setCurrentSession($containerId, $sessionId);
            return $this->store->claimOperation($sessionId, 0, $requestHash, $now, $this->config->int('lease_seconds'));
        });
        $row = $this->store->session($sessionId);
        $turn = new Turn($row['snapshot'], $row['engine'], $now, 'START');
        $turn->event('SESSION_STARTED', [
            'entry_route' => $entryRoute,
            'provider' => $this->model->provider()->name(),
            'model' => $this->model->provider()->model(),
            'architecture_version' => '1.2',
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
        ], $turn->triggerEventId);
        if ($target !== null) {
            $this->startEpisode($turn, $target, 'START');
        } else {
            $this->transition($turn, 'TARGET_SELECTION', 'Learner chose to pick a topic.');
            $turn->eng['menu'] = $this->targetMenu();
            $turn->say('INFORMATION', 'Choose a skill to work on.');
        }
        $this->runGeneration($turn);
        $view = $this->commit($sessionId, $op, $turn, 0, $containerId, $idemKey);
        return ['session_id' => $sessionId, 'view' => $view];
    }

    /** Handle one public event for an owned session. @return array public view */
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
            if (in_array($req['action'], ['SUBMIT_RESPONSE', 'CLARIFY_RESPONSE'], true)) {
                $this->store->setPendingInput($op['operation_id'], [
                    'action' => $req['action'], 'item_id' => $req['item_id'], 'item_version' => $req['item_version'],
                    'text' => $req['payload']['text'] ?? '', 'working' => $req['payload']['working'] ?? '',
                ]);
            }
            return [$row, $op];
        });
        $turn = new Turn($row['snapshot'], $row['engine'], $now, $req['action']);
        try {
            $this->dispatch($turn, $req);
            $this->runGeneration($turn);
        } catch (MissingCurriculum $e) {
            $turn = new Turn($row['snapshot'], $row['engine'], $now, $req['action']);
            $this->holdMissingCurriculum($turn, $e->skillId);
        } catch (\Throwable $e) {
            $this->abandon($sessionId, $op, $req['idempotency_key']);
            throw $e;
        }
        return $this->commit($sessionId, $op, $turn, $row['revision'], $sessionId, $req['idempotency_key']);
    }

    public function view(string $sessionId): ?array
    {
        $row = $this->store->session($sessionId);
        if ($row === null || $this->expired($row, $this->clock->now())) {
            return null;
        }
        return $this->projection->build($row, $this->pendingActive($row));
    }

    public function report(string $sessionId): ?array
    {
        $row = $this->store->session($sessionId);
        if ($row === null || $this->expired($row, $this->clock->now())) {
            return null;
        }
        return $this->store->latestReport($sessionId) ?? $this->buildReport($row, $this->store->events($sessionId), 'IN_PROGRESS');
    }

    /**
     * Correction (architecture §12 contradiction protocol): invalidate earlier
     * events after a demonstrated checker or item conflict. The original
     * records stay in the ledger; a CORRECTION event is appended and every
     * dependent count and claim is recomputed from the ledger. Operator-only
     * in the beta: there is no public endpoint for it.
     *
     * @param list<string> $eventIds response or checker-result events to withdraw
     */
    public function invalidate(string $sessionId, array $eventIds, string $reason): array
    {
        return $this->store->db->transaction(function () use ($sessionId, $eventIds, $reason) {
            $now = $this->clock->now();
            $row = $this->store->session($sessionId) ?? throw new EngineError(404, 'SESSION_NOT_FOUND', 'Unknown session.');
            $known = array_column($this->store->events($sessionId), 'event_id');
            foreach ($eventIds as $id) {
                if (!in_array($id, $known, true)) {
                    throw new \InvalidArgumentException('Cannot invalidate an event that is not in this ledger: ' . $id);
                }
            }
            $turn = new Turn($row['snapshot'], $row['engine'], $now, 'CORRECTION');
            $turn->event('CORRECTION', ['invalidated_event_ids' => array_values($eventIds), 'reason' => $reason,
                'note' => 'Original records are preserved; dependent streaks, criteria and claims are recomputed from the ledger.'], $turn->triggerEventId);
            $turn->eng['messages'] = $row['engine']['messages'];
            $turn->messages = [['kind' => 'INFORMATION', 'text' => 'An earlier result was corrected. Your progress has been recalculated.']];
            $turn->reportNeeded = $this->store->latestReport($sessionId) !== null;
            $op = $this->store->claimOperation($sessionId, $row['revision'], 'correction', $now, $this->config->int('lease_seconds'));
            return $this->commit($sessionId, $op, $turn, $row['revision'], $sessionId, 'correction-' . $turn->triggerEventId);
        });
    }

    public function expired(array $row, \DateTimeImmutable $now): bool
    {
        return Time::iso($now) > $row['idle_expires_at'] || Time::iso($now) > $row['absolute_expires_at'];
    }

    // ============================================================ transactions

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

    /** Spec 02 step 6-7: verify lease and base revision, then commit atomically. */
    private function commit(string $sessionId, array $op, Turn $turn, int $baseRevision, string $idemScope, string $idemKey): array
    {
        $now = $turn->now;
        $view = $this->store->db->transaction(function () use ($sessionId, $op, $turn, $baseRevision, $idemScope, $idemKey, $now) {
            $current = $this->store->operation($op['operation_id']);
            $row = $this->store->session($sessionId);
            if ($row === null || $current === null || $current['status'] !== 'ACTIVE' || !hash_equals($current['lease_token'], $op['lease_token'])
                || Time::iso($this->clock->now()) >= $current['expires_at'] || $row['revision'] !== $baseRevision) {
                if ($current !== null && $current['status'] === 'ACTIVE') {
                    $this->store->finishOperation($op['operation_id'], 'DISCARDED', $now);
                }
                $error = ['http' => 409, 'body' => ['error_code' => 'OPERATION_CANCELLED', 'message' => 'This request was cancelled or superseded. Reload to see the current state.']];
                if ($this->store->idempotency($idemScope, $idemKey) !== null) {
                    $this->store->idempotencyComplete($idemScope, $idemKey, 409, $error['body']);
                }
                return null; // late or cancelled: discard, never overwrite current state
            }
            $revision = $baseRevision + 1;
            $turn->snap['revision'] = $revision;
            $turn->snap['pending_operation_id'] = null;
            $turn->snap['last_activity_at'] = Time::iso($now);
            $turn->snap['expires_at'] = Time::iso($now->modify('+' . $this->config->int('session_idle_ttl') . ' seconds'));
            foreach ($turn->items as $it) {
                $this->store->insertItem($sessionId, $it['record'], $it['fingerprint'], $now);
            }
            foreach ($turn->results as $r) {
                $this->store->insertCheckerResult($sessionId, $r);
            }
            foreach ($turn->events as $e) {
                $this->store->appendEvent($sessionId, $e['type'], $revision, $e['data'], $now, $e['event_id']);
            }
            // Criteria are always recomputed from the whole accepted ledger.
            $events = $this->store->events($sessionId);
            $computed = CriteriaCalculator::compute($events);
            $turn->snap['criteria'] = $computed['criteria'];
            foreach ($computed['met'] as $m) {
                if (!$this->criterionRecorded($events, $m)) {
                    $id = $this->store->appendEvent($sessionId, 'CRITERION_MET', $revision, $m + ['scope' => $this->criterionScope($m)], $now);
                    $events[] = ['event_id' => $id, 'type' => 'CRITERION_MET', 'data' => $m + ['scope' => $this->criterionScope($m)], 'revision' => $revision, 'created_at' => Time::iso($now)];
                }
            }
            $ids = array_column($events, 'event_id');
            $turn->snap['accepted_event_ids'] = array_slice($ids, -2000);
            $turn->eng['messages'] = $turn->messages;
            $turn->snap['active_item_id'] = $turn->eng['active']['item_id'] ?? null;
            $turn->snap['active_item_version'] = $turn->eng['active']['item_version'] ?? null;
            if ($turn->reportNeeded) {
                $report = $this->buildReport(['snapshot' => $turn->snap, 'engine' => $turn->eng, 'session_id' => $sessionId], $events, $turn->eng['report_status']);
                $this->store->insertReport($sessionId, $revision, $report, $now);
            }
            $snapErrors = $this->schemas->errors($turn->snap, 'session.schema.json');
            if ($snapErrors !== []) {
                throw new \LogicException('Snapshot failed session.schema.json: ' . implode('; ', $snapErrors));
            }
            $this->store->appendEvent($sessionId, 'STATE_COMMITTED', $revision, ['snapshot' => $turn->snap], $now);
            $this->store->saveSession($sessionId, $turn->snap, $turn->eng, $now, $this->config->int('session_idle_ttl'));
            $this->store->finishOperation($op['operation_id'], 'COMPLETED', $now);
            $view = $this->projection->build($this->store->session($sessionId), null);
            $this->store->idempotencyComplete($idemScope, $idemKey, 200, $view);
            return $view;
        });
        if ($view === null) {
            throw new EngineError(409, 'OPERATION_CANCELLED', 'This request was cancelled or superseded. Reload to see the current state.');
        }
        return $view;
    }

    private function criterionRecorded(array $events, array $m): bool
    {
        foreach ($events as $e) {
            if ($e['type'] === 'CRITERION_MET' && $e['data']['skill_id'] === $m['skill_id'] && $e['data']['episode_id'] === $m['episode_id']) {
                return true;
            }
        }
        return false;
    }

    private function criterionScope(array $m): string
    {
        return $m['status'] === 'IMMEDIATE_CRITERION_MET'
            ? 'Three consecutive independent correct items in one session covering constructed, contrast and transfer variants. Immediate criterion only; no retention or long-term learning claim.'
            : 'Three consecutive independent correct items on the implemented numeric component only. Conceptual and transfer requirements of this skill are not implemented in the beta (PARTIAL_COMPONENTS).';
    }

    private function abandon(string $sessionId, array $op, string $idemKey): void
    {
        $this->store->db->transaction(function () use ($sessionId, $op, $idemKey) {
            $this->store->finishOperation($op['operation_id'], 'FAILED', $this->clock->now());
            $this->store->setPendingOperation($sessionId, null);
            $this->store->db->exec('DELETE FROM idempotency WHERE scope_id = ? AND idem_key = ?', [$sessionId, $idemKey]);
        });
    }

    /**
     * END_SESSION stays usable while a provider call is pending: it cancels
     * the lease and closes with a partial report in one transaction. A late
     * provider result is then discarded at its commit.
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
            if ($row['snapshot']['mode'] === 'SESSION_CLOSED') {
                throw new EngineError(409, 'SESSION_CLOSED', 'This session has already ended.');
            }
            if ($req['item_id'] !== null || $req['item_version'] !== null || $req['payload'] !== []) {
                throw new EngineError(400, 'INVALID_ITEM_REFERENCE', 'This action does not take an item or payload.');
            }
            $this->store->idempotencyPending($sessionId, $req['idempotency_key'], $requestHash, $now);
            $turn = new Turn($row['snapshot'], $row['engine'], $now, 'END_SESSION');
            $turn->event('ACTION_RECEIVED', ['action' => 'END_SESSION'], $turn->triggerEventId);
            $pending = $row['pending_operation_id'];
            if ($pending !== null) {
                $op = $this->store->operation($pending);
                if ($op !== null && $op['status'] === 'ACTIVE') {
                    $this->store->finishOperation($pending, 'CANCELLED', $now);
                    $turn->event('OPERATION_CANCELLED', ['operation_id' => $pending, 'reason' => 'END_SESSION while the provider call was pending; any late output is discarded.']);
                    if ($op['pending_input_json'] !== null) {
                        $input = json_decode($op['pending_input_json'], true);
                        $turn->event('PENDING_INPUT_UNSCORED', [
                            'item_id' => $input['item_id'], 'item_version' => $input['item_version'], 'action' => $input['action'],
                            'response_sha256' => hash('sha256', $input['text'] . "\0" . $input['working']),
                            'text' => $input['text'], 'note' => 'Submitted while a provider call was pending; no accepted verdict exists, so it is unscored.',
                        ]);
                    }
                }
            }
            $this->closeSession($turn, 'Learner ended the session.');
            $op = $this->store->claimOperation($sessionId, $row['revision'], $requestHash, $now, $this->config->int('lease_seconds'));
            return $this->commit($sessionId, $op, $turn, $row['revision'], $sessionId, $req['idempotency_key']);
        });
    }

    // ================================================================ validation

    private function validateEvent(array $row, array $req): void
    {
        $snap = $row['snapshot'];
        $eng = $row['engine'];
        if ($row['revision'] !== $req['expected_revision']) {
            throw new EngineError(409, 'STALE_REVISION', 'This page is out of date. Reload to continue.');
        }
        if ($snap['mode'] === 'SESSION_CLOSED') {
            if ($req['action'] === 'RESUME_SESSION') {
                throw new EngineError(422, 'FEATURE_NOT_IN_BETA', 'Resuming a closed session is not available in this beta. Start a new session.');
            }
            throw new EngineError(409, 'SESSION_CLOSED', 'This session has ended. Start a new session to continue.');
        }
        $allowed = $this->projection->allowedActions($snap, $eng);
        if (!in_array($req['action'], $allowed, true)) {
            throw new EngineError(409, 'ACTION_NOT_ALLOWED', 'That action is not available right now.');
        }
        $active = $eng['active'];
        if (in_array($req['action'], self::ITEM_ACTIONS, true)) {
            if ($active === null || $req['item_id'] !== $active['item_id'] || $req['item_version'] !== $active['item_version']) {
                throw new EngineError(409, 'ITEM_CONFLICT', 'That question is no longer active. Reload to see the current question.');
            }
        } elseif ($req['item_id'] !== null || $req['item_version'] !== null) {
            throw new EngineError(400, 'INVALID_ITEM_REFERENCE', 'This action does not take an item.');
        }
        foreach (['text' => 'answer_max', 'working' => 'working_max'] as $k => $limit) {
            if (isset($req['payload'][$k]) && mb_strlen($req['payload'][$k]) > $this->config->int($limit)) {
                throw new EngineError(413, 'FIELD_TOO_LONG', 'That entry is too long.');
            }
        }
        if (isset($req['payload']['target_skill_id'])) {
            $this->assertSelectable($req['payload']['target_skill_id']);
        }
        if ($req['action'] === 'CLARIFY_RESPONSE' && !($active['unscorable'] ?? false)) {
            throw new EngineError(409, 'ACTION_NOT_ALLOWED', 'There is nothing to clarify for this question.');
        }
    }

    public function assertSelectable(string $skillId): void
    {
        if (!$this->curriculum->has($skillId)) {
            throw new EngineError(422, 'UNKNOWN_SKILL', 'That skill is not in the curriculum.');
        }
        if (!BetaScope::contains($skillId)) {
            throw new EngineError(422, 'SKILL_NOT_IN_BETA', 'That skill is in the curriculum but is not available in this beta.');
        }
    }

    // =================================================================== state

    private function initialState(string $sessionId, string $entryRoute, \DateTimeImmutable $now): array
    {
        $provider = $this->model->provider();
        $snap = [
            'session_id' => $sessionId,
            'revision' => 0,
            'architecture_version' => '1.2',
            'curriculum_id' => CurriculumRepository::CURRICULUM_ID,
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'mode' => 'AWAITING_SESSION_CONTEXT',
            'entry_route' => $entryRoute,
            'target_episode_id' => null,
            'original_target_skill_id' => null,
            'current_skill_id' => null,
            'active_item_id' => null,
            'active_item_version' => null,
            'return_stack' => [],
            'suspended_episode_ids' => [],
            'criteria' => [],
            'orientation' => ['presented_count' => 0, 'usable_event_ids' => [], 'recommendation_id' => null, 'provisional_target_skill_id' => null],
            'budgets' => ['target_assessment_items' => 0, 'diagnostic_items' => 0, 'instructional_turns' => 0, 'failed_repair_cycles' => 0, 'maximum_minutes' => $this->config->int('default_session_minutes')],
            'pending_operation_id' => null,
            'issue' => null,
            'created_at' => Time::iso($now),
            'last_activity_at' => Time::iso($now),
            'expires_at' => Time::iso($now->modify('+' . $this->config->int('session_idle_ttl') . ' seconds')),
            'accepted_event_ids' => [],
            'binding' => ['provider' => $provider->name(), 'model' => $provider->model(), 'architecture_sha256' => $this->model->architectureSha256(), 'acknowledged' => false],
        ];
        $eng = [
            'status' => 'READY',
            'provider' => $provider->name(),
            'active' => null,
            'guided' => null,
            'diagnosis' => null,
            'cycles' => [],
            'hypotheses' => [],
            'boundaries' => [],
            'path' => [],
            'suspended' => [],
            'pending_generation' => null,
            'menu' => null,
            'messages' => [],
            'history' => [],
            'deadline' => Time::iso($now->modify('+' . $this->config->int('default_session_minutes') . ' minutes')),
            'continuation_threshold' => $this->config->int('continuation_target_items'),
            'continuation_offered' => false,
            'report_status' => 'IN_PROGRESS',
            'hold_kind' => null,
        ];
        return [$snap, $eng];
    }

    private function transition(Turn $turn, string $to, string $reason, array $evidence = [], array $ctx = []): void
    {
        $from = $turn->mode();
        $this->guard->assert($from, $to, $ctx);
        if ($from !== $to) {
            $turn->event('TRANSITION', ['from' => $from, 'to' => $to, 'skill_id' => $turn->skill(), 'reason' => $reason, 'evidence_event_ids' => $evidence, 'source' => 'HOST']);
        }
        $turn->snap['mode'] = $to;
    }

    private function startEpisode(Turn $turn, string $skill, string $reason): void
    {
        $this->curriculum->skill($skill);
        $episode = Ids::make('ep');
        $turn->event('EPISODE_STARTED', ['episode_id' => $episode, 'target_skill_id' => $skill, 'reason' => $reason]);
        $this->transition($turn, 'TARGET_ASSESSMENT', 'Confirmed canonical target ' . $skill . ' (' . $reason . ').', [], ['explicit_target_change' => $reason === 'CHANGE_TARGET']);
        $turn->snap['target_episode_id'] = $episode;
        $turn->snap['original_target_skill_id'] = $skill;
        $turn->snap['current_skill_id'] = $skill;
        $turn->snap['return_stack'] = [];
        $turn->snap['issue'] = null;
        $turn->eng['guided'] = null;
        $turn->eng['diagnosis'] = null;
        $turn->eng['cycles'] = [];
        $turn->eng['path'] = [$skill];
        $turn->eng['menu'] = null;
        $turn->eng['status'] = 'READY';
        $turn->eng['hold_kind'] = null;
        $turn->eng['pending_generation'] = null;
        $this->presentItem($turn, 'TARGET', $skill, 'constructed');
    }

    private function targetMenu(): array
    {
        return [
            'menu_id' => 'beta-targets',
            'options' => array_map(fn ($id) => ['option_id' => $id, 'label' => $id . ': ' . $this->curriculum->title($id), 'action' => 'SELECT_TARGET'], BetaScope::SKILLS),
        ];
    }

    // ================================================================ dispatch

    private function dispatch(Turn $turn, array $req): void
    {
        $turn->eng['menu'] = $turn->eng['menu'] ?? null;
        switch ($req['action']) {
            case 'SELECT_TARGET':
                $turn->event('ACTION_RECEIVED', ['action' => 'SELECT_TARGET', 'target_skill_id' => $req['payload']['target_skill_id']], $turn->triggerEventId);
                $this->startEpisode($turn, $req['payload']['target_skill_id'], 'SELECT_TARGET');
                return;
            case 'CHANGE_TARGET':
                $turn->event('ACTION_RECEIVED', ['action' => 'CHANGE_TARGET', 'target_skill_id' => $req['payload']['target_skill_id']], $turn->triggerEventId);
                $this->suspendEpisode($turn);
                $this->startEpisode($turn, $req['payload']['target_skill_id'], 'CHANGE_TARGET');
                return;
            case 'SUBMIT_RESPONSE':
            case 'CLARIFY_RESPONSE':
                $this->onResponse($turn, $req);
                return;
            case 'REQUEST_HINT':
            case 'REQUEST_EXPLANATION':
                $this->onSupport($turn, $req['action']);
                return;
            case 'SKIP_ITEM':
                $this->onSkip($turn);
                return;
            case 'NOT_LEARNED':
                $this->onNotLearned($turn);
                return;
            case 'CONTINUE_SESSION':
                $turn->event('ACTION_RECEIVED', ['action' => 'CONTINUE_SESSION', 'continue_minutes' => $req['payload']['continue_minutes']], $turn->triggerEventId);
                $turn->eng['deadline'] = Time::iso($turn->now->modify('+' . $req['payload']['continue_minutes'] . ' minutes'));
                $turn->eng['continuation_threshold'] += $this->config->int('continuation_target_items');
                $turn->eng['continuation_offered'] = false;
                $turn->eng['menu'] = null;
                $turn->generation = $turn->eng['pending_generation'];
                $turn->eng['pending_generation'] = null;
                return;
            case 'RESUME_SESSION':
                $turn->event('ACTION_RECEIVED', ['action' => 'RESUME_SESSION', 'reason' => 'retry held generation'], $turn->triggerEventId);
                $turn->generation = $turn->eng['pending_generation'];
                $turn->eng['pending_generation'] = null;
                $turn->eng['status'] = 'READY';
                if ($turn->snap['issue'] !== null) {
                    $turn->event('ISSUE_CLEARED', ['type' => $turn->snap['issue']['type'], 'reason' => 'Learner asked to retry.']);
                }
                $turn->snap['issue'] = null;
                $turn->eng['hold_kind'] = null;
                return;
        }
        throw new EngineError(400, 'UNKNOWN_ACTION', 'Unknown action.');
    }

    private function suspendEpisode(Turn $turn): void
    {
        $episode = $turn->snap['target_episode_id'];
        if ($episode === null) {
            return;
        }
        $turn->eng['suspended'][$episode] = [
            'original_target_skill_id' => $turn->snap['original_target_skill_id'],
            'current_skill_id' => $turn->snap['current_skill_id'],
            'mode' => $turn->mode(),
            'return_stack' => $turn->snap['return_stack'],
            'path' => $turn->eng['path'],
        ];
        $turn->snap['suspended_episode_ids'][] = $episode;
        $turn->event('EPISODE_SUSPENDED', ['episode_id' => $episode] + $turn->eng['suspended'][$episode]);
        if ($turn->eng['active'] !== null) {
            $turn->event('ITEM_CLOSED', ['item_id' => $turn->eng['active']['item_id'], 'item_version' => $turn->eng['active']['item_version'], 'reason' => 'TARGET_CHANGED']);
            $turn->eng['active'] = null;
        }
    }

    // ============================================================== responses

    private function onResponse(Turn $turn, array $req): void
    {
        $active = $turn->eng['active'];
        $text = $req['payload']['text'];
        $working = $req['payload']['working'] ?? '';
        $hash = hash('sha256', $text . "\0" . $working);
        $responseEvent = $turn->event('RESPONSE_SUBMITTED', [
            'item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'response_sha256' => $hash,
            'text' => $text, 'working' => $working, 'action' => $req['action'],
        ], $turn->triggerEventId);
        $turn->learnerPayload = ['text' => $text, 'working' => $working];
        $this->remember($turn, 'learner', $responseEvent, $text);

        // R5-C: stuck and off-task entries. None of them is ever scored as incorrect.
        $entry = EntryInterpreter::interpret($text, $active, fn (string $t): bool => $this->check($active, $t)->verdict !== 'UNSCORABLE');
        if ($entry['kind'] !== 'ANSWER') {
            $turn->event('ENTRY_INTERPRETED', ['item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'response_event_id' => $responseEvent,
                'kind' => $entry['kind'], 'normalised' => $entry['normalised'], 'read_as' => $entry['read_as'],
                'note' => in_array($entry['kind'], ['TRAILING_QUESTION_MARK', 'CAPITAL_X'], true) ? 'Read and scored as written in read_as (round 5, R5-C).' : 'Not an answer; not scored (round 5, R5-C).']);
        }
        if (in_array($entry['kind'], ['STUCK', 'ASK_FOR_ANSWER'], true)) {
            $this->onStuckEntry($turn, $entry['kind'], $responseEvent);
            return;
        }
        $readAs = $entry['read_as'] ?? $text;
        $outcome = $this->check($active, $readAs);
        $independent = $active['role'] !== 'GUIDED' && $active['support_status'] === 'INDEPENDENT' && $active['independent_eligible'];
        $supportEvents = $active['support_event_ids'] ?? [];
        $result = [
            'checker_result_id' => Ids::make('chk'),
            'checker_id' => match ($active['answer_spec']['kind']) { 'exact_rational' => 'exact-rational', 'polynomial' => 'polynomial-identity', default => 'classification-exact-set' },
            'checker_version' => '1.0',
            'item_id' => $active['item_id'],
            'item_version' => $active['item_version'],
            'event_id' => $responseEvent,
            'response_sha256' => $hash,
            'verdict' => $outcome->verdict,
            'verification_scope' => $outcome->scope,
            'provenance' => 'DETERMINISTIC',
            'required_components_satisfied' => $outcome->verdict === 'CORRECT',
            'independent_eligible' => $independent,
            'support_event_ids' => $supportEvents,
            'observation' => $outcome->observation,
            'limitations' => array_values(array_merge($outcome->limitations, $active['role'] === 'GUIDED' ? ['Guided step: assisted work, not independent evidence.'] : [],
                $readAs !== $text ? ['The response was read as "' . $readAs . '" (' . strtolower(str_replace('_', ' ', $entry['kind'])) . ').'] : [])),
            'created_at' => Time::iso($turn->now),
        ];
        $errors = $this->schemas->errors($result, 'checker-result.schema.json');
        if ($errors !== []) {
            throw new \LogicException('Checker result failed schema: ' . implode('; ', $errors));
        }
        $turn->results[] = $result;
        $turn->event('CHECKER_RESULT', ['result' => $result, 'skill_id' => $active['skill_id'], 'role' => $active['role'], 'variant_id' => $active['variant_id'], 'mode' => $turn->mode()]);
        $turn->lastResult = $result;
        $turn->lastItem = $active;

        if ($outcome->verdict === 'UNSCORABLE') {
            $turn->eng['active']['unscorable'] = true;
            $turn->say('ERROR', match ($entry['kind']) {
                'QUESTION_TO_TUTOR' => EntryInterpreter::LINE_QUESTION,
                'NUMBER_WORD' => EntryInterpreter::LINE_NUMBER_WORD,
                default => $outcome->clarification ?? 'That answer could not be read. Please check the format and try again.',
            });
            return;
        }

        $signatures = $this->signatures($active, $text, $outcome);
        $facts = [];
        foreach ($signatures as $sig) {
            $turn->event('OBSERVATION', [
                'statement' => 'Response ' . $sig['label'] . '.', 'code' => $sig['code'], 'branch' => $sig['branch'],
                'skill_id' => $active['skill_id'], 'item_id' => $active['item_id'], 'evidence_event_ids' => [$responseEvent],
                'provenance' => 'HOST_SIGNATURE', 'status' => 'OBSERVATION_NOT_CAUSE',
            ]);
            if ($sig['fact'] !== null) {
                $facts[] = $sig['fact'];
            }
        }
        $turn->facts = $facts;
        $turn->event('ITEM_CLOSED', ['item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'reason' => $outcome->verdict]);
        $turn->eng['active'] = null;
        $turn->say('FEEDBACK', $this->verdictSentence($outcome, $active['role'] === 'GUIDED'));
        if ($outcome->verdict === 'INCORRECT' && $active['role'] !== 'GUIDED') {
            // R4-B: the repair practises the structure of the item the learner failed.
            $turn->eng['last_failed'][$active['skill_id']] = ['template_id' => $active['template_id'], 'params' => $active['params'], 'item_id' => $active['item_id']];
        }

        $verdict = $outcome->verdict;
        match ($turn->mode()) {
            'TARGET_ASSESSMENT' => $this->routeTarget($turn, $verdict, $independent, $signatures, $active),
            'DIAGNOSTIC_PROBE' => $this->routeProbe($turn, $verdict, $signatures, $active, $responseEvent),
            'PREREQUISITE_CHECK' => $this->routePrerequisite($turn, $verdict, $independent, $signatures, $active, $responseEvent),
            'GUIDED_REPAIR' => $this->routeGuided($turn, $verdict, $facts, $outcome, $active),
            'INDEPENDENT_CHECK' => $this->routeIndependent($turn, $verdict, $independent, $signatures, $active, $responseEvent),
            'RETURN_TO_TARGET' => $this->routeReturn($turn, $verdict, $independent, $signatures, $active, $responseEvent),
            default => throw new StateConflict('No response routing for mode ' . $turn->mode()),
        };
    }

    private function check(array $active, string $text): CheckOutcome
    {
        $spec = $active['answer_spec'];
        return match ($spec['kind']) {
            'exact_rational' => ExactRationalChecker::check(ScalarParser::parse($spec['expected']), $text, 'x'),
            'polynomial' => PolynomialChecker::check($spec['expected'], $text, $active['objects']['variables'] ?? ['x'], $spec['requested_form'] === 'expanded' ? 'expanded' : null),
            'classification' => ClassificationChecker::check($spec['expected'], $active['objects']['option_letters'], $text),
            default => throw new \LogicException('No checker for ' . $spec['kind']),
        };
    }

    /** @return list<array{code:string,label:string,branch:?string,fact:?string}> */
    private function signatures(array $active, string $text, CheckOutcome $outcome): array
    {
        $out = [];
        if ($active['template_id'] === AcceptanceGate::GUIDED_TEMPLATE) {
            $g = $active['guided_step'] ?? null;
            if ($g !== null && $g['final'] && $outcome->verdict === 'INCORRECT') {
                $plan = $active['guided_plan_params'];
                $template = $this->factory->get($active['guided_template_id']);
                foreach ($template->matchSignatures($plan, $text, $outcome) as $sig) {
                    $out[] = $sig + ['fact' => $template->signatureFeedback($plan, $sig['code'])];
                }
            }
            return $out;
        }
        $template = $this->factory->get($active['template_id']);
        foreach ($template->matchSignatures($active['params'], $text, $outcome) as $sig) {
            $out[] = $sig + ['fact' => $template->signatureFeedback($active['params'], $sig['code'])];
        }
        return $out;
    }

    private function verdictSentence(CheckOutcome $o, bool $guided): string
    {
        return match ($o->verdict) {
            'CORRECT' => $guided ? 'Yes, that is right.' : 'Correct.',
            'INCORRECT' => $guided ? 'Not yet.' : 'That is not correct.',
            'PARTIAL' => match ($o->formIssue) {
                'STILL_GROUPED' => 'That expression is equal, but it still has parentheses. The question asks for it without parentheses.',
                'NUMBERS_NOT_MULTIPLIED' => 'That expression is equal, but some numbers are not multiplied out yet.',
                'LIKE_TERMS_NOT_COMBINED' => 'That expression is equal, but some terms can still be combined.',
                'ZERO_TERM' => 'That expression is equal, but it includes a term that adds zero.',
                default => 'That expression is equal, but it is not in the requested form.',
            },
            default => '',
        };
    }

    /** First signature branch, if any. */
    private static function branchOf(array $signatures): ?string
    {
        foreach ($signatures as $s) {
            if ($s['branch'] !== null) {
                return $s['branch'];
            }
        }
        return null;
    }

    // -------------------------------------------------------------- routing

    private function routeTarget(Turn $turn, string $verdict, bool $independent, array $sigs, array $item): void
    {
        $skill = $turn->skill();
        if ($verdict === 'CORRECT') {
            if ($independent && $this->criterionWillBeMet($turn, $skill)) {
                $this->completeTarget($turn);
                return;
            }
            $this->presentItem($turn, 'TARGET', $skill, $this->nextVariant($turn, $skill));
            return;
        }
        if ($verdict === 'PARTIAL') {
            $this->presentItem($turn, 'TARGET', $skill, $item['variant_id']);
            return;
        }
        $this->startDiagnosis($turn, $skill, 'TARGET_ASSESSMENT', $sigs, $item);
    }

    private function routeProbe(Turn $turn, string $verdict, array $sigs, array $item, string $responseEvent): void
    {
        $skill = $turn->skill();
        $diag = $turn->eng['diagnosis'];
        // Round 5.1 P4: an answer given after a hint, an explanation or a stuck entry cannot show the error was a slip.
        if (($item['support_event_ids'] ?? []) !== []) {
            if (($diag['probes'] ?? 1) < self::MAX_PROBES_PER_DECISION) {
                $turn->event('OBSERVATION', [
                    'statement' => 'Answered after a hint; inconclusive.',
                    'code' => 'PROBE_INCONCLUSIVE_AFTER_HELP', 'branch' => null, 'skill_id' => $skill, 'item_id' => $item['item_id'],
                    'evidence_event_ids' => [$responseEvent], 'provenance' => 'HOST_RULE', 'status' => 'OBSERVATION_NOT_CAUSE',
                ]);
                $turn->eng['diagnosis']['probes'] = ($diag['probes'] ?? 1) + 1;
                $turn->eng['diagnosis']['inconclusive'] = true;
                $this->presentItem($turn, 'DIAGNOSTIC', $skill, 'constructed', $item['params']);
                return;
            }
            // The second probe also needed help: the breakdown is confirmed (owner rule). No hypothesis update from assisted work.
            $turn->event('OBSERVATION', [
                'statement' => 'The second probe was also answered after a hint; the breakdown is treated as confirmed.',
                'code' => 'BREAKDOWN_CONFIRMED_AFTER_HELP', 'branch' => null, 'skill_id' => $skill, 'item_id' => $item['item_id'],
                'evidence_event_ids' => array_values(array_filter([$diag['trigger_event_id'] ?? null, $responseEvent])), 'provenance' => 'HOST_RULE', 'status' => 'OBSERVATION_NOT_CAUSE',
            ]);
            $turn->eng['diagnosis'] = null;
            $this->descend($turn, $skill, ($diag['branch'] ?? null) ?? self::branchOf($sigs), $responseEvent);
            return;
        }
        if ($verdict === 'CORRECT' || $verdict === 'PARTIAL') {
            $turn->event('OBSERVATION', [
                'statement' => 'The earlier error was not reproduced on a fresh probe. This does not confirm a slip.',
                'code' => 'ERROR_NOT_REPRODUCED', 'branch' => null, 'skill_id' => $skill, 'item_id' => $item['item_id'],
                'evidence_event_ids' => array_values(array_filter([$diag['trigger_event_id'] ?? null, $responseEvent])),
                'provenance' => 'HOST_RULE', 'status' => 'OBSERVATION_NOT_CAUSE',
            ]);
            $turn->eng['diagnosis'] = null;
            if ($turn->stackEmpty() && in_array($diag['from_mode'], ['TARGET_ASSESSMENT', 'RETURN_TO_TARGET'], true)) {
                $this->transition($turn, 'TARGET_ASSESSMENT', 'Error not reproduced; resume target assessment. The broken streak stays broken.', [$responseEvent]);
                $this->presentItem($turn, 'TARGET', $skill, $this->nextVariant($turn, $skill));
                return;
            }
            $this->beginRepair($turn, $skill, 'Error not reproduced on the probe; bounded instructional trial before further independent checks.', [$responseEvent]);
            return;
        }
        // After an inconclusive first probe, descend along the branch the original error implicated (round 5.1 P4).
        $branch = ($diag['inconclusive'] ?? false)
            ? (($diag['branch'] ?? null) ?? self::branchOf($sigs))
            : (self::branchOf($sigs) ?? ($diag['branch'] ?? null));
        $this->updateHypothesis($turn, $diag['branch'] ?? null, self::branchOf($sigs), [$diag['trigger_event_id'] ?? $responseEvent, $responseEvent]);
        $turn->eng['diagnosis'] = null;
        $this->descend($turn, $skill, $branch, $responseEvent);
    }

    private function routePrerequisite(Turn $turn, string $verdict, bool $independent, array $sigs, array $item, string $responseEvent): void
    {
        $skill = $turn->skill();
        if ($verdict === 'CORRECT' && $independent) {
            $turn->event('OBSERVATION', [
                'statement' => 'Succeeded independently on a fresh check of ' . $skill . '. Descent stops here.',
                'code' => 'PREREQUISITE_SUCCEEDED', 'branch' => null, 'skill_id' => $skill, 'item_id' => $item['item_id'],
                'evidence_event_ids' => [$responseEvent], 'provenance' => 'HOST_RULE', 'status' => 'OBSERVATION_NOT_CAUSE',
            ]);
            $this->contradictHypothesis($turn, $skill, $responseEvent);
            $frame = array_pop($turn->snap['return_stack']);
            $turn->snap['current_skill_id'] = $frame['parent_skill_id'];
            $turn->event('RETURN', ['from_skill' => $skill, 'to_skill' => $frame['parent_skill_id'], 'reason' => 'Prerequisite check succeeded; repair at the parent.', 'evidence_event_ids' => [$responseEvent]]);
            $this->beginRepair($turn, $frame['parent_skill_id'], 'Prerequisite ' . $skill . ' succeeded independently; bounded instructional trial at ' . $frame['parent_skill_id'] . '.', [$responseEvent]);
            return;
        }
        if ($verdict === 'INCORRECT') {
            $this->descend($turn, $skill, self::branchOf($sigs), $responseEvent);
            return;
        }
        $this->beginRepair($turn, $skill, 'Check at ' . $skill . ' was assisted or not in the requested form; bounded instructional trial.', [$responseEvent]);
    }

    private function routeIndependent(Turn $turn, string $verdict, bool $independent, array $sigs, array $item, string $responseEvent): void
    {
        $skill = $turn->skill();
        if ($verdict === 'CORRECT') {
            if ($independent && $this->criterionWillBeMet($turn, $skill)) {
                if ($turn->stackEmpty()) {
                    $this->completeTarget($turn);
                } else {
                    $this->returnUp($turn, 'Repair criterion met at ' . $skill . '.', $responseEvent);
                }
                return;
            }
            $this->presentItem($turn, 'INDEPENDENT', $skill, $this->nextVariant($turn, $skill));
            return;
        }
        if ($verdict === 'PARTIAL') {
            $this->presentItem($turn, 'INDEPENDENT', $skill, $item['variant_id']);
            return;
        }
        if (!$independent) {
            $this->beginRepair($turn, $skill, 'Assisted independent-check attempt was not correct; continue guided work (not a failed cycle).', [$responseEvent]);
            return;
        }
        $turn->eng['cycles'][$skill] = ($turn->eng['cycles'][$skill] ?? 0) + 1;
        $turn->snap['budgets']['failed_repair_cycles']++;
        $turn->event('REPAIR_CYCLE_FAILED', ['skill_id' => $skill, 'count' => $turn->eng['cycles'][$skill], 'evidence_event_ids' => [$responseEvent]]);
        if ($turn->eng['cycles'][$skill] >= self::MAX_FAILED_CYCLES) {
            $this->repairLimit($turn, $skill, $responseEvent);
            return;
        }
        $this->startDiagnosis($turn, $skill, 'INDEPENDENT_CHECK', $sigs, $item);
    }

    private function routeReturn(Turn $turn, string $verdict, bool $independent, array $sigs, array $item, string $responseEvent): void
    {
        $skill = $turn->skill();
        if ($verdict === 'CORRECT' && $independent) {
            if ($turn->stackEmpty()) {
                if ($this->criterionWillBeMet($turn, $skill)) {
                    $this->completeTarget($turn);
                    return;
                }
                $this->presentItem($turn, 'TARGET', $skill, $this->nextVariant($turn, $skill));
                return;
            }
            $turn->event('OBSERVATION', [
                'statement' => 'Parent ' . $skill . ' re-verified with fresh independent work before returning further up.',
                'code' => 'PARENT_REVERIFIED', 'branch' => null, 'skill_id' => $skill, 'item_id' => $item['item_id'],
                'evidence_event_ids' => [$responseEvent], 'provenance' => 'HOST_RULE', 'status' => 'OBSERVATION_NOT_CAUSE',
            ]);
            $this->returnUp($turn, 'Parent ' . $skill . ' re-verified with fresh work.', $responseEvent);
            return;
        }
        if ($verdict === 'CORRECT' || $verdict === 'PARTIAL') {
            $this->presentItem($turn, $turn->stackEmpty() ? 'TARGET' : 'PREREQUISITE', $skill, $item['variant_id']);
            return;
        }
        $this->startDiagnosis($turn, $skill, 'RETURN_TO_TARGET', $sigs, $item);
    }

    private function routeGuided(Turn $turn, string $verdict, array $facts, CheckOutcome $outcome, array $item): void
    {
        if ($verdict === 'CORRECT') {
            $this->advanceGuided($turn);
            return;
        }
        $step = $item['guided_step'];
        if ($step['wrong_choice_fact'] !== null && $verdict === 'INCORRECT') {
            $facts[] = $step['wrong_choice_fact'];
        }
        $this->stuck($turn, $facts);
    }

    // -------------------------------------------------------------- diagnosis

    private function startDiagnosis(Turn $turn, string $skill, string $fromMode, array $sigs, array $item): void
    {
        $branch = self::branchOf($sigs);
        $turn->eng['diagnosis'] = [
            'trigger_event_id' => $turn->triggerEventId,
            'from_mode' => $fromMode,
            'skill_id' => $skill,
            'branch' => $branch,
            'codes' => array_column($sigs, 'code'),
            'probes' => 1,
        ];
        if ($branch !== null) {
            $this->openHypothesis($turn, $branch, $skill, $sigs);
        }
        $this->transition($turn, 'DIAGNOSTIC_PROBE', 'Unsuccessful performance on ' . $skill . '; one fresh probe varying the implicated component (slip check).', [$turn->triggerEventId]);
        $avoid = $item['variant_id'] === 'constructed' ? $item['params'] : null;
        $this->presentItem($turn, 'DIAGNOSTIC', $skill, 'constructed', $avoid);
    }

    /**
     * D2: descend one declared edge along the implicated branch, or repair here at a boundary.
     * With $stuckStep (Round 2 ruling 8) the branch is the skill exercised by the guided step
     * the learner is stuck at, and descent leaves GUIDED_REPAIR directly.
     */
    private function descend(Turn $turn, string $skill, ?string $branch, string $triggerEvent, bool $stuckStep = false): void
    {
        if ($branch === null || !$this->curriculum->isDeclaredPrerequisite($skill, $branch)) {
            if ($stuckStep) {
                $note = $branch === $skill
                    ? 'The stuck question exercises ' . $skill . ' itself; there is no declared prerequisite to check. The breakdown is unresolved; this is not a learner failure.'
                    : 'The stuck question exercises ' . $branch . ', which is not a declared prerequisite of ' . $skill . '. The breakdown is unresolved; this is not a learner failure.';
                $turn->event('BOUNDARY', ['skill_id' => $branch, 'from_skill' => $skill, 'reason' => 'NOT_A_DECLARED_PREREQUISITE', 'trigger_event_id' => $triggerEvent, 'note' => $note]);
                $turn->eng['boundaries'][] = ['skill_id' => $branch, 'from_skill' => $skill, 'event_id' => $triggerEvent];
                $this->continueAfterStuck($turn, $skill, 'Stuck question exercises no declared prerequisite of ' . $skill . '.');
                return;
            }
            $turn->event('BOUNDARY', ['skill_id' => $branch, 'from_skill' => $skill, 'reason' => 'NO_IMPLICATED_BRANCH', 'trigger_event_id' => $triggerEvent,
                'note' => 'No error pattern implicated a declared prerequisite; uncertainty preserved.']);
            $this->beginRepair($turn, $skill, 'No discriminating branch; bounded instructional trial at ' . $skill . '.', [$triggerEvent]);
            return;
        }
        if (!BetaScope::contains($branch)) {
            $turn->event('BOUNDARY', ['skill_id' => $branch, 'from_skill' => $skill, 'reason' => 'NOT_AVAILABLE_IN_BETA', 'trigger_event_id' => $triggerEvent,
                'note' => $branch . ' is not available in beta. The branch is unresolved; this is not a learner failure.']);
            $turn->eng['boundaries'][] = ['skill_id' => $branch, 'from_skill' => $skill, 'event_id' => $triggerEvent];
            if ($stuckStep) {
                $this->continueAfterStuck($turn, $skill, 'Stuck question exercises ' . $branch . ', which is not available in beta.');
                return;
            }
            $this->beginRepair($turn, $skill, 'Implicated prerequisite ' . $branch . ' is not available in beta; repair at ' . $skill . '.', [$triggerEvent]);
            return;
        }
        $this->curriculum->skill($branch);
        $onPath = array_merge([$turn->snap['original_target_skill_id']], array_column($turn->snap['return_stack'], 'parent_skill_id'), [$skill]);
        if (count($turn->snap['return_stack']) >= self::MAX_DEPTH || in_array($branch, $onPath, true)) {
            $turn->event('BOUNDARY', ['skill_id' => $branch, 'from_skill' => $skill, 'reason' => count($turn->snap['return_stack']) >= self::MAX_DEPTH ? 'DEPTH_LIMIT' : 'ALREADY_ON_PATH', 'trigger_event_id' => $triggerEvent]);
            if ($stuckStep) {
                $this->continueAfterStuck($turn, $skill, 'Descent bounded.');
                return;
            }
            $this->beginRepair($turn, $skill, 'Descent bounded; repair at ' . $skill . '.', [$triggerEvent]);
            return;
        }
        $edge = $this->curriculum->edge($skill, $branch);
        $turn->snap['return_stack'][] = [
            'parent_skill_id' => $skill,
            'parent_mode' => $turn->mode(),
            'edge_type' => $edge['edge_type'],
            'edge_condition' => $edge['edge_condition'],
            'trigger_event_id' => $triggerEvent,
            'target_episode_id' => $turn->snap['target_episode_id'],
        ];
        $turn->event('DESCENT', ['from_skill' => $skill, 'to_skill' => $branch, 'edge_type' => $edge['edge_type'], 'edge_condition' => $edge['edge_condition'],
            'trigger_event_id' => $triggerEvent, 'depth' => count($turn->snap['return_stack']), 'trigger' => $stuckStep ? 'STUCK_AT_SMALLEST_GUIDED_STEP' : 'CONFIRMED_BREAKDOWN']);
        $turn->eng['path'][] = $branch;
        if ($stuckStep) {
            $turn->eng['guided'] = null;
            $this->transition($turn, 'PREREQUISITE_CHECK', 'Stuck at the smallest guided question, which exercises ' . $branch . ', a declared prerequisite of ' . $skill . '; check ' . $branch . ' directly.',
                [$triggerEvent], ['trigger' => 'STUCK_AT_SMALLEST_GUIDED_STEP']);
        } else {
            $this->transition($turn, 'PREREQUISITE_CHECK', 'Breakdown confirmed at ' . $skill . '; check declared prerequisite ' . $branch . ' implicated by the observed error.', [$triggerEvent]);
        }
        $turn->snap['current_skill_id'] = $branch;
        $this->presentItem($turn, 'PREREQUISITE', $branch, 'constructed');
    }

    private function returnUp(Turn $turn, string $reason, string $evidence): void
    {
        $from = $turn->skill();
        $frame = array_pop($turn->snap['return_stack']);
        $parent = $frame['parent_skill_id'];
        $turn->event('RETURN', ['from_skill' => $from, 'to_skill' => $parent, 'reason' => $reason, 'evidence_event_ids' => [$evidence]]);
        $this->transition($turn, 'RETURN_TO_TARGET', $reason . ' Return to ' . $parent . ' for fresh independent work.', [$evidence]);
        $turn->snap['current_skill_id'] = $parent;
        $turn->eng['guided'] = null;
        $this->presentItem($turn, $turn->stackEmpty() ? 'TARGET' : 'PREREQUISITE', $parent, $this->nextVariant($turn, $parent));
    }

    private function openHypothesis(Turn $turn, string $branch, string $skill, array $sigs): void
    {
        foreach ($turn->eng['hypotheses'] as $h) {
            if ($h['branch'] === $branch && $h['skill_id'] === $skill) {
                return;
            }
        }
        $id = Ids::make('hyp');
        $h = [
            'hypothesis_id' => $id, 'skill_id' => $skill, 'branch' => $branch, 'status' => 'PROVISIONAL',
            'statement' => 'The observed error pattern (' . implode(', ', array_column($sigs, 'label')) . ') may involve ' . $branch
                . ($this->curriculum->has($branch) ? ' (' . $this->curriculum->title($branch) . ')' : '') . '. Testable hypothesis only.',
            'evidence_event_ids' => [$turn->triggerEventId],
        ];
        $turn->eng['hypotheses'][] = $h;
        $turn->event('HYPOTHESIS', $h);
    }

    private function updateHypothesis(Turn $turn, ?string $first, ?string $second, array $evidence): void
    {
        foreach ($turn->eng['hypotheses'] as $i => $h) {
            if ($first !== null && $h['branch'] === $first && $second === $first && $h['status'] === 'PROVISIONAL') {
                $turn->eng['hypotheses'][$i]['status'] = 'SUPPORTED_FOR_OBSERVED_PATTERN';
                $turn->eng['hypotheses'][$i]['evidence_event_ids'] = array_values(array_unique(array_merge($h['evidence_event_ids'], $evidence)));
                $turn->event('HYPOTHESIS', $turn->eng['hypotheses'][$i] + ['note' => 'The same pattern recurred on a fresh probe; supported for the observed pattern only, not a permanent trait.']);
            }
        }
    }

    private function contradictHypothesis(Turn $turn, string $skill, string $evidence): void
    {
        foreach ($turn->eng['hypotheses'] as $i => $h) {
            if ($h['branch'] === $skill && $h['status'] !== 'CONTRADICTED') {
                $turn->eng['hypotheses'][$i]['status'] = 'CONTRADICTED';
                $turn->eng['hypotheses'][$i]['evidence_event_ids'][] = $evidence;
                $turn->event('HYPOTHESIS', $turn->eng['hypotheses'][$i] + ['note' => 'An independent check of ' . $skill . ' succeeded, which conflicts with this hypothesis for the isolated skill.']);
            }
        }
    }

    // ----------------------------------------------------------------- repair

    private function beginRepair(Turn $turn, string $skill, string $reason, array $evidence): void
    {
        $this->transition($turn, 'GUIDED_REPAIR', $reason, $evidence);
        $turn->snap['current_skill_id'] = $skill;
        $route = $this->implicatedRoute($turn, $skill);
        // The opening step is shown with the feedback facts, so no step answer may coincide with a number in them.
        // R4-B: this problem and the next fresh one keep the structure of the item the learner failed.
        $failed = $turn->eng['last_failed'][$skill] ?? null;
        $keep = $failed === null ? [] : StructuralFeatures::of($failed['template_id'], $failed['params']);
        $plan = $this->planner->plan($skill, $this->rng, false, ['route' => $route, 'avoid_text' => implode(' ', $turn->facts), 'keep' => $keep]);
        $turn->eng['guided'] = ['plan' => $plan, 'path' => [$plan['steps'][0]['id']], 'done' => [], 'entered' => [], 'alt' => [], 'problems' => 0, 'clean' => true, 'phrasing' => 'main',
            'keep' => $keep, 'keep_left' => 1];
        $turn->event('REPAIR_STARTED', ['skill_id' => $skill, 'plan_id' => $plan['plan_id'], 'faded' => false, 'route' => $plan['route'], 'reason' => $reason,
            'kept_features' => $plan['kept_features'], 'unkept_features' => $plan['unkept_features'], 'failed_item_id' => $failed['item_id'] ?? null]);
        // D1: repair may open by stating what the learner's last answer did.
        $this->presentStep($turn, $turn->facts);
    }

    /** Q07 repairs use the expansion route when the distribution branch was implicated. */
    private function implicatedRoute(Turn $turn, string $skill): string
    {
        if ($skill !== 'Q07') {
            return 'divide';
        }
        foreach ($turn->eng['hypotheses'] as $h) {
            if ($h['branch'] === 'E06' && $h['status'] !== 'CONTRADICTED') {
                return 'expand';
            }
        }
        return 'divide';
    }

    private function findStep(array $steps, string $id): ?array
    {
        foreach ($steps as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
            foreach ($s['smaller'] as $sub) {
                if ($sub['id'] === $id) {
                    return $sub;
                }
            }
        }
        return null;
    }

    private function currentStep(array $guided): array
    {
        $id = $guided['path'][count($guided['path']) - 1];
        return $this->findStep($guided['plan']['steps'], $id);
    }

    private function advanceGuided(Turn $turn): void
    {
        $g = &$turn->eng['guided'];
        $id = $g['path'][count($g['path']) - 1];
        $g['done'][$id] = true;
        if (count($g['path']) === 2) {
            $parentId = $g['path'][0];
            $parent = $this->findStep($g['plan']['steps'], $parentId);
            foreach ($parent['smaller'] as $sub) {
                if (!isset($g['done'][$sub['id']])) {
                    $g['path'] = [$parentId, $sub['id']];
                    unset($g);
                    $this->presentStep($turn, []);
                    return;
                }
            }
            $g['path'] = [$parentId];
            unset($g);
            $this->presentStep($turn, [], 'Now try the earlier question again.', 'main', true);
            return;
        }
        foreach ($g['plan']['steps'] as $s) {
            if (!isset($g['done'][$s['id']])) {
                $g['path'] = [$s['id']];
                unset($g);
                $this->presentStep($turn, []);
                return;
            }
        }
        // Guided problem complete.
        $g['problems']++;
        $plan = $g['plan'];
        $clean = $g['clean'];
        $problems = $g['problems'];
        unset($g);
        $skill = $turn->skill();
        $turn->event('GUIDED_PROBLEM_COMPLETED', ['skill_id' => $skill, 'plan_id' => $plan['plan_id'], 'faded' => $plan['faded'], 'without_breakdown' => $clean]);
        if (($plan['faded'] && $clean) || $problems >= self::MAX_GUIDED_PROBLEMS) {
            $this->startIndependentCheck($turn, $skill, $plan['faded'] && $clean ? 'Support faded: the whole problem was answered without breaking it down.' : 'Guided problem budget reached; check independently.');
            return;
        }
        [$keep, $keepLeft] = $this->keepForNextPlan($turn);
        $next = $this->planner->plan($skill, $this->rng, true, ['route' => $plan['route'], 'keep' => $keep]);
        $turn->eng['guided'] = ['plan' => $next, 'path' => [$next['steps'][0]['id']], 'done' => [], 'entered' => [], 'alt' => [], 'problems' => $problems, 'clean' => true, 'phrasing' => 'main',
            'keep' => $turn->eng['guided']['keep'] ?? [], 'keep_left' => $keepLeft];
        $turn->event('REPAIR_STARTED', ['skill_id' => $skill, 'plan_id' => $next['plan_id'], 'faded' => true, 'route' => $next['route'], 'reason' => 'Fading support: whole problem first; smaller questions only if needed.',
            'kept_features' => $next['kept_features'], 'unkept_features' => $next['unkept_features']]);
        $this->presentStep($turn, []);
    }

    /**
     * D1: when stuck, ask a smaller question; never show the answer.
     *
     * @return string what follows: SMALLER, ANOTHER_WAY, PREREQUISITE (a prerequisite check) or OTHER (after a boundary)
     */
    private function stuck(Turn $turn, array $facts): string
    {
        $g = &$turn->eng['guided'];
        $g['clean'] = false;
        $id = $g['path'][count($g['path']) - 1];
        $step = $this->findStep($g['plan']['steps'], $id);
        if ($step['smaller'] !== [] && !isset($g['entered'][$id])) {
            $g['entered'][$id] = true;
            $g['path'] = [$id, $step['smaller'][0]['id']];
            unset($g);
            $this->presentStep($turn, $facts);
            return 'SMALLER';
        }
        if ($step['alt_prompt'] !== null && !isset($g['alt'][$id])) {
            $g['alt'][$id] = true;
            unset($g);
            // R4-A: also a re-ask of a failed step. Host-written facts may be shown; no model framing.
            foreach (array_values(array_unique($facts)) as $fact) {
                $turn->say('INFORMATION', $fact);
            }
            $this->presentStep($turn, [], 'Here is the same question, asked another way.', 'alt', true);
            return 'ANOTHER_WAY';
        }
        // Round 2 ruling 8: still stuck at the smallest available question. Set the
        // problem aside (no answer shown) and check the declared prerequisite that
        // the step exercises directly, or record the boundary.
        $g['problems']++;
        $plan = $g['plan'];
        unset($g);
        $turn->event('GUIDED_PROBLEM_ABANDONED', ['skill_id' => $turn->skill(), 'plan_id' => $plan['plan_id'], 'step_id' => $id, 'exercises' => $step['exercises'],
            'note' => 'Stuck at the smallest available question; no answer was shown.']);
        $this->descend($turn, $turn->skill(), $step['exercises'], $turn->triggerEventId, true);
        return $turn->mode() === 'PREREQUISITE_CHECK' ? 'PREREQUISITE' : 'OTHER';
    }

    /** After a boundary at a stuck question: a fresh guided problem at the same skill, within the problem budget. */
    private function continueAfterStuck(Turn $turn, string $skill, string $reason): void
    {
        $g = $turn->eng['guided'];
        if ($g['problems'] >= self::MAX_GUIDED_PROBLEMS) {
            $this->startIndependentCheck($turn, $skill, $reason . ' Guided problem budget reached; check independently.');
            return;
        }
        [$keep, $keepLeft] = $this->keepForNextPlan($turn);
        $plan = $this->planner->plan($skill, $this->rng, false, ['route' => $g['plan']['route'], 'keep' => $keep]);
        $turn->eng['guided'] = ['plan' => $plan, 'path' => [$plan['steps'][0]['id']], 'done' => [], 'entered' => [], 'alt' => [], 'problems' => $g['problems'], 'clean' => true, 'phrasing' => 'main',
            'keep' => $g['keep'] ?? [], 'keep_left' => $keepLeft];
        $turn->event('REPAIR_STARTED', ['skill_id' => $skill, 'plan_id' => $plan['plan_id'], 'faded' => false, 'route' => $plan['route'], 'reason' => $reason . ' A new guided problem with different numbers.',
            'kept_features' => $plan['kept_features'], 'unkept_features' => $plan['unkept_features']]);
        $this->presentStep($turn, []);
    }

    /** R4-B: the first fresh guided problem after the first one still keeps the failed item's features; later ones may vary. */
    private function keepForNextPlan(Turn $turn): array
    {
        $g = $turn->eng['guided'];
        $left = (int) ($g['keep_left'] ?? 0);
        return $left > 0 ? [$g['keep'] ?? [], $left - 1] : [[], 0];
    }

    private function startIndependentCheck(Turn $turn, string $skill, string $reason): void
    {
        $this->transition($turn, 'INDEPENDENT_CHECK', $reason);
        $turn->eng['guided'] = null;
        $this->presentItem($turn, 'INDEPENDENT', $skill, $this->nextVariant($turn, $skill));
    }

    private function repairLimit(Turn $turn, string $skill, string $evidence): void
    {
        $issue = [
            'type' => 'REPAIR_LIMIT_REACHED',
            'affected_scope' => 'Repair of ' . $skill . ' in the current target episode.',
            'detail' => 'Three independent-check cycles at this repair point did not meet the criterion after intervening instruction. Observations are retained; no foundational incapacity is inferred.',
            'next_action' => 'Pause this repair: end the session for a summary or choose another topic.',
        ];
        $turn->snap['issue'] = $issue;
        $turn->event('ISSUE', $issue + ['evidence_event_ids' => [$evidence]]);
        $turn->eng['status'] = 'HOLD';
        $turn->eng['hold_kind'] = 'REPAIR_LIMIT';
        $turn->eng['report_status'] = 'BOUNDARY_REACHED';
        $turn->eng['active'] = null;
        $turn->eng['guided'] = null;
        $turn->say('INFORMATION', 'Let\'s pause this topic here. You can end the session to see a summary, or choose another topic.');
    }

    private function completeTarget(Turn $turn): void
    {
        $skill = $turn->skill();
        $three = BetaScope::threeVariant($skill);
        $this->transition($turn, 'SESSION_REPORT', 'Target ' . $skill . ' criterion met in this session; requested work complete.', [$turn->triggerEventId], ['end_session' => true]);
        $turn->eng['report_status'] = 'COMPLETED';
        $turn->eng['active'] = null;
        $turn->eng['guided'] = null;
        $turn->reportNeeded = true;
        $turn->say('INFORMATION', $three
            ? 'You answered three different kinds of questions on this skill correctly in a row, without help. That meets this session\'s check for the skill. It is a check from today\'s session, not a long-term claim.'
            : 'You answered three questions on this skill correctly in a row, without help. That meets this session\'s check for the numeric part of the skill; other parts of the skill are not checked in this beta.');
        $turn->say('INFORMATION', 'Your session summary is ready.');
    }

    private function closeSession(Turn $turn, string $reason): void
    {
        if ($turn->mode() !== 'SESSION_REPORT') {
            $this->transition($turn, 'SESSION_REPORT', $reason, [], ['end_session' => true]);
        }
        if ($turn->eng['report_status'] === 'IN_PROGRESS') {
            $turn->eng['report_status'] = 'INTERRUPTED';
        }
        $this->transition($turn, 'SESSION_CLOSED', 'Report accepted; session closed.');
        if ($turn->eng['active'] !== null) {
            $turn->event('ITEM_CLOSED', ['item_id' => $turn->eng['active']['item_id'], 'item_version' => $turn->eng['active']['item_version'], 'reason' => 'SESSION_ENDED']);
        }
        $turn->eng['active'] = null;
        $turn->eng['guided'] = null;
        $turn->eng['menu'] = null;
        $turn->eng['status'] = 'CLOSED';
        $turn->eng['pending_generation'] = null;
        $turn->snap['issue'] = null;
        $turn->reportNeeded = true;
        $turn->event('SESSION_CLOSED', ['report_status' => $turn->eng['report_status']]);
        $turn->say('INFORMATION', 'Session ended. Your summary is ready.');
    }

    // ------------------------------------------------- support, skip, not learned

    /**
     * R5-C rows 1–2: "idk" and similar, or asking for the answer. In guided repair: a smaller
     * question (D1). On an independent item: the existing hint path; the item becomes assisted.
     */
    private function onStuckEntry(Turn $turn, string $kind, string $responseEvent): void
    {
        $active = $turn->eng['active'];
        if ($turn->mode() === 'GUIDED_REPAIR') {
            $turn->event('ITEM_CLOSED', ['item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'reason' => 'STUCK_ENTRY',
                'note' => 'The learner typed that they are stuck or asked for the answer; not an incorrect attempt.']);
            $turn->eng['active'] = null;
            $at = count($turn->messages);
            $next = $this->stuck($turn, []);
            if ($kind === 'ASK_FOR_ANSWER') {
                // Round 5.1 P1: the line names what actually follows, placed before it.
                $turn->sayAt($at, 'INFORMATION', match ($next) {
                    'SMALLER' => EntryInterpreter::LINE_ASK_SMALLER,
                    'ANOTHER_WAY' => EntryInterpreter::LINE_ASK_ANOTHER_WAY,
                    'PREREQUISITE' => EntryInterpreter::LINE_ASK_PREREQUISITE,
                    default => EntryInterpreter::LINE_ASK_DIFFERENT_PROBLEM,
                });
            }
            return;
        }
        if ($kind === 'ASK_FOR_ANSWER') {
            $turn->say('INFORMATION', EntryInterpreter::LINE_ASK_HINT);
        }
        $this->onSupport($turn, 'REQUEST_HINT', $responseEvent);
    }

    private function onSupport(Turn $turn, string $action, ?string $entryEvent = null): void
    {
        $active = $turn->eng['active'];
        $kind = $action === 'REQUEST_HINT' ? 'HINT' : 'EXPLANATION';
        $supportEvent = $turn->event('SUPPORT_GIVEN', ['item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'kind' => $kind,
            'committed_before_display' => true] + ($entryEvent === null ? [] : ['trigger' => 'STUCK_ENTRY', 'entry_event_id' => $entryEvent]),
            $entryEvent === null ? $turn->triggerEventId : null);
        $turn->snap['budgets']['instructional_turns']++;
        if ($turn->mode() === 'GUIDED_REPAIR') {
            $turn->event('ITEM_CLOSED', ['item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'reason' => 'STUCK_' . $kind]);
            $turn->eng['active'] = null;
            $this->stuck($turn, []);
            return;
        }
        $turn->eng['active']['support_status'] = 'ASSISTED';
        $turn->eng['active']['support_event_ids'][] = $supportEvent;
        $turn->say('INFORMATION', 'This question now counts as practice with help, not as independent work.');
        $turn->generation = [
            'operation' => $kind,
            'message_type' => 'FEEDBACK',
            'role' => $active['role'],
            'skill_id' => $active['skill_id'],
            'variant_id' => $active['variant_id'],
            'component_id' => $active['component_id'],
            'hint_focus' => $this->hintFocus($active),
        ];
    }

    private function onSkip(Turn $turn): void
    {
        $active = $turn->eng['active'];
        $turn->event('ITEM_CLOSED', ['item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'reason' => 'SKIPPED'], $turn->triggerEventId);
        $turn->eng['active'] = null;
        $turn->say('INFORMATION', 'Skipped. A skipped question is not marked wrong.');
        if ($turn->mode() === 'DIAGNOSTIC_PROBE') {
            $turn->eng['diagnosis']['probes'] = ($turn->eng['diagnosis']['probes'] ?? 1) + 1;
            if ($turn->eng['diagnosis']['probes'] > self::MAX_PROBES_PER_DECISION) {
                $turn->eng['diagnosis'] = null;
                $this->beginRepair($turn, $turn->skill(), 'Probe budget reached without usable evidence; bounded instructional trial.', [$turn->triggerEventId]);
                return;
            }
        }
        $this->presentItem($turn, $active['role'], $active['skill_id'], $active['variant_id'], $active['role'] === 'DIAGNOSTIC' ? $active['params'] : null);
    }

    private function onNotLearned(Turn $turn): void
    {
        $active = $turn->eng['active'];
        $label = $turn->mode() === 'GUIDED_REPAIR' ? 'I_DONT_KNOW' : 'NOT_LEARNED';
        $turn->event('ITEM_CLOSED', ['item_id' => $active['item_id'], 'item_version' => $active['item_version'], 'reason' => $label,
            'note' => 'Not learned / I don\'t know is not an incorrect attempt.'], $turn->triggerEventId);
        $turn->eng['active'] = null;
        $skill = $turn->skill();
        switch ($turn->mode()) {
            case 'GUIDED_REPAIR':
                $this->stuck($turn, []);
                return;
            case 'TARGET_ASSESSMENT':
            case 'RETURN_TO_TARGET':
                $this->transition($turn, 'DIAGNOSTIC_PROBE', 'Learner reports not having learned this; no error to probe.', [$turn->triggerEventId]);
                // fall through to a bounded trial
            default:
                $turn->eng['diagnosis'] = null;
                $this->beginRepair($turn, $skill, 'Learner reports not having learned this; bounded instructional trial (not counted as incorrect).', [$turn->triggerEventId]);
        }
    }

    private function hintFocus(array $active): string
    {
        return match ($active['template_id']) {
            'GROUPED_LINEAR', 'GROUPED_LINEAR_TRANSFER' => 'The number outside the parentheses multiplies the whole group, not only the x.',
            'GROUPED_FIRST_STEP_CONTRAST' => 'For each option, decide whether the new equation still has the same solution as the original one.',
            'POSITIVE_DISTRIBUTION', 'DISTRIBUTION_TRANSFER' => 'The factor outside the parentheses multiplies every term inside them.',
            'DISTRIBUTION_CONTRAST' => 'An expression is equal to the original only if the factor has multiplied every term inside the parentheses.',
            'TWO_STEP' => 'Undo the added or subtracted number on both sides first, then undo the multiplication.',
            'ONE_STEP_MULTIPLY' => 'Undo a multiplication by dividing both sides by the same number.',
            'ONE_STEP_ADD' => 'Undo an addition or subtraction by doing the opposite to both sides.',
            'SIGNED_ADD' => 'Look at which number is farther from 0; its sign decides the sign of the sum.',
            'SIGNED_SUBTRACT' => 'Subtracting a number gives the same result as adding its opposite.',
            'SIGNED_MULTIPLY' => 'Multiply the sizes first, then decide the sign from how many factors are negative.',
            'SIGNED_DIVIDE' => 'Divide the sizes first, then decide the sign the same way as for multiplication.',
            'WHOLE_MULTIPLY_DIVIDE' => 'Split one number into parts you know, such as 10 and the rest.',
            default => 'Look again at the structure of the question.',
        };
    }

    private function representationHint(string $skill): string
    {
        return match ($skill) {
            'Q07', 'Q06', 'Q05', 'Q04' => 'Think of the equation as a balance: whatever you do to one side, you also do to the other.',
            'E06' => 'Picture the parentheses as equal groups, each holding the same terms.',
            'N03', 'N04' => 'Picture a number line with 0 in the middle.',
            'N05', 'N06' => 'Work with the sizes of the numbers first, then decide the sign.',
            default => 'Break the numbers into parts you know.',
        };
    }

    // ------------------------------------------------------------- presenting

    private function presentItem(Turn $turn, string $role, string $skill, string $variant, ?array $avoid = null): void
    {
        $this->curriculum->skill($skill);
        $template = $this->factory->templateFor($skill, $variant);
        if ($role === 'TARGET') {
            $turn->snap['budgets']['target_assessment_items']++;
        } elseif (in_array($role, ['DIAGNOSTIC', 'PREREQUISITE'], true)) {
            $turn->snap['budgets']['diagnostic_items']++;
        }
        $generation = [
            'operation' => 'PRESENT_ITEM',
            'message_type' => 'QUESTION',
            'role' => $role,
            'skill_id' => $skill,
            'variant_id' => $variant,
            'component_id' => $template->componentId(),
            'template_ids' => [$template->id()],
            'avoid_parameters' => $avoid,
        ];
        if ($role === 'TARGET' && $this->continuationDue($turn)) {
            $turn->eng['pending_generation'] = $generation;
            $turn->eng['continuation_offered'] = true;
            $turn->eng['menu'] = ['menu_id' => 'continuation', 'options' => [
                ['option_id' => 'continue', 'label' => 'Continue for 10 more minutes', 'action' => 'CONTINUE_SESSION'],
                ['option_id' => 'end', 'label' => 'End and see the summary', 'action' => 'END_SESSION'],
            ]];
            $turn->event('CONTINUATION_OFFERED', ['target_assessment_items' => $turn->snap['budgets']['target_assessment_items'], 'deadline' => $turn->eng['deadline']]);
            $turn->say('INFORMATION', 'You have been working for a while. Would you like to continue or end and see your summary?');
            return;
        }
        $turn->generation = $generation;
    }

    private function continuationDue(Turn $turn): bool
    {
        if ($turn->eng['continuation_offered']) {
            return false;
        }
        return $turn->snap['budgets']['target_assessment_items'] > $turn->eng['continuation_threshold'] || Time::iso($turn->now) > $turn->eng['deadline'];
    }

    /**
     * @param bool $hostOnly R4-A: a re-ask of a previously failed guided step shows the host line and the item only;
     *                       no model framing is requested or shown.
     */
    private function presentStep(Turn $turn, array $facts, ?string $lead = null, string $phrasing = 'main', bool $hostOnly = false): void
    {
        $g = $turn->eng['guided'];
        $step = $this->currentStep($g);
        $turn->snap['budgets']['instructional_turns']++;
        if ($lead !== null) {
            $turn->say('INFORMATION', $lead);
        }
        $turn->generation = [
            'operation' => 'GUIDED_STEP',
            'message_type' => 'QUESTION',
            'role' => 'GUIDED',
            'skill_id' => $turn->skill(),
            'variant_id' => 'guided_step',
            'component_id' => 'guided_repair',
            'template_ids' => [AcceptanceGate::GUIDED_TEMPLATE],
            'fixed_parameters' => ['plan_id' => $g['plan']['plan_id'], 'step_id' => $step['id'], 'phrasing' => $phrasing],
            'feedback_facts' => array_values(array_unique($facts)),
            'host_only' => $hostOnly,
        ];
    }

    private function nextVariant(Turn $turn, string $skill): string
    {
        $events = array_merge($this->store->events($turn->snap['session_id']), $turn->events);
        $computed = CriteriaCalculator::compute($events);
        $run = $computed['runs'][$turn->snap['target_episode_id'] . '|' . $skill] ?? [];
        return CriteriaCalculator::nextVariant($skill, $run);
    }

    /** Would the criterion for $skill be met once this turn's events are committed? */
    private function criterionWillBeMet(Turn $turn, string $skill): bool
    {
        $events = array_merge($this->store->events($turn->snap['session_id']), $turn->events);
        foreach (CriteriaCalculator::compute($events)['criteria'] as $c) {
            if ($c['skill_id'] === $skill && $c['target_episode_id'] === $turn->snap['target_episode_id'] && $c['immediate_status'] !== 'NOT_MET') {
                return true;
            }
        }
        return false;
    }

    // ============================================================= generation

    /** Call the provider (no transaction held) and fold the accepted output into the turn. */
    private function runGeneration(Turn $turn): void
    {
        $gen = $turn->generation;
        if ($gen === null) {
            return;
        }
        $sessionId = $turn->snap['session_id'];
        if ($gen['host_only'] ?? false) {
            $result = $this->model->hostOnly($this->allocation($turn, $gen), $this->rng, $this->store->fingerprints($sessionId));
            $turn->event('HOST_ONLY_STEP', ['operation' => $gen['operation'], 'step_id' => $gen['fixed_parameters']['step_id'],
                'note' => 'Re-ask of a previously failed guided step: host line and item only; no model call (round 4, R4-A).']);
            $this->presentGenerated($turn, $gen, $result, $sessionId);
            return;
        }
        // Never start a provider call that could outlive this operation's lease:
        // a slow provider then produces a retryable hold, not a discarded result.
        $leaseEnds = $turn->now->getTimestamp() + $this->config->int('lease_seconds') - 5;
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
        $provider = $this->model->provider();
        $log = function (string $kind, string $outcome, ?ProviderResponse $resp = null) use ($sessionId, $provider, $turn): void {
            $turn->modelCalls++;
            // Metadata only (round 3 ruling 3–4): finish reason, token counts, duration. Never reasoning text.
            $this->store->logModelCall($sessionId, null, $kind, $provider->name(), $provider->model(), $outcome, $this->clock->now(), $resp === null ? [] : [
                'finish_reason' => $resp->finishReason, 'duration_ms' => $resp->durationMs,
                'prompt_tokens' => $resp->usage['prompt_tokens'] ?? null, 'completion_tokens' => $resp->usage['completion_tokens'] ?? null, 'reasoning_tokens' => $resp->usage['reasoning_tokens'] ?? null,
                'transport_detail' => $resp->diagnostics,
            ]);
        };

        $binding = $turn->snap['binding'];
        if (!$binding['acknowledged'] || $binding['provider'] !== $provider->name() || $binding['model'] !== $provider->model() || $binding['architecture_sha256'] !== $this->model->architectureSha256()) {
            $bound = $this->model->bind($budget, $log);
            if (!$bound['ok']) {
                $this->hold($turn, $gen, $bound['failure']);
                return;
            }
            $turn->snap['binding'] = $bound['binding'];
            $turn->event('BINDING_ACCEPTED', $bound['binding'] + [
                'handshake' => $this->model->expectedHandshake(),
                'note' => 'Exact activation line received in text mode. This acknowledges the contract; it is not evidence of enforcement.',
            ]);
        }

        $allocation = $this->allocation($turn, $gen);
        $context = $this->context($turn, $allocation);
        $expect = [
            'session_id' => $sessionId,
            'revision' => $turn->snap['revision'],
            'event_id' => $turn->triggerEventId,
            'mode' => $turn->mode(),
            'current_skill' => $turn->skill(),
            'disclosure_guard' => in_array($gen['operation'], ['GUIDED_STEP', 'HINT', 'EXPLANATION'], true),
            'learner_text_shown' => !self::hostLeadOnly($gen),
            'protected_answers' => $this->protectedAnswers($turn, $gen),
            'completed_answers' => $this->completedAnswers($turn, $gen),
            'planned_transition' => $this->plannedTransition($turn),
            'known_event_ids' => array_merge($turn->snap['accepted_event_ids'], array_column($turn->events, 'event_id')),
        ];
        $result = $this->model->generate($context, $expect, $budget, $log, $this->rng, $this->store->fingerprints($sessionId));
        foreach ($result['repairs'] as $r) {
            $turn->event('MODEL_OUTPUT_REJECTED', ['attempt' => $r['attempt'], 'errors' => $r['errors'], 'operation' => $gen['operation'],
                'note' => 'Rejected before display; bounded repair requested. Not a learner failure.']);
        }
        if (!$result['ok']) {
            $this->hold($turn, $gen, $result['failure']);
            return;
        }
        // Audit record: which operation was accepted and after how many attempts.
        $turn->event('MODEL_OUTPUT_ACCEPTED', ['operation' => $gen['operation'], 'attempts' => count($result['repairs']) + 1]);
        foreach ($result['proposals'] as $p) {
            $turn->event($p['status'] === 'REJECTED' ? 'MODEL_PROPOSAL_REJECTED' : 'MODEL_PROPOSAL', $p);
        }
        $this->presentGenerated($turn, $gen, $result, $sessionId);
    }

    /** Fold an accepted generation (model or host-only) into the turn: message, item record, active item. */
    private function presentGenerated(Turn $turn, array $gen, array $result, string $sessionId): void
    {
        $turn->generation = null;
        $hostLead = self::hostLeadOnly($gen);
        if ($hostLead) {
            // R5-A: an independent item is never shown with model-written text. The model's
            // learner text from this call is discarded (not shown, not kept); the host line
            // states only what is asked.
            if (($result['discarded_message_chars'] ?? 0) > 0) {
                $turn->event('MODEL_TEXT_DISCARDED', ['operation' => $gen['operation'], 'role' => $gen['role'], 'characters' => $result['discarded_message_chars'],
                    'note' => 'Model text proposed with an independent item is never shown (round 5, R5-A).']);
            }
            $turn->say('INFORMATION', self::INDEPENDENT_LEAD);
            $lead = self::INDEPENDENT_LEAD;
        } else {
            $turn->say('INFORMATION', $result['student_message'], 'MODEL');
            $lead = $result['student_message'];
        }
        if ($gen['message_type'] === 'FEEDBACK') {
            $this->remember($turn, 'tutor', $turn->triggerEventId, $lead);
            return;
        }
        $record = $result['record'];
        foreach ($result['flags'] as $f) {
            $turn->event('ITEM_FLAGGED', $f + ['item_id' => $record['item_id'], 'item_version' => $record['item_version'],
                'note' => 'Model-proposed key disagreed with the host-derived key; the host key governs.']);
        }
        $turn->items[] = ['record' => $record, 'fingerprint' => $result['fingerprint']];
        $independentEligible = $record['independent_eligible_proposal'] && !in_array($result['fingerprint'], $this->store->fingerprints($sessionId), true);
        $turn->event('ITEM_PRESENTED', [
            'item_id' => $record['item_id'], 'item_version' => $record['item_version'], 'skill_id' => $record['skill_id'], 'role' => $record['role'],
            'variant_id' => $record['variant_id'], 'component_id' => $record['component_id'], 'template_id' => $record['template_id'],
            'episode_id' => $turn->snap['target_episode_id'], 'fingerprint' => $result['fingerprint'], 'independent_eligible' => $independentEligible,
            'host_generated_parameters' => $result['host_generated'], 'mode' => $turn->mode(), 'prompt' => $record['prompt'],
            'model_text_shown' => !$hostLead && trim($result['student_message']) !== '',
        ]);
        $active = [
            'item_id' => $record['item_id'], 'item_version' => $record['item_version'], 'role' => $record['role'], 'skill_id' => $record['skill_id'],
            'variant_id' => $record['variant_id'], 'component_id' => $record['component_id'], 'template_id' => $record['template_id'],
            'response_type' => $record['response_type'], 'prompt' => $record['prompt'], 'input_help' => $this->inputHelp($record),
            'support_status' => $record['role'] === 'GUIDED' ? 'ASSISTED' : 'INDEPENDENT', 'independent_eligible' => $independentEligible,
            'params' => $record['parameters'], 'answer_spec' => $record['answer_spec'], 'objects' => $record['mathematical_objects'],
            'support_event_ids' => [], 'unscorable' => false,
        ];
        if ($record['role'] === 'GUIDED') {
            $g = $turn->eng['guided'];
            $active['guided_step'] = $this->currentStep($g);
            $active['guided_plan_params'] = $g['plan']['params'];
            $active['guided_template_id'] = $g['plan']['template_id'];
        }
        $turn->eng['active'] = $active;
        $turn->snap['active_item_id'] = $record['item_id'];
        $turn->snap['active_item_version'] = $record['item_version'];
        $this->remember($turn, 'tutor', $turn->triggerEventId, trim($lead . ' ' . $record['prompt']));
    }

    /** R5-A: every presentation of an independent item (target, probe, prerequisite check, independent check, return). */
    private static function hostLeadOnly(array $gen): bool
    {
        return $gen['message_type'] === 'QUESTION' && $gen['role'] !== 'GUIDED';
    }

    private function inputHelp(array $record): string
    {
        if ($record['template_id'] === AcceptanceGate::GUIDED_TEMPLATE) {
            return match ($record['response_type']) {
                'scalar' => 'Enter a single number, for example 7, -3 or 5/2.',
                'expression' => 'Enter an expression using x, for example 2x + 5.',
                default => 'Choose the option that applies.',
            };
        }
        return $this->factory->get($record['template_id'])->inputHelp();
    }

    private function hold(Turn $turn, array $gen, array $failure): void
    {
        // Round 2 ruling 6: a provider timeout, HTTP error or budget stop is not a verification failure.
        $type = match ($failure['type']) {
            'BINDING_NOT_ESTABLISHED' => 'CONTRACT_CONFLICT',
            'INVALID_OUTPUT' => 'INVALID_ITEM',
            'PROVIDER_UNAVAILABLE', 'BUDGET' => 'PROVIDER_UNAVAILABLE',
            default => 'ITEM_VERIFICATION_UNAVAILABLE',
        };
        $issue = [
            'type' => $type,
            'affected_scope' => 'Preparing the next ' . strtolower($gen['operation']) . ' for ' . $gen['skill_id'] . '.',
            'detail' => 'Model provider: ' . $failure['type'] . ' (' . $failure['detail'] . '). Accepted work is preserved; this is not a learner failure.',
            'next_action' => 'Retry with RESUME_SESSION, or end the session for a partial summary.',
        ];
        $turn->event('PROVIDER_FAILURE', ['failure' => $failure, 'operation' => $gen['operation']]);
        $turn->event('ISSUE', $issue);
        $turn->snap['issue'] = $issue;
        $turn->eng['status'] = 'HOLD';
        $turn->eng['hold_kind'] = 'GENERATION';
        $turn->eng['pending_generation'] = $gen;
        $turn->generation = null;
        if ($gen['message_type'] === 'QUESTION') {
            $turn->snap['active_item_id'] = null;
            $turn->snap['active_item_version'] = null;
        }
        $turn->say('ERROR', 'Your work is saved, but the next step could not be prepared right now. You can try again or end the session.');
    }

    private function holdMissingCurriculum(Turn $turn, string $skillId): void
    {
        $issue = [
            'type' => 'MISSING_CURRICULUM',
            'affected_scope' => 'Operation requiring skill ' . $skillId . '.',
            'detail' => 'The canonical record for ' . $skillId . ' is not available. No record is invented; the parent state and return path are preserved.',
            'next_action' => 'Restore the curriculum resource, or end the session for a partial summary.',
        ];
        $turn->event('ISSUE', $issue, $turn->triggerEventId);
        $turn->snap['issue'] = $issue;
        $turn->eng['status'] = 'HOLD';
        $turn->eng['hold_kind'] = 'MISSING_CURRICULUM';
        $turn->say('ERROR', 'Something needed for the next step is not available right now. Your work is saved.');
    }

    private function plannedTransition(Turn $turn): ?array
    {
        for ($i = count($turn->events) - 1; $i >= 0; $i--) {
            if ($turn->events[$i]['type'] === 'TRANSITION') {
                return ['to' => $turn->events[$i]['data']['to'], 'skill_id' => $turn->skill()];
            }
        }
        return null;
    }

    private function allocation(Turn $turn, array $gen): array
    {
        $alloc = [
            'required_message_type' => $gen['message_type'],
            'operation' => $gen['operation'],
            'item_id' => Ids::make('itm'),
            'item_version' => 1,
            'role' => $gen['role'],
            'skill_id' => $gen['skill_id'],
            'variant_id' => $gen['variant_id'],
            'component_id' => $gen['component_id'],
            'host_planned_transition' => $this->plannedTransition($turn),
            'feedback_facts' => $gen['feedback_facts'] ?? [],
        ];
        if ($gen['message_type'] === 'FEEDBACK') {
            $alloc['item_id'] = $turn->eng['active']['item_id'];
            $alloc['hint_focus'] = $gen['hint_focus'];
            $alloc['allowed_templates'] = [];
            return $alloc;
        }
        $alloc['template_ids'] = $gen['template_ids'];
        $alloc['host_item_fields'] = [
            'curriculum_id' => CurriculumRepository::CURRICULUM_ID,
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'domain' => ['number_system' => 'RATIONAL', 'restrictions' => []],
            'checking' => ['requested_route' => 'DETERMINISTIC_TEMPLATE', 'required_capabilities' => [], 'scope' => 'Set by the host.'],
            'representation_requirements' => [],
            'support_already_supplied' => [],
        ];
        if ($gen['operation'] === 'GUIDED_STEP') {
            $g = $turn->eng['guided'];
            $step = $this->currentStep($g);
            $alloc['allowed_templates'] = [['template_id' => AcceptanceGate::GUIDED_TEMPLATE, 'response_type' => $step['response_type'], 'parameters' => 'copy fixed_parameters exactly']];
            $alloc['fixed_parameters'] = $gen['fixed_parameters'];
            $alloc['guided_step'] = $step;
            $alloc['guided_problem'] = $g['plan']['problem'];
            $alloc['host_question'] = $gen['fixed_parameters']['phrasing'] === 'alt' && $step['alt_prompt'] !== null ? $step['alt_prompt'] : $step['prompt'];
            $alloc['representation_hint'] = $this->representationHint($turn->skill());
            return $alloc;
        }
        if (self::hostLeadOnly($gen)) {
            $alloc['student_message'] = 'Not shown: this is independent work, so the learner sees only a host line and the item. Return an empty string.';
        }
        $alloc['allowed_templates'] = array_map(fn ($id) => [
            'template_id' => $id,
            'response_type' => $this->factory->get($id)->responseType(),
            'parameters' => $this->factory->get($id)->parameterSpec(),
        ], $gen['template_ids']);
        $alloc['avoid_parameters'] = $gen['avoid_parameters'];
        return $alloc;
    }

    /** Answers the model must not disclose: the active item and every unanswered step of the guided problem. */
    private function protectedAnswers(Turn $turn, array $gen): array
    {
        $out = [];
        if (in_array($gen['operation'], ['HINT', 'EXPLANATION'], true) && $turn->eng['active'] !== null) {
            $a = $turn->eng['active'];
            $out[] = ['answer' => $a['answer_spec'], 'variables' => $a['objects']['variables'] ?? ['x'], 'options' => $a['objects']['options'] ?? null, 'active' => true];
        }
        if ($gen['operation'] === 'GUIDED_STEP' && $turn->eng['guided'] !== null) {
            $g = $turn->eng['guided'];
            $current = $this->currentStep($g)['id'];
            foreach ($this->planner->flatten($g['plan']['steps']) as $s) {
                if (!isset($g['done'][$s['id']])) {
                    $out[] = ['answer' => $s['answer'], 'variables' => $s['variables'], 'options' => $s['options'], 'active' => $s['id'] === $current];
                }
            }
        }
        return $out;
    }

    /** Round 4 ruling 1(b): the learner's own (correct) answers to completed steps of the current guided problem. */
    private function completedAnswers(Turn $turn, array $gen): array
    {
        if ($gen['operation'] !== 'GUIDED_STEP' || $turn->eng['guided'] === null) {
            return [];
        }
        $g = $turn->eng['guided'];
        $out = [];
        foreach ($this->planner->flatten($g['plan']['steps']) as $s) {
            if (isset($g['done'][$s['id']])) {
                $out[] = $s['answer'];
            }
        }
        return $out;
    }

    /**
     * Trusted blocks, rebuilt from accepted state for every call. Exactly one
     * curriculum block, bounded to relevant canonical records, anchors stripped.
     */
    private function context(Turn $turn, array $allocation): array
    {
        $relevant = array_values(array_unique(array_filter(array_merge(
            [$turn->snap['original_target_skill_id'], $turn->skill()],
            array_column($turn->snap['return_stack'], 'parent_skill_id'),
            $turn->skill() !== null ? array_values(array_filter($this->curriculum->declaredPrerequisites($turn->skill()), fn ($id) => BetaScope::contains($id))) : [],
        ))));
        $records = [];
        foreach ($relevant as $id) {
            if ($this->curriculum->has($id)) {
                $records[] = $this->curriculum->promptRecord($id);
            }
        }
        $item = $turn->lastItem ?? $turn->eng['active'];
        return [
            'TRUSTED_CAPABILITIES' => [
                'beta_skills' => BetaScope::SKILLS,
                'scored_response_types' => ['scalar (exact rational)', 'expression (polynomial identity, expanded form)', 'classification (exact option set)'],
                'unavailable' => ['graph_spec', 'table', 'reasoning rubric', 'solution_set', 'interval', 'skills outside the beta list'],
                'provider_mode' => $this->model->provider()->name(),
            ],
            'TRUSTED_CURRICULUM' => [
                'curriculum_id' => CurriculumRepository::CURRICULUM_ID,
                'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
                'records' => $records,
            ],
            'TRUSTED_STATE' => [
                'session_id' => $turn->snap['session_id'],
                'revision' => $turn->snap['revision'],
                'mode' => $turn->mode(),
                'original_target_skill_id' => $turn->snap['original_target_skill_id'],
                'current_skill_id' => $turn->skill(),
                'return_stack' => $turn->snap['return_stack'],
                'failed_repair_cycles' => $turn->eng['cycles'],
            ],
            'TRUSTED_EVENT' => [
                'event_id' => $turn->triggerEventId,
                'event_type' => $turn->action,
                'learner_payload' => $turn->learnerPayload,
            ],
            'TRUSTED_ITEM' => $item === null ? null : [
                'item_id' => $item['item_id'], 'item_version' => $item['item_version'], 'skill_id' => $item['skill_id'], 'role' => $item['role'],
                'prompt' => $item['prompt'], 'response_type' => $item['response_type'], 'support_status' => $item['support_status'],
                'answer_spec' => $item['answer_spec'],
            ],
            'TRUSTED_VERDICT' => $turn->lastResult,
            'TRUSTED_OUTPUT_ALLOCATION' => $allocation,
            'BOUNDED_HISTORY' => array_slice($turn->eng['history'], -6),
        ];
    }

    private function remember(Turn $turn, string $role, string $eventId, string $text): void
    {
        $turn->eng['history'][] = ['role' => $role, 'event_id' => $eventId, 'text' => mb_substr($text, 0, 600)];
        $turn->eng['history'] = array_slice($turn->eng['history'], -self::HISTORY_LIMIT);
    }

    // ================================================================= report

    private function buildReport(array $row, array $events, string $status): array
    {
        return $this->reports->build($row, $events, $status, $this->model->provider()->name(), $this->clock->now());
    }
}
