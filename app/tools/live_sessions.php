<?php
declare(strict_types=1);

/*
 * Scripted sessions for Conductor Round 2 §4.2–4.3: the B1 and B3 paths with
 * a scripted learner, against the configured provider.
 *
 *   ALGEBRA_CONFIG=/private/config.php php app/tools/live_sessions.php --out=DIR [--b1=5] [--b3=3] [--b11=0] [--label=NAME] [--accept-unlisted-model]
 *
 * B11 (round 4 §4) follows the B3 script with a messy learner: free-text and
 * unreadable entries, and correct answers written loosely (x = 3, 3.0, 6/2,
 * a Unicode minus, a typo then its correction). Round 5 adds "-2?", "idk",
 * "IDK." and "i dont know" (stuck entries in guided repair and on an
 * independent check) and one Explain request, so every kind of model-written
 * learner text has a live example. Each input is listed in METRICS.md with
 * the checker verdict and scope, what the tutor showed and any host rejection.
 *
 * Round 5 metrics: independent items shown with model-written text (B14),
 * internal-vocabulary hits in learner-visible text (R5-B), guard rejections
 * on sign steps, provider calls per session and learner wait.
 *
 * Round 6 (§8): N1, a "natural learner" built from the owner's four live
 * cases (a capital X with the x-only distribution error, an answer with its
 * reason, "4 x x", an arithmetic slip), answers written as sentences and
 * arithmetic slips. Metrics add every rejected model output with its text
 * and reason (envelope, reading, decision, slip and step proposals),
 * clarifying questions, slips confirmed and rejected, the forward-looking
 * audit list of replies before independent items, and calls per entry.
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
use AlgebraEngine\Support\LearnerText;
use AlgebraEngine\Support\RandomSource;

$opts = getopt('', ['out:', 'b1::', 'b3::', 'b11::', 'n1::', 'accept-unlisted-model', 'max-turns::', 'label::']);
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
$b11 = (int) ($opts['b11'] ?? 0);
$n1 = (int) ($opts['n1'] ?? 0);
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
            $alloc = [];
            foreach ($req['messages'] ?? [] as $m) {
                if ($m['role'] === 'user' && str_starts_with((string) $m['content'], 'JSON ')) {
                    $block = json_decode(substr($m['content'], 5), true);
                    // Round 6: INTERPRET and DECIDE calls carry their own request block.
                    $alloc = isset($block['INTERPRET_REQUEST']) ? ['operation' => 'INTERPRET'] : (isset($block['DECISION_REQUEST']) ? ['operation' => 'DECIDE:' . $block['DECISION_REQUEST']['question']] : ($block['TRUSTED_OUTPUT_ALLOCATION'] ?? []));
                    break;
                }
            }
            $this->calls[] = [
                'context' => $this->context, 'json_mode' => isset($req['response_format']), 'status' => $r['status'], 'error' => $r['error'],
                'seconds' => round(microtime(true) - $t, 2), 'finish_reason' => is_array($d) ? ($d['choices'][0]['finish_reason'] ?? null) : null,
                'usage' => is_array($d) ? ($d['usage'] ?? null) : null, 'response_model' => is_array($d) ? ($d['model'] ?? null) : null,
                'content' => is_string($msg['content'] ?? null) ? $msg['content'] : null,
                'diagnostics' => $r['diagnostics'] ?? null,
                'operation' => $alloc['operation'] ?? null, 'host_question' => $alloc['host_question'] ?? null,
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
    // B11 uses the B3 script: its path has the most items with a single-number answer for the loosely written inputs.
    'B11' => [['TARGET_ASSESSMENT', 'Q07', 'SIGN_ERROR_B'], ['DIAGNOSTIC_PROBE', 'Q07', 'SIGN_ERROR_B'], ['PREREQUISITE_CHECK', 'Q06', 'MULTIPLY_INSTEAD_OF_DIVIDE'],
        ['PREREQUISITE_CHECK', 'Q05', 'QUOTIENT_SIGN'], ['PREREQUISITE_CHECK', 'N06', 'NO_SIGNATURE']],
    'B1' => [['TARGET_ASSESSMENT', 'Q07', 'DIST_X_ONLY'], ['DIAGNOSTIC_PROBE', 'Q07', 'DIST_X_ONLY'], ['PREREQUISITE_CHECK', 'E06', 'FIRST_TERM_ONLY'], ['GUIDED_REPAIR', 'E06', 'PLAIN_WRONG']],
    'B3' => [['TARGET_ASSESSMENT', 'Q07', 'SIGN_ERROR_B'], ['DIAGNOSTIC_PROBE', 'Q07', 'SIGN_ERROR_B'], ['PREREQUISITE_CHECK', 'Q06', 'MULTIPLY_INSTEAD_OF_DIVIDE'],
        ['PREREQUISITE_CHECK', 'Q05', 'QUOTIENT_SIGN'], ['PREREQUISITE_CHECK', 'N06', 'NO_SIGNATURE']],
    'N1' => [],
];
$targetOf = fn (string $path) => $path === 'N1' ? 'E06' : 'Q07';

/**
 * Round 6 N1: the natural learner's entry for the item on screen, or null for a plain correct answer.
 * Built from the owner's four live cases, plus answers written as sentences and an arithmetic slip.
 * $done counts how often each case was used in this session.
 *
 * @return array{label:string,text:string}|null
 */
$naturalFor = function (array $a, string $mode, array &$done) {
    $spec = $a['answer_spec'];
    $p = $a['params'];
    $use = function (string $case, int $max) use (&$done): bool {
        if (($done[$case] ?? 0) >= $max) {
            return false;
        }
        $done[$case] = ($done[$case] ?? 0) + 1;
        return true;
    };
    $key = is_array($spec['expected']) ? null : (string) $spec['expected'];
    $spaced = fn (string $e) => trim(preg_replace(['/\*/', '/\+/', '/(?<=\d)-/'], ['', ' + ', ' - '], $e));
    if ($a['template_id'] === 'POSITIVE_DISTRIBUTION' && in_array($mode, ['TARGET_ASSESSMENT', 'DIAGNOSTIC_PROBE'], true) && $use('capital_x', 2)) {
        return ['label' => 'case 1: capital X, x-only distribution', 'text' => $p['a'] . 'X + ' . $p['b']];
    }
    if ($a['template_id'] === 'POSITIVE_DISTRIBUTION' && in_array($mode, ['INDEPENDENT_CHECK', 'RETURN_TO_TARGET', 'TARGET_ASSESSMENT'], true) && ($done['capital_x'] ?? 0) >= 2 && $use('slip', 1)) {
        $ab = $p['a'] * $p['b'];
        $slip = $ab >= 20 ? $ab - 10 : $ab + 1;
        return ['label' => 'case 4: arithmetic slip in a × b', 'text' => $p['a'] . 'x + ' . $slip];
    }
    if ($a['template_id'] === 'GUIDED_STEP' && preg_match('/What is the total of the (\d+)s in all (\d+) groups/', $a['prompt'], $m) && $use('reason', 1)) {
        return ['label' => 'case 2: answer with its reason', 'text' => $key . ' because it\'s ' . $m[2] . ' x ' . $m[1]];
    }
    if ($a['template_id'] === 'GUIDED_STEP' && preg_match('/How many x\'s are in all (\d+) groups/', $a['prompt'], $m) && $use('x_as_times', 1)) {
        return ['label' => 'case 3: x used as times', 'text' => $m[1] . ' x x'];
    }
    if ($a['template_id'] === 'MICRO_CHECK') {
        return ['label' => 'micro-question answered', 'text' => $key];
    }
    if ($spec['kind'] === 'classification' && $use('choice_sentence', 2)) {
        return ['label' => 'choice written as a sentence', 'text' => 'I think it\'s ' . implode(' and ', (array) $spec['expected'])];
    }
    if ($spec['kind'] === 'polynomial' && $a['role'] !== 'GUIDED' && $use('expression_sentence', 2)) {
        return ['label' => 'expression written as a sentence', 'text' => 'the answer is ' . $spaced($key)];
    }
    if ($spec['kind'] === 'exact_rational' && $a['role'] === 'GUIDED' && $use('number_sentence', 2)) {
        return ['label' => 'number written as a sentence', 'text' => 'it\'s ' . $key];
    }
    return null;
};
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

$plan = array_merge(array_fill(0, $b1, 'B1'), array_fill(0, $b3, 'B3'), array_fill(0, $b11, 'B11'), array_fill(0, $n1, 'N1'));

/**
 * B11 messy inputs, in order. phase 'any': submitted on the next scalar item, before scripted errors
 * (expected to be unreadable). phase 'correct': used when the item is answered correctly anyway.
 * phase 'guided': on the next guided step that has a smaller question and has not had a stuck entry.
 * phase 'independent_check': on the next item in INDEPENDENT_CHECK.
 * needs: 'x' = an item asking for the value of x; 'int' = an integer key; 'neg' = a negative key.
 * action: REQUEST_EXPLANATION instead of a typed entry.
 */
$words = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty'];
$messyList = [
    ['label' => 'idk', 'phase' => 'any', 'needs' => null, 'text' => fn ($v) => 'idk'],
    ['label' => 'empty submit', 'phase' => 'any', 'needs' => null, 'text' => fn ($v) => ''],
    ['label' => 'can you just tell me the answer', 'phase' => 'any', 'needs' => null, 'text' => fn ($v) => 'can you just tell me the answer'],
    ['label' => 'is this on the test?', 'phase' => 'any', 'needs' => null, 'text' => fn ($v) => 'is this on the test?'],
    ['label' => 'number word ("three")', 'phase' => 'any', 'needs' => null, 'text' => fn ($v) => preg_match('/^\d+$/', $v) && (int) $v <= 20 ? $words[(int) $v] : 'three'],
    // Round 5: stuck entries in guided repair, on a step that has a smaller question (listed before the 'correct' entries so they take that step).
    ['label' => 'idk (guided repair)', 'phase' => 'guided', 'needs' => null, 'text' => fn ($v) => 'idk'],
    ['label' => 'IDK. (guided repair)', 'phase' => 'guided', 'needs' => null, 'text' => fn ($v) => 'IDK.'],
    ['label' => 'x = 3 form', 'phase' => 'correct', 'needs' => 'x', 'text' => fn ($v) => 'x = ' . $v],
    ['label' => 'x=3 form', 'phase' => 'correct', 'needs' => 'x', 'text' => fn ($v) => 'x=' . $v],
    ['label' => '"3 " with spaces', 'phase' => 'correct', 'needs' => null, 'text' => fn ($v) => '  ' . $v . ' '],
    ['label' => '3.0 form', 'phase' => 'correct', 'needs' => 'int', 'text' => fn ($v) => $v . '.0'],
    ['label' => '6/2 form', 'phase' => 'correct', 'needs' => 'int', 'text' => fn ($v) => (2 * (int) $v) . '/2'],
    ['label' => 'Unicode minus', 'phase' => 'correct', 'needs' => 'neg', 'text' => fn ($v) => "\u{2212}" . ltrim($v, '-')],
    ['label' => 'typo', 'phase' => 'correct', 'needs' => null, 'text' => fn ($v) => $v . ']'],
    // Round 5 additions.
    ['label' => '"-2?" (correct answer with a trailing ?)', 'phase' => 'correct', 'needs' => 'neg', 'text' => fn ($v) => $v . '?'],
    ['label' => 'i dont know (independent check)', 'phase' => 'independent_check', 'needs' => null, 'text' => fn ($v) => 'i dont know'],
    ['label' => 'Explain button (independent check)', 'phase' => 'independent_check', 'needs' => null, 'action' => 'REQUEST_EXPLANATION', 'text' => fn ($v) => ''],
];
$correction = ['label' => 'correction after the typo', 'text' => fn ($v) => $v];   // submitted on the same item right after the typo
$xTemplates = ['ONE_STEP_ADD', 'ONE_STEP_MULTIPLY', 'TWO_STEP', 'GROUPED_LINEAR', 'GROUPED_LINEAR_TRANSFER'];
$messyRows = [];
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
    $natural = [];
    $script = $scripts[$path];
    $messy = $path === 'B11' ? $messyList : [];
    $pendingCorrection = null;   // item id that was answered with the typo
    $stuckSteps = [];            // guided steps that already had a stuck entry
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
            $action = 'START ' . $targetOf($path);
            [$r, $secs] = $call('POST', 'start', ['idempotency_key' => $key(), 'expected_revision' => 0, 'entry_route' => 'TOPIC', 'target_skill_id' => $targetOf($path), 'task_token' => null, 'replace_current' => false]);
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
                $entry = null;
                $expected = is_array($active['answer_spec']['expected']) ? null : (string) $active['answer_spec']['expected'];
                $mode = $view['mode'] ?? '';
                $stepKey = $active['role'] === 'GUIDED' ? ($active['params']['plan_id'] ?? '') . '/' . ($active['guided_step']['id'] ?? '') : null;
                $fits = function (array $e) use ($active, $expected, $xTemplates, $mode, $stepKey, &$stuckSteps): bool {
                    if ($e['phase'] === 'guided') {
                        return $mode === 'GUIDED_REPAIR' && $stepKey !== null && ($active['guided_step']['smaller'] ?? []) !== [] && !isset($stuckSteps[$stepKey]);
                    }
                    if ($e['phase'] === 'independent_check') {
                        return $mode === 'INDEPENDENT_CHECK' && $active['role'] !== 'GUIDED' && $active['support_status'] === 'INDEPENDENT';
                    }
                    if ($active['answer_spec']['kind'] !== 'exact_rational' || $expected === null) {
                        return false;
                    }
                    return match ($e['needs']) {
                        'x' => in_array($active['template_id'], $xTemplates, true),
                        'int' => preg_match('/^-?\d+$/', $expected) === 1,
                        'neg' => str_starts_with($expected, '-'),
                        default => true,
                    };
                };
                $due = $script !== [] && $script[0][0] === $view['mode'] && $script[0][1] === $active['skill_id'];
                if ($path === 'N1') {
                    $entry = $naturalFor($active, $mode, $natural);
                    $entry = $entry === null ? null : ['label' => $entry['label'], 'phase' => 'natural', 'text' => fn ($v) => $entry['text']];
                } elseif ($pendingCorrection !== null && $pendingCorrection === $active['item_id']) {
                    $entry = $correction;
                } else {
                    foreach ($messy as $i => $e) {
                        if (in_array($e['phase'], ['any', 'guided', 'independent_check'], true) && $fits($e) || ($e['phase'] === 'correct' && !$due && $fits($e))) {
                            $entry = $e;
                            array_splice($messy, $i, 1);
                            break;
                        }
                    }
                }
                $pendingCorrection = ($entry['label'] ?? null) === 'typo' ? $active['item_id'] : null;
                if ($entry !== null && ($entry['phase'] ?? '') === 'guided') {
                    $stuckSteps[$stepKey] = true;
                }
                if ($entry !== null && isset($entry['action'])) {
                    $action = $entry['action'] . ' (messy input: ' . $entry['label'] . ')';
                } elseif ($entry !== null) {
                    $learner = ($entry['text'])($expected);
                    $action = 'SUBMIT_RESPONSE (' . ($path === 'N1' ? 'natural learner' : 'messy input') . ': ' . $entry['label'] . ')';
                } else {
                    if ($due) {
                        $code = array_shift($script)[2];
                    }
                    $learner = $answerFor($active, $code);
                    $action = 'SUBMIT_RESPONSE' . ($code !== null ? " (scripted error: $code)" : ' (correct)');
                }
                [$r, $secs] = isset($entry['action']) ? $evt($entry['action'], [], true) : $evt('SUBMIT_RESPONSE', ['text' => $learner], true);
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
            'seconds' => $secs, 'calls' => $calls, 'verdicts' => [], 'scopes' => [], 'rejections' => [], 'notes' => [], 'accepted' => [], 'holds' => 0,
            'item_before' => $t === 1 ? null : ($active['prompt'] ?? null),
            'sources' => [], 'independent_presented' => 0, 'independent_with_model_text' => 0, 'vocab' => [], 'discarded' => [],
            'refer_back' => 0, 'readings' => [], 'clarifications' => [], 'slips' => [], 'audit' => [], 'decisions' => [], 'instructional_calls' => 0,
        ];
        // Round 5: who wrote each message (host or model), B14 and internal vocabulary, from the committed turn.
        if ($r->status === 200) {
            $engNow = json_decode((string) $app->store->db->scalar('SELECT engine_json FROM sessions WHERE session_id = ?', [$sid]), true);
            $turn['sources'] = array_map(fn ($m) => $m['source'] ?? 'UNKNOWN', $engNow['messages'] ?? []);
            $a = $engNow['active'] ?? null;
            // Round 6: model text with an independent item must be a refer-back reply (B14 replaced); anything else counts here (must be 0).
            $presentedNow = array_values(array_filter($new, fn ($e) => $e['type'] === 'ITEM_PRESENTED' && $a !== null && $e['data']['item_id'] === $a['item_id']));
            if ($a !== null && $a['role'] !== 'GUIDED' && $a['support_status'] === 'INDEPENDENT' && ($view['mode'] ?? '') !== 'SESSION_CLOSED' && $presentedNow !== []
                && array_filter($turn['sources'], fn ($x) => $x !== 'HOST') && !$presentedNow[0]['data']['refer_back_reply'] && !isset($a['slip_stage'])) {
                $turn['independent_with_model_text'] = 1;
            }
            $visible = array_merge(array_column($r->body['messages'] ?? [], 'text'), [$r->body['progress']['label'] ?? '', $r->body['active_item']['prompt'] ?? '']);
            foreach ($visible as $text) {
                foreach (LearnerText::internalTerms($text) as $term) {
                    $turn['vocab'][] = $term . ': ' . $text;
                }
            }
        }
        foreach ($new as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'CHECKER_RESULT':
                    $turn['verdicts'][] = $d['result']['verdict'] . ' (' . $d['role'] . ', ' . ($d['result']['item_id'] ?? '') . ')';
                    $turn['scopes'][] = $d['result']['verification_scope'];
                    break;
                case 'MODEL_OUTPUT_REJECTED':
                    $ofOp = array_values(array_filter($jsonCalls, fn ($c) => $d['operation'] === 'INTERPRET' ? $c['operation'] === 'INTERPRET' : !in_array(substr((string) $c['operation'], 0, 6), ['INTERP', 'DECIDE'], true)));
                    $text = $ofOp[$d['attempt']]['content'] ?? null;
                    $question = $ofOp[$d['attempt']]['host_question'] ?? null;
                    $guard = (bool) array_filter($d['errors'], fn ($x) => str_contains($x, 'expected answer'));
                    $rej = ['session' => $name, 'turn' => $t, 'operation' => $d['operation'], 'attempt' => $d['attempt'], 'errors' => $d['errors'],
                        'guard' => $guard, 'sign_step' => $guard && is_string($question) && preg_match('/positive or negative/i', $question) === 1,
                        'vocab' => (bool) array_filter($d['errors'], fn ($x) => str_contains($x, 'internal terms')), 'question' => $question, 'text' => $text];
                    $turn['rejections'][] = $rej;
                    $allRejections[] = $rej;
                    break;
                case 'MODEL_OUTPUT_ACCEPTED':
                    $turn['accepted'][] = $d;
                    break;
                case 'ITEM_PRESENTED':
                    if ($d['role'] !== 'GUIDED') {
                        $turn['independent_presented']++;
                    }
                    break;
                case 'MODEL_TEXT_DISCARDED':
                    $turn['discarded'][] = $d['characters'];
                    $turn['notes'][] = 'Model text proposed with the independent item was discarded unread (' . $d['characters'] . ' characters; R5-A)';
                    break;
                case 'ENTRY_INTERPRETED':
                    $turn['notes'][] = 'Entry interpreted as ' . $d['kind'] . ($d['read_as'] !== null ? ' (read as `' . $d['read_as'] . '`)' : '') . ' (R5-C)';
                    break;
                case 'SUPPORT_GIVEN':
                    $turn['notes'][] = 'Support given: ' . $d['kind'] . (isset($d['trigger']) ? ' (' . $d['trigger'] . ')' : '');
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
                // Round 6 events.
                case 'ENTRY_READ':
                    $turn['readings'][] = $d;
                    $turn['notes'][] = 'Model reading: ' . $d['kind'] . ($d['normalized_answer'] !== null ? ' `' . $d['normalized_answer'] . '`' : '') . ' (' . $d['reading_note'] . '); host parse: ' . ($d['host_reading'] ?? 'none') . '; why asked: ' . implode(', ', $d['triggers']);
                    break;
                case 'READING_REJECTED':
                case 'STEP_PROPOSAL_REJECTED':
                case 'SLIP_PROPOSAL_REJECTED':
                case 'MODEL_DECISION_REJECTED':
                    $why = $d['errors'] ?? $d['reasons'] ?? [$d['reason'] ?? ''];
                    $what = match ($e['type']) {
                        'READING_REJECTED' => 'reading `' . $d['proposed'] . '`',
                        'STEP_PROPOSAL_REJECTED' => 'smaller step: ' . json_encode($d['proposal'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'SLIP_PROPOSAL_REJECTED' => 'slip proposal on sub-step ' . $d['sub_step_id'],
                        default => 'decision (' . $d['question'] . ')',
                    };
                    $rawOp = $e['type'] === 'READING_REJECTED' ? 'INTERPRET' : ($e['type'] === 'STEP_PROPOSAL_REJECTED' ? null : 'DECIDE');
                    $raw = $rawOp === null ? null : (array_values(array_filter($jsonCalls, fn ($c) => str_starts_with((string) $c['operation'], $rawOp)))[0]['content'] ?? null);
                    $rej = ['session' => $name, 'turn' => $t, 'operation' => $e['type'], 'attempt' => 0, 'errors' => array_map(fn ($x) => $what . ': ' . $x, $why),
                        'guard' => false, 'sign_step' => false, 'vocab' => false, 'question' => null, 'text' => $raw ?? json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                    $turn['rejections'][] = $rej;
                    $allRejections[] = $rej;
                    break;
                case 'CLARIFICATION_ASKED':
                    $turn['clarifications'][] = $d;
                    $turn['notes'][] = 'Clarifying question (' . $d['source'] . '): ' . ($d['question'] ?? '(host line)') . ' — host parse `' . $d['host_reading'] . '`, model reading `' . $d['model_reading'] . '`';
                    break;
                case 'SLIP_CANDIDATE':
                case 'SLIP_CONFIRMED':
                case 'SLIP_NOT_CONFIRMED':
                case 'SLIP_VOIDED':
                case 'SLIP_RETRY_ANSWERED':
                case 'SLIP_PROPOSAL_ACCEPTED':
                    $turn['slips'][] = ['type' => $e['type']] + $d;
                    $turn['notes'][] = $e['type'] . (isset($d['sub_step']['display']) ? ': ' . $d['sub_step']['display'] . (isset($d['wrong_value']) ? ' (answer used ' . $d['wrong_value'] . ', found by ' . $d['source'] . ')' : '') : '') . (isset($d['reason']) ? ': ' . $d['reason'] : '');
                    break;
                case 'REPLY_AUDIT':
                    $turn['audit'][] = $d;
                    $turn['notes'][] = 'Audit list (forward-looking phrase before an independent item: ' . implode(', ', $d['phrases']) . '): ' . $d['text'];
                    break;
                case 'TEACHING_DECISION':
                    $turn['decisions'][] = $d;
                    $turn['notes'][] = 'Teaching decision ' . $d['question'] . ' by ' . $d['source'] . ': ' . ($d['decision'] ?? 'host rule') . (isset($d['choice']) && $d['choice'] !== null ? ' ' . $d['choice'] : '') . ' — ' . $d['reason'];
                    break;
                case 'STEP_PROPOSAL_ACCEPTED':
                    $turn['notes'][] = 'Smaller step proposed by the model accepted: ' . $d['proposal']['question'] . ' (' . $d['proposal']['step_expression'] . ')';
                    break;
                case 'ITEM_FLAGGED':
                    $turn['notes'][] = 'Item flagged ' . $d['flag'] . (isset($d['reasons']) ? ': ' . implode(' | ', $d['reasons']) : '');
                    break;
            }
        }
        $turn['refer_back'] = count(array_filter($new, fn ($e) => $e['type'] === 'ITEM_PRESENTED' && ($e['data']['refer_back_reply'] ?? false)));
        $turn['instructional_calls'] = $transport !== null ? count($jsonCalls) : count(array_filter($app->store->db->all('SELECT kind FROM model_calls WHERE session_id = ? ORDER BY rowid', [$sid]), fn ($x) => $x['kind'] !== 'binding')) - array_sum(array_column($turns, 'instructional_calls'));
        $turns[] = $turn;
        if (preg_match('/^[A-Z_]+ \((?:messy input|natural learner): (.*)\)$/', $action, $mm)) {
            $messyRows[] = ['session' => $name, 'turn' => $t, 'label' => $mm[1], 'text' => $learner, 'item' => $turn['item_before'], 'http' => $r->status,
                'verdict' => $turn['verdicts'] ? explode(' ', $turn['verdicts'][0])[0] : '(none)', 'scope' => $turn['scopes'][0] ?? '',
                'shown' => implode(' / ', array_map(fn ($m) => '[' . $m['kind'] . '] ' . $m['text'], $turn['messages'])),
                'rejections' => array_map(fn ($x) => $x['operation'] . ': ' . implode(' | ', $x['errors']), $turn['rejections'])];
        }
    }
    foreach ($messy as $e) {
        $messyRows[] = ['session' => $name, 'turn' => null, 'label' => $e['label'], 'text' => '', 'item' => null, 'http' => null, 'verdict' => 'NOT APPLIED (no suitable item before the session ended)',
            'scope' => '', 'shown' => '', 'rejections' => []];
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
        'sign_guard' => count(array_filter($allRejections, fn ($x) => $x['session'] === $name && $x['sign_step'])),
        'vocab_rejections' => count(array_filter($allRejections, fn ($x) => $x['session'] === $name && $x['vocab'])),
        'independent_presented' => array_sum(array_column($turns, 'independent_presented')),
        'independent_with_model_text' => array_sum(array_column($turns, 'independent_with_model_text')),
        'discarded' => count(array_merge(...array_column($turns, 'discarded'))),
        'vocab_hits' => array_merge(...array_column($turns, 'vocab')),
        'refer_back' => array_sum(array_column($turns, 'refer_back')),
        'readings' => count(array_merge(...array_column($turns, 'readings'))),
        'clarifications' => array_merge(...array_column($turns, 'clarifications')),
        'slips' => array_merge(...array_column($turns, 'slips')),
        'audit' => array_merge(...array_map(fn ($tn) => array_map(fn ($x) => $x + ['turn' => $tn['n']], $tn['audit']), $turns)),
        'max_calls_per_entry' => max(array_merge([0], array_map(fn ($tn) => $tn['n'] === 1 ? 0 : $tn['instructional_calls'], $turns))),
        'decisions' => array_merge(...array_column($turns, 'decisions')),
    ];
    $metrics[] = $m;

    // ---------------------------------------------------- transcript
    $tx = ["# Transcript $name — " . match ($path) {
            'B1' => 'B1 path (Q07 distribution error → E06 → repair → return)',
            'B3' => 'B3 path (Q07 → Q06 → Q05 → N06 and back)',
            default => 'B11 messy learner on the B3 path (free text, unreadable input, loosely written answers)',
        }, '',
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
        foreach ($tn['messages'] as $i => $msg) {
            $tx[] = "- Tutor [{$msg['kind']}, " . strtolower($tn['sources'][$i] ?? '?') . ' text]: ' . $msg['text'];
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
                $c['error'] ? ' ' . $c['error'] . (isset($c['diagnostics']) ? sprintf(' (curl error %d, stage %s, %d ms)', $c['diagnostics']['curl_errno'], $c['diagnostics']['stage'], $c['diagnostics']['elapsed_ms']) : '') : '', $c['response_model'] ?? '-', $c['seconds'], $c['finish_reason'] ?? '-', $u['prompt_tokens'] ?? '-', $u['completion_tokens'] ?? '-', $u['completion_tokens_details']['reasoning_tokens'] ?? '-');
        }
        foreach ($tn['calls'] as $i => $c) {
            if (!$c['json_mode']) {
                // Binding replies are short and are the only text-mode output; a non-exact reply is a host rejection.
                $tx[] = '- Binding reply (call ' . ($i + 1) . '): `' . str_replace("\n", ' / ', (string) $c['content']) . '`';
            }
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
    echo sprintf("%s  %-3s turns=%d calls=%d max/entry=%d final=%s report=%s holds=%d rejections=%d slips=%d clarifications=%d audit=%d\n", $name, $path, $m['turns'], $m['provider_calls'], $m['max_calls_per_entry'], $m['final_mode'], $m['report_status'], $m['holds'],
        $m['guard_rejections'] + $m['other_rejections'], count(array_filter($m['slips'], fn ($s) => $s['type'] === 'SLIP_CONFIRMED')), count($m['clarifications']), count($m['audit']));
}

// ------------------------------------------------------------ metrics file
$fmt = fn ($v) => $v === null ? '-' : (is_float($v) ? number_format($v, 1) : (string) $v);
$mx = ["# Scripted sessions — metrics", '', implode("\n", array_slice($runLog, 2)), '',
    '| Session | Path | Final mode | Report | Turns | Binding 1st try | Provider calls | Repairs by operation (accepted generations) | HOLDs | Guard rejections (on sign steps) | Other rejections (internal vocabulary) | Independent items shown / with model text | Model text discarded | Internal-vocabulary hits in learner text | Empty responses | finish=length | Call time median / slowest (s) | Learner wait median / slowest (s) | Tokens in | Tokens out (reasoning) | Cache hit / miss | Cost |',
    '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|'];
$tot = ['turns' => 0, 'calls' => 0, 'holds' => 0, 'guard' => 0, 'other' => 0, 'empty' => 0, 'length' => 0, 'in' => 0, 'out' => 0, 'reasoning' => 0, 'hit' => 0, 'miss' => 0, 'first' => 0, 'ops' => [], 'rep' => [],
    'sign' => 0, 'vocabrej' => 0, 'ind' => 0, 'indmodel' => 0, 'disc' => 0, 'vocab' => 0];
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
        . " | {$m['holds']} | {$m['guard_rejections']} ({$m['sign_guard']}) | {$m['other_rejections']} ({$m['vocab_rejections']}) | {$m['independent_presented']} / {$m['independent_with_model_text']} | {$m['discarded']} | " . count($m['vocab_hits']) . ' | ' . ($live ? $m['empty'] . ' | ' . $m['length'] . ' | ' . $fmt($m['call_median']) . ' / ' . $fmt($m['call_max']) : 'n/a | n/a | n/a') . ' | ' . $fmt($m['latency_median']) . ' / ' . $fmt($m['latency_max'])
        . ($live ? " | {$tk['in']} | {$tk['out']} ({$tk['reasoning']}) | {$tk['cache_hit']} / {$tk['cache_miss']} | " . ($tk['cost'] === null ? 'not reported by provider' : $tk['cost']) : ' | n/a (mock) | n/a | n/a | n/a') . ' |';
    $tot['turns'] += $m['turns'];
    $tot['calls'] += $m['provider_calls'];
    $tot['holds'] += $m['holds'];
    $tot['guard'] += $m['guard_rejections'];
    $tot['other'] += $m['other_rejections'];
    $tot['empty'] += $m['empty'];
    $tot['length'] += $m['length'];
    $tot['first'] += $m['binding_first_try'] === 'yes' ? 1 : 0;
    $tot['sign'] += $m['sign_guard'];
    $tot['vocabrej'] += $m['vocab_rejections'];
    $tot['ind'] += $m['independent_presented'];
    $tot['indmodel'] += $m['independent_with_model_text'];
    $tot['disc'] += $m['discarded'];
    $tot['vocab'] += count($m['vocab_hits']);
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
$mx[] = '| **Total** | ' . count($metrics) . ' sessions | | | ' . $tot['turns'] . ' | ' . $tot['first'] . '/' . count($metrics) . ' | ' . $tot['calls'] . ' (' . ($metrics ? number_format($tot['calls'] / count($metrics), 1) : '-') . ' per session) | ' . implode('; ', $ops) . ' | ' . $tot['holds'] . ' | ' . $tot['guard'] . ' (' . $tot['sign'] . ') | ' . $tot['other'] . ' (' . $tot['vocabrej'] . ')'
    . ' | ' . $tot['ind'] . ' / ' . $tot['indmodel'] . ' | ' . $tot['disc'] . ' | ' . $tot['vocab'] . ' | ' . ($live ? $tot['empty'] . ' | ' . $tot['length'] . ' | ' . $fmt($median($allCallTimes)) . ' / ' . $fmt($allCallTimes ? max($allCallTimes) : null) : 'n/a | n/a | n/a')
    . ' | ' . $fmt($median($allLatencies)) . ' / ' . $fmt($allLatencies ? max($allLatencies) : null) . ' | ' . ($live ? $tot['in'] . ' | ' . $tot['out'] . ' (' . $tot['reasoning'] . ') | ' . $tot['hit'] . ' / ' . $tot['miss'] . ' | not reported by provider' : 'n/a | n/a | n/a | n/a') . ' |';
$mx[] = '';
$mx[] = 'Repairs by operation: bounded repair requests per operation, with accepted generations in brackets. Call time: one provider call. Learner wait: the wall time of one API request (all provider calls in it included). Empty responses: HTTP 200 with empty content.'
    . ' Guard rejections on sign steps: disclosure-guard rejections while the step asked "positive or negative". Other rejections (internal vocabulary): rejections naming an internal term (R5-B).'
    . ' Independent items shown / with model text: turns that showed an independent item / of those, turns that also showed model-written text (B14; must be 0). Model text discarded: independent items whose model text was dropped unread (R5-A).'
    . ' Internal-vocabulary hits: listed terms found in any learner-visible message, progress line or item (must be 0).';
$hits = array_merge(...array_map(fn ($m) => array_map(fn ($h) => $m['session'] . ': ' . $h, $m['vocab_hits']), $metrics));
if ($hits !== []) {
    $mx[] = '';
    $mx[] = 'Internal-vocabulary hits: ' . implode('; ', $hits);
}
if ($live) {
    $mx[] = '';
    $counts = array_count_values(array_map(fn ($c) => (string) ($c['response_model'] ?? '(none)'), $transport->calls));
    $mx[] = 'Model name returned in each response (count of calls): ' . implode(', ', array_map(fn ($k, $v) => "$k ($v)", array_keys($counts), $counts)) . '. Each call\'s value is in the transcripts.';
}
$mx[] = '';
// Round 6 (§8) per-session table.
$mx[] = '## Round 6 metrics per session';
$mx[] = '';
$mx[] = '| Session | Path | Provider calls | Most calls for one learner entry | HOLDs | Repairs | Rejected model outputs | Model readings | Clarifying questions | Slips: candidates / confirmed / not confirmed / withdrawn / model proposals rejected | Refer-back replies | Audit list entries | Model teaching decisions | Learner wait median / slowest (s) |';
$mx[] = '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|';
$slipCount = fn (array $s, string $type) => count(array_filter($s, fn ($x) => $x['type'] === $type));
$r6 = ['calls' => 0, 'max' => 0, 'holds' => 0, 'rep' => 0, 'rej' => 0, 'read' => 0, 'clar' => 0, 'cand' => 0, 'conf' => 0, 'notc' => 0, 'void' => 0, 'refer' => 0, 'audit' => 0, 'dec' => 0];
foreach ($metrics as $m) {
    $rejected = count(array_filter($allRejections, fn ($x) => $x['session'] === $m['session']));
    $repairs = array_sum($m['repairs_by_op']);
    $slipRejected = count(array_filter($allRejections, fn ($x) => $x['session'] === $m['session'] && $x['operation'] === 'SLIP_PROPOSAL_REJECTED'));
    $modelDecisions = count(array_filter($m['decisions'], fn ($d) => $d['source'] === 'MODEL'));
    $mx[] = "| {$m['session']} | {$m['path']} | {$m['provider_calls']} | {$m['max_calls_per_entry']} | {$m['holds']} | $repairs | $rejected | {$m['readings']} | " . count($m['clarifications']) . ' | '
        . $slipCount($m['slips'], 'SLIP_CANDIDATE') . ' / ' . $slipCount($m['slips'], 'SLIP_CONFIRMED') . ' / ' . $slipCount($m['slips'], 'SLIP_NOT_CONFIRMED') . ' / ' . $slipCount($m['slips'], 'SLIP_VOIDED') . " / $slipRejected | {$m['refer_back']} | " . count($m['audit']) . " | $modelDecisions | " . $fmt($m['latency_median']) . ' / ' . $fmt($m['latency_max']) . ' |';
    $r6['calls'] += $m['provider_calls'];
    $r6['max'] = max($r6['max'], $m['max_calls_per_entry']);
    $r6['holds'] += $m['holds'];
    $r6['rep'] += $repairs;
    $r6['rej'] += $rejected;
    $r6['read'] += $m['readings'];
    $r6['clar'] += count($m['clarifications']);
    foreach (['cand' => 'SLIP_CANDIDATE', 'conf' => 'SLIP_CONFIRMED', 'notc' => 'SLIP_NOT_CONFIRMED', 'void' => 'SLIP_VOIDED'] as $k => $type) {
        $r6[$k] += $slipCount($m['slips'], $type);
    }
    $r6['refer'] += $m['refer_back'];
    $r6['audit'] += count($m['audit']);
    $r6['dec'] += $modelDecisions;
}
$mx[] = '| **Total** | ' . count($metrics) . ' sessions | ' . $r6['calls'] . ' (' . ($metrics ? number_format($r6['calls'] / count($metrics), 1) : '-') . ' per session) | ' . $r6['max'] . ' | ' . $r6['holds'] . ' | ' . $r6['rep'] . ' | ' . $r6['rej'] . ' | ' . $r6['read'] . ' | ' . $r6['clar']
    . ' | ' . $r6['cand'] . ' / ' . $r6['conf'] . ' / ' . $r6['notc'] . ' / ' . $r6['void'] . ' / ' . count(array_filter($allRejections, fn ($x) => $x['operation'] === 'SLIP_PROPOSAL_REJECTED')) . ' | ' . $r6['refer'] . ' | ' . $r6['audit'] . ' | ' . $r6['dec']
    . ' | ' . $fmt($median($allLatencies)) . ' / ' . $fmt($allLatencies ? max($allLatencies) : null) . ' |';
$mx[] = '';
$mx[] = 'Most calls for one learner entry: instructional calls (reading, decision, reply and repairs) made for one learner action after the start; the limit is 2. Rejected model outputs: every rejection listed below (envelope, reading, decision, slip and step proposals).';
$mx[] = '';
$mx[] = '## Clarifying questions';
$mx[] = '';
$clar = array_merge(...array_map(fn ($m) => array_map(fn ($c) => $m['session'] . ': host parse `' . $c['host_reading'] . '`, model reading `' . $c['model_reading'] . '`; asked (' . $c['source'] . '): ' . ($c['question'] ?? '(host line)'), $m['clarifications']), $metrics));
$mx = array_merge($mx, $clar === [] ? ['None.'] : array_map(fn ($l) => '- ' . $l, $clar));
$mx[] = '';
$mx[] = '## Slips';
$mx[] = '';
$slipLines = [];
foreach ($metrics as $m) {
    foreach ($m['slips'] as $s) {
        $slipLines[] = '- ' . $m['session'] . ': ' . $s['type'] . (isset($s['sub_step']['display']) ? ' — ' . $s['sub_step']['display'] . (isset($s['wrong_value']) ? ' (the answer used ' . $s['wrong_value'] . '; found by ' . $s['source'] . ')' : '') : '') . (isset($s['reason']) ? ' — ' . $s['reason'] : '');
    }
}
foreach (array_filter($allRejections, fn ($x) => $x['operation'] === 'SLIP_PROPOSAL_REJECTED') as $x) {
    $slipLines[] = '- ' . $x['session'] . ': model slip proposal rejected — ' . implode(' | ', $x['errors']);
}
$mx = array_merge($mx, $slipLines === [] ? ['None.'] : $slipLines);
$mx[] = '';
$mx[] = '## Forward-looking audit list (replies shown before an independent item)';
$mx[] = '';
$auditLines = array_merge(...array_map(fn ($m) => array_map(fn ($a) => '- ' . $m['session'] . ' turn ' . $a['turn'] . ' (' . implode(', ', $a['phrases']) . '): ' . $a['text'], $m['audit']), $metrics));
$mx = array_merge($mx, $auditLines === [] ? ['None.'] : $auditLines);
$mx[] = '';
if ($messyRows !== []) {
    $mx[] = '## B11 messy inputs and N1 natural-learner inputs, one by one';
    $mx[] = '';
    $mx[] = '| Session | Turn | Input | Text submitted | Item | HTTP | Checker verdict | Verification scope | Tutor showed | Host rejections |';
    $mx[] = '|---|---|---|---|---|---|---|---|---|---|';
    foreach ($messyRows as $m) {
        $mx[] = '| ' . implode(' | ', array_map($md, [$m['session'], (string) ($m['turn'] ?? '-'), $m['label'], $m['turn'] === null ? '-' : '`' . $m['text'] . '`', (string) ($m['item'] ?? '-'),
            (string) ($m['http'] ?? '-'), $m['verdict'], $m['scope'], $m['shown'], $m['rejections'] ? implode('; ', $m['rejections']) : 'none'])) . ' |';
    }
    $mx[] = '';
}
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
// Round 6 (§7): at most two instructional provider calls per learner entry.
if ($r6['max'] > 2) {
    fwrite(STDERR, 'A learner entry used ' . $r6['max'] . " provider calls; the limit is 2.\n");
    exit(4);
}
