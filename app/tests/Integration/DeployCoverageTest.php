<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Capability\CapabilityRegistry;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Acceptance scenarios COVER-01, DEPLOY-01 and DEPLOY-02. */
final class DeployCoverageTest extends TestCase
{
    /** COVER-01: all 205 records browsable with explicit runtime status; no all-skills operational claim. */
    public function testCover01CapabilityMatrix(): void
    {
        $h = new Harness();
        $catalog = $h->bootstrap()->body['catalog'];
        $this->assertCount(205, $catalog);
        $counts = array_count_values(array_column($catalog, 'runtime_status'));
        $this->assertSame(['AVAILABLE_SCORED' => 2, 'PARTIAL_COMPONENTS' => 8, 'UNAVAILABLE' => 195], [
            'AVAILABLE_SCORED' => $counts['AVAILABLE_SCORED'], 'PARTIAL_COMPONENTS' => $counts['PARTIAL_COMPONENTS'], 'UNAVAILABLE' => $counts['UNAVAILABLE'],
        ]);
        foreach ($catalog as $c) {
            $this->assertNotSame('', $c['title']);
            $this->assertNotSame('', $c['domain']);
        }
        $this->assertSame('SKILL_NOT_IN_BETA', $h->start('Q08')->body['error_code']);
        // Every registry test reference points at a real test.
        $registry = new CapabilityRegistry($h->app->curriculum, $h->app->factory);
        foreach ($registry->all() as $e) {
            foreach ($e['test_references'] as $ref) {
                [$file, $method] = array_pad(explode('::', $ref), 2, null);
                $this->assertFileExists(ALGEBRA_APP_ROOT . '/' . $file, $ref);
                if ($method !== null) {
                    $this->assertStringContainsString('function ' . $method . '(', (string) file_get_contents(ALGEBRA_APP_ROOT . '/' . $file), $ref);
                }
            }
        }
        // The committed matrix matches the registry.
        $matrix = json_decode((string) file_get_contents(ALGEBRA_APP_ROOT . '/capability-matrix.json'), true);
        $this->assertCount(205, $matrix['skills']);
        $this->assertSame(array_column($registry->all(), 'runtime_status', 'skill_id'), array_column($matrix['skills'], 'runtime_status', 'skill_id'));
        // The live result is stated with its limits; mock success is never reported as live validation.
        $this->assertStringContainsString('not teaching quality', $matrix['live_provider_smoke_test']);
        $this->assertStringContainsString('mock mode', $matrix['live_provider_smoke_test']);
        $this->assertCount(10, $matrix['deferred']);
    }

    /** DEPLOY-01: guessed private URLs are unavailable outside the public document root; no keys in client assets. */
    public function testDeploy01PrivateFilesNotServed(): void
    {
        $tmp = sys_get_temp_dir() . '/ae_deploy_' . bin2hex(random_bytes(4));
        mkdir($tmp);
        file_put_contents("$tmp/config.php", "<?php return ['db_path' => '$tmp/db.sqlite', 'provider_api_key' => 'sk-SECRET-should-never-leak'];");
        [$proc, $port] = self::server(ALGEBRA_APP_ROOT . '/public', ['ALGEBRA_CONFIG' => "$tmp/config.php"]);
        try {
            $ok = self::get($port, '/api.php?action=bootstrap');
            $this->assertSame(200, $ok['status']);
            $this->assertStringNotContainsString('SECRET', $ok['body']);
            foreach (['/../private/config.example.php', '/private/bootstrap.php', '/../private/var/algebra.sqlite', '/resources/curriculum/algebra-map.json',
                '/config.php', '/src/App.php', '/vendor/autoload.php', '/../private/resources/architecture/Algebra_Constraint_Architecture_v1.3.md', '/composer.json'] as $path) {
                $r = self::get($port, $path);
                $this->assertSame(404, $r['status'], $path);
                $this->assertStringNotContainsString('<?php', $r['body'], $path);
                $this->assertStringNotContainsString('ACE_BOUND', $r['body'], $path);
                $this->assertStringNotContainsString('provider_api_key', $r['body'], $path);
            }
        } finally {
            proc_terminate($proc);
            proc_close($proc);
            array_map('unlink', glob("$tmp/*") ?: []);
            @rmdir($tmp);
        }
        foreach (glob(ALGEBRA_APP_ROOT . '/public/{,assets/}*', GLOB_BRACE) as $file) {
            if (is_file($file)) {
                $content = (string) file_get_contents($file);
                foreach (['provider_api_key', 'answer_spec', 'algebra-map', 'ACE_BOUND', 'sk-'] as $needle) {
                    $this->assertStringNotContainsString($needle, $content, basename($file));
                }
            }
        }
        $public = array_map('basename', glob(ALGEBRA_APP_ROOT . '/public/*'));
        sort($public);
        $this->assertSame(['api.php', 'assets', 'index.html'], $public);
    }

    /** DEPLOY-02: without PDO_SQLite the preflight reports the unmet dependency and the app refuses to run (no text-file fallback). */
    public function testDeploy02MissingExtension(): void
    {
        $tmp = sys_get_temp_dir() . '/ae_noext_' . bin2hex(random_bytes(4));
        mkdir($tmp);
        file_put_contents("$tmp/config.php", "<?php return ['db_path' => '$tmp/db.sqlite'];");
        $cmd = 'ALGEBRA_CONFIG=' . escapeshellarg("$tmp/config.php") . ' ' . escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(ALGEBRA_APP_ROOT . '/private/bin/preflight.php') . ' 2>&1';
        exec($cmd, $out, $code);
        $text = implode("\n", $out);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('FAIL  PDO_SQLITE', $text);
        $this->assertStringContainsString('no file-based fallback', $text);
        $this->assertStringContainsString('PREFLIGHT FAILED', $text);

        [$proc, $port] = self::server(ALGEBRA_APP_ROOT . '/public', ['ALGEBRA_CONFIG' => "$tmp/config.php"], ['-n']);
        try {
            $r = self::get($port, '/api.php?action=bootstrap');
            $this->assertSame(503, $r['status']);
            $this->assertStringContainsString('UNAVAILABLE', $r['body']);
        } finally {
            proc_terminate($proc);
            proc_close($proc);
        }
        $this->assertSame([], array_values(array_diff(scandir($tmp), ['.', '..', 'config.php'])), 'no storage files were created as a fallback');
        unlink("$tmp/config.php");
        rmdir($tmp);
    }

    /** @return array{0:resource,1:int} */
    private static function server(string $docroot, array $env, array $phpFlags = []): array
    {
        $port = random_int(20000, 40000);
        $cmd = array_merge([PHP_BINARY], $phpFlags, ['-S', "127.0.0.1:$port", '-t', $docroot]);
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env + ['PATH' => getenv('PATH')]);
        for ($i = 0; $i < 50; $i++) {
            $s = @fsockopen('127.0.0.1', $port);
            if ($s) {
                fclose($s);
                break;
            }
            usleep(100000);
        }
        return [$proc, $port];
    }

    private static function get(int $port, string $path): array
    {
        $s = fsockopen('127.0.0.1', $port, $errno, $errstr, 5);
        fwrite($s, "GET $path HTTP/1.0\r\nHost: 127.0.0.1:$port\r\n\r\n");
        $raw = stream_get_contents($s);
        fclose($s);
        [$head, $body] = array_pad(explode("\r\n\r\n", (string) $raw, 2), 2, '');
        preg_match('#HTTP/\S+ (\d{3})#', $head, $m);
        return ['status' => (int) ($m[1] ?? 0), 'body' => $body];
    }
}
