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
// Optional diagnostics: ALGEBRA_TRANSPORT_LOG=/path logs response metadata (status, finish reason,
// token usage, content length). Request bodies, headers and reasoning text are never logged.
$transport = null;
$logPath = getenv('ALGEBRA_TRANSPORT_LOG') ?: '';
if ($logPath !== '') {
    $transport = new class ($logPath) implements AlgebraEngine\Provider\HttpTransport {
        private AlgebraEngine\Provider\CurlTransport $inner;

        public function __construct(private readonly string $path)
        {
            $this->inner = new AlgebraEngine\Provider\CurlTransport();
        }

        public function post(string $url, array $headers, string $body, int $connectTimeout, int $timeout, int $maxBytes): array
        {
            $t = microtime(true);
            $r = $this->inner->post($url, $headers, $body, $connectTimeout, $timeout, $maxBytes);
            $req = json_decode($body, true);
            $d = json_decode($r['body'], true);
            $msg = $d['choices'][0]['message'] ?? [];
            file_put_contents($this->path, json_encode([
                'json_mode' => isset($req['response_format']), 'status' => $r['status'], 'error' => $r['error'], 'seconds' => round(microtime(true) - $t, 1),
                'finish_reason' => $d['choices'][0]['finish_reason'] ?? null, 'usage' => $d['usage'] ?? null,
                'content_chars' => strlen((string) ($msg['content'] ?? '')), // reasoning_content is not read (round 3 ruling 3); usage has its token count
                'content_head' => substr((string) ($msg['content'] ?? ''), 0, 400), 'error_body' => $r['status'] >= 400 ? substr($r['body'], 0, 300) : null,
            ]) . "\n", FILE_APPEND);
            return $r;
        }
    };
}
$app = new App(Config::fromArray(['db_path' => $db] + $settings, dirname(__DIR__) . '/private'), null, null, null, null, $transport);
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
$seen = [];
$report = function (string $step, $r, float $secs) use ($app, &$failures, &$seen) {
    $sid = (string) $app->store->db->scalar('SELECT session_id FROM sessions ORDER BY created_at DESC, rowid DESC LIMIT 1');
    $events = $sid === '' ? [] : $app->store->events($sid);
    $new = array_slice($events, $seen[$sid] ?? 0);
    $seen[$sid] = count($events);
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

// --journey: follow the B1 path into Socratic repair so the model's guided-step
// messages (D1, disclosure guard active) can be reviewed. The scripted learner
// reads keys from this script's own throwaway database.
if (in_array('--journey', $argv, true)) {
    $active = function () use ($app): ?array {
        $row = $app->store->db->one('SELECT engine_json FROM sessions ORDER BY created_at DESC, rowid DESC LIMIT 1');
        return json_decode($row['engine_json'], true)['active'];
    };
    $send = function (string $label, string $text) use ($call, $key, &$view, $report) {
        [$r, $s] = $call('POST', 'event', ['idempotency_key' => $key(), 'expected_revision' => $view['revision'], 'action' => 'SUBMIT_RESPONSE',
            'item_id' => $view['active_item']['item_id'], 'item_version' => $view['active_item']['item_version'], 'payload' => ['text' => $text]]);
        $report($label . ' [mode now ' . ($r->body['mode'] ?? '?') . ']', $r, $s);
    };
    $correct = function () use ($active): string {
        $e = $active()['answer_spec']['expected'];
        return is_array($e) ? implode(',', $e) : (string) $e;
    };
    // Start a fresh Q07 session for the journey.
    [$r, $s] = $call('POST', 'start', ['idempotency_key' => $key(), 'expected_revision' => 0, 'entry_route' => 'TOPIC', 'target_skill_id' => 'Q07', 'task_token' => null, 'replace_current' => false]);
    $report('J1. new Q07 session', $r, $s);
    $a = $active();
    if ($a !== null && isset($a['objects']['signature_answers'])) {
        $send('J2. Q07 answer matching distribution-to-x-only', $a['objects']['signature_answers']['DIST_X_ONLY']);
    }
    $a = $active();
    if ($a !== null && isset($a['objects']['signature_answers'])) {
        $send('J3. probe answer with the same pattern', $a['objects']['signature_answers']['DIST_X_ONLY']);
    }
    $a = $active();
    if ($a !== null && $a['template_id'] === 'POSITIVE_DISTRIBUTION') {
        $send('J4. E06 check with the factor on the first term only', $a['params']['a'] . 'x+' . $a['params']['b']);
    }
    $a = $active();
    if ($a !== null && $a['role'] === 'GUIDED') {
        $wrong = $a['answer_spec']['kind'] === 'exact_rational' ? (string) ((int) $a['answer_spec']['expected'] + 37) : ($a['answer_spec']['kind'] === 'polynomial' ? 'x+997' : 'A');
        $send('J5. guided step answered wrongly (expect a smaller question)', $wrong);
        // Answer the remaining steps correctly so the run shows the return to the interrupted parent.
        for ($i = 6; $i <= 12 && ($active()['role'] ?? null) === 'GUIDED'; $i++) {
            $send("J$i. guided step answered correctly", $correct());
        }
    }
    [$r, $s] = $call('POST', 'event', ['idempotency_key' => $key(), 'expected_revision' => $view['revision'], 'action' => 'END_SESSION', 'item_id' => null, 'item_version' => null, 'payload' => new stdClass()]);
    $report('J-end. end the journey session', $r, $s);
}
$calls = (int) $app->store->db->scalar('SELECT COUNT(*) FROM model_calls');
echo "Provider calls made: $calls\n";
echo "This confirms network and adapter compatibility only. It is not evidence of teaching quality or learning.\n";
foreach (['', '-wal', '-shm'] as $x) {
    @unlink($db . $x);
}
echo $failures === 0 ? "LIVE SMOKE PASSED\n" : "LIVE SMOKE FAILED ($failures step(s))\n";
exit($failures === 0 ? 0 : 1);
