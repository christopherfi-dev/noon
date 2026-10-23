<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Support;

use AlgebraEngine\App;
use AlgebraEngine\Config;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Http\Api;
use AlgebraEngine\Http\Request;
use AlgebraEngine\Http\Response;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Provider\ProviderAdapter;
use AlgebraEngine\Support\ManualClock;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Tutor\AnswerCheck;
use AlgebraEngine\Tutor\Goals;
use AlgebraEngine\Tutor\Gradebook;
use AlgebraEngine\Tutor\MockTutor;
use PHPUnit\Framework\Assert;

/**
 * In-process API driver for integration tests. It talks to the real Api,
 * tutor engine, SQLite storage and checks. Test-only "oracle" helpers read the
 * private problem record from storage to know code's answer; the public API
 * never exposes it.
 *
 * After every successful request the harness re-checks three guarantees on the
 * whole ledger: every presented problem has an answer code computed; every
 * recorded verdict is what code's checker gives for that exact problem and
 * entry; and the goals in the snapshot are what code computes from the gradebook.
 */
final class Harness
{
    public App $app;
    public Api $api;
    public MockTutor $mock;
    public ManualClock $clock;
    public array $cookies = [];
    public ?string $csrf = null;
    public ?array $view = null;
    public ?Response $last = null;
    public string $dbPath;
    public int $invariantChecks = 0;

    public function __construct(array $config = [], ?CurriculumRepository $curriculum = null, ?ProviderAdapter $provider = null, ?string $dbPath = null, ?ManualClock $clock = null, string $seed = 'harness')
    {
        $this->dbPath = $dbPath ?? sys_get_temp_dir() . '/ae_test_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->clock = $clock ?? new ManualClock();
        $this->mock = $provider instanceof MockTutor ? $provider : new MockTutor();
        $this->app = new App(
            Config::fromArray(['db_path' => $this->dbPath, 'display_errors' => true] + $config, ALGEBRA_APP_ROOT . '/private'),
            $this->clock,
            $provider ?? $this->mock,
            $curriculum,
            RandomSource::seeded($seed),
        );
        $this->api = new Api($this->app);
    }

    /** A second harness on the same database and clock (another browser tab or device). */
    public function sibling(array $config = [], ?ProviderAdapter $provider = null): self
    {
        $h = new self($config, null, $provider ?? $this->mock, $this->dbPath, $this->clock);
        $h->cookies = $this->cookies;
        $h->csrf = $this->csrf;
        return $h;
    }

    public function request(string $method, string $action, ?array $body = null, array $headers = [], ?string $rawBody = null): Response
    {
        $h = ['content-type' => 'application/json', 'host' => 'localhost'] + $headers;
        if ($this->csrf !== null && !array_key_exists('x-csrf-token', $headers)) {
            $h['x-csrf-token'] = $this->csrf;
        }
        $query = ['action' => $action];
        if (isset($headers['_query'])) {
            $query += $headers['_query'];
            unset($h['_query']);
        }
        $raw = $rawBody ?? ($body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR));
        $r = $this->api->handle(new Request($method, $query, $h, $this->cookies, $raw, false, 'localhost', '/'));
        foreach ($r->cookies as $c) {
            if (preg_match('/^([A-Za-z0-9_]+)=([^;]*)/', $c, $m)) {
                if (str_contains($c, 'Max-Age=0')) {
                    unset($this->cookies[$m[1]]);
                } else {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
        }
        $this->last = $r;
        if ($r->status === 200 && isset($r->body['revision'])) {
            $this->view = $r->body;
            $this->checkInvariants();
        }
        return $r;
    }

    public function bootstrap(): Response
    {
        $r = $this->request('GET', 'bootstrap');
        $this->csrf = $r->body['csrf_token'];
        if ($r->body['session'] !== null) {
            $this->view = $r->body['session'];
        }
        return $r;
    }

    public function start(?string $target = 'Q07', bool $replace = false): Response
    {
        if ($this->csrf === null) {
            $this->bootstrap();
        }
        return $this->request('POST', 'start', [
            'idempotency_key' => self::key(), 'expected_revision' => $replace && $this->view ? $this->view['revision'] : 0,
            'entry_route' => 'TOPIC', 'target_skill_id' => $target, 'task_token' => null, 'replace_current' => $replace,
        ]);
    }

    public function event(string $action, array $payload = [], ?string $key = null, ?int $revision = null, ?bool $withProblem = null): Response
    {
        $withProblem ??= in_array($action, ['SEND', 'STUCK', 'SKIP'], true) && ($this->view['open_problem'] ?? null) !== null;
        $p = $this->view['open_problem'] ?? null;
        return $this->request('POST', 'event', [
            'idempotency_key' => $key ?? self::key(),
            'expected_revision' => $revision ?? $this->view['revision'],
            'action' => $action,
            'problem_id' => $withProblem ? $p['problem_id'] : null,
            'problem_version' => $withProblem ? $p['problem_version'] : null,
            'payload' => (object) $payload,
        ]);
    }

    public function send(string $text): Response
    {
        return $this->event('SEND', ['text' => $text]);
    }

    public function stuck(string $choice = 'HINT'): Response
    {
        return $this->event('STUCK', ['choice' => $choice]);
    }

    // ----------------------------------------------------------- oracles

    public function sessionId(): ?string
    {
        if (!isset($this->cookies['ae_sid'])) {
            return null;
        }
        $sid = $this->app->store->db->scalar('SELECT current_session_id FROM containers WHERE cookie_hash = ?', [hash('sha256', $this->cookies['ae_sid'])]);
        return $sid === null || $sid === false ? null : (string) $sid;
    }

    public function row(): ?array
    {
        $sid = $this->sessionId();
        return $sid === null ? null : $this->app->store->session($sid);
    }

    /** The open problem's private record (test oracle only). */
    public function open(): ?array
    {
        return $this->row()['engine']['open'] ?? null;
    }

    /** Code's answer to the open problem, as a learner would type it. */
    public function answer(): string
    {
        $a = $this->open()['answer'];
        if ($a['kind'] === 'classification') {
            return implode(',', $a['expected']);
        }
        return $a['kind'] === 'polynomial' ? str_replace('*', '', (string) $a['expected']) : (string) $a['expected'];
    }

    /** A plainly wrong answer with no error signature. */
    public function wrong(): string
    {
        $o = $this->open();
        $a = $o['answer'];
        if ($a['kind'] === 'classification') {
            $all = array_column($o['options'], 'letter');
            $wrong = array_values(array_diff($all, $a['expected']));
            return $wrong === [] ? 'A' : implode(',', array_unique(array_merge($a['expected'], [$wrong[0]])));
        }
        return $a['kind'] === 'polynomial' ? 'x + 997' : (string) (ScalarParser::parse((string) $a['expected'])->num() + 97);
    }

    public function answerCorrectly(): Response
    {
        return $this->send($this->answer());
    }

    /** @return list<array> */
    public function events(): array
    {
        $sid = $this->sessionId();
        return $sid === null ? [] : $this->app->store->events($sid);
    }

    /** @return list<array> */
    public function eventsOf(string $type): array
    {
        return array_values(array_filter($this->events(), fn ($e) => $e['type'] === $type));
    }

    public function attempts(): array
    {
        return Gradebook::attempts($this->events());
    }

    public function goal(string $skill): array
    {
        return Goals::forSkill(Goals::compute($this->attempts()), $skill);
    }

    /** The conversation lines added by the last request. */
    public function lastLines(array $before): array
    {
        $ids = array_column($before, 'id');
        return array_values(array_filter($this->view['conversation'], fn ($l) => !in_array($l['id'], $ids, true)));
    }

    public function lines(): array
    {
        return $this->view['conversation'] ?? [];
    }

    /** Calls per learner turn, from the turn metrics. @return list<int> */
    public function callsPerTurn(): array
    {
        return array_map(fn ($e) => $e['data']['calls'], $this->eventsOf('TURN_METRICS'));
    }

    // -------------------------------------------------------- invariants

    private function checkInvariants(): void
    {
        $sid = $this->sessionId();
        if ($sid === null) {
            return;
        }
        $this->invariantChecks++;
        $events = $this->app->store->events($sid);
        $checker = new AnswerCheck($this->app->factory);
        foreach ($events as $e) {
            if ($e['type'] === 'PROBLEM_PRESENTED') {
                $record = $this->app->store->item($sid, $e['data']['problem_id'], $e['data']['version']);
                Assert::assertNotNull($record, 'presented problem has a stored record');
                Assert::assertArrayHasKey('expected', $record['answer'], 'every presented problem has an answer code computed');
            }
            if ($e['type'] === 'ATTEMPT_RECORDED') {
                $record = $this->app->store->item($sid, $e['data']['problem_id'], $e['data']['version']);
                Assert::assertNotNull($record, 'an attempt belongs to a stored problem');
                Assert::assertSame(AnswerCheck::outcome($record, $e['data']['read_as'])->verdict, $e['data']['verdict'], 'the recorded verdict is code\'s verdict for this exact problem and entry');
                Assert::assertSame(hash('sha256', $e['data']['read_as']), $e['data']['response_sha256']);
            }
        }
        $row = $this->app->store->session($sid);
        $goals = Goals::compute(Gradebook::attempts($events));
        foreach ($row['snapshot']['goals'] as $g) {
            Assert::assertSame($goals[$g['skill_id']]['met'] ?? false, $g['met'], 'goals in the snapshot are code\'s goals');
            Assert::assertSame($goals[$g['skill_id']]['count'] ?? 0, $g['count']);
        }
        unset($checker);
    }

    public static function key(): string
    {
        return bin2hex(random_bytes(12));
    }
}
