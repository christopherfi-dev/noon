<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Provider\CurlTransport;
use AlgebraEngine\Provider\HttpTransport;
use AlgebraEngine\Provider\MockProvider;
use AlgebraEngine\Provider\OpenAiCompatibleProvider;
use AlgebraEngine\Config;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * The OpenAI-compatible adapter against a simulated server (no network).
 * This checks request format and response handling only; it is not a live
 * provider test.
 */
final class LiveAdapterTest extends TestCase
{
    private const LIVE = [
        'provider' => 'openai_compatible',
        'provider_base_url' => 'https://api.example.test/v1',
        'provider_model' => 'operator-confirmed-model',
        'provider_api_key' => 'test-key-not-real',
        'provider_thinking' => 'disabled',
    ];

    /** Reasoning text the fake server returns; it must never be stored, shown or sent back. */
    public const REASONING = 'REASONING-TEXT-MARKER-7f3a';

    /** A fake server whose answers come from the mock model, wrapped as chat completions. */
    private static function fakeServer(): HttpTransport
    {
        return new class implements HttpTransport {
            public array $requests = [];
            public array $script = [];
            private MockProvider $model;

            public function __construct()
            {
                $this->model = new MockProvider(new ItemFactory());
            }

            public function post(string $url, array $headers, string $body, int $connectTimeout, int $timeout, int $maxBytes): array
            {
                $req = json_decode($body, true);
                $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $req];
                $next = array_shift($this->script);
                if ($next === 'TIMEOUT') {
                    return ['status' => 0, 'body' => '', 'error' => 'TIMEOUT'];
                }
                if ($next === 'NETWORK_DIAG') {
                    return ['status' => 0, 'body' => '', 'error' => 'NETWORK', 'diagnostics' => ['curl_errno' => 56, 'stage' => 'response', 'elapsed_ms' => 5300]];
                }
                if ($next === 'HTTP_500') {
                    return ['status' => 500, 'body' => '{"error":{"message":"overloaded"}}', 'error' => null];
                }
                $answer = $this->model->complete($req['messages'], isset($req['response_format']));
                $content = $answer->content;
                $finish = 'stop';
                if ($next === 'LENGTH') {
                    $content = substr((string) $content, 0, 40);
                    $finish = 'length';
                }
                if ($next === 'EMPTY') {
                    $content = ''; // seen live: JSON mode occasionally returns empty content with finish_reason stop
                }
                if ($next === 'INTERNAL_WORDS') {
                    $env = json_decode((string) $content); // objects kept as objects
                    $env->student_message = 'That step is done, and the host matched it to the key.';
                    $content = json_encode($env);
                }
                return ['status' => 200, 'body' => json_encode([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => $content, 'reasoning_content' => LiveAdapterTest::REASONING], 'finish_reason' => $finish]],
                    'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 300, 'completion_tokens_details' => ['reasoning_tokens' => 120]],
                ]), 'error' => null];
            }
        };
    }

    public function testAdapterRequestFormatAndFullJourneyStart(): void
    {
        $server = self::fakeServer();
        $h = new Harness(self::LIVE, null, null);
        $app = new \AlgebraEngine\App($h->app->config, $h->clock, null, null, null, $server);
        $this->assertInstanceOf(OpenAiCompatibleProvider::class, $app->provider);
        $h->app = $app;
        $h->api = new \AlgebraEngine\Http\Api($app);
        try {
            $this->assertSame('live', $h->bootstrap()->body['provider_mode']);
            $r = $h->start('Q07');
            $this->assertSame(200, $r->status);
            $this->assertSame('READY', $h->view['status']);
            [$binding, $envelope] = $server->requests;
            $this->assertSame('https://api.example.test/v1/chat/completions', $binding['url']);
            $this->assertSame('Bearer test-key-not-real', $binding['headers']['Authorization']);
            $this->assertSame('operator-confirmed-model', $binding['body']['model']);
            $this->assertFalse($binding['body']['stream']);
            $this->assertArrayNotHasKey('response_format', $binding['body'], 'JSON mode off for the activation call');
            $this->assertArrayNotHasKey('tools', $binding['body']);
            // Round 3 ruling 1–2: thinking is explicit on every call; temperature only without thinking.
            $this->assertSame(['type' => 'disabled'], $binding['body']['thinking']);
            $this->assertSame(['type' => 'disabled'], $envelope['body']['thinking']);
            $this->assertArrayHasKey('temperature', $envelope['body']);
            $this->assertArrayNotHasKey('reasoning_effort', $envelope['body'], 'sent only when thinking is enabled');
            $this->assertSame(['type' => 'json_object'], $envelope['body']['response_format']);
            $this->assertStringContainsString('JSON', end($envelope['body']['messages'])['content']);
            $this->assertSame('openai_compatible', $h->row()['snapshot']['binding']['provider']);
            $this->assertTrue($h->row()['snapshot']['binding']['acknowledged']);
            // The key never reaches the browser.
            $this->assertStringNotContainsString('test-key-not-real', json_encode($h->view));

            // A truncated reply is repaired; a server error or timeout holds without scoring the learner.
            $server->script = ['LENGTH'];
            $h->submit($h->wrong('DIST_X_ONLY'));
            $this->assertSame('READY', $h->view['status']);
            $this->assertStringContainsString('truncated', $h->events('MODEL_OUTPUT_REJECTED')[0]['data']['errors'][0]);
            $server->script = ['HTTP_500'];
            $h->event('REQUEST_HINT');
            $this->assertSame('HOLD', $h->view['status']);
            $this->assertSame('PROVIDER_UNAVAILABLE', $h->row()['snapshot']['issue']['type']);
            $server->script = ['TIMEOUT'];
            $h->event('RESUME_SESSION');
            $this->assertSame('HOLD', $h->view['status']);
            $h->event('RESUME_SESSION');
            $this->assertSame('READY', $h->view['status']);
            $this->assertCount(1, $h->events('CHECKER_RESULT'), 'the learner answer was scored once');
        } finally {
            $h->cleanup();
        }
    }

    public function testEmptyContentAndInternalWordsAreRepairedNotShown(): void
    {
        $server = self::fakeServer();
        $h = new Harness(self::LIVE, null, null);
        $app = new \AlgebraEngine\App($h->app->config, $h->clock, null, null, null, $server);
        $h->app = $app;
        $h->api = new \AlgebraEngine\Http\Api($app);
        try {
            $h->bootstrap();
            $h->start('Q07');
            // A hint is model-written learner text (an independent item's framing is host-only since round 5).
            $server->script = ['EMPTY', 'INTERNAL_WORDS'];
            $r = $h->event('REQUEST_HINT');
            $this->assertSame(200, $r->status);
            $this->assertSame('READY', $h->view['status']);
            $errors = array_map(fn ($e) => $e['data']['errors'][0], $h->events('MODEL_OUTPUT_REJECTED'));
            $this->assertSame('empty content', $errors[0]);
            $this->assertStringContainsString('uses internal terms: "host"', $errors[1]);
            $this->assertStringNotContainsString('host', strtolower(json_encode($h->view['messages'])));
        } finally {
            $h->cleanup();
        }
    }

    /** Round 3 ruling 1–2: explicit, consistent thinking settings; temperature only when thinking is disabled. */
    public function testThinkingSettingsAreExplicit(): void
    {
        $server = self::fakeServer();
        $provider = new OpenAiCompatibleProvider(Config::fromArray(['provider_thinking' => 'enabled', 'provider_reasoning_effort' => 'low'] + self::LIVE, ALGEBRA_APP_ROOT . '/private'), $server);
        $provider->complete([['role' => 'user', 'content' => 'CONTROL']], false);
        $body = $server->requests[0]['body'];
        $this->assertSame(['type' => 'enabled'], $body['thinking']);
        $this->assertSame('low', $body['reasoning_effort']);
        $this->assertArrayNotHasKey('temperature', $body, 'no effect in thinking mode, so not sent');
        $bad = [
            ['provider_thinking' => ''],                                              // never the provider default
            ['provider_thinking' => 'auto'],
            ['provider_thinking' => 'enabled'],                                       // effort missing
            ['provider_thinking' => 'enabled', 'provider_reasoning_effort' => 'medium'],
            ['provider_thinking' => 'enabled', 'provider_reasoning_effort' => 'none'],
            ['provider_thinking' => 'disabled', 'provider_reasoning_effort' => 'high'],
        ];
        foreach ($bad as $settings) {
            try {
                new OpenAiCompatibleProvider(Config::fromArray($settings + self::LIVE, ALGEBRA_APP_ROOT . '/private'), $server);
                $this->fail('accepted ' . json_encode($settings));
            } catch (\InvalidArgumentException $e) {
                $this->assertMatchesRegularExpression('/provider_(thinking|reasoning_effort)/', $e->getMessage());
            }
        }
    }

    /**
     * Round 3 ruling 3–4: reasoning_content is never stored, shown or sent back; only token counts are logged,
     * and every finish_reason=length is recorded.
     */
    public function testReasoningContentIsNeverKeptAndTruncationIsRecorded(): void
    {
        $server = self::fakeServer();
        $h = new Harness(self::LIVE, null, null);
        $app = new \AlgebraEngine\App($h->app->config, $h->clock, null, null, null, $server);
        $h->app = $app;
        $h->api = new \AlgebraEngine\Http\Api($app);
        try {
            $h->bootstrap();
            $h->start('Q07');
            $server->script = ['LENGTH', 'EMPTY'];                 // two rejected outputs → two repair requests
            $h->submit($h->wrong('DIST_X_ONLY'));
            $this->assertSame('READY', $h->view['status']);
            $h->event('END_SESSION');
            $report = $h->request('GET', 'report')->body;

            foreach ($server->requests as $r) {
                $this->assertStringNotContainsString(self::REASONING, json_encode($r['body']), 'never sent back to the API');
            }
            $pdo = new \PDO('sqlite:' . $app->config->string('db_path'));
            $dump = '';
            foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN) as $table) {
                $dump .= json_encode($pdo->query("SELECT * FROM $table")->fetchAll(\PDO::FETCH_ASSOC));
            }
            $this->assertStringNotContainsString(self::REASONING, $dump, 'never stored');
            $this->assertStringNotContainsString(self::REASONING, json_encode($h->view) . json_encode($report), 'never in a public payload');

            $calls = $pdo->query('SELECT kind, outcome, finish_reason, prompt_tokens, completion_tokens, reasoning_tokens, duration_ms FROM model_calls ORDER BY rowid')->fetchAll(\PDO::FETCH_ASSOC);
            $this->assertNotEmpty($calls);
            foreach ($calls as $c) {
                $this->assertSame([900, 300, 120], [(int) $c['prompt_tokens'], (int) $c['completion_tokens'], (int) $c['reasoning_tokens']], 'token counts are logged');
                $this->assertNotNull($c['duration_ms']);
            }
            $this->assertCount(1, array_filter($calls, fn ($c) => $c['finish_reason'] === 'length'), 'finish_reason=length recorded');
        } finally {
            $h->cleanup();
        }
    }

    /** Round 4 ruling 4: a network failure logs the curl error number, the stage reached and the elapsed time. */
    public function testNetworkFailureDiagnosticsAreLogged(): void
    {
        $server = self::fakeServer();
        $h = new Harness(self::LIVE, null, null);
        $app = new \AlgebraEngine\App($h->app->config, $h->clock, null, null, null, $server);
        $h->app = $app;
        $h->api = new \AlgebraEngine\Http\Api($app);
        try {
            $h->bootstrap();
            $h->start('Q07');
            $server->script = ['NETWORK_DIAG'];
            $h->submit($h->wrong('DIST_X_ONLY'));
            $this->assertSame('HOLD', $h->view['status']);
            $failure = $h->events('PROVIDER_FAILURE')[0]['data']['failure'];
            $this->assertSame(['curl_errno' => 56, 'stage' => 'response', 'elapsed_ms' => 5300], $failure['transport']);
            $row = $app->store->db->one("SELECT transport_detail FROM model_calls WHERE outcome = 'TRANSPORT_NETWORK'");
            $this->assertSame(['curl_errno' => 56, 'stage' => 'response', 'elapsed_ms' => 5300], json_decode($row['transport_detail'], true));
            $this->assertStringNotContainsString('curl_errno', json_encode($h->view), 'diagnostics stay server-side');
        } finally {
            $h->cleanup();
        }
        // The real transport reports the stage: a closed local port fails at connect.
        $r = (new CurlTransport())->post('https://127.0.0.1:1/', [], '{}', 2, 2, 1000);
        $this->assertSame('NETWORK', $r['error']);
        $this->assertSame('connect', $r['diagnostics']['stage']);
        $this->assertSame(CURLE_COULDNT_CONNECT, $r['diagnostics']['curl_errno']);
        $this->assertIsInt($r['diagnostics']['elapsed_ms']);
    }

    public function testLiveProviderRequiresExplicitSettingsAndHttps(): void
    {
        foreach (['provider_base_url', 'provider_model', 'provider_api_key'] as $missing) {
            try {
                new OpenAiCompatibleProvider(Config::fromArray([$missing => ''] + self::LIVE, ALGEBRA_APP_ROOT . '/private'), self::fakeServer());
                $this->fail("$missing must be required");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($missing, $e->getMessage());
            }
        }
        $result = (new CurlTransport())->post('http://api.example.test/v1/chat/completions', [], '{}', 1, 1, 1000);
        $this->assertSame('NETWORK', $result['error'], 'plain HTTP is refused before any connection');
    }
}
