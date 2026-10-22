<?php
declare(strict_types=1);

/*
 * Builds the Bluehost upload ZIP with two trees:
 *   algebra-private/      -> place in the account home, OUTSIDE public_html
 *   public_html/algebra/  -> place under public_html
 * and (round PHP80) the nginx VPS upload ZIP with two top-level folders:
 *   algebra-engine/          -> /var/www/html/educational-engines/algebra-engine/  (public)
 *   algebra-engine-private/  -> /var/www/algebra-engine-private/                   (outside the web root)
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
$zipPath = $out . '/algebra-engine-bluehost-upload.zip';
$vpsStage = $out . '/stage-vps';
$vpsZipPath = $out . '/algebra-engine-vps-upload.zip';
const VPS_PRIVATE_DIR = '/var/www/algebra-engine-private';
const VPS_BASE_PATH = '/educational-engines/algebra-engine/';

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
@unlink($zipPath);
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
@mkdir("$priv/var", 0700, true);
file_put_contents("$priv/var/README.txt", "SQLite database location. Keep this folder outside public_html and writable only by your account.\n");
$deny = "# Defence in depth: this folder must never be web-accessible.\nRequire all denied\n";
file_put_contents("$priv/.htaccess", $deny);
file_put_contents("$priv/var/.htaccess", $deny);

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

$n = zipTree($stage, $zipPath);
echo "Wrote $zipPath ($n files, " . round(filesize($zipPath) / 1024) . " KiB)\n";

// ------------------------------------------------------------------ nginx VPS bundle (round PHP80)
// Same code and dependencies; only the layout, the api.php default private path and the
// example base_path differ. nginx ignores .htaccess, so the VPS bundle carries no dotfiles.
$vpsPub = "$vpsStage/algebra-engine";
$vpsPriv = "$vpsStage/algebra-engine-private";
copyTree($priv, $vpsPriv, ['.htaccess']);
@unlink("$vpsPriv/var/.htaccess");
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
file_put_contents("$vpsPriv/var/README.txt", "SQLite database location. Keep this folder outside the web root, owned by the PHP-FPM user (nginx) and not readable by others.\n");
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
    if (str_starts_with($rel, 'algebra-engine/') && !preg_match('#^algebra-engine/(index\.html|api\.php|assets/[^/]+)$#', $rel)) {
        fwrite(STDERR, "Refusing to place $rel in the public folder\n");
        exit(1);
    }
}
$n = zipTree($vpsStage, $vpsZipPath);
echo "Wrote $vpsZipPath ($n files, " . round(filesize($vpsZipPath) / 1024) . " KiB)\n";
