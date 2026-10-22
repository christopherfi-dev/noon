<?php
declare(strict_types=1);

namespace AlgebraEngine\Http;

use AlgebraEngine\App;
use AlgebraEngine\Capability\CapabilityRegistry;
use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Storage\StorageUnavailable;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\Time;
use AlgebraEngine\Workflow\EngineError;

/**
 * Same-origin JSON API at api.php?action=... Session ownership comes only
 * from the HttpOnly cookie; IDs in requests never authorize anything.
 */
final class Api
{
    private const ACTIONS = [
        'bootstrap' => 'GET', 'state' => 'GET', 'report' => 'GET', 'task' => 'GET',
        'start' => 'POST', 'event' => 'POST', 'forget' => 'POST', 'create_task' => 'POST',
    ];
    private const START_KEYS = ['idempotency_key', 'expected_revision', 'entry_route', 'target_skill_id', 'task_token', 'replace_current'];

    private array $actionRules;

    public function __construct(private App $app)
    {
        $rules = Json::decode((string) file_get_contents($app->config->string('resources_dir') . '/contracts/public-actions.json'));
        foreach ($rules['actions'] as $r) {
            $this->actionRules[$r['action']] = $r;
        }
    }

    public function handle(Request $req): Response
    {
        try {
            return $this->route($req);
        } catch (EngineError $e) {
            return Response::error($e->httpStatus, $e->errorCode, $e->getMessage(), ['reload' => in_array($e->errorCode, ['STALE_REVISION', 'ITEM_CONFLICT', 'OPERATION_CANCELLED'], true)]);
        } catch (StorageUnavailable) {
            return Response::error(503, 'STORAGE_UNAVAILABLE', 'The service is temporarily unavailable.');
        } catch (\Throwable $e) {
            if ($this->app->config->get('display_errors')) {
                throw $e;
            }
            error_log('algebra-engine: ' . get_class($e) . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
            return Response::error(500, 'INTERNAL_ERROR', 'Something went wrong. Your saved work is unchanged.');
        }
    }

    private function route(Request $req): Response
    {
        $action = $req->query['action'] ?? '';
        if (!isset(self::ACTIONS[$action])) {
            return Response::error(404, 'UNKNOWN_ACTION', 'Unknown action.');
        }
        if ($req->method !== self::ACTIONS[$action]) {
            return Response::error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed for this action.');
        }
        $gate = $this->app->config->string('access_gate_token');
        if ($gate !== '' && !hash_equals($gate, (string) ($req->header('x-access-gate') ?? $req->cookies['ae_gate'] ?? ''))) {
            return Response::error(403, 'ACCESS_GATE', 'This pilot requires an access code.');
        }
        if (random_int(1, 50) === 1) {
            $this->app->store->cleanupExpired($this->app->clock->now());
        }
        if ($action === 'task' || $action === 'create_task') {
            return Response::error(422, 'FEATURE_NOT_IN_BETA', 'Teacher task links are not available in this beta.');
        }
        if ($action === 'bootstrap') {
            return $this->bootstrap($req);
        }
        $container = $this->container($req);
        if ($container === null) {
            return Response::error(403, 'NO_SESSION_CREDENTIAL', 'Reload the page to start.');
        }
        if (self::ACTIONS[$action] === 'POST') {
            $guard = $this->checkMutation($req, $container);
            if ($guard !== null) {
                return $guard;
            }
        }
        return match ($action) {
            'state' => $this->state($container),
            'report' => $this->report($req, $container),
            'start' => $this->start($req, $container),
            'event' => $this->event($req, $container),
            'forget' => $this->forget($req, $container),
        };
    }

    // ------------------------------------------------------------ containers

    private function container(Request $req): ?array
    {
        $cookie = $req->cookies[$this->app->config->string('cookie_name')] ?? '';
        if (!preg_match('/^[0-9a-f]{64}$/', $cookie)) {
            return null;
        }
        $c = $this->app->store->containerByCookie($cookie);
        if ($c === null || Time::iso($this->app->clock->now()) > $c['expires_at']) {
            return null;
        }
        return $c;
    }

    private function cookie(Request $req, string $value, int $maxAge): string
    {
        $secure = $this->app->config->get('cookie_secure');
        $secure = $secure === 'auto' ? $req->https : (bool) $secure;
        $path = $this->app->config->get('base_path') ?? $req->scriptDir;
        return $this->app->config->string('cookie_name') . '=' . $value . '; Path=' . $path . '; Max-Age=' . $maxAge . '; HttpOnly; SameSite=Lax' . ($secure ? '; Secure' : '');
    }

    /** CSRF token bound to the container, same-origin check, JSON body limits. */
    private function checkMutation(Request $req, array $container): ?Response
    {
        $origin = $req->header('origin');
        $expected = ($req->https ? 'https://' : 'http://') . $req->host;
        if ($origin !== null && $origin !== $expected) {
            return Response::error(403, 'CROSS_ORIGIN', 'Request rejected.');
        }
        $site = $req->header('sec-fetch-site');
        if ($site !== null && !in_array($site, ['same-origin', 'none'], true)) {
            return Response::error(403, 'CROSS_ORIGIN', 'Request rejected.');
        }
        $token = (string) ($req->header('x-csrf-token') ?? '');
        if ($token === '' || !hash_equals($container['csrf_token'], $token)) {
            return Response::error(403, 'CSRF', 'Request rejected. Reload the page.');
        }
        if (strlen($req->body) > $this->app->config->int('request_body_max')) {
            return Response::error(413, 'BODY_TOO_LARGE', 'Request too large.');
        }
        if (!str_starts_with((string) $req->header('content-type'), 'application/json')) {
            return Response::error(400, 'CONTENT_TYPE', 'JSON body required.');
        }
        return null;
    }

    private function decode(Request $req): array
    {
        try {
            $data = Json::decode($req->body === '' ? '{}' : $req->body, 8);
        } catch (\JsonException) {
            throw new EngineError(400, 'INVALID_JSON', 'Invalid request body.');
        }
        if (!is_array($data) || ($data !== [] && self::isList($data))) {
            throw new EngineError(400, 'INVALID_JSON', 'Invalid request body.');
        }
        return $data;
    }

    // --------------------------------------------------------------- actions

    private function bootstrap(Request $req): Response
    {
        $app = $this->app;
        $now = $app->clock->now();
        $container = $this->container($req);
        $cookies = [];
        if ($container === null) {
            $hour = 'containers-hour-' . gmdate('YmdH', $now->getTimestamp());
            if ($app->store->bump($hour, $now->modify('+2 hours')) > $app->config->int('global_starts_per_hour') * 4) {
                return Response::error(429, 'RATE_LIMITED', 'Too many new visitors right now. Please try again later.');
            }
            [$container, $cookie] = $app->store->createContainer($now, $app->config->int('session_absolute_ttl'));
            $cookies[] = $this->cookie($req, $cookie, $app->config->int('session_absolute_ttl'));
        } else {
            $app->store->touchContainer($container['container_id'], $now, $app->config->int('session_absolute_ttl'));
        }
        $session = $container['current_session_id'] !== null ? $app->engine->view($container['current_session_id']) : null;
        if ($session !== null) {
            $session['csrf_token'] = $container['csrf_token'];
        }
        $registry = new CapabilityRegistry($app->curriculum, $app->factory);
        $resp = new Response(200, [
            'csrf_token' => $container['csrf_token'],
            'provider_mode' => $app->config->isMock() ? 'mock' : 'live',
            'beta' => [
                'default_target' => BetaScope::DEFAULT_TARGET,
                'skills' => array_values(array_map(fn ($id) => ['skill_id' => $id, 'title' => $app->curriculum->title($id)],
                    array_filter(BetaScope::SKILLS, fn ($id) => $app->curriculum->has($id)))),
            ],
            'catalog' => array_map(fn ($e) => [
                'skill_id' => $e['skill_id'], 'title' => $e['title'], 'curricular_role' => $e['curricular_role'],
                'domain' => $app->curriculum->skill($e['skill_id'])['domain_name'], 'runtime_status' => $e['runtime_status'],
            ], $registry->all()),
            'notices' => [
                'data' => 'Your answers are stored on this server for this browser session so the tutor can continue and build your summary. No name or account is needed. Use "Forget this session" to delete them.',
                'provider' => $app->config->isMock()
                    ? 'This beta is running on a simulated tutor model. No answers are sent to an outside AI service.'
                    : 'Your answers are sent to the configured AI provider to word the tutor\'s messages.',
            ],
            'session' => $session,
        ]);
        $resp->cookies = $cookies;
        return $resp;
    }

    private function state(array $container): Response
    {
        $view = $container['current_session_id'] !== null ? $this->app->engine->view($container['current_session_id']) : null;
        if ($view !== null) {
            $view['csrf_token'] = $container['csrf_token'];
        }
        return new Response(200, ['session' => $view]);
    }

    private function report(Request $req, array $container): Response
    {
        if ($container['current_session_id'] === null) {
            return Response::error(404, 'NO_REPORT', 'There is no session report for this browser.');
        }
        $report = $this->app->engine->report($container['current_session_id']);
        if ($report === null) {
            return Response::error(404, 'NO_REPORT', 'There is no session report for this browser.');
        }
        $resp = new Response(200, $report);
        if (($req->query['format'] ?? '') === 'download') {
            $resp->headers['Content-Disposition'] = 'attachment; filename="algebra-session-report.json"';
        }
        return $resp;
    }

    private function start(Request $req, array $container): Response
    {
        $app = $this->app;
        $body = $this->decode($req);
        $unknown = array_diff(array_keys($body), self::START_KEYS);
        $missing = array_diff(self::START_KEYS, array_keys($body));
        if ($unknown !== [] || $missing !== []) {
            return Response::error(400, 'INVALID_START', 'Invalid start request.');
        }
        if (!is_string($body['idempotency_key']) || strlen($body['idempotency_key']) < 16 || strlen($body['idempotency_key']) > 100
            || !is_int($body['expected_revision']) || $body['expected_revision'] < 0 || !is_bool($body['replace_current'])
            || !in_array($body['entry_route'], ['TOPIC', 'UNSURE', 'TEACHER_TASK'], true)
            || ($body['target_skill_id'] !== null && (!is_string($body['target_skill_id']) || !preg_match('/^[A-Z][0-9]{2}$/', $body['target_skill_id'])))
            || ($body['task_token'] !== null && !is_string($body['task_token']))) {
            return Response::error(400, 'INVALID_START', 'Invalid start request.');
        }
        $hash = hash('sha256', Json::encode($body));
        $cached = $this->idempotent($container['container_id'], $body['idempotency_key'], $hash, $container);
        if ($cached !== null) {
            return $cached;
        }
        if ($body['entry_route'] === 'UNSURE') {
            return Response::error(422, 'FEATURE_NOT_IN_BETA', '"Help me find a starting point" is not available in this beta. Choose a skill instead.');
        }
        if ($body['entry_route'] === 'TEACHER_TASK') {
            return Response::error(422, 'FEATURE_NOT_IN_BETA', 'Teacher task links are not available in this beta.');
        }
        if ($body['task_token'] !== null) {
            return Response::error(400, 'CONFLICTING_ROUTE_FIELDS', 'A topic start does not take a task token.');
        }
        $currentId = $container['current_session_id'];
        $current = $currentId !== null ? $app->store->session($currentId) : null;
        $live = $current !== null && !$app->engine->expired($current, $app->clock->now()) && $current['snapshot']['mode'] !== 'SESSION_CLOSED';
        if ($live && !$body['replace_current']) {
            return Response::error(409, 'SESSION_EXISTS', 'A session is already in progress. Continue it, or choose to start over.');
        }
        if ($body['expected_revision'] !== ($live ? $current['revision'] : 0)) {
            return Response::error(409, 'STALE_REVISION', 'This page is out of date. Reload to continue.', ['reload' => true]);
        }
        $now = $app->clock->now();
        if ($app->store->bump('starts-' . $container['container_id'] . '-' . gmdate('YmdH', $now->getTimestamp()), $now->modify('+2 hours')) > $app->config->int('starts_per_container_per_hour')
            || $app->store->bump('starts-global-' . gmdate('YmdH', $now->getTimestamp()), $now->modify('+2 hours')) > $app->config->int('global_starts_per_hour')) {
            return Response::error(429, 'RATE_LIMITED', 'Too many sessions started. Please try again later.');
        }
        $limited = $this->providerBudgetExhausted(null);
        if ($limited !== null) {
            return $limited;
        }
        $result = $app->engine->createSession($container['container_id'], 'TOPIC', $body['target_skill_id'], $body['idempotency_key'], $hash);
        $view = $result['view'];
        $resp = new Response(200, $view);
        if ($live) {
            $cookie = $app->store->rotateCookie($container['container_id']);
            $resp->cookies[] = $this->cookie($req, $cookie, $app->config->int('session_absolute_ttl'));
        }
        $resp->body['csrf_token'] = $container['csrf_token'];
        return $resp;
    }

    private function event(Request $req, array $container): Response
    {
        $app = $this->app;
        $sessionId = $container['current_session_id'];
        if ($sessionId === null) {
            return Response::error(404, 'SESSION_NOT_FOUND', 'There is no active session. Start a new one.');
        }
        $body = $this->decode($req);
        // Validate the raw object form so {} and [] keep their JSON meaning.
        $errors = $app->schemas->errors(Json::decodeObject($req->body, 8), 'public-event.schema.json');
        if ($errors !== []) {
            return Response::error(400, 'INVALID_EVENT', 'Invalid request.');
        }
        $rule = $this->actionRules[$body['action']];
        $keys = array_keys($body['payload']);
        if (array_diff($rule['required_payload'], $keys) !== [] || array_diff($keys, $rule['allowed_payload']) !== []) {
            return Response::error(400, 'INVALID_PAYLOAD', 'This action has missing or unexpected fields.');
        }
        $hasItem = $body['item_id'] !== null || $body['item_version'] !== null;
        if ($rule['requires_active_item'] && ($body['item_id'] === null || $body['item_version'] === null)) {
            return Response::error(400, 'INVALID_ITEM_REFERENCE', 'This action needs the current question.');
        }
        if (!$rule['requires_active_item'] && $hasItem) {
            return Response::error(400, 'INVALID_ITEM_REFERENCE', 'This action does not take a question.');
        }
        $hash = hash('sha256', Json::encode($body));
        $cached = $this->idempotent($sessionId, $body['idempotency_key'], $hash, $container);
        if ($cached !== null) {
            return $cached;
        }
        if ($body['action'] !== 'END_SESSION') {
            $limited = $this->providerBudgetExhausted($sessionId);
            if ($limited !== null) {
                return $limited;
            }
        }
        $view = $app->engine->handle($sessionId, $body, $hash);
        $view['csrf_token'] = $container['csrf_token'];
        return new Response(200, $view);
    }

    private function forget(Request $req, array $container): Response
    {
        $body = $this->decode($req);
        if (array_keys($body) !== ['idempotency_key'] || !is_string($body['idempotency_key']) || strlen($body['idempotency_key']) < 16) {
            return Response::error(400, 'INVALID_FORGET', 'Invalid request.');
        }
        $app = $this->app;
        $app->store->db->transaction(function () use ($container, $app) {
            foreach ($app->store->db->all('SELECT session_id FROM sessions WHERE container_id = ?', [$container['container_id']]) as $r) {
                $app->store->deleteSession($r['session_id']);
            }
            $app->store->deleteContainer($container['container_id']);
        });
        $resp = new Response(200, ['forgotten' => true, 'note' => 'This session, its answers, items, results, operations and reports were deleted from application storage. Server backups, if any, follow the hosting provider\'s own retention.']);
        $resp->cookies[] = $this->cookie($req, 'deleted', 0);
        return $resp;
    }

    // --------------------------------------------------------------- helpers

    /** Spec 02 step 2: same key and hash replays the stored response; a different hash is a conflict. */
    private function idempotent(string $scope, string $key, string $hash, array $container): ?Response
    {
        $row = $this->app->store->idempotency($scope, $key);
        if ($row === null) {
            return null;
        }
        if (!hash_equals($row['request_hash'], $hash)) {
            return Response::error(409, 'IDEMPOTENCY_CONFLICT', 'This request key was already used for a different request.');
        }
        if ($row['status'] !== 'COMPLETE') {
            return new Response(202, ['status' => 'PENDING', 'message' => 'Still working on this request.']);
        }
        $body = Json::decode($row['response_json'], 64);
        if ((int) $row['http_status'] === 200) {
            $body['csrf_token'] = $container['csrf_token'];
        }
        return new Response((int) $row['http_status'], $body);
    }

    private function providerBudgetExhausted(?string $sessionId): ?Response
    {
        $app = $this->app;
        if ($sessionId !== null && $app->store->modelCallCount($sessionId) >= $app->config->int('max_model_calls_per_session')) {
            return Response::error(429, 'SESSION_BUDGET', 'This session has reached its limit. End the session to see your summary.');
        }
        $day = 'provider-day-' . gmdate('Y-m-d', $app->clock->now()->getTimestamp());
        if ($app->store->counter($day) >= $app->config->int('global_provider_calls_per_day')) {
            return Response::error(429, 'GLOBAL_BUDGET', 'The tutor is at its daily limit. Please try again later.');
        }
        return null;
    }

    /** Same result as PHP 8.1's array_is_list(): keys are exactly 0, 1, 2, … in order (an empty array is a list). */
    private static function isList(array $a): bool
    {
        $i = 0;
        foreach ($a as $k => $_) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }
}
