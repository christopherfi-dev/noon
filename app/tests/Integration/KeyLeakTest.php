<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once ALGEBRA_APP_ROOT . '/tools/key_leak_scan.php';

/**
 * Conductor Round 2 §1.2: no tracked file and no upload ZIP entry contains a
 * provider-style key or the configured key. Failure messages name locations
 * only, never the matched value.
 */
final class KeyLeakTest extends TestCase
{
    public function testNoTrackedFileOrZipEntryContainsAKey(): void
    {
        $repo = dirname(ALGEBRA_APP_ROOT);
        $zip = $repo . '/dist/algebra-engine-ai-vps-upload.zip';
        $this->assertFileExists($zip, 'the upload ZIP is scanned too');
        $files = array_map(fn ($f) => $repo . '/' . $f, \key_leak_tracked_files($repo));
        $this->assertGreaterThan(100, count($files));
        $r = \key_leak_scan($files, [$zip], \key_leak_configured_key(ALGEBRA_APP_ROOT), $repo);
        $this->assertGreaterThan(100, $r['zip_entries']);
        $this->assertSame([], $r['hits'], 'key material found; revoke the key and report it (Round 2 §1.4)');
    }

    /** The scanner itself finds planted fake keys in a file, a ZIP entry and by literal value. */
    public function testScannerDetectsPlantedKeys(): void
    {
        $dir = sys_get_temp_dir() . '/ae_keyscan_' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $fake = 'sk' . '-' . str_repeat('Ab1', 8);        // built at run time so this file stays clean
            $literal = 'not-sk-shaped-' . bin2hex(random_bytes(6));
            $prefixed = 'sk' . '-proj-' . str_repeat('Ab_1-', 6);
            file_put_contents("$dir/a.txt", "token: $fake\n");
            file_put_contents("$dir/b.txt", "value=$literal\n");
            file_put_contents("$dir/c.txt", "sk-short, risk-assessment and task-based-assessment-for-learning are fine\n");
            file_put_contents("$dir/d.txt", "OPENAI=$prefixed\n");
            $entries = ['private/config.php' => "<?php return ['provider_api_key' => '$fake'];", 'public/index.html' => '<p>clean</p>'];
            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                $zip->open("$dir/u.zip", \ZipArchive::CREATE);
                foreach ($entries as $name => $content) {
                    $zip->addFromString($name, $content);
                }
                $zip->close();
            } else {
                // Round PHP80: the PHP 8.0 gate image has no zip extension; PharData writes the same ZIP.
                $zip = new \PharData("$dir/u.zip", 0, null, \Phar::ZIP);
                foreach ($entries as $name => $content) {
                    $zip->addFromString($name, $content);
                }
                unset($zip);
            }
            $r = \key_leak_scan(["$dir/a.txt", "$dir/b.txt", "$dir/c.txt", "$dir/d.txt"], ["$dir/u.zip"], $literal, $dir);
            $this->assertSame([
                'a.txt: matches sk-[A-Za-z0-9]{20,}',
                'b.txt: contains the configured key',
                'd.txt: matches a prefixed sk-…- key',
                'u.zip!private/config.php: matches sk-[A-Za-z0-9]{20,}',
            ], $r['hits']);
            foreach ($r['hits'] as $h) {
                $this->assertStringNotContainsString($fake, $h);
                $this->assertStringNotContainsString($literal, $h);
                $this->assertStringNotContainsString($prefixed, $h);
            }
        } finally {
            array_map('unlink', glob("$dir/*"));
            rmdir($dir);
        }
    }
}
