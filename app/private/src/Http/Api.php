<?php
declare(strict_types=1);

namespace AlgebraEngine\Http;

use AlgebraEngine\App;
use AlgebraEngine\Capability\CapabilityRegistry;
use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Progress\ProgressCode;
use AlgebraEngine\Report\ReportHtml;
use AlgebraEngine\Report\SummaryView;
use AlgebraEngine\Storage\StorageUnavailable;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\Time;
use AlgebraEngine\Tutor\EngineError;

/**
 * Same-origin JSON API at api.php?action=... Session ownership comes only
 * from the HttpOnly cookie; IDs in requests never authorize anything.
 */
final class Api
{
    private const ACTIONS = [
        'bootstrap' => 'GET', 'state' => 'GET', 'report' => 'GET', 'task' => 'GET',
        'start' => 'POST', 'event' => 'POST', 'forget' => 'POST', 'create_task' => 'POST',
        'progress' => 'GET', 'progress_lookup' => 'POST', 'progress_start' => 'POST',
    ];
    /** Round UI v3 (U10): the cookie that keeps a progress code on this device; the server stores only its sha256. */
    private const CODE_COOKIE = 'ae_pc';
    private const CODE_FAILURES_PER_HOUR = 10;
    private const CODE_NOT_FOUND = 'That code wasn\'t found. Check it, or start fresh.';
    private const MOVED = 'This progress continued on another device.';
    private const START_KEYS = ['idempotency_key', 'expected_revision', 'entry_route', 'target_skill_id', 'task_token', 'replace_current'];

    public function __construct(private App $app)
    {
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
        // Round UI v3 (U10): after a take-over the earlier device keeps its pointer but no longer owns the session.
        if ($this->movedAway($container)) {
            if ($action === 'state') {
                return new Response(200, ['session' => null, 'continued_elsewhere' => true, 'message' => self::MOVED]);
            }
            if (in_array($action, ['event', 'report', 'progress'], true)) {
                return Response::error(409, 'CONTINUED_ELSEWHERE', self::MOVED);
            }
            $container['current_session_id'] = null; // start, forget and codes act as if no session were open here
        }
        return match ($action) {
            'state' => $this->state($container),
            'report' => $this->report($req, $container),
            'start' => $this->start($req, $container),
            'event' => $this->event($req, $container),
            'forget' => $this->forget($req, $container),
            'progress' => $this->progress($req, $container),
            'progress_lookup' => $this->progressLookup($req, $container),
            'progress_start' => $this->progressStart($req, $container),
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

    private function cookie(Request $req, string $value, int $maxAge, ?string $name = null): string
    {
        $secure = $this->app->config->get('cookie_secure');
        $secure = $secure === 'auto' ? $req->https : (bool) $secure;
        $path = $this->app->config->get('base_path') ?? $req->scriptDir;
        return ($name ?? $this->app->config->string('cookie_name')) . '=' . $value . '; Path=' . $path . '; Max-Age=' . $maxAge . '; HttpOnly; SameSite=Lax' . ($secure ? '; Secure' : '');
    }

    /** True when this browser's current session now belongs to another device. */
    private function movedAway(array $container): bool
    {
        if ($container['current_session_id'] === null) {
            return false;
        }
        $owner = $this->app->store->db->scalar('SELECT container_id FROM sessions WHERE session_id = ?', [$container['current_session_id']]);
        return $owner !== null && $owner !== $container['container_id'];
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
        $moved = $this->movedAway($container);
        $session = $container['current_session_id'] !== null && !$moved ? $app->engine->view($container['current_session_id']) : null;
        if ($session !== null) {
            $session['csrf_token'] = $container['csrf_token'];
        }
        $days = $app->config->int('progress_retention_days');
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
                'progress' => 'Progress codes keep skill results only, for up to ' . $days . ' days after last use. Anyone with your code or your downloaded report can continue your progress.',
                'provider' => $app->config->isMock()
                    ? 'This tutor is running on a simulated tutor model. No answers or messages are sent to an outside AI service.'
                    : 'Your answers and messages are sent to the configured AI provider, which writes the tutor\'s replies. The app checks every answer itself.',
            ],
            'session' => $session,
            'continued_elsewhere' => $moved,
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
        $code = $this->deviceCode($req, $this->app->store->progressBySession($container['current_session_id']));
        $report['progress_code'] = $code === null ? null : ProgressCode::format($code);
        $format = $req->query['format'] ?? '';
        if ($format === 'html') {
            // Round UI v3 (U9): one self-contained file with inline CSS, no scripts and no external requests.
            $date = $this->app->clock->now()->format('Y-m-d');
            $resp = new Response(200, []);
            $resp->raw = ReportHtml::render($report, $date);
            $resp->headers['Content-Type'] = 'text/html; charset=utf-8';
            $resp->headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'";
            $resp->headers['Content-Disposition'] = 'attachment; filename="algebra-report-' . $date . '.html"';
            return $resp;
        }
        $resp = new Response(200, $report);
        if ($format === 'download') {
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
        $live = $app->engine->live($current);
        // Round UI v3 (U10): "Start over" begins a fresh session with no code; any progress record stays as it is.
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
        $result = $app->engine->createSession($container['container_id'], $body['target_skill_id'] ?? BetaScope::DEFAULT_TARGET, $body['idempotency_key'], $hash);
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
        $errors = $app->schemas->errors(Json::decodeObject($req->body, 8), 'tutor-event.schema.json');
        if ($errors !== []) {
            return Response::error(400, 'INVALID_EVENT', 'Invalid request.');
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
        $resp = new Response(200, $view);
        $issued = $app->engine->takeIssuedCode();
        if ($issued !== null) { // round UI v3 (U10): the code created at this session's first answer
            $resp->cookies[] = $this->cookie($req, $issued, $app->config->int('session_absolute_ttl'), self::CODE_COOKIE);
        }
        return $resp;
    }

    private function forget(Request $req, array $container): Response
    {
        $body = $this->decode($req);
        if (array_keys($body) !== ['idempotency_key'] || !is_string($body['idempotency_key']) || strlen($body['idempotency_key']) < 16) {
            return Response::error(400, 'INVALID_FORGET', 'Invalid request.');
        }
        $app = $this->app;
        $codes = $app->store->db->transaction(function () use ($container, $app) {
            $codes = 0;
            foreach ($app->store->db->all('SELECT session_id FROM sessions WHERE container_id = ?', [$container['container_id']]) as $r) {
                $codes += $app->store->deleteProgressForSession($r['session_id']); // round UI v3 (U10)
                $app->store->deleteSession($r['session_id']);
            }
            $app->store->deleteContainer($container['container_id']);
            return $codes;
        });
        $resp = new Response(200, ['forgotten' => true, 'progress_codes_deleted' => $codes,
            'message' => $codes > 0 ? 'This session, its summary and its progress code were deleted.' : 'This session and its summary were deleted.',
            'note' => 'This session, its answers, items, results, operations and reports' . ($codes > 0 ? ', and the progress record linked to it,' : '') . ' were deleted from application storage. Server backups, if any, follow the hosting provider\'s own retention.']);
        $resp->cookies[] = $this->cookie($req, 'deleted', 0);
        $resp->cookies[] = $this->cookie($req, 'deleted', 0, self::CODE_COOKIE);
        return $resp;
    }

    // ------------------------------------------------------- progress codes

    /** The code on this device for $row, read from the code cookie and checked against the stored hash. */
    private function deviceCode(Request $req, ?array $row): ?string
    {
        $code = ProgressCode::normalize((string) ($req->cookies[self::CODE_COOKIE] ?? ''));
        return $row !== null && $code !== null && hash_equals($row['code_sha256'], ProgressCode::hash($code)) ? $code : null;
    }

    /**
     * Round UI v3 (U10): the current session's code, for "Your code" on screen.
     * A code that never reached this device (its response was lost) is replaced
     * once; a code already shown is never replaced.
     */
    private function progress(Request $req, array $container): Response
    {
        $app = $this->app;
        $sessionId = $container['current_session_id'];
        $row = $sessionId === null ? null : $app->store->progressBySession($sessionId);
        if ($row === null) {
            return new Response(200, ['linked' => false, 'code' => null]);
        }
        $code = $this->deviceCode($req, $row);
        $cookies = [];
        if ($code === null && $row['code_shown'] === 0) {
            $code = $app->store->db->transaction(function () use ($app, $row) {
                do {
                    $code = ProgressCode::generate();
                } while ($app->store->progressHashTaken(ProgressCode::hash($code)));
                $app->store->replaceProgressCode($row['progress_id'], ProgressCode::hash($code));
                return $code;
            });
            $cookies[] = $this->cookie($req, $code, $app->config->int('session_absolute_ttl'), self::CODE_COOKIE);
        }
        if ($code !== null) {
            $app->store->markProgressShown($row['progress_id']);
        }
        $resp = new Response(200, ['linked' => true, 'code' => $code === null ? null : ProgressCode::format($code)]);
        $resp->cookies = $cookies;
        return $resp;
    }

    /** A typed code, checked under the failure limit. @return array{0:?array,1:?Response} the record, or the error to send */
    private function findCode(array $container, mixed $input): array
    {
        $app = $this->app;
        $now = $app->clock->now();
        $bucket = 'progress-fail-' . $container['container_id'] . '-' . gmdate('YmdH', $now->getTimestamp());
        if ($app->store->counter($bucket) >= self::CODE_FAILURES_PER_HOUR) {
            return [null, Response::error(429, 'TOO_MANY_CODE_TRIES', 'Too many codes were tried. Please wait an hour, or start fresh.')];
        }
        $code = is_string($input) && strlen($input) <= 64 ? ProgressCode::normalize($input) : null;
        $row = $code === null ? null : $app->store->progressByHash(ProgressCode::hash($code), $now);
        if ($row === null) {
            $app->store->bump($bucket, $now->modify('+2 hours'));
            return [null, Response::error(404, 'CODE_NOT_FOUND', self::CODE_NOT_FOUND)];
        }
        return [$row + ['code' => $code], null];
    }

    /** "Welcome back": what the record says, in the report's four headings. Changes nothing. */
    private function progressLookup(Request $req, array $container): Response
    {
        $body = $this->decode($req);
        if (array_keys($body) !== ['code']) {
            return Response::error(400, 'INVALID_CODE_REQUEST', 'Invalid request.');
        }
        [$row, $error] = $this->findCode($container, $body['code']);
        if ($error !== null) {
            return $error;
        }
        // Entering a code is a use: the retention period restarts.
        $this->app->store->updateProgress($row['progress_id'], null, $this->app->clock->now(), $this->app->config->int('progress_retention_days'));
        return new Response(200, $this->welcomeBack($row));
    }

    private function welcomeBack(array $row): array
    {
        $app = $this->app;
        $record = $row['record'];
        $live = $row['live_session_id'] !== null && $app->engine->live($app->store->session($row['live_session_id']));
        $next = $record['next_skill'] ?? null;
        $title = fn (?string $id) => $id === null ? null : ['skill_id' => $id, 'title' => $app->curriculum->title($id)];
        return [
            'code' => ProgressCode::format($row['code']),
            'live' => $live,
            'all_met' => !$live && $next === null,
            'next_skill' => $title($next),
            'route_target' => $title($record['route_target'] ?? null),
            'summary' => (new SummaryView($app->curriculum))->fromRecord($record),
        ];
    }

    /**
     * Start with a code: a live session continues exactly where it stopped (taken
     * over from another device if need be); otherwise a new session begins at the
     * record's next skill, or at a chosen beta skill once every goal on the route is met.
     */
    private function progressStart(Request $req, array $container): Response
    {
        $app = $this->app;
        $body = $this->decode($req);
        if (array_keys($body) !== ['code', 'idempotency_key', 'target_skill_id'] || !is_string($body['idempotency_key'])
            || strlen($body['idempotency_key']) < 16 || strlen($body['idempotency_key']) > 100
            || ($body['target_skill_id'] !== null && (!is_string($body['target_skill_id']) || !BetaScope::contains($body['target_skill_id'])))) {
            return Response::error(400, 'INVALID_CODE_REQUEST', 'Invalid request.');
        }
        [$row, $error] = $this->findCode($container, $body['code']);
        if ($error !== null) {
            return $error;
        }
        $cookies = [$this->cookie($req, $row['code'], $app->config->int('session_absolute_ttl'), self::CODE_COOKIE)];
        // Like "Start over", leaving this browser's own session in progress rotates the browser credential.
        $own = $container['current_session_id'] !== null ? $app->store->session($container['current_session_id']) : null;
        $rotate = fn () => $own !== null && $own['session_id'] !== $row['live_session_id'] && $app->engine->live($own)
            ? [$this->cookie($req, $app->store->rotateCookie($container['container_id']), $app->config->int('session_absolute_ttl'))] : [];
        $live = $row['live_session_id'] !== null ? $app->store->session($row['live_session_id']) : null;
        if ($app->engine->live($live)) {
            $view = $live['container_id'] === $container['container_id']
                ? $app->engine->view($live['session_id'])
                : $app->engine->takeOver($live['session_id'], $container['container_id']);
            $app->store->setCurrentSession($container['container_id'], $live['session_id']);
            $app->store->updateProgress($row['progress_id'], null, $app->clock->now(), $app->config->int('progress_retention_days'));
            $view['csrf_token'] = $container['csrf_token'];
            $resp = new Response(200, $view);
            $resp->cookies = array_merge($cookies, $rotate());
            return $resp;
        }
        $record = $row['record'];
        $target = $body['target_skill_id'] ?? $record['next_skill'] ?? null;
        if ($target === null) {
            return Response::error(409, 'ALL_GOALS_MET', 'You met every goal on this route. Choose a skill to keep practising.');
        }
        $hash = hash('sha256', Json::encode(['code_sha256' => $row['code_sha256'], 'target' => $target]));
        $cached = $this->idempotent($container['container_id'], $body['idempotency_key'], $hash, $container);
        if ($cached !== null) {
            return $cached;
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
        // Round 6 §8: the topic stays the skill the learner set out for (unless they chose a new one);
        // the AI continues from the record's next skill, with earlier results as context only.
        $route = $body['target_skill_id'] !== null ? $target : ($record['route_target'] ?? $target);
        $focus = $body['target_skill_id'] !== null ? $target : ($record['next_skill'] ?? $route);
        $result = $app->engine->createSession($container['container_id'], $route, $body['idempotency_key'], $hash,
            ['progress_id' => $row['progress_id'], 'record' => $record, 'route_target' => $route], $focus);
        $resp = new Response(200, $result['view']);
        $resp->body['csrf_token'] = $container['csrf_token'];
        $resp->cookies = array_merge($cookies, $rotate());
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
