<?php
declare(strict_types=1);

/*
 * Key-leak scan (Conductor Round 2 §1.2). Fails if any tracked file, or any
 * entry of the upload ZIP, contains a string matching sk-[A-Za-z0-9]{20,} or
 * the literal value of the configured provider key.
 *
 *   php app/tools/key_leak_scan.php
 *
 * The configured key is read from ALGEBRA_CONFIG when set, otherwise from
 * app/private/config.php when present. Output names locations only; it never
 * prints a matched value. Only "key present: yes/no" is reported.
 *
 * Round PHP80: runs where git or the zip extension are absent (the PHP 8.0
 * gate image). ALGEBRA_TRACKED_FILES may name a file holding `git ls-files -z`
 * output written by the caller; ZIP entries are read with ZipArchive when it
 * exists, otherwise with PharData (built into PHP).
 */

const KEY_PATTERN = '/sk-[A-Za-z0-9]{20,}/';
// Prefixed keys (sk-proj-…, sk-svcacct-…, sk-admin-…) have a hyphen after the prefix, which KEY_PATTERN does not match.
const PREFIXED_KEY_PATTERN = '/(?<![A-Za-z0-9])sk-(?:proj|svcacct|admin)-[A-Za-z0-9_-]{20,}/';

/** The configured key, or '' when none is configured. */
function key_leak_configured_key(string $appRoot): string
{
    $file = getenv('ALGEBRA_CONFIG') ?: $appRoot . '/private/config.php';
    if (!is_file($file)) {
        return '';
    }
    $settings = require $file;
    return is_array($settings) ? trim((string) ($settings['provider_api_key'] ?? '')) : '';
}

/** Tracked files (git ls-files), relative to the repository root. @return list<string> */
function key_leak_tracked_files(string $repo): array
{
    $list = getenv('ALGEBRA_TRACKED_FILES');
    if (is_string($list) && $list !== '') {
        $out = file_get_contents($list);
        if ($out === false) {
            throw new RuntimeException('cannot read the tracked-file list ' . $list);
        }
        return array_values(array_filter(explode("\0", $out), fn ($f) => $f !== ''));
    }
    $proc = proc_open(['git', '-C', $repo, 'ls-files', '-z'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('git is required to list tracked files');
    }
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($proc) !== 0 || $out === false) {
        throw new RuntimeException('git ls-files failed in ' . $repo);
    }
    return array_values(array_filter(explode("\0", $out), fn ($f) => $f !== ''));
}

/** Reasons found in one text; never the matched value. @return list<string> */
function key_leak_check(string $text, string $key): array
{
    $found = [];
    if (preg_match(KEY_PATTERN, $text)) {
        $found[] = 'matches sk-[A-Za-z0-9]{20,}';
    }
    if (preg_match(PREFIXED_KEY_PATTERN, $text)) {
        $found[] = 'matches a prefixed sk-…- key';
    }
    if ($key !== '' && str_contains($text, $key)) {
        $found[] = 'contains the configured key';
    }
    return $found;
}

/**
 * @param list<string> $files absolute paths
 * @return array{files:int,zip_entries:int,hits:list<string>}
 */
function key_leak_scan(array $files, array $zips, string $key, string $base = ''): array
{
    $hits = [];
    $count = 0;
    foreach ($files as $path) {
        if (!is_file($path)) {
            continue; // tracked but deleted in the working tree
        }
        $count++;
        foreach (key_leak_check((string) file_get_contents($path), $key) as $why) {
            $hits[] = ltrim(substr($path, strlen($base)), '/') . ': ' . $why;
        }
    }
    $entries = 0;
    foreach ($zips as $zipPath) {
        $list = key_leak_zip_entries($zipPath);
        if ($list === null) {
            $hits[] = basename($zipPath) . ': cannot be opened for scanning';
            continue;
        }
        foreach ($list as [$name, $content]) {
            $entries++;
            foreach (key_leak_check($content, $key) as $why) {
                $hits[] = basename($zipPath) . '!' . $name . ': ' . $why;
            }
        }
    }
    return ['files' => $count, 'zip_entries' => $entries, 'hits' => $hits];
}

/** Every entry of a ZIP as [name, content], in archive order; null when it cannot be opened. @return ?list<array{0:string,1:string}> */
function key_leak_zip_entries(string $zipPath): ?array
{
    $out = [];
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return null;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $out[] = [(string) $zip->getNameIndex($i), (string) $zip->getFromIndex($i)];
        }
        $zip->close();
        return $out;
    }
    try {
        $archive = new PharData($zipPath);
    } catch (Throwable $e) {
        return null;
    }
    $prefix = 'phar://' . realpath($zipPath) . '/';
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        $out[] = [substr($file->getPathname(), strlen($prefix)), (string) file_get_contents($file->getPathname())];
    }
    return $out;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $app = dirname(__DIR__);
    $repo = dirname($app);
    $key = key_leak_configured_key($app);
    // Round 6: the AI tutor's upload ZIP, and the beta's two ZIPs that stay in dist/ unchanged.
    $zips = [$repo . '/dist/algebra-engine-ai-vps-upload.zip', $repo . '/dist/algebra-engine-bluehost-upload.zip', $repo . '/dist/algebra-engine-vps-upload.zip'];
    $present = array_values(array_filter($zips, 'is_file'));
    $files = array_map(fn ($f) => $repo . '/' . $f, key_leak_tracked_files($repo));
    $r = key_leak_scan($files, $present, $key, $repo);
    echo 'Configured key present: ' . ($key !== '' ? 'yes' : 'no') . "\n";
    echo "Tracked files scanned: {$r['files']}\n";
    echo 'Upload ZIP entries scanned: ' . $r['zip_entries'] . ' (' . implode(', ', array_map('basename', $present)) . ')'
        . (count($present) < count($zips) ? '; ZIP NOT FOUND: ' . implode(', ', array_map('basename', array_diff($zips, $present))) : '') . "\n";
    foreach ($r['hits'] as $h) {
        echo "LEAK  $h\n";
    }
    $ok = $r['hits'] === [] && count($present) === count($zips);
    echo $ok ? "KEY-LEAK SCAN PASSED\n" : "KEY-LEAK SCAN FAILED\n";
    exit($ok ? 0 : 1);
}
