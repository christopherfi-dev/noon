<?php
declare(strict_types=1);

/*
 * Live provider smoke test. Confirms network access and adapter
 * compatibility only; it says nothing about teaching quality.
 *
 *   ALGEBRA_CONFIG=/path/outside/the/repo/config.php php app/tools/live_smoke.php
 *
 * The config must select provider 'openai_compatible' with base URL, model
 * and key. A throwaway database is used. The key is never printed.
 */

require dirname(__DIR__) . '/private/vendor/autoload.php';

use AlgebraEngine\App;
use AlgebraEngine\Config;
use AlgebraEngine\Http\Api;
use AlgebraEngine\Http\Request;

$file = getenv('ALGEBRA_CONFIG') ?: '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Set ALGEBRA_CONFIG to a private config file (outside the repository).\n");
    exit(2);
}
$settings = require $file;
if (($settings['provider'] ?? 'mock') !== 'openai_compatible' || trim((string) ($settings['provider_api_key'] ?? '')) === '') {
    fwrite(STDERR, "The config must set provider => 'openai_compatible' and a non-empty provider_api_key.\n");
    exit(2);
}
$db = sys_get_temp_dir() . '/ae_live_smoke_' . bin2hex(random_bytes(4)) . '.sqlite';
$app = new App(Config::fromArray(['db_path' => $db] + $settings, dirname(__DIR__) . '/private'));
$api = new Api($app);
$cookies = [];
$csrf = null;
$view = null;
$call = function (string $method, string $action, ?array $body = null) use ($api, &$cookies, &$csrf, &$view) {
    $h = ['content-type' => 'application/json', 'host' => 'localhost'];
    if ($csrf !== null) {
        $h['x-csrf-token'] = $csrf;
    }
    $t = microtime(true);
    $r = $api->handle(new Request($method, ['action' => $action], $h, $cookies, $body === null ? '' : json_encode($body), false, 'localhost', '/'));
    foreach ($r->cookies as $c) {
        if (preg_match('/^ae_sid=([^;]*)/', $c, $m)) {
            $cookies['ae_sid'] = $m[1];
        }
    }
    if ($r->status === 200 && isset($r->body['revision'])) {
        $view = $r->body;
    }
    return [$r, round(microtime(true) - $t, 1)];
};
$key = fn () => bin2hex(random_bytes(12));
$failures = 0;
$seen = 0;
$report = function (string $step, $r, float $secs) use ($app, &$failures, &$seen) {
    $sid = (string) $app->store->db->scalar('SELECT session_id FROM sessions LIMIT 1');
    $events = $sid === '' ? [] : $app->store->events($sid);
    $new = array_slice($events, $seen);
    $seen = count($events);
    $ok = $r->status === 200 && in_array($r->body['status'] ?? '', ['READY', 'CLOSED'], true);
    $failures += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $step . "  (HTTP {$r->status}, " . ($r->body['status'] ?? $r->body['error_code'] ?? '?') . ", {$secs}s)\n";
    foreach ($new as $e) {
        if ($e['type'] === 'BINDING_ACCEPTED') {
            echo "     binding acknowledged: {$e['data']['provider']} / {$e['data']['model']} (architecture sha256 {$e['data']['architecture_sha256']})\n";
        } elseif ($e['type'] === 'MODEL_OUTPUT_REJECTED') {
            echo "     host rejected an output (attempt {$e['data']['attempt']}): " . implode(' | ', array_slice($e['data']['errors'], 0, 4)) . "\n";
        } elseif ($e['type'] === 'PROVIDER_FAILURE') {
            echo "     provider failure: {$e['data']['failure']['type']} ({$e['data']['failure']['detail']})\n";
        } elseif ($e['type'] === 'ITEM_FLAGGED') {
            echo "     item flagged: model key {$e['data']['model_expected']} vs host key (host key governs)\n";
        } elseif ($e['type'] === 'MODEL_PROPOSAL_REJECTED') {
            echo "     proposal rejected: {$e['data']['reason']}\n";
        }
    }
    foreach ($r->body['messages'] ?? [] as $m) {
        echo "     [{$m['kind']}] {$m['text']}\n";
    }
    if (isset($r->body['active_item']['prompt'])) {
        echo "     item: " . str_replace("\n", ' / ', $r->body['active_item']['prompt']) . "\n";
    }
};

echo "Live smoke test against " . $app->provider->name() . ' / ' . $app->provider->model() . "\n";
[$r] = $call('GET', 'bootstrap');
$csrf = $r->body['csrf_token'];
[$r, $s] = $call('POST', 'start', ['idempotency_key' => $key(), 'expected_revision' => 0, 'entry_route' => 'TOPIC', 'target_skill_id' => 'Q07', 'task_token' => null, 'replace_current' => false]);
$report('1. binding call (text mode) + first Q07 item (JSON mode)', $r, $s);
if (($view['active_item'] ?? null) !== null) {
    [$r, $s] = $call('POST', 'event', ['idempotency_key' => $key(), 'expected_revision' => $view['revision'], 'action' => 'SUBMIT_RESPONSE',
        'item_id' => $view['active_item']['item_id'], 'item_version' => 1, 'payload' => ['text' => '9999']]);
    $report('2. wrong answer -> diagnostic probe item', $r, $s);
}
if (($view['active_item'] ?? null) !== null) {
    [$r, $s] = $call('POST', 'event', ['idempotency_key' => $key(), 'expected_revision' => $view['revision'], 'action' => 'REQUEST_HINT',
        'item_id' => $view['active_item']['item_id'], 'item_version' => $view['active_item']['item_version'], 'payload' => new stdClass()]);
    $report('3. hint (FEEDBACK envelope, disclosure guard active)', $r, $s);
}
[$r, $s] = $call('POST', 'event', ['idempotency_key' => $key(), 'expected_revision' => $view['revision'], 'action' => 'END_SESSION', 'item_id' => null, 'item_version' => null, 'payload' => new stdClass()]);
$report('4. end session and build the report', $r, $s);
$calls = (int) $app->store->db->scalar('SELECT COUNT(*) FROM model_calls');
echo "Provider calls made: $calls\n";
echo "This confirms network and adapter compatibility only. It is not evidence of teaching quality or learning.\n";
foreach (['', '-wal', '-shm'] as $x) {
    @unlink($db . $x);
}
echo $failures === 0 ? "LIVE SMOKE PASSED\n" : "LIVE SMOKE FAILED ($failures step(s))\n";
exit($failures === 0 ? 0 : 1);
