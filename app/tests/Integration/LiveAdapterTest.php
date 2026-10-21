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
    ];

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
                return ['status' => 200, 'body' => json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finish]]]), 'error' => null];
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
            $server->script = ['EMPTY', 'INTERNAL_WORDS'];
            $h->submit($h->wrong('DIST_X_ONLY'));
            $this->assertSame('READY', $h->view['status']);
            $errors = array_map(fn ($e) => $e['data']['errors'][0], $h->events('MODEL_OUTPUT_REJECTED'));
            $this->assertSame('empty content', $errors[0]);
            $this->assertStringContainsString('must not mention the host or keys', $errors[1]);
            $this->assertStringNotContainsString('host', strtolower(json_encode($h->view['messages'])));
        } finally {
            $h->cleanup();
        }
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
