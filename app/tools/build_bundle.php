<?php
declare(strict_types=1);

/*
 * Builds the side-by-side VPS upload ZIP for the round 6 AI tutor (conductor
 * round 6 §11), with two top-level folders:
 *   algebra-engine-ai/          -> /var/www/html/educational-engines/algebra-engine-ai/  (public)
 *   algebra-engine-ai-private/  -> /var/www/algebra-engine-ai-private/                   (outside the web root)
 * It installs next to the live beta without touching it: its own folders, its
 * own database (inside its own private folder), its own cookie path. The
 * beta's ZIPs in dist/ are left as they are. Production dependencies are
 * installed without dev packages, so no Composer or shell is needed on the
 * host. No config.php and nothing inside var/. Nothing here deploys anything.
 *
 *   php app/tools/build_bundle.php [--out=dist]
 */

$app = dirname(__DIR__);
$repo = dirname($app);
$out = $repo . '/dist';
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--out=')) {
        $out = substr($a, 6);
    }
}
const PUBLIC_FOLDER = 'algebra-engine-ai';
const PRIVATE_FOLDER = 'algebra-engine-ai-private';
const VPS_PRIVATE_DIR = '/var/www/algebra-engine-ai-private';
const VPS_BASE_PATH = '/educational-engines/algebra-engine-ai/';
$stage = $out . '/stage-ai-vps';
$zipPath = $out . '/algebra-engine-ai-vps-upload.zip';

function rmrf(string $p): void
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
            rmrf("$p/$f");
        }
    }
    rmdir($p);
}

function copyTree(string $from, string $to, array $skip = []): void
{
    if (is_file($from)) {
        @mkdir(dirname($to), 0755, true);
        copy($from, $to);
        return;
    }
    @mkdir($to, 0755, true);
    foreach (scandir($from) as $f) {
        if ($f === '.' || $f === '..' || in_array($f, $skip, true)) {
            continue;
        }
        copyTree("$from/$f", "$to/$f", $skip);
    }
}

function run(string $cmd): void
{
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Command failed: $cmd\n");
        exit(1);
    }
}

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/** Zip a staged tree with stable ordering. */
function zipTree(string $stage, string $zipPath): int
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("Cannot create $zipPath");
    }
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = substr($file->getPathname(), strlen($stage) + 1);
    }
    sort($files);
    foreach ($files as $rel) {
        $zip->addFile("$stage/$rel", $rel);
    }
    $zip->close();
    return count($files);
}

rmrf($stage);
@unlink($zipPath);
$priv = $stage . '/' . PRIVATE_FOLDER;
$pub = $stage . '/' . PUBLIC_FOLDER;

// Private tree.
copyTree("$app/private/src", "$priv/src");
copyTree("$app/private/resources", "$priv/resources");
copyTree("$app/private/migrations", "$priv/migrations");
copyTree("$app/private/bin", "$priv/bin");
copy("$app/private/bootstrap.php", "$priv/bootstrap.php");

// Production dependencies only.
$composer = json_decode((string) file_get_contents("$app/composer.json"), true);
unset($composer['require-dev'], $composer['autoload-dev']);
$composer['autoload'] = ['psr-4' => ['AlgebraEngine\\' => 'src/']];
$composer['config']['vendor-dir'] = 'vendor';
file_put_contents("$priv/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
copy("$app/composer.lock", "$priv/composer.lock");
run('cd ' . escapeshellarg($priv) . ' && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --quiet 2>&1');
rmrf("$priv/composer.lock");
// Dependencies' repository metadata (.git, .gitattributes, .github/, .editorconfig) is not needed at run time.
$dotted = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$priv/vendor", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
    if (str_starts_with($file->getFilename(), '.')) {
        $dotted[] = $file->getPathname();
    }
}
foreach ($dotted as $path) {
    rmrf($path); // a dotted folder may already be gone with its parent
}

// config.example.php for this install: its own cookie path and its own database.
$example = (string) file_get_contents("$app/private/config.example.php");
$example = str_replace("    // 'db_path' => '/home/ACCOUNT/algebra-private/var/algebra.sqlite',",
    "    'db_path' => '" . VPS_PRIVATE_DIR . "/var/algebra.sqlite',   // this install's own database (never the beta's)", $example, $replacedDb);
$example = str_replace("    // 'base_path' => '/algebra/',        // cookie path; defaults to the api.php folder",
    "    'base_path' => '" . VPS_BASE_PATH . "',   // cookie path: the public folder's URL path on this server", $example, $replacedBase);
if ($replacedDb !== 1 || $replacedBase !== 1) {
    fail('config.example.php: db_path or base_path line not found');
}
file_put_contents("$priv/config.example.php", $example);

// Public tree: index.html, api.php (pointing at this install's private folder) and assets/.
@mkdir("$pub/assets", 0755, true);
copy("$app/public/index.html", "$pub/index.html");
copyTree("$app/public/assets", "$pub/assets");
$api = (string) file_get_contents("$app/public/api.php");
$api = str_replace("const ALGEBRA_PRIVATE_DIR = __DIR__ . '/../../algebra-private';", "const ALGEBRA_PRIVATE_DIR = '" . VPS_PRIVATE_DIR . "';", $api, $replaced);
if ($replaced !== 1) {
    fail('api.php: ALGEBRA_PRIVATE_DIR line not found');
}
file_put_contents("$pub/api.php", $api);

// Guards: nothing private, dev-only or secret; the public folder holds only index.html, api.php and assets/; no dotfiles.
$forbidden = ['config.php', 'algebra.sqlite', 'phpunit', '.git'];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $file) {
    $rel = substr($file->getPathname(), strlen($stage) + 1);
    if (preg_match('#(^|/)\.#', $rel)) {
        fail("Refusing to bundle dotfile $rel");
    }
    if (preg_match('#^[^/]+/var/#', $rel)) {
        fail("Refusing to bundle $rel: nothing inside var/ may be in an upload bundle");
    }
    foreach ($forbidden as $f) {
        if (preg_match('#(^|/)' . preg_quote($f, '#') . '(/|$)#', $rel)) {
            fail("Refusing to bundle $rel");
        }
    }
    if (preg_match('#^' . PRIVATE_FOLDER . '/tests?/#', $rel)) {
        fail("Refusing to bundle $rel");
    }
    if (str_starts_with($rel, PUBLIC_FOLDER . '/') && !preg_match('#^' . PUBLIC_FOLDER . '/(index\.html|api\.php|assets/[^/]+)$#', $rel)) {
        fail("Refusing to place $rel in the public folder");
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pub, FilesystemIterator::SKIP_DOTS)) as $file) {
    $content = (string) file_get_contents($file->getPathname());
    foreach (['provider_api_key', 'answer_spec', 'ACE_BOUND', 'algebra-map'] as $needle) {
        if (str_contains($content, $needle)) {
            fail("Public file {$file->getFilename()} contains '$needle'");
        }
    }
}

$n = zipTree($stage, $zipPath);
echo "Wrote $zipPath ($n files, " . round(filesize($zipPath) / 1024) . " KiB)\n";
echo 'sha256 ' . hash_file('sha256', $zipPath) . "\n";
