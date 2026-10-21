<?php
declare(strict_types=1);

/*
 * Installation preflight for the actual hosting plan (spec 05).
 *   php algebra-private/bin/preflight.php [--public=/path/to/public_html/algebra]
 * Uses only core PHP so it can report missing extensions instead of crashing.
 * Exit code 0 = no FAIL lines. Results are deployment checks for this host
 * only; they are not facts about any other account.
 */

$privateDir = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);
$publicDir = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--public=')) {
        $publicDir = substr($a, 9);
    }
}
$results = [];
$add = function (string $status, string $check, string $detail) use (&$results): void {
    $results[] = [$status, $check, $detail];
};

// PHP version
if (PHP_VERSION_ID < 80300) {
    $add('FAIL', 'PHP_VERSION', PHP_VERSION . ' found; PHP 8.3 or newer is required.');
} else {
    $add(PHP_VERSION_ID < 80400 ? 'WARN' : 'PASS', 'PHP_VERSION', PHP_VERSION . (PHP_VERSION_ID < 80400 ? ' works; a supported 8.4/8.5 release is preferred if the host offers it.' : ''));
}

// Extensions
$required = [
    'pdo' => 'PDO is required for storage.',
    'pdo_sqlite' => 'PDO SQLite is required. There is no file-based fallback; the app will refuse to run without it.',
    'json' => 'JSON is required.',
    'mbstring' => 'mbstring is required for text length limits.',
];
foreach ($required as $ext => $why) {
    extension_loaded($ext)
        ? $add('PASS', strtoupper($ext), 'loaded')
        : $add('FAIL', strtoupper($ext), 'MISSING. ' . $why);
}

// Configuration
$configFile = getenv('ALGEBRA_CONFIG') ?: $privateDir . '/config.php';
$config = [];
if (is_file($configFile)) {
    $loaded = include $configFile;
    $config = is_array($loaded) ? $loaded : [];
    $add('PASS', 'CONFIG', 'config.php found');
    $perms = fileperms($configFile) & 0777;
    if ($perms & 0007) {
        $add('WARN', 'CONFIG_PERMISSIONS', sprintf('config.php is world-accessible (%o); use chmod 600.', $perms));
    }
} else {
    $add('WARN', 'CONFIG', 'No config.php; defaults apply (mock provider). Copy config.example.php to config.php.');
}
$provider = $config['provider'] ?? 'mock';
if ($provider === 'openai_compatible') {
    foreach (['provider_base_url', 'provider_model', 'provider_api_key'] as $k) {
        trim((string) ($config[$k] ?? '')) === ''
            ? $add('FAIL', 'PROVIDER_CONFIG', "$k is empty but the live provider is selected.")
            : $add('PASS', 'PROVIDER_CONFIG', "$k set");
    }
    if (!str_starts_with((string) ($config['provider_base_url'] ?? ''), 'https://')) {
        $add('FAIL', 'PROVIDER_HTTPS', 'provider_base_url must use https://');
    }
    // Round 3 ruling 1: thinking is set explicitly; the provider default is never relied on.
    $thinking = (string) ($config['provider_thinking'] ?? '');
    $effort = (string) ($config['provider_reasoning_effort'] ?? '');
    if (!in_array($thinking, ['disabled', 'enabled'], true)) {
        $add('FAIL', 'THINKING', "provider_thinking must be 'disabled' or 'enabled'; the provider default is never relied on.");
    } elseif ($thinking === 'enabled' && !in_array($effort, ['low', 'high', 'max'], true)) {
        $add('FAIL', 'THINKING', "provider_reasoning_effort must be 'low', 'high' or 'max' when thinking is enabled.");
    } elseif ($thinking === 'disabled' && $effort !== '') {
        $add('FAIL', 'THINKING', "provider_reasoning_effort applies only when thinking is enabled; leave it empty.");
    } else {
        $add('PASS', 'THINKING', 'thinking ' . $thinking . ($thinking === 'enabled' ? ', reasoning_effort ' . $effort : '') . ', max_tokens ' . (int) ($config['provider_max_tokens'] ?? 4096));
    }
    extension_loaded('curl')
        ? $add('PASS', 'CURL', 'loaded (live provider calls use HTTPS with certificate verification)')
        : $add('FAIL', 'CURL', 'MISSING. cURL is required for the live provider.');
    extension_loaded('openssl') ? $add('PASS', 'OPENSSL', 'loaded') : $add('FAIL', 'OPENSSL', 'MISSING. Needed for HTTPS.');
} else {
    $add('PASS', 'PROVIDER', 'mock provider (no outbound calls). Live provider not configured.');
}

// Private resources
$expected = [
    'resources/architecture/Algebra_Constraint_Architecture_v1.2.md',
    'resources/curriculum/algebra-map.json',
    'resources/contracts/model-envelope.schema.json',
    'resources/contracts/workflow-policy.json',
    'migrations/001_init.sql',
    'migrations/002_model_call_usage.sql',
    'vendor/autoload.php',
];
foreach ($expected as $rel) {
    is_file($privateDir . '/' . $rel) ? $add('PASS', 'FILE', $rel) : $add('FAIL', 'FILE', $rel . ' is missing from the private folder.');
}
$arch = $privateDir . '/resources/architecture/Algebra_Constraint_Architecture_v1.2.md';
if (is_file($arch)) {
    $sha = hash_file('sha256', $arch);
    $sha === 'ef03667dabf7152be63009ec6c1e327a83340b4fff65eef303e3eeb326291390' // v1.2 after Conductor Round 2 rulings 2 and 3
        ? $add('PASS', 'ARCHITECTURE_HASH', 'v1.2 ' . $sha)
        : $add('FAIL', 'ARCHITECTURE_HASH', 'Architecture file differs from the reviewed v1.2 (' . $sha . ').');
}

// Storage
$dbPath = $config['db_path'] ?? $privateDir . '/var/algebra.sqlite';
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    @mkdir($dbDir, 0700, true);
}
is_dir($dbDir) && is_writable($dbDir)
    ? $add('PASS', 'STORAGE_DIR', 'writable: ' . $dbDir)
    : $add('FAIL', 'STORAGE_DIR', 'Not writable: ' . $dbDir);
if (extension_loaded('pdo_sqlite') && is_dir($dbDir) && is_writable($dbDir)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS preflight_probe (x INTEGER)');
        $pdo->exec('DROP TABLE preflight_probe');
        $add('PASS', 'SQLITE_OPEN', 'database opened and writable');
    } catch (Throwable $e) {
        $add('FAIL', 'SQLITE_OPEN', 'Could not open the SQLite database.');
    }
}

// Public folder must not hold private files.
if ($publicDir !== null) {
    $real = realpath($publicDir);
    if ($real === false) {
        $add('FAIL', 'PUBLIC_DIR', 'Public folder not found: ' . $publicDir);
    } else {
        $privateReal = realpath($privateDir);
        str_starts_with($privateReal . '/', $real . '/')
            ? $add('FAIL', 'LAYOUT', 'The private folder is inside the public folder. Move algebra-private outside public_html.')
            : $add('PASS', 'LAYOUT', 'private folder is outside the public folder');
        foreach (['config.php', 'bootstrap.php', 'algebra.sqlite', 'src', 'vendor', 'resources'] as $bad) {
            if (file_exists($real . '/' . $bad)) {
                $add('FAIL', 'PUBLIC_CONTENT', $bad . ' must not be in the public folder.');
            }
        }
    }
}

$fail = 0;
foreach ($results as [$status, $check, $detail]) {
    if ($status === 'FAIL') {
        $fail++;
    }
    echo str_pad($status, 5) . ' ' . str_pad($check, 20) . ' ' . $detail . PHP_EOL;
}
echo ($fail === 0 ? 'PREFLIGHT PASSED' : "PREFLIGHT FAILED ($fail problem(s)). Do not deploy until fixed.") . PHP_EOL;
exit($fail === 0 ? 0 : 1);
