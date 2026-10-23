<?php
declare(strict_types=1);

/*
 * Scripted tutor sessions (conductor round 6 §9.5 and §10). Scripted learners
 * talk to the real API, tutor engine and storage; one transcript per session
 * and METRICS.md are written to --out.
 *
 *   php app/tools/tutor_sessions.php --out=DIR [--b1=5] [--descent=3] [--b11=2] [--n1=2] [--label=NAME]
 *   ALGEBRA_CONFIG=/private/config.php php app/tools/tutor_sessions.php --out=DIR ...
 *
 * Without ALGEBRA_CONFIG, or with provider 'mock', the mock tutor answers:
 * every output is labelled MOCK and says nothing about a live model, about
 * learning, or about latency. With provider 'openai_compatible' this is the
 * Stage 2 runner; provider_thinking in the config selects disabled, per_call
 * or enabled, and provider_model selects the model.
 *
 * Learners:
 * - B1: starts on Q07 and answers correctly, with one plainly wrong answer;
 * - descent: starts on Q07, multiplies only the x first, then answers
 *   correctly and asks for one hint;
 * - B11 (messy): loose and unreadable entries, stuck phrases, a question;
 * - N1 (natural): the owner's four cases where the open problem allows them
 *   (a capital X with the factor on the x only, an answer in a sentence,
 *   "N x x" for Nx, a product slip), questions to the tutor and going off topic.
 * The learner adapts to the problem the tutor poses; it reads code's answer
 * from this tool's own throwaway database.
 *
 * Per turn: the learner's input, what the student saw, the verdict code
 * recorded, the calls with their purpose and reasoning setting, and the wait.
 * METRICS.md: calls per turn, learner wait (median, slowest) against §3.7,
 * every rejected AI output with its text and reasons, clarifying questions,
 * slips confirmed and slips rejected, the forward-looking audit list, HOLDs,
 * and tokens per session. The key is never printed; reasoning text is never
 * read or stored.
 */

require dirname(__DIR__) . '/private/vendor/autoload.php';

use AlgebraEngine\App;
use AlgebraEngine\Config;
use AlgebraEngine\Http\Api;
use AlgebraEngine\Http\Request;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Tutor\Gradebook;

$opts = getopt('', ['out:', 'b1::', 'descent::', 'b11::', 'n1::', 'label::', 'max-turns::']);
$out = $opts['out'] ?? '';
if ($out === '') {
    fwrite(STDERR, "Usage: [ALGEBRA_CONFIG=/private/config.php] php tutor_sessions.php --out=DIR [--b1=5] [--descent=3] [--b11=2] [--n1=2] [--label=NAME]\n");
    exit(2);
}
$settings = [];
$file = getenv('ALGEBRA_CONFIG') ?: '';
if ($file !== '') {
    if (!is_file($file)) {
        fwrite(STDERR, "ALGEBRA_CONFIG does not name a file.\n");
        exit(2);
    }
    $settings = require $file;
}
$live = ($settings['provider'] ?? 'mock') === 'openai_compatible';
if ($live && trim((string) ($settings['provider_api_key'] ?? '')) === '') {
    fwrite(STDERR, "The live config has no provider_api_key.\n");
    exit(2);
}
@mkdir($out, 0775, true);
$db = sys_get_temp_dir() . '/ae_sessions_' . bin2hex(random_bytes(6)) . '.sqlite';
$settings = ['db_path' => $db, 'display_errors' => false] + $settings;
$app = new App(Config::fromArray($settings, dirname(__DIR__) . '/private'), null, null, null, RandomSource::seeded('tutor-sessions'));
$api = new Api($app);
$label = $opts['label'] ?? ($live ? 'live' : 'mock');
$maxTurns = (int) ($opts['max-turns'] ?? 30);
$providerLine = $live ? 'LIVE ' . $app->provider->name() . ' / ' . $app->provider->model() . ', provider_thinking ' . (($settings['provider_thinking'] ?? '') ?: 'per_call')
    : 'MOCK (simulated tutor; not a live result, not evidence of learning, latency not meaningful)';

/** A browser: cookies, CSRF token and the last view. */
final class Learner
{
    public array $cookies = [];
    public ?string $csrf = null;
    public ?array $view = null;
    public ?string $sid = null;
    public int $keys = 0;

    public function __construct(private Api $api, private App $app, public string $name)
    {
    }

    public function request(string $method, string $action, ?array $body = null): array
    {
        $h = ['content-type' => 'application/json', 'host' => 'localhost'] + ($this->csrf ? ['x-csrf-token' => $this->csrf] : []);
        $t = microtime(true);
        $r = $this->api->handle(new Request($method, ['action' => $action], $h, $this->cookies, $body === null ? '' : json_encode($body), false, 'localhost', '/'));
        $ms = (int) round((microtime(true) - $t) * 1000);
        foreach ($r->cookies as $c) {
            if (preg_match('/^([A-Za-z0-9_]+)=([^;]*)/', $c, $m)) {
                $this->cookies[$m[1]] = $m[2];
            }
        }
        if ($r->status === 200 && isset($r->body['revision'])) {
            $this->view = $r->body;
        }
        return [$r, $ms];
    }

    public function start(string $skill): array
    {
        $boot = $this->request('GET', 'bootstrap')[0];
        $this->csrf = $boot->body['csrf_token'];
        $res = $this->request('POST', 'start', ['idempotency_key' => $this->key(), 'expected_revision' => 0, 'entry_route' => 'TOPIC',
            'target_skill_id' => $skill, 'task_token' => null, 'replace_current' => false]);
        $sid = isset($this->cookies['ae_sid']) ? $this->app->store->db->scalar('SELECT current_session_id FROM containers WHERE cookie_hash = ?', [hash('sha256', $this->cookies['ae_sid'])]) : null;
        $this->sid = is_string($sid) ? $sid : null;
        return $res;
    }

    public function event(string $action, array $payload = []): array
    {
        $p = $this->view['open_problem'] ?? null;
        $with = in_array($action, ['SEND', 'STUCK', 'SKIP'], true) && $p !== null;
        return $this->request('POST', 'event', ['idempotency_key' => $this->key(), 'expected_revision' => $this->view['revision'], 'action' => $action,
            'problem_id' => $with ? $p['problem_id'] : null, 'problem_version' => $with ? $p['problem_version'] : null, 'payload' => (object) $payload]);
    }

    /** Code's record of the open problem (read from this tool's own database). */
    public function open(): ?array
    {
        return $this->sid === null ? null : ($this->app->store->session($this->sid)['engine']['open'] ?? null);
    }

    public function events(): array
    {
        return $this->sid === null ? [] : $this->app->store->events($this->sid);
    }

    private function key(): string
    {
        return 'k' . bin2hex(random_bytes(8)) . (++$this->keys);
    }
}

/** What the learner could type for the open problem. */
final class Entries
{
    public static function correct(array $o): string
    {
        $a = $o['answer'];
        if ($a['kind'] === 'classification') {
            return implode(',', $a['expected']);
        }
        return $a['kind'] === 'polynomial' ? str_replace('*', '', (string) $a['expected']) : (string) $a['expected'];
    }

    public static function wrong(array $o): string
    {
        $a = $o['answer'];
        if ($a['kind'] === 'classification') {
            $wrong = array_values(array_diff(array_column($o['options'], 'letter'), $a['expected']));
            return $wrong[0] ?? 'A';
        }
        return $a['kind'] === 'polynomial' ? 'x + 97' : (string) (ScalarParser::parse((string) $a['expected'])->num() + 7);
    }

    /** The factor reaches only the x (Q07 and E06 recipes), written with a capital X when asked. */
    public static function xOnly(array $o, bool $capital = false): ?string
    {
        $p = $o['params'] ?? [];
        $x = $capital ? 'X' : 'x';
        return match ($o['template_id'] ?? '') {
            'GROUPED_LINEAR' => (string) Rational::of($p['c'] - $p['b'], $p['a']),
            'POSITIVE_DISTRIBUTION' => $p['a'] . $x . ' + ' . $p['b'],
            default => null,
        };
    }

    /** A product slip on a(x + b): the structure is right, the product is off (7x + 25 for 7(x + 5); 2x + 7 for 2(x + 3)). */
    public static function productSlip(array $o): ?string
    {
        $p = $o['params'] ?? [];
        if (($o['template_id'] ?? '') !== 'POSITIVE_DISTRIBUTION') {
            return null;
        }
        $product = $p['a'] * $p['b'];
        return $p['a'] . 'x + ' . ($product > 10 ? $product - 10 : $product + 1);
    }

    /** "N x x" for Nx in a correct expansion (4 x x + 12 for 4x + 12). */
    public static function spacedX(array $o): ?string
    {
        $p = $o['params'] ?? [];
        return ($o['template_id'] ?? '') === 'POSITIVE_DISTRIBUTION' ? $p['a'] . ' x x + ' . ($p['a'] * $p['b']) : null;
    }
}

/**
 * One session. $script returns the learner's next move for the turn number,
 * the open problem record and the view: ['SEND', text] | ['STUCK', choice] | ['END'].
 */
$run = function (string $name, string $skill, callable $script) use ($api, $app, $maxTurns, $providerLine): array {
    $l = new Learner($api, $app, $name);
    $turns = [];
    [$r, $ms] = $l->start($skill);
    $turns[] = ['input' => '(start on ' . $skill . ')', 'status' => $r->status, 'ms' => $ms];
    for ($i = 1; $i <= $maxTurns && $l->view !== null; $i++) {
        if ($l->view['status'] === 'HOLD') {
            [$r, $ms] = $l->event('RESUME');
            $turns[] = ['input' => '(resume after a hold)', 'status' => $r->status, 'ms' => $ms];
            continue;
        }
        if ($l->view['wrap_up'] ?? false) {
            break;
        }
        $move = $script($i, $l->open(), $l->view);
        if ($move[0] === 'END') {
            break;
        }
        [$r, $ms] = $move[0] === 'STUCK' ? $l->event('STUCK', ['choice' => $move[1]]) : $l->event('SEND', ['text' => $move[1]]);
        $turns[] = ['input' => $move[0] === 'STUCK' ? 'I\'m stuck: ' . $move[1] : $move[1], 'status' => $r->status, 'ms' => $ms];
    }
    $l->event('END_SESSION');
    return ['name' => $name, 'learner' => $l, 'turns' => $turns];
};

$scripts = [];
$b1 = (int) ($opts['b1'] ?? 5);
$descent = (int) ($opts['descent'] ?? 3);
$b11 = (int) ($opts['b11'] ?? 2);
$n1 = (int) ($opts['n1'] ?? 2);
for ($n = 1; $n <= $b1; $n++) {
    $scripts[] = ['B1-' . $n, 'Q07', function (int $i, ?array $o) {
        if ($o === null) {
            return ['SEND', 'What should I do next?'];
        }
        return ['SEND', $i === 3 ? Entries::wrong($o) : Entries::correct($o)];
    }];
}
for ($n = 1; $n <= $descent; $n++) {
    $scripts[] = ['descent-' . $n, 'Q07', function (int $i, ?array $o) {
        if ($o === null) {
            return ['SEND', 'ok, what next?'];
        }
        if ($i === 1 && ($x = Entries::xOnly($o)) !== null) {
            return ['SEND', $x];
        }
        return $i === 4 ? ['STUCK', 'HINT'] : ['SEND', Entries::correct($o)];
    }];
}
for ($n = 1; $n <= $b11; $n++) {
    $scripts[] = ['B11-' . $n, $n % 2 === 1 ? 'Q06' : 'E06', function (int $i, ?array $o) {
        if ($o === null) {
            return ['SEND', 'hmm'];
        }
        $c = Entries::correct($o);
        $numeric = $o['answer']['kind'] === 'exact_rational';
        return match ($i) {
            1 => ['SEND', 'idk'],
            2 => ['SEND', '((('],
            3 => ['SEND', $numeric ? 'x = ' . $c : $c],
            4 => ['SEND', 'can you explain why that works?'],
            5 => ['SEND', '???'],
            6 => ['SEND', $numeric ? str_replace('-', "\u{2212}", $c) . '.0' : strtoupper($c)],
            7 => ['SEND', $numeric ? $c . '?' : $c],
            default => ['SEND', $c],
        };
    }];
}
for ($n = 1; $n <= $n1; $n++) {
    $scripts[] = ['N1-' . $n, 'E06', function (int $i, ?array $o) {
        static $used = [];
        if ($o === null) {
            return ['SEND', $i % 2 === 0 ? 'what\'s your favourite football team?' : 'why do we multiply both terms inside?'];
        }
        if (!isset($used['x']) && ($x = Entries::xOnly($o, true)) !== null) {
            $used['x'] = true;
            return ['SEND', $x];
        }
        if (!isset($used['spaced']) && ($s = Entries::spacedX($o)) !== null && $o['purpose'] === 'PRACTICE') {
            $used['spaced'] = true;
            return ['SEND', $s];
        }
        if (!isset($used['slip']) && ($s = Entries::productSlip($o)) !== null && $o['purpose'] === 'CHECK') {
            $used['slip'] = true;
            return ['SEND', $s];
        }
        if (!isset($used['sentence']) && $o['answer']['kind'] === 'exact_rational') {
            $used['sentence'] = true;
            return ['SEND', Entries::correct($o) . ' because I worked it out in my head'];
        }
        if (!isset($used['question'])) {
            $used['question'] = true;
            return ['SEND', 'is there a quicker way to do these?'];
        }
        return ['SEND', Entries::correct($o)];
    }];
}

// ---------------------------------------------------------------- run and report
$sessions = [];
foreach ($scripts as [$name, $skill, $script]) {
    $sessions[] = $run($name, $skill, $script);
    fwrite(STDOUT, "ran $name\n");
}

$median = function (array $v): ?float {
    if ($v === []) {
        return null;
    }
    sort($v);
    $n = count($v);
    return $n % 2 ? (float) $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
};
$md = fn (string $s) => str_replace(['|', "\n"], ['\\|', ' / '], $s);
$histogram = [];
$waits = [];
$summaryRows = [];
$rejections = [];
$audit = [];
$clarify = [];
$slipsConfirmed = [];
$slipsRejected = [];
$holds = 0;
foreach ($sessions as $s) {
    $l = $s['learner'];
    $events = $l->events();
    $metrics = array_values(array_filter($events, fn ($e) => $e['type'] === 'TURN_METRICS'));
    $calls = $l->sid === null ? [] : $app->store->db->all('SELECT call_purpose, thinking, reasoning_effort, outcome, duration_ms, prompt_tokens, completion_tokens, reasoning_tokens FROM model_calls WHERE session_id = ? ORDER BY rowid', [$l->sid]);
    $tokens = ['prompt' => array_sum(array_column($calls, 'prompt_tokens')), 'completion' => array_sum(array_column($calls, 'completion_tokens')), 'reasoning' => array_sum(array_column($calls, 'reasoning_tokens'))];
    $attempts = Gradebook::attempts($events);
    $t = "# {$s['name']} — " . $providerLine . "\n\n";
    $t .= "| # | Learner input | Calls (purpose: reasoning) | Wait ms |\n|---|---|---|---|\n";
    $m = 0;
    foreach ($s['turns'] as $k => $turn) {
        $metric = $turn['status'] === 200 ? ($metrics[$m++] ?? null) : null;
        $c = $metric['data']['calls'] ?? 0;
        if ($metric !== null) {
            $histogram[$c] = ($histogram[$c] ?? 0) + 1;
            $waits[] = $turn['ms'];
        }
        $callText = $metric === null ? '—' : implode('; ', array_map(fn ($p, $r) => $p . ': ' . $r, $metric['data']['purposes'], $metric['data']['reasoning']));
        $t .= '| ' . $k . ' | ' . $md($turn['input']) . ' | ' . $c . ($callText !== '' ? ' — ' . $md($callText) : '') . ' | ' . $turn['ms'] . ($turn['status'] !== 200 ? ' (HTTP ' . $turn['status'] . ')' : '') . " |\n";
    }
    $t .= "\n## Conversation as the student saw it\n\n";
    foreach (($l->view['conversation'] ?? []) as $line) {
        $t .= '- **' . $line['role'] . ' (' . $line['kind'] . '):** ' . $md($line['text']) . "\n";
    }
    $t .= "\n## Verdicts recorded by code\n\n| Problem | Purpose | Entry | Read as | Verdict | Help | Slip |\n|---|---|---|---|---|---|---|\n";
    foreach ($attempts as $a) {
        $t .= '| ' . $md($a['prompt']) . ' | ' . $a['purpose'] . ' | ' . $md($a['entry']) . ' | ' . $md((string) $a['read_as']) . ' | ' . $a['verdict'] . ' | ' . implode(', ', $a['help']) . ' | ' . ($a['slip']['code'] ?? '') . " |\n";
        if ($a['slip'] !== null) {
            $slipsConfirmed[] = $s['name'] . ': ' . $a['entry'] . ' on ' . $a['prompt'];
        }
    }
    foreach ($events as $e) {
        switch ($e['type']) {
            case 'MODEL_OUTPUT_REJECTED':
                $rejections[] = [$s['name'], $e['data']['purpose'], implode(' / ', $e['data']['errors']), (string) ($e['data']['text'] ?? '')];
                foreach ($e['data']['errors'] as $err) {
                    if (str_contains($err, 'SLIP_CHECK')) {
                        $slipsRejected[] = $s['name'] . ': ' . $err;
                    }
                }
                break;
            case 'READING_REJECTED':
                $rejections[] = [$s['name'], 'READING', implode(' / ', $e['data']['reasons']), (string) ($e['data']['reading'] ?? '')];
                break;
            case 'CLARIFICATION_ASKED':
                $clarify[] = $s['name'] . ': “' . $e['data']['entry'] . '” (' . $e['data']['question_source'] . ')';
                break;
            case 'CHECK_CARD_AUDIT':
                $audit[] = $s['name'] . ': ' . implode(', ', $e['data']['phrases']) . ' — “' . $e['data']['text'] . '”';
                break;
            case 'PROVIDER_FAILURE':
                $holds++;
                break;
        }
    }
    file_put_contents($out . '/' . $s['name'] . '.md', $t);
    $goals = array_values(array_filter(array_map(fn ($g) => $g['met'] ? $g['skill_id'] : null, \AlgebraEngine\Tutor\Goals::compute($attempts))));
    $summaryRows[] = '| ' . $s['name'] . ' | ' . count($s['turns']) . ' | ' . count($calls) . ' | ' . count($attempts) . ' | ' . implode(', ', $goals) . ' | '
        . $tokens['prompt'] . ' / ' . $tokens['completion'] . ' / ' . $tokens['reasoning'] . ' |';
}
ksort($histogram);
$total = array_sum($histogram);
$med = $median($waits);
$slowest = $waits === [] ? null : max($waits);
$r = "# Tutor sessions — $label\n\n" . $providerLine . "\n\nGenerated " . gmdate('Y-m-d\TH:i:s\Z') . " by `app/tools/tutor_sessions.php`.\n\n";
if (!$live) {
    $r .= "**MOCK:** the tutor is the scripted mock. These numbers show how the loop spends calls; they are not live results, not evidence of learning, and the waits are not a latency measurement.\n\n";
}
$r .= "## Calls per turn\n\n| Calls in the turn | Turns |\n|---|---|\n";
foreach ($histogram as $c => $n) {
    $r .= "| $c | $n |\n";
}
$r .= "\nTurns with exactly one call: " . ($histogram[1] ?? 0) . " of $total (" . ($total ? round(100 * ($histogram[1] ?? 0) / $total) : 0) . "%). "
    . "The first turn of each session also makes the architecture-binding call; a turn with an entry the AI must read makes a reading call first; a rejected output makes one repair call.\n\n";
$r .= "## Learner wait (§3.7: median ≤ " . $app->config->int('latency_target_median_ms') . " ms, slowest ≤ " . $app->config->int('latency_target_slowest_ms') . " ms)\n\n"
    . 'Median ' . ($med === null ? '—' : (int) $med . ' ms') . ', slowest ' . ($slowest === null ? '—' : $slowest . ' ms') . ' over ' . count($waits) . " turns."
    . ($live ? '' : ' (Mock: not meaningful.)') . "\n\n";
$r .= "## Sessions\n\n| Session | Turns | Calls | Answers recorded | Goals met (code) | Tokens prompt / completion / reasoning |\n|---|---|---|---|---|---|\n" . implode("\n", $summaryRows) . "\n\n";
$r .= "## Rejected AI output (" . count($rejections) . ")\n\n" . ($rejections === [] ? "None.\n" : "| Session | Call | Reasons | Rejected text |\n|---|---|---|---|\n"
    . implode("\n", array_map(fn ($x) => '| ' . $x[0] . ' | ' . $x[1] . ' | ' . $md($x[2]) . ' | ' . $md(mb_substr($x[3], 0, 400)) . ' |', $rejections)) . "\n") . "\n";
$r .= "## Clarifying questions (" . count($clarify) . ")\n\n" . ($clarify === [] ? "None.\n" : '- ' . implode("\n- ", array_map($md, $clarify)) . "\n") . "\n";
$r .= "## Slips\n\nConfirmed by code (" . count($slipsConfirmed) . "):\n\n" . ($slipsConfirmed === [] ? "None.\n" : '- ' . implode("\n- ", array_map($md, $slipsConfirmed)) . "\n")
    . "\nClaimed by the AI and rejected (" . count($slipsRejected) . "):\n\n" . ($slipsRejected === [] ? "None.\n" : '- ' . implode("\n- ", array_map($md, $slipsRejected)) . "\n") . "\n";
$r .= "## Forward-looking words before a check question (audit list, " . count($audit) . ")\n\n" . ($audit === [] ? "None.\n" : '- ' . implode("\n- ", array_map($md, $audit)) . "\n") . "\n";
$r .= "## HOLDs\n\n$holds\n";
file_put_contents($out . '/METRICS.md', $r);
@unlink($db);
echo "Wrote " . count($sessions) . " transcripts and METRICS.md to $out\n";
echo 'Calls per turn: ' . json_encode($histogram) . "\n";
