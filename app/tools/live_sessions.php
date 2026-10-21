<?php
declare(strict_types=1);

/*
 * Scripted sessions for Conductor Round 2 §4.2–4.3: the B1 and B3 paths with
 * a scripted learner, against the configured provider.
 *
 *   ALGEBRA_CONFIG=/private/config.php php app/tools/live_sessions.php --out=DIR [--b1=5] [--b3=3] [--label=NAME] [--accept-unlisted-model]
 *
 * With provider 'openai_compatible' it first lists the provider's models and
 * stops (exit 3) when the configured model is not listed (§4.1), unless
 * --accept-unlisted-model is given with the owner's approval. With the mock
 * provider it is a dry run of this script; every output is labelled MOCK and
 * is not a live result.
 *
 * Per session it writes the learner-visible transcript (learner text, tutor
 * text, mode, verdict per turn) with every host rejection of model output and
 * the rejected text. METRICS.md holds the per-session and total metrics. The
 * scripted learner reads keys from this script's own throwaway database. The
 * key is never printed or written; provider error bodies are not recorded.
 * Reasoning text (reasoning_content) is never read or written; only its
 * token count is reported (round 3 ruling 3). The scripted learner's inputs
 * (which error, at which mode and skill) are the same in every run, and each
 * session seeds the host random source the same way; item numbers the model
 * proposes still differ, so items are not identical across runs.
 */

require dirname(__DIR__) . '/private/vendor/autoload.php';

use AlgebraEngine\App;
use AlgebraEngine\Config;
use AlgebraEngine\Http\Api;
use AlgebraEngine\Http\Request;
use AlgebraEngine\Support\RandomSource;

$opts = getopt('', ['out:', 'b1::', 'b3::', 'accept-unlisted-model', 'max-turns::', 'label::']);
$out = $opts['out'] ?? '';
$file = getenv('ALGEBRA_CONFIG') ?: '';
if ($out === '' || $file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: ALGEBRA_CONFIG=/private/config.php php live_sessions.php --out=DIR [--b1=5] [--b3=3] [--accept-unlisted-model]\n");
    exit(2);
}
$settings = require $file;
$live = ($settings['provider'] ?? 'mock') === 'openai_compatible';
if ($live && trim((string) ($settings['provider_api_key'] ?? '')) === '') {
    fwrite(STDERR, "The live config has no provider_api_key.\n");
    exit(2);
}
$b1 = (int) ($opts['b1'] ?? 5);
$b3 = (int) ($opts['b3'] ?? 3);
$maxTurns = (int) ($opts['max-turns'] ?? 80);
if (!is_dir($out) && !mkdir($out, 0777, true)) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(2);
}
$label = ($live ? 'LIVE' : 'MOCK (simulated provider; dry run of this script, not a live result)') . (isset($opts['label']) ? ' — configuration ' . $opts['label'] : '');
$config = Config::fromArray($settings, dirname(__DIR__) . '/private');
$runLog = ["# Scripted sessions — run log", '', "Mode: $label", 'Started (UTC): ' . gmdate('Y-m-d\TH:i:s\Z'),
    'Settings: thinking ' . ($config->string('provider_thinking') ?: '(not set)') . ', reasoning_effort ' . ($config->string('provider_reasoning_effort') ?: '(not sent)')
    . ', max_tokens ' . $config->int('provider_max_tokens') . ', provider_timeout ' . $config->int('provider_timeout') . ' s, lease ' . $config->int('lease_seconds') . ' s'];

// ------------------------------------------------------------ §4.1 model-list gate
if ($live) {
    $url = rtrim((string) $settings['provider_base_url'], '/') . '/models';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $settings['provider_api_key']]]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $d = json_decode((string) $body, true);
    $ids = isset($d['data']) && is_array($d['data']) ? array_column($d['data'], 'id') : [];
    $listed = in_array($settings['provider_model'], $ids, true);
    $runLog[] = "Model list: GET $url → HTTP $status; listed: " . ($ids ? implode(', ', $ids) : '(none)');
    $runLog[] = "Configured model `{$settings['provider_model']}` listed: " . ($listed ? 'yes' : 'no');
    if (!$listed && !isset($opts['accept-unlisted-model'])) {
        $runLog[] = 'STOPPED before any session (Round 2 §4.1): the configured model is not listed. No sessions were run.';
        file_put_contents("$out/RUN_LOG.md", implode("\n", $runLog) . "\n");
        echo implode("\n", $runLog) . "\n";
        exit(3);
    }
    if (!$listed) {
        $runLog[] = 'Continuing with an unlisted model: --accept-unlisted-model was given (owner approval required).';
    }
}

// ------------------------------------------------------------ app with a recording transport
$transport = null;
if ($live) {
    $transport = new class implements AlgebraEngine\Provider\HttpTransport {
        /** @var list<array<string,mixed>> */
        public array $calls = [];
        public string $context = '';
        private AlgebraEngine\Provider\CurlTransport $inner;

        public function __construct()
        {
            $this->inner = new AlgebraEngine\Provider\CurlTransport();
        }

        public function post(string $url, array $headers, string $body, int $connectTimeout, int $timeout, int $maxBytes): array
        {
            $t = microtime(true);
            $r = $this->inner->post($url, $headers, $body, $connectTimeout, $timeout, $maxBytes);
            $req = json_decode($body, true);
            $d = json_decode($r['body'], true);
            $msg = is_array($d) ? ($d['choices'][0]['message'] ?? []) : [];
            $this->calls[] = [
                'context' => $this->context, 'json_mode' => isset($req['response_format']), 'status' => $r['status'], 'error' => $r['error'],
                'seconds' => round(microtime(true) - $t, 2), 'finish_reason' => is_array($d) ? ($d['choices'][0]['finish_reason'] ?? null) : null,
                'usage' => is_array($d) ? ($d['usage'] ?? null) : null, 'response_model' => is_array($d) ? ($d['model'] ?? null) : null,
                'content' => is_string($msg['content'] ?? null) ? $msg['content'] : null,
            ];
            return $r;
        }
    };
}
$db = sys_get_temp_dir() . '/ae_live_sessions_' . bin2hex(random_bytes(4)) . '.sqlite';
$appConfig = Config::fromArray(['db_path' => $db] + $settings, dirname(__DIR__) . '/private');
$app = new App($appConfig, null, null, null, null, $transport);
$runLog[] = 'Provider / model: ' . $app->provider->name() . ' / ' . $app->provider->model();

// ------------------------------------------------------------ scripted learner
/** Wrong answers in order; each is used once when the session reaches that mode and skill. Everything else is answered correctly. */
$scripts = [
    'B1' => [['TARGET_ASSESSMENT', 'Q07', 'DIST_X_ONLY'], ['DIAGNOSTIC_PROBE', 'Q07', 'DIST_X_ONLY'], ['PREREQUISITE_CHECK', 'E06', 'FIRST_TERM_ONLY'], ['GUIDED_REPAIR', 'E06', 'PLAIN_WRONG']],
    'B3' => [['TARGET_ASSESSMENT', 'Q07', 'SIGN_ERROR_B'], ['DIAGNOSTIC_PROBE', 'Q07', 'SIGN_ERROR_B'], ['PREREQUISITE_CHECK', 'Q06', 'MULTIPLY_INSTEAD_OF_DIVIDE'],
        ['PREREQUISITE_CHECK', 'Q05', 'QUOTIENT_SIGN'], ['PREREQUISITE_CHECK', 'N06', 'NO_SIGNATURE']],
];
$answerFor = function (array $a, ?string $code): string {
    $spec = $a['answer_spec'];
    $correct = is_array($spec['expected']) ? implode(',', $spec['expected']) : (string) $spec['expected'];
    if ($code === null) {
        return $correct;
    }
    if (isset($a['objects']['signature_answers'][$code])) {
        return $a['objects']['signature_answers'][$code];
    }
    $p = $a['params'];
    $q = fn ($x, $y) => intdiv($x, $y);
    return match ($a['template_id'] . ':' . $code) {
        'POSITIVE_DISTRIBUTION:FIRST_TERM_ONLY' => $p['a'] . 'x+' . $p['b'],
        'TWO_STEP:MULTIPLY_INSTEAD_OF_DIVIDE' => (string) (($p['c'] - $p['b']) * $p['a']),
        'ONE_STEP_MULTIPLY:QUOTIENT_SIGN' => (string) (-$q($p['b'], $p['a'])),
        'SIGNED_DIVIDE:NO_SIGNATURE' => (string) ($q($p['a'], $p['b']) > 0 ? -(abs($q($p['a'], $p['b'])) + 7) : abs($q($p['a'], $p['b'])) + 7),
        default => match ($spec['kind']) {
            'exact_rational' => (string) ((int) $spec['expected'] + 37),
            'polynomial' => 'x+997',
            default => count($spec['expected']) === 1 && $spec['expected'][0] === 'A' ? 'B' : 'A',
        },
    };
};

// ------------------------------------------------------------ helpers
$md = fn (string $s) => str_replace(["\r\n", "\n", '|'], [' / ', ' / ', '\\|'], $s);
$median = function (array $xs): ?float {
    if ($xs === []) {
        return null;
    }
    sort($xs);
    $n = count($xs);
    return $n % 2 ? $xs[intdiv($n, 2)] : ($xs[$n / 2 - 1] + $xs[$n / 2]) / 2;
};
$usageSum = function (array $calls): array {
    $t = ['in' => 0, 'out' => 0, 'reasoning' => 0, 'cache_hit' => 0, 'cache_miss' => 0, 'cost' => null];
    foreach ($calls as $c) {
        $u = $c['usage'] ?? null;
        if (!is_array($u)) {
            continue;
        }
        $t['in'] += (int) ($u['prompt_tokens'] ?? 0);
        $t['out'] += (int) ($u['completion_tokens'] ?? 0);
        $t['reasoning'] += (int) ($u['completion_tokens_details']['reasoning_tokens'] ?? 0);
        $t['cache_hit'] += (int) ($u['prompt_cache_hit_tokens'] ?? 0);
        $t['cache_miss'] += (int) ($u['prompt_cache_miss_tokens'] ?? 0);
        if (isset($u['cost'])) {
            $t['cost'] = ($t['cost'] ?? 0) + (float) $u['cost'];
        }
    }
    return $t;
};

$plan = array_merge(array_fill(0, $b1, 'B1'), array_fill(0, $b3, 'B3'));
$metrics = [];
$allRejections = [];
$allLatencies = [];
foreach ($plan as $n => $path) {
    $name = sprintf('%02d-%s', $n + 1, $path);
    // Same host random source per session in every run (same scripted inputs, same host-chosen numbers).
    $app = new App($appConfig, null, null, null, RandomSource::seeded('live-sessions-' . $name), $transport);
    $api = new Api($app);
    $cookies = [];
    $csrf = null;
    $view = null;
    $call = function (string $method, string $action, ?array $body = null) use ($api, &$cookies, &$csrf, &$view): array {
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
        return [$r, round(microtime(true) - $t, 2)];
    };
    $key = fn () => 'k' . bin2hex(random_bytes(12));
    [$r] = $call('GET', 'bootstrap');
    $csrf = $r->body['csrf_token'];

    $turns = [];
    $script = $scripts[$path];
    $seenEvents = 0;
    $sid = null;
    $consecutiveHolds = 0;
    $started = gmdate('Y-m-d\TH:i:s\Z');
    for ($t = 1; $t <= $maxTurns; $t++) {
        if ($transport !== null) {
            $transport->context = "$name#$t";
        }
        $modeBefore = $view['mode'] ?? '(none)';
        $learner = '';
        if ($t === 1) {
            $action = 'START Q07';
            [$r, $secs] = $call('POST', 'start', ['idempotency_key' => $key(), 'expected_revision' => 0, 'entry_route' => 'TOPIC', 'target_skill_id' => 'Q07', 'task_token' => null, 'replace_current' => false]);
        } else {
            if (($view['status'] ?? '') === 'CLOSED') {
                break;
            }
            $active = json_decode((string) $app->store->db->scalar('SELECT engine_json FROM sessions WHERE session_id = ?', [$sid]), true)['active'] ?? null;
            $evt = fn (string $a, array $payload = [], bool $item = false) => $call('POST', 'event', ['idempotency_key' => $key(), 'expected_revision' => $view['revision'], 'action' => $a,
                'item_id' => $item ? $view['active_item']['item_id'] : null, 'item_version' => $item ? $view['active_item']['item_version'] : null, 'payload' => (object) $payload]);
            if (($view['mode'] ?? '') === 'SESSION_REPORT') {
                $action = 'END_SESSION';
                [$r, $secs] = $evt('END_SESSION');
            } elseif (($view['status'] ?? '') === 'HOLD') {
                $consecutiveHolds++;
                if ($consecutiveHolds <= 3 && in_array('RESUME_SESSION', $view['allowed_actions'] ?? [], true)) {
                    $action = 'RESUME_SESSION';
                    [$r, $secs] = $evt('RESUME_SESSION');
                } else {
                    $action = 'END_SESSION (held)';
                    [$r, $secs] = $evt('END_SESSION');
                }
            } elseif ($active !== null && ($view['active_item'] ?? null) !== null) {
                $code = null;
                if ($script !== [] && $script[0][0] === $view['mode'] && $script[0][1] === $active['skill_id']) {
                    $code = array_shift($script)[2];
                }
                $learner = $answerFor($active, $code);
                $action = 'SUBMIT_RESPONSE' . ($code !== null ? " (scripted error: $code)" : ' (correct)');
                [$r, $secs] = $evt('SUBMIT_RESPONSE', ['text' => $learner], true);
            } else {
                $action = 'END_SESSION (no active item)';
                [$r, $secs] = $evt('END_SESSION');
            }
            if (($r->body['status'] ?? '') !== 'HOLD') {
                $consecutiveHolds = 0;
            }
        }
        $sid ??= (string) $app->store->db->scalar('SELECT session_id FROM sessions ORDER BY created_at DESC, rowid DESC LIMIT 1');
        $events = $app->store->events($sid);
        $new = array_slice($events, $seenEvents);
        $seenEvents = count($events);
        $calls = $transport !== null ? array_values(array_filter($transport->calls, fn ($c) => $c['context'] === "$name#$t")) : [];
        $jsonCalls = array_values(array_filter($calls, fn ($c) => $c['json_mode']));
        $turn = [
            'n' => $t, 'utc' => gmdate('H:i:s'), 'action' => $action, 'learner_text' => $learner, 'mode_before' => $modeBefore,
            'http' => $r->status, 'status' => $r->body['status'] ?? ($r->body['error_code'] ?? '?'), 'mode' => $r->body['mode'] ?? ($view['mode'] ?? '?'),
            'skill' => (string) ($app->store->db->scalar('SELECT json_extract(snapshot_json, "$.current_skill_id") FROM sessions WHERE session_id = ?', [$sid]) ?? ''),
            'messages' => $r->body['messages'] ?? [], 'item' => $r->body['active_item']['prompt'] ?? null, 'support' => $r->body['active_item']['support_status'] ?? null,
            'seconds' => $secs, 'calls' => $calls, 'verdicts' => [], 'rejections' => [], 'notes' => [], 'accepted' => [], 'holds' => 0,
        ];
        foreach ($new as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'CHECKER_RESULT':
                    $turn['verdicts'][] = $d['result']['verdict'] . ' (' . $d['role'] . ', ' . ($d['result']['item_id'] ?? '') . ')';
                    break;
                case 'MODEL_OUTPUT_REJECTED':
                    $text = $jsonCalls[$d['attempt']]['content'] ?? null;
                    $rej = ['session' => $name, 'turn' => $t, 'operation' => $d['operation'], 'attempt' => $d['attempt'], 'errors' => $d['errors'],
                        'guard' => (bool) array_filter($d['errors'], fn ($x) => str_contains($x, 'expected answer')), 'text' => $text];
                    $turn['rejections'][] = $rej;
                    $allRejections[] = $rej;
                    break;
                case 'MODEL_OUTPUT_ACCEPTED':
                    $turn['accepted'][] = $d;
                    break;
                case 'PROVIDER_FAILURE':
                    $turn['holds']++;
                    $turn['notes'][] = 'HOLD: provider failure ' . $d['failure']['type'] . ' (' . $d['failure']['detail'] . ') during ' . $d['operation'];
                    break;
                case 'BINDING_ACCEPTED':
                    $turn['notes'][] = 'Binding accepted: ' . $d['provider'] . ' / ' . $d['model'] . ', architecture sha256 ' . $d['architecture_sha256'];
                    break;
                case 'MODEL_PROPOSAL_REJECTED':
                    $turn['notes'][] = 'Model proposal rejected by host: ' . $d['reason'];
                    break;
                case 'TRANSITION':
                    $turn['notes'][] = 'Transition ' . $d['from'] . ' → ' . $d['to'] . ': ' . $d['reason'];
                    break;
                case 'DESCENT':
                    $turn['notes'][] = 'Descent ' . $d['from_skill'] . ' → ' . $d['to_skill'];
                    break;
                case 'RETURN':
                    $turn['notes'][] = 'Return ' . $d['from_skill'] . ' → ' . $d['to_skill'];
                    break;
                case 'BOUNDARY':
                    $turn['notes'][] = 'Boundary ' . ($d['skill_id'] ?? '-') . ' from ' . $d['from_skill'] . ': ' . $d['reason'];
                    break;
                case 'ISSUE':
                    $turn['notes'][] = 'Issue ' . $d['type'];
                    break;
                case 'CRITERION_MET':
                    $turn['notes'][] = 'Criterion: ' . $d['skill_id'] . ' ' . $d['status'];
                    break;
            }
        }
        $turns[] = $turn;
    }
    $report = $call('GET', 'report')[0];
    $ended = gmdate('Y-m-d\TH:i:s\Z');

    // ---------------------------------------------------- per-session metrics
    $rows = $app->store->db->all('SELECT kind, outcome FROM model_calls WHERE session_id = ? ORDER BY rowid', [$sid]);
    $bindingCalls = array_values(array_filter($rows, fn ($x) => $x['kind'] === 'binding'));
    $sessionCalls = $transport !== null ? array_values(array_filter($transport->calls, fn ($c) => str_starts_with($c['context'], "$name#"))) : [];
    $repairsByOp = [];
    $opsAccepted = [];
    foreach ($turns as $tn) {
        foreach ($tn['accepted'] as $a) {
            $opsAccepted[$a['operation']] = ($opsAccepted[$a['operation']] ?? 0) + 1;
            $repairsByOp[$a['operation']] = ($repairsByOp[$a['operation']] ?? 0) + $a['attempts'] - 1;
        }
        foreach ($tn['rejections'] as $x) {
            if (!array_filter($tn['accepted'], fn ($a) => $a['operation'] === $x['operation'])) {
                $repairsByOp[$x['operation']] = ($repairsByOp[$x['operation']] ?? 0) + ($x['attempt'] > 0 ? 1 : 0); // repairs in a held generation
            }
        }
    }
    $latencies = array_map(fn ($tn) => $tn['seconds'], $turns);
    $allLatencies = array_merge($allLatencies, $latencies);
    $callTimes = array_map(fn ($c) => $c['seconds'], $sessionCalls);
    $m = [
        'session' => $name, 'path' => $path, 'started' => $started, 'ended' => $ended, 'turns' => count($turns),
        'final_mode' => $view['mode'] ?? '?', 'report_status' => $report->body['report']['status'] ?? '?',
        'binding_first_try' => $bindingCalls !== [] && $bindingCalls[0]['outcome'] === 'ACCEPTED' ? 'yes' : ($bindingCalls === [] ? 'no binding call' : 'no'),
        'provider_calls' => count($rows), 'ops_accepted' => $opsAccepted, 'repairs_by_op' => $repairsByOp,
        'holds' => array_sum(array_column($turns, 'holds')),
        'guard_rejections' => count(array_filter($allRejections, fn ($x) => $x['session'] === $name && $x['guard'])),
        'other_rejections' => count(array_filter($allRejections, fn ($x) => $x['session'] === $name && !$x['guard'])),
        'latency_median' => $median($latencies), 'latency_max' => $latencies ? max($latencies) : null,
        'empty' => count(array_filter($sessionCalls, fn ($c) => $c['error'] === null && $c['status'] === 200 && ($c['content'] ?? '') === '')),
        'length' => count(array_filter($sessionCalls, fn ($c) => $c['finish_reason'] === 'length')),
        'call_median' => $median($callTimes), 'call_max' => $callTimes ? max($callTimes) : null,
        'tokens' => $usageSum($sessionCalls), 'response_models' => array_values(array_unique(array_filter(array_column($sessionCalls, 'response_model')))),
    ];
    $metrics[] = $m;

    // ---------------------------------------------------- transcript
    $tx = ["# Transcript $name — " . ($path === 'B1' ? 'B1 path (Q07 distribution error → E06 → repair → return)' : 'B3 path (Q07 → Q06 → Q05 → N06 and back)'), '',
        "Mode: $label", "Provider / model: {$app->provider->name()} / {$app->provider->model()}", "Started $started, ended $ended (UTC). Turns: " . count($turns) . ". Provider calls: " . count($rows) . '.',
        'Learner responses are supplied by script. Verdicts are the host checker\'s. Nothing is stripped.', ''];
    foreach ($turns as $tn) {
        $tx[] = "## Turn {$tn['n']} ({$tn['utc']} UTC, {$tn['seconds']} s)";
        $tx[] = '';
        $tx[] = "- Learner action: {$tn['action']}";
        if ($tn['learner_text'] !== '') {
            $tx[] = '- Learner text: `' . $tn['learner_text'] . '`';
        }
        $tx[] = "- Mode: {$tn['mode_before']} → {$tn['mode']} (skill {$tn['skill']}); status {$tn['status']} (HTTP {$tn['http']})";
        $tx[] = '- Verdict: ' . ($tn['verdicts'] ? implode('; ', $tn['verdicts']) : '(none this turn)');
        foreach ($tn['messages'] as $msg) {
            $tx[] = "- Tutor [{$msg['kind']}]: " . $msg['text'];
        }
        if ($tn['item'] !== null) {
            $tx[] = '- Item shown' . ($tn['support'] ? " ({$tn['support']})" : '') . ': ' . str_replace("\n", ' / ', $tn['item']);
        }
        foreach ($tn['notes'] as $note) {
            $tx[] = "- Host: $note";
        }
        foreach ($tn['calls'] as $i => $c) {
            $u = $c['usage'] ?? [];
            $tx[] = sprintf('- Provider call %d: %s, HTTP %s%s, model returned %s, %s s, finish %s, tokens in %s / out %s (reasoning %s)', $i + 1, $c['json_mode'] ? 'JSON' : 'text', $c['status'],
                $c['error'] ? ' ' . $c['error'] : '', $c['response_model'] ?? '-', $c['seconds'], $c['finish_reason'] ?? '-', $u['prompt_tokens'] ?? '-', $u['completion_tokens'] ?? '-', $u['completion_tokens_details']['reasoning_tokens'] ?? '-');
        }
        foreach ($tn['rejections'] as $x) {
            $tx[] = "- **Host rejected model output** ({$x['operation']}, attempt {$x['attempt']}): " . implode(' | ', $x['errors']);
            $tx[] = '';
            $tx[] = '  Rejected text:';
            $tx[] = '';
            $tx[] = '  ```';
            $tx[] = '  ' . ($x['text'] === null ? '(not captured: mock provider)' : ($x['text'] === '' ? '(empty content)' : str_replace("\n", "\n  ", $x['text'])));
            $tx[] = '  ```';
        }
        $tx[] = '';
    }
    $tx[] = '## Session report (as shown to the learner)';
    $tx[] = '';
    $tx[] = '```json';
    $tx[] = json_encode($report->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $tx[] = '```';
    file_put_contents("$out/$name.md", implode("\n", $tx) . "\n");
    echo sprintf("%s  %-3s turns=%d calls=%d final=%s report=%s holds=%d rejections=%d\n", $name, $path, $m['turns'], $m['provider_calls'], $m['final_mode'], $m['report_status'], $m['holds'], $m['guard_rejections'] + $m['other_rejections']);
}

// ------------------------------------------------------------ metrics file
$fmt = fn ($v) => $v === null ? '-' : (is_float($v) ? number_format($v, 1) : (string) $v);
$mx = ["# Scripted sessions — metrics", '', implode("\n", array_slice($runLog, 2)), '',
    '| Session | Path | Final mode | Report | Turns | Binding 1st try | Provider calls | Repairs by operation (accepted generations) | HOLDs | Guard rejections | Other rejections | Empty responses | finish=length | Call time median / slowest (s) | Turn latency median / slowest (s) | Tokens in | Tokens out (reasoning) | Cache hit / miss | Cost |',
    '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|'];
$tot = ['turns' => 0, 'calls' => 0, 'holds' => 0, 'guard' => 0, 'other' => 0, 'empty' => 0, 'length' => 0, 'in' => 0, 'out' => 0, 'reasoning' => 0, 'hit' => 0, 'miss' => 0, 'first' => 0, 'ops' => [], 'rep' => []];
$allCallTimes = $transport !== null ? array_map(fn ($c) => $c['seconds'], $transport->calls) : [];
foreach ($metrics as $i => $m) {
    $ops = [];
    foreach ($m['ops_accepted'] + $m['repairs_by_op'] as $op => $_) {
        $ops[] = "$op " . ($m['repairs_by_op'][$op] ?? 0) . ' (' . ($m['ops_accepted'][$op] ?? 0) . ')';
        $tot['ops'][$op] = ($tot['ops'][$op] ?? 0) + ($m['ops_accepted'][$op] ?? 0);
        $tot['rep'][$op] = ($tot['rep'][$op] ?? 0) + ($m['repairs_by_op'][$op] ?? 0);
    }
    $tk = $m['tokens'];
    $mx[] = "| {$m['session']} | {$m['path']} | {$m['final_mode']} | {$m['report_status']} | {$m['turns']} | {$m['binding_first_try']} | {$m['provider_calls']} | " . implode('; ', $ops)
        . " | {$m['holds']} | {$m['guard_rejections']} | {$m['other_rejections']} | " . ($live ? $m['empty'] . ' | ' . $m['length'] . ' | ' . $fmt($m['call_median']) . ' / ' . $fmt($m['call_max']) : 'n/a | n/a | n/a') . ' | ' . $fmt($m['latency_median']) . ' / ' . $fmt($m['latency_max'])
        . ($live ? " | {$tk['in']} | {$tk['out']} ({$tk['reasoning']}) | {$tk['cache_hit']} / {$tk['cache_miss']} | " . ($tk['cost'] === null ? 'not reported by provider' : $tk['cost']) : ' | n/a (mock) | n/a | n/a | n/a') . ' |';
    $tot['turns'] += $m['turns'];
    $tot['calls'] += $m['provider_calls'];
    $tot['holds'] += $m['holds'];
    $tot['guard'] += $m['guard_rejections'];
    $tot['other'] += $m['other_rejections'];
    $tot['empty'] += $m['empty'];
    $tot['length'] += $m['length'];
    $tot['first'] += $m['binding_first_try'] === 'yes' ? 1 : 0;
    foreach (['in', 'out', 'reasoning'] as $k) {
        $tot[$k] += $tk[$k];
    }
    $tot['hit'] += $tk['cache_hit'];
    $tot['miss'] += $tk['cache_miss'];
}
$ops = [];
foreach ($tot['ops'] + $tot['rep'] as $op => $_) {
    $ops[] = "$op " . ($tot['rep'][$op] ?? 0) . ' (' . ($tot['ops'][$op] ?? 0) . ')';
}
$mx[] = '| **Total** | ' . count($metrics) . ' sessions | | | ' . $tot['turns'] . ' | ' . $tot['first'] . '/' . count($metrics) . ' | ' . $tot['calls'] . ' | ' . implode('; ', $ops) . ' | ' . $tot['holds'] . ' | ' . $tot['guard'] . ' | ' . $tot['other']
    . ' | ' . ($live ? $tot['empty'] . ' | ' . $tot['length'] . ' | ' . $fmt($median($allCallTimes)) . ' / ' . $fmt($allCallTimes ? max($allCallTimes) : null) : 'n/a | n/a | n/a')
    . ' | ' . $fmt($median($allLatencies)) . ' / ' . $fmt($allLatencies ? max($allLatencies) : null) . ' | ' . ($live ? $tot['in'] . ' | ' . $tot['out'] . ' (' . $tot['reasoning'] . ') | ' . $tot['hit'] . ' / ' . $tot['miss'] . ' | not reported by provider' : 'n/a | n/a | n/a | n/a') . ' |';
$mx[] = '';
$mx[] = 'Repairs by operation: bounded repair requests per operation, with accepted generations in brackets. Call time: one provider call. Turn latency: the wall time of one API request (all provider calls in it included). Empty responses: HTTP 200 with empty content.';
if ($live) {
    $mx[] = '';
    $counts = array_count_values(array_map(fn ($c) => (string) ($c['response_model'] ?? '(none)'), $transport->calls));
    $mx[] = 'Model name returned in each response (count of calls): ' . implode(', ', array_map(fn ($k, $v) => "$k ($v)", array_keys($counts), $counts)) . '. Each call\'s value is in the transcripts.';
}
$mx[] = '';
$mx[] = '## Every host rejection of model output';
$mx[] = '';
if ($allRejections === []) {
    $mx[] = 'None.';
}
foreach ($allRejections as $x) {
    $mx[] = "- {$x['session']} turn {$x['turn']}, {$x['operation']}, attempt {$x['attempt']}" . ($x['guard'] ? ' (disclosure guard)' : '') . ': ' . implode(' | ', $x['errors']);
    $mx[] = '';
    $mx[] = '  ```';
    $mx[] = '  ' . ($x['text'] === null ? '(not captured: mock provider)' : ($x['text'] === '' ? '(empty content)' : str_replace("\n", "\n  ", $x['text'])));
    $mx[] = '  ```';
}
$mx[] = '';
$mx[] = $live ? 'This run confirms behaviour with the live provider for scripted learners only. It is not evidence of teaching quality or learning.' : 'MOCK dry run: not a live result and not evidence of anything about a provider.';
file_put_contents("$out/METRICS.md", implode("\n", $mx) . "\n");
$runLog[] = 'Finished (UTC): ' . gmdate('Y-m-d\TH:i:s\Z');
file_put_contents("$out/RUN_LOG.md", implode("\n", $runLog) . "\n");
foreach (['', '-wal', '-shm'] as $x) {
    @unlink($db . $x);
}
echo "Wrote " . count($metrics) . " transcripts and METRICS.md to $out ($label)\n";
