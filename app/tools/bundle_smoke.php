<?php
declare(strict_types=1);

/*
 * Smoke test of the round 6 side-by-side VPS upload bundle in its deployment
 * layout, mock mode, using PHP's built-in server. Not a substitute for the
 * on-host smoke test.
 *   php app/tools/bundle_smoke.php [dist]
 *
 * algebra-engine-ai/ is served at /educational-engines/algebra-engine-ai/ and
 * algebra-engine-ai-private/ sits outside the web root (dist/stage-ai-vps and
 * dist/algebra-engine-ai-vps-upload.zip).
 */
$dist = realpath($argv[1] ?? (dirname(__DIR__, 2) . '/dist')) ?: (dirname(__DIR__, 2) . '/dist');
$vpsStage = $dist . '/stage-ai-vps';
$vpsZip = $dist . '/algebra-engine-ai-vps-upload.zip';
const PUB = 'algebra-engine-ai';
const PRIV = 'algebra-engine-ai-private';
$fail = 0;

function req(string $method, string $url, ?array $body, array $headers, string $jar, ?array &$responseHeaders = null): array
{
    $ch = curl_init($url);
    $responseHeaders = [];
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
            $responseHeaders[] = rtrim($line);
            return strlen($line);
        }]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $out = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode($out, true), $out];
}

$check = function (string $name, bool $ok, string $detail = '') use (&$fail) {
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($detail !== '' ? "  ($detail)" : '') . PHP_EOL;
};

/** Starts PHP's built-in server for $docroot; returns [process, port]. */
function serve(string $docroot, array $env): array
{
    $port = random_int(41000, 49000);
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $docroot], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env + ['PATH' => getenv('PATH')]);
    usleep(600000);
    return [$proc, $port];
}

/** The tutoring flow through a bundled api.php at $base (bootstrap, start, I'm stuck, end, report, CSRF). */
function flow(string $base, string $jar, callable $check, string $bootstrapLabel): array
{
    [$c, , $raw] = req('GET', "$base/", null, [], $jar);
    $check('index.html served', $c === 200 && str_contains($raw, 'Algebra Engine AI tutor'));
    [$c, $b] = req('GET', "$base/api.php?action=bootstrap", null, [], $jar, $headers);
    $check($bootstrapLabel, $c === 200 && $b['provider_mode'] === 'mock' && count($b['catalog']) === 205, "HTTP $c");
    $csrf = $b['csrf_token'] ?? '';
    [$c, $v] = req('POST', "$base/api.php?action=start", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => 0, 'entry_route' => 'TOPIC', 'target_skill_id' => 'Q07', 'task_token' => null, 'replace_current' => false], ["X-CSRF-Token: $csrf"], $jar);
    $check('start Q07: the tutor speaks and a problem code accepted is open', $c === 200 && ($v['open_problem']['response_type'] ?? '') === 'scalar' && ($v['conversation'][0]['role'] ?? '') === 'tutor', "HTTP $c");
    $p = $v['open_problem'] ?? ['problem_id' => null, 'problem_version' => null];
    [$c, $v2] = req('POST', "$base/api.php?action=event", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => $v['revision'] ?? 0, 'action' => 'STUCK', 'problem_id' => $p['problem_id'], 'problem_version' => $p['problem_version'], 'payload' => ['choice' => 'HINT']], ["X-CSRF-Token: $csrf"], $jar);
    $check("I'm stuck records help before the hint", $c === 200 && ($v2['open_problem']['with_help'] ?? false) === true, "HTTP $c");
    [$c, $v3] = req('POST', "$base/api.php?action=event", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => $v2['revision'] ?? 0, 'action' => 'END_SESSION', 'problem_id' => null, 'problem_version' => null, 'payload' => new stdClass()], ["X-CSRF-Token: $csrf"], $jar);
    $check('end session', $c === 200 && ($v3['status'] ?? '') === 'CLOSED', "HTTP $c");
    [$c, $r] = req('GET', "$base/api.php?action=report", null, [], $jar);
    $check('report is labelled mock and written by code', $c === 200 && str_starts_with($r['provider_label'] ?? '', 'MOCK') && ($r['report']['architecture_version'] ?? '') === '1.5', "HTTP $c");
    [$c] = req('POST', "$base/api.php?action=event", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => 0, 'action' => 'END_SESSION', 'problem_id' => null, 'problem_version' => null, 'payload' => new stdClass()], [], $jar);
    $check('mutation without CSRF refused', $c === 403, "HTTP $c");
    return $headers;
}

/** ZIP entry names and contents, with ZipArchive or (where the zip extension is absent) PharData. @return array<string,string> */
function zipEntries(string $zipPath): array
{
    $out = [];
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return [];
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $out[(string) $zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        return $out;
    }
    $prefix = 'phar://' . realpath($zipPath) . '/';
    foreach (new RecursiveIteratorIterator(new PharData($zipPath)) as $file) {
        $out[substr($file->getPathname(), strlen($prefix))] = (string) file_get_contents($file->getPathname());
    }
    return $out;
}

function rmrfSmoke(string $p): void
{
    if (is_link($p) || is_file($p)) {
        unlink($p);
        return;
    }
    if (!is_dir($p)) {
        return;
    }
    foreach (scandir($p) as $f) {
        if ($f !== '.' && $f !== '..') {
            rmrfSmoke("$p/$f");
        }
    }
    rmdir($p);
}

function copyTreeSmoke(string $from, string $to): void
{
    if (is_file($from)) {
        @mkdir(dirname($to), 0755, true);
        copy($from, $to);
        return;
    }
    @mkdir($to, 0755, true);
    foreach (scandir($from) as $f) {
        if ($f !== '.' && $f !== '..') {
            copyTreeSmoke("$from/$f", "$to/$f");
        }
    }
}

// ---------------------------------------------------------------- nginx VPS layout, side by side with the beta
echo "== nginx VPS layout (dist/algebra-engine-ai-vps-upload.zip, dist/stage-ai-vps)\n";
$check('zip and staged tree exist', is_file($vpsZip) && is_dir("$vpsStage/" . PUB) && is_dir("$vpsStage/" . PRIV));
if (is_file($vpsZip) && is_dir($vpsStage)) {
    $entries = zipEntries($vpsZip);
    $names = array_keys($entries);
    sort($names);
    $top = array_values(array_unique(array_map(fn ($n) => explode('/', $n)[0], $names)));
    sort($top);
    $check('zip has exactly two top-level folders', $top === [PUB, PRIV], implode(', ', $top));
    $public = array_values(array_filter($names, fn ($n) => str_starts_with($n, PUB . '/')));
    $outside = array_values(array_filter($public, fn ($n) => !preg_match('#^' . PUB . '/(index\.html|api\.php|assets/[^/]+)$#', $n)));
    $check(PUB . '/ holds only index.html, api.php and assets/', $outside === [] && in_array(PUB . '/index.html', $public, true)
        && in_array(PUB . '/api.php', $public, true) && in_array(PUB . '/assets/app.js', $public, true), $outside === [] ? count($public) . ' files' : implode(', ', $outside));
    $dotted = array_values(array_filter($names, fn ($n) => preg_match('#(^|/)\.#', $n) === 1));
    $check('no dotfiles (no .htaccess) anywhere in the zip', $dotted === [], implode(', ', array_slice($dotted, 0, 5)));
    $leaks = [];
    foreach ($public as $n) {
        if (preg_match('#\.(php|sql|sqlite|json|md|lock)$#', $n) && $n !== PUB . '/api.php') {
            $leaks[] = $n;
        }
        if (preg_match('#(config|bootstrap|composer|vendor|src|resources|migrations|var)(/|\.|$)#', substr($n, strlen(PUB . '/')))) {
            $leaks[] = $n;
        }
        foreach (['provider_api_key', 'answer_spec', 'ACE_BOUND', 'algebra-map'] as $needle) {
            if (str_contains($entries[$n], $needle)) {
                $leaks[] = "$n contains $needle";
            }
        }
    }
    $check('no private file or private content under ' . PUB . '/', $leaks === [], implode(', ', $leaks));
    $check("api.php default ALGEBRA_PRIVATE_DIR is /var/www/algebra-engine-ai-private (never the beta's)",
        str_contains($entries[PUB . '/api.php'] ?? '', "const ALGEBRA_PRIVATE_DIR = '/var/www/algebra-engine-ai-private';"));
    $example = $entries[PRIV . '/config.example.php'] ?? '';
    $check("config.example.php sets base_path '/educational-engines/algebra-engine-ai/' and its own db_path",
        preg_match("#^\s*'base_path' => '/educational-engines/algebra-engine-ai/',#m", $example) === 1
        && preg_match("#^\s*'db_path' => '/var/www/algebra-engine-ai-private/var/algebra\.sqlite',#m", $example) === 1
        && !str_contains($example, "/var/www/algebra-engine-private"));
    $staged = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vpsStage, FilesystemIterator::SKIP_DOTS)) as $f) {
        $staged[] = substr($f->getPathname(), strlen($vpsStage) + 1);
    }
    sort($staged);
    $same = $staged === $names;
    foreach ($same ? $names : [] as $n) {
        if ($entries[$n] !== file_get_contents("$vpsStage/$n")) {
            $same = false;
            break;
        }
    }
    $check('zip matches the staged tree that is served below', $same, count($names) . ' entries');

    // Serve it as nginx would: the web root contains educational-engines/algebra-engine-ai/; the private folder is elsewhere.
    $tmp = sys_get_temp_dir() . '/ae_vps_' . bin2hex(random_bytes(4));
    mkdir($tmp);
    copyTreeSmoke("$vpsStage/" . PUB, "$tmp/www/educational-engines/" . PUB);
    file_put_contents("$tmp/config.php", "<?php return ['db_path' => '$tmp/db.sqlite', 'base_path' => '/educational-engines/algebra-engine-ai/'];");
    [$proc, $port] = serve("$tmp/www", ['ALGEBRA_CONFIG' => "$tmp/config.php", 'ALGEBRA_PRIVATE_DIR' => "$vpsStage/" . PRIV]);
    $jar = "$tmp/cookies";
    try {
        $headers = flow("http://127.0.0.1:$port/educational-engines/" . PUB, $jar, $check, 'bootstrap via the bundled api.php finds ' . PRIV . ' outside the web root');
        $cookie = array_values(array_filter($headers, fn ($h) => stripos($h, 'Set-Cookie: ae_sid=') === 0));
        $check('session cookie path is /educational-engines/algebra-engine-ai/ (separate from the beta)', $cookie !== [] && stripos($cookie[0], 'Path=/educational-engines/algebra-engine-ai/') !== false, $cookie[0] ?? 'no cookie');
        foreach (['/educational-engines/' . PUB . '/config.php', '/educational-engines/' . PUB . '/bootstrap.php', '/educational-engines/' . PUB . '/src/App.php',
            '/educational-engines/' . PUB . '/../' . PRIV . '/bootstrap.php', '/educational-engines/' . PRIV . '/config.example.php', '/' . PRIV . '/var/README.txt'] as $p) {
            [$c, , $raw] = req('GET', "http://127.0.0.1:$port$p", null, [], $jar);
            $check("private path not served: $p", $c === 404 && !str_contains($raw, '<?php'), "HTTP $c");
        }
    } finally {
        proc_terminate($proc);
        proc_close($proc);
        rmrfSmoke($tmp);
    }
}
// ---------------------------------------------------------------- upgrade safety
echo "== Upgrade safety\n";
$names = array_keys(is_file($vpsZip) ? zipEntries($vpsZip) : []);
$config = array_values(array_filter($names, fn ($n) => preg_match('#(^|/)config\.php$#', $n) === 1));
$var = array_values(array_filter($names, fn ($n) => preg_match('#^[^/]+/var/.#', $n) === 1));
$check(basename($vpsZip) . ': no config.php, nothing inside var/', $names !== [] && $config === [] && $var === [], implode(', ', array_merge($config, $var)) ?: count($names) . ' entries');
foreach (['004_progress.sql', '005_round6_call_detail.sql', '006_round7_cached_tokens.sql'] as $migration) {
    $check(basename($vpsZip) . ': carries migration ' . $migration, (bool) array_filter($names, fn ($n) => str_ends_with($n, '/migrations/' . $migration)));
}
$check(basename($vpsZip) . ': carries architecture v1.5 and no older architecture', (bool) array_filter($names, fn ($n) => str_ends_with($n, '/Algebra_Constraint_Architecture_v1.5.md'))
    && !array_filter($names, fn ($n) => preg_match('#/Algebra_Constraint_Architecture_v1\.[0-4]\.md$#', $n) === 1));
echo $fail === 0 ? "BUNDLE SMOKE PASSED\n" : "BUNDLE SMOKE FAILED ($fail)\n";
exit($fail === 0 ? 0 : 1);
