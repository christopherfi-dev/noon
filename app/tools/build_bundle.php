<?php
declare(strict_types=1);

/*
 * Round 6 (§9): builds the side-by-side nginx VPS upload ZIP of the AI line,
 * dist/algebra-engine-ai-vps-upload.zip, with two top-level folders:
 *   algebra-engine-ai/          -> /var/www/html/educational-engines/algebra-engine-ai/  (public)
 *   algebra-engine-ai-private/  -> /var/www/algebra-engine-ai-private/                   (outside the web root)
 * Its base path is /educational-engines/algebra-engine-ai/ and its database lives in its own
 * private folder, so it cannot touch the beta at /educational-engines/algebra-engine/.
 * The Bluehost layout is still staged in dist/stage (the smoke test serves it) but not zipped,
 * and the beta's own ZIPs are not built on this branch: they are in tag beta-1.0.
 * Production dependencies are installed without dev packages so no Composer
 * or shell is needed on the host. Nothing here deploys anything.
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
$stage = $out . '/stage';
$vpsStage = $out . '/stage-ai-vps';
$vpsZipPath = $out . '/algebra-engine-ai-vps-upload.zip';
const VPS_PUBLIC = 'algebra-engine-ai';
const VPS_PRIVATE = 'algebra-engine-ai-private';
const VPS_PRIVATE_DIR = '/var/www/algebra-engine-ai-private';
const VPS_BASE_PATH = '/educational-engines/algebra-engine-ai/';

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

/** Zip a staged tree with stable ordering. */
function zipTree(string $stage, string $zipPath): int
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Cannot create $zipPath\n");
        exit(1);
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
rmrf($vpsStage);
@unlink($vpsZipPath);
$priv = "$stage/algebra-private";
$pub = "$stage/public_html/algebra";

// Private tree.
copyTree("$app/private/src", "$priv/src");
copyTree("$app/private/resources", "$priv/resources");
copyTree("$app/private/migrations", "$priv/migrations");
copyTree("$app/private/bin", "$priv/bin");
copy("$app/private/bootstrap.php", "$priv/bootstrap.php");
copy("$app/private/config.example.php", "$priv/config.example.php");
// Round UI v3 (U11): no config.php and nothing inside var/ in either bundle, so unpacking over a live
// install never replaces the server's configuration or its database. The app creates var/ when needed.
$deny = "# Defence in depth: this folder (and var/ inside it) must never be web-accessible.\nRequire all denied\n";
file_put_contents("$priv/.htaccess", $deny);

// Production dependencies only.
$composer = json_decode((string) file_get_contents("$app/composer.json"), true);
unset($composer['require-dev'], $composer['autoload-dev']);
$composer['autoload'] = ['psr-4' => ['AlgebraEngine\\' => 'src/']];
$composer['config']['vendor-dir'] = 'vendor';
file_put_contents("$priv/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
copy("$app/composer.lock", "$priv/composer.lock");
run('cd ' . escapeshellarg($priv) . ' && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --quiet 2>&1');
rmrf("$priv/composer.lock");
foreach (glob("$priv/vendor/*/*/.git") ?: [] as $gitDir) {
    rmrf($gitDir);
}

// Public tree.
copyTree("$app/public", $pub);

// Guard: nothing private or dev-only in the bundle.
$forbidden = ['config.php', 'algebra.sqlite', 'phpunit', 'tests', '.git'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $rel = substr($file->getPathname(), strlen($stage) + 1);
    if (preg_match('#^[^/]+/var/#', $rel)) {
        fwrite(STDERR, "Refusing to bundle $rel: nothing inside var/ may be in an upload bundle\n");
        exit(1);
    }
    foreach ($forbidden as $f) {
        if (preg_match('#(^|/)' . preg_quote($f, '#') . '(/|$)#', $rel) && !($f === 'tests' && str_starts_with($rel, 'algebra-private/vendor/'))) {
            fwrite(STDERR, "Refusing to bundle $rel\n");
            exit(1);
        }
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pub, FilesystemIterator::SKIP_DOTS)) as $file) {
    $content = (string) file_get_contents($file->getPathname());
    foreach (['provider_api_key', 'answer_spec', 'ACE_BOUND', 'algebra-map'] as $needle) {
        if (str_contains($content, $needle)) {
            fwrite(STDERR, "Public file {$file->getFilename()} contains '$needle'\n");
            exit(1);
        }
    }
}

echo "Staged the Bluehost layout in $stage (not zipped on this branch)\n";

// ------------------------------------------------------------------ nginx VPS bundle (round PHP80)
// Same code and dependencies; only the layout, the api.php default private path and the
// example base_path differ. nginx ignores .htaccess, so the VPS bundle carries no dotfiles.
$vpsPub = "$vpsStage/" . VPS_PUBLIC;
$vpsPriv = "$vpsStage/" . VPS_PRIVATE;
copyTree($priv, $vpsPriv, ['.htaccess']);
// Dependencies' repository metadata (.gitattributes, .github/, .gitignore, .editorconfig) is not needed at run time.
$dotted = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$vpsPriv/vendor", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
    if (str_starts_with($file->getFilename(), '.')) {
        $dotted[] = $file->getPathname();
    }
}
foreach ($dotted as $path) {
    rmrf($path); // a dotted folder may already be gone with its parent
}
$example = (string) file_get_contents("$vpsPriv/config.example.php");
$example = str_replace("    // 'base_path' => '/algebra/',        // cookie path; defaults to the api.php folder",
    "    'base_path' => '" . VPS_BASE_PATH . "',   // cookie path: the public folder's URL path on this server", $example, $replaced);
if ($replaced !== 1) {
    fwrite(STDERR, "config.example.php: base_path line not found\n");
    exit(1);
}
file_put_contents("$vpsPriv/config.example.php", $example);
@mkdir($vpsPub . '/assets', 0755, true);
copy("$app/public/index.html", "$vpsPub/index.html");
copyTree("$app/public/assets", "$vpsPub/assets");
$api = (string) file_get_contents("$app/public/api.php");
$api = str_replace("const ALGEBRA_PRIVATE_DIR = __DIR__ . '/../../algebra-private';", "const ALGEBRA_PRIVATE_DIR = '" . VPS_PRIVATE_DIR . "';", $api, $replaced);
if ($replaced !== 1) {
    fwrite(STDERR, "api.php: ALGEBRA_PRIVATE_DIR line not found\n");
    exit(1);
}
file_put_contents("$vpsPub/api.php", $api);
// Guard: the public folder holds only index.html, api.php and assets/, and no dotfiles anywhere.
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vpsStage, FilesystemIterator::SKIP_DOTS)) as $file) {
    $rel = substr($file->getPathname(), strlen($vpsStage) + 1);
    if (preg_match('#(^|/)\.#', $rel)) {
        fwrite(STDERR, "Refusing to bundle dotfile $rel\n");
        exit(1);
    }
    if (preg_match('#(^|/)config\.php$#', $rel) || preg_match('#^[^/]+/var/#', $rel)) {
        fwrite(STDERR, "Refusing to bundle $rel: no config.php and nothing inside var/\n");
        exit(1);
    }
    if (str_starts_with($rel, VPS_PUBLIC . '/') && !preg_match('#^' . VPS_PUBLIC . '/(index\.html|api\.php|assets/[^/]+)$#', $rel)) {
        fwrite(STDERR, "Refusing to place $rel in the public folder\n");
        exit(1);
    }
}
$n = zipTree($vpsStage, $vpsZipPath);
echo "Wrote $vpsZipPath ($n files, " . round(filesize($vpsZipPath) / 1024) . " KiB)\n";
