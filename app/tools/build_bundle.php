<?php
declare(strict_types=1);

/*
 * Builds the Bluehost upload ZIP with two trees:
 *   algebra-private/      -> place in the account home, OUTSIDE public_html
 *   public_html/algebra/  -> place under public_html
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

rmrf($stage);
@unlink($zipPath);
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

// Zip with stable ordering.
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
echo "Wrote $zipPath (" . count($files) . " files, " . round(filesize($zipPath) / 1024) . " KiB)\n";
