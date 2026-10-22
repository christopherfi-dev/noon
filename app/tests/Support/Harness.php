<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Support;

use AlgebraEngine\App;
use AlgebraEngine\Config;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Http\Api;
use AlgebraEngine\Http\Request;
use AlgebraEngine\Http\Response;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Provider\MockProvider;
use AlgebraEngine\Provider\ProviderAdapter;
use AlgebraEngine\Support\ManualClock;
use AlgebraEngine\Support\RandomSource;
use PHPUnit\Framework\Assert;

/**
 * In-process API driver for integration tests. It talks to the real Api,
 * Engine, SQLite storage and acceptance pipeline. Test-only "oracle"
 * helpers read the private item record from storage to know the key; the
 * public API never exposes it.
 */
final class Harness
{
    public App $app;
    public Api $api;
    public MockProvider $mock;
    public ManualClock $clock;
    public array $cookies = [];
    public ?string $csrf = null;
    public ?array $view = null;
    public ?Response $last = null;
    public string $dbPath;

    public function __construct(array $config = [], ?CurriculumRepository $curriculum = null, ?ProviderAdapter $provider = null, ?string $dbPath = null, ?ManualClock $clock = null, string $seed = 'harness')
    {
        $this->dbPath = $dbPath ?? sys_get_temp_dir() . '/ae_test_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->clock = $clock ?? new ManualClock();
        $factory = new ItemFactory();
        $this->mock = $provider instanceof MockProvider ? $provider : new MockProvider($factory);
        $this->app = new App(
            Config::fromArray(['db_path' => $this->dbPath, 'display_errors' => true] + $config, ALGEBRA_APP_ROOT . '/private'),
            $this->clock,
            $provider ?? $this->mock,
            $curriculum,
            RandomSource::seeded($seed),
        );
        $this->api = new Api($this->app);
    }

    /** A second harness on the same database and clock (another browser tab or process). */
    public function sibling(array $config = [], ?ProviderAdapter $provider = null): self
    {
        $h = new self($config, null, $provider, $this->dbPath, $this->clock);
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
            // Every cookie the API sets (the session cookie and, since round UI v3, the progress-code cookie).
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
            $this->checkB14();
        }
        return $r;
    }

    /** Independent item presentations checked by B14 in this harness. */
    public int $b14Checked = 0;

    /**
     * B14 (round 5): no INDEPENDENT item is shown in the same turn as model-written
     * text. Checked after every successful request of every integration test.
     */
    private function checkB14(): void
    {
        if (!isset($this->cookies['ae_sid'])) {
            return;
        }
        $sid = $this->app->store->db->scalar('SELECT current_session_id FROM containers WHERE cookie_hash = ?', [hash('sha256', $this->cookies['ae_sid'])]);
        $row = $sid === null || $sid === false ? null : $this->app->store->session((string) $sid);
        $a = $row['engine']['active'] ?? null;
        if ($a === null || $a['role'] === 'GUIDED' || $a['support_status'] !== 'INDEPENDENT' || $row['snapshot']['mode'] === 'SESSION_CLOSED') {
            return;
        }
        $this->b14Checked++;
        foreach ($row['engine']['messages'] as $m) {
            Assert::assertSame('HOST', $m['source'] ?? 'UNKNOWN', 'B14: independent item ' . $a['item_id'] . ' shown with non-host text: ' . $m['text']);
        }
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

    public function event(string $action, array $payload = [], ?string $key = null, ?int $revision = null, bool $withItem = null): Response
    {
        $itemActions = ['SUBMIT_RESPONSE', 'REQUEST_HINT', 'REQUEST_EXPLANATION', 'CLARIFY_RESPONSE', 'SKIP_ITEM', 'NOT_LEARNED'];
        $withItem ??= in_array($action, $itemActions, true);
        $item = $this->view['active_item'] ?? null;
        return $this->request('POST', 'event', [
            'idempotency_key' => $key ?? self::key(),
            'expected_revision' => $revision ?? $this->view['revision'],
            'action' => $action,
            'item_id' => $withItem ? $item['item_id'] : null,
            'item_version' => $withItem ? $item['item_version'] : null,
            'payload' => (object) $payload,
        ]);
    }

    public function submit(string $text, string $working = ''): Response
    {
        $payload = ['text' => $text];
        if ($working !== '') {
            $payload['working'] = $working;
        }
        $r = $this->event('SUBMIT_RESPONSE', $payload);
        Assert::assertSame(200, $r->status, 'submit failed: ' . json_encode($r->body));
        return $r;
    }

    public static function key(): string
    {
        return 'k' . bin2hex(random_bytes(12));
    }

    // ------------------------------------------------------------ oracle

    public function sessionId(): string
    {
        return (string) $this->app->store->db->scalar('SELECT current_session_id FROM containers WHERE cookie_hash = ?', [hash('sha256', $this->cookies['ae_sid'])]);
    }

    public function row(): array
    {
        return $this->app->store->session($this->sessionId());
    }

    /** Private active item (test oracle only). */
    public function active(): array
    {
        $a = $this->row()['engine']['active'];
        $rejections = array_slice(array_map(fn ($e) => $e['data']['operation'] . ': ' . implode(' | ', $e['data']['errors']), $this->events('MODEL_OUTPUT_REJECTED')), -3);
        Assert::assertNotNull($a, 'no active item; view=' . json_encode($this->view) . '; last rejections: ' . json_encode($rejections));
        return $a;
    }

    public function correct(): string
    {
        $spec = $this->active()['answer_spec'];
        return is_array($spec['expected']) ? implode(',', $spec['expected']) : (string) $spec['expected'];
    }

    public function answerCorrect(): Response
    {
        return $this->submit($this->correct());
    }

    /** A wrong answer that matches a named error signature of the active item. */
    public function wrong(string $code): string
    {
        $a = $this->active();
        $p = $a['params'];
        if (isset($a['objects']['signature_answers'][$code])) {
            return $a['objects']['signature_answers'][$code];
        }
        return match ($a['template_id'] . ':' . $code) {
            'POSITIVE_DISTRIBUTION:FIRST_TERM_ONLY' => $p['a'] . 'x+' . $p['b'],
            'TWO_STEP:MULTIPLY_INSTEAD_OF_DIVIDE' => (string) (($p['c'] - $p['b']) * $p['a']),
            'TWO_STEP:ADD_INSTEAD_OF_SUBTRACT' => $p['c'] + $p['b'] . '/' . $p['a'],
            'ONE_STEP_MULTIPLY:QUOTIENT_SIGN' => (string) (-intdiv($p['b'], $p['a'])),
            'ONE_STEP_MULTIPLY:MULTIPLIED' => (string) ($p['a'] * $p['b']),
            'SIGNED_DIVIDE:SIGN_FLIP' => (string) (-intdiv($p['a'], $p['b'])),
            'SIGNED_DIVIDE:NO_SIGNATURE' => (string) (intdiv($p['a'], $p['b']) > 0 ? -(abs(intdiv($p['a'], $p['b'])) + 7) : abs(intdiv($p['a'], $p['b'])) + 7),
            default => throw new \InvalidArgumentException('No wrong-answer recipe for ' . $a['template_id'] . ':' . $code),
        };
    }

    /** A wrong answer for any item type that matches no signature (best effort). */
    public function plainWrong(): string
    {
        $a = $this->active();
        $spec = $a['answer_spec'];
        return match ($spec['kind']) {
            'exact_rational' => (string) ((int) $spec['expected'] + 37),
            'polynomial' => 'x+997',
            'classification' => count($spec['expected']) === 1 && $spec['expected'][0] === 'A' ? 'B' : 'A',
        };
    }

    public function mode(): string
    {
        return $this->view['mode'];
    }

    public function skill(): ?string
    {
        return $this->row()['snapshot']['current_skill_id'];
    }

    /** @return list<array> */
    public function events(?string $type = null): array
    {
        $e = $this->app->store->events($this->sessionId());
        return $type === null ? $e : array_values(array_filter($e, fn ($x) => $x['type'] === $type));
    }

    /** Answer guided steps correctly until the mode leaves GUIDED_REPAIR. */
    public function completeGuidedRepair(int $limit = 40): void
    {
        for ($i = 0; $i < $limit && $this->mode() === 'GUIDED_REPAIR'; $i++) {
            $this->answerCorrect();
        }
        Assert::assertNotSame('GUIDED_REPAIR', $this->mode(), 'guided repair did not finish');
    }

    /** Answer independent items correctly until $skill's criterion is met (mode changes away from checks at $skill). */
    public function passChecksAt(string $skill, int $limit = 6): int
    {
        $n = 0;
        while ($n < $limit && $this->skill() === $skill && in_array($this->mode(), ['INDEPENDENT_CHECK', 'RETURN_TO_TARGET', 'TARGET_ASSESSMENT'], true) && $this->view['active_item'] !== null) {
            $this->answerCorrect();
            $n++;
        }
        return $n;
    }

    public function cleanup(): void
    {
        foreach (['', '-wal', '-shm'] as $s) {
            @unlink($this->dbPath . $s);
        }
    }
}
