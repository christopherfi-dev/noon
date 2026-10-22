<?php
declare(strict_types=1);

/*
 * Round UI v3 (U11): builds the upgrade test fixture with the PREVIOUS release.
 * Run it with a checkout of commit a1f9e16 (its own vendor folder), never with
 * this release's code:
 *   git worktree add --detach /tmp/rel-a1f9e16 a1f9e16
 *   cp -r app/private/vendor /tmp/rel-a1f9e16/app/private/vendor
 *   php app/tools/make_upgrade_fixture.php /tmp/rel-a1f9e16/app app/tests/fixtures/upgrade-a1f9e16
 * It starts a Q07 session through that release's API, leaves it live in guided
 * repair (mid-repair at E06), and saves the database and the browser's cookie.
 */

[$self, $oldApp, $out] = $argv + [null, null, null];
if ($oldApp === null || $out === null || !is_file("$oldApp/tests/Support/Harness.php")) {
    fwrite(STDERR, "usage: php make_upgrade_fixture.php <old-release app dir> <output dir>\n");
    exit(2);
}
require "$oldApp/tests/bootstrap.php";
require "$oldApp/tests/Support/Harness.php";

$h = new AlgebraEngine\Tests\Support\Harness([], null, null, null, null, 'upgrade-fixture');
$h->start('Q07');
$h->submit($h->wrong('DIST_X_ONLY'));
$h->submit($h->wrong('DIST_X_ONLY'));
$p = $h->active()['params'];
$h->submit($p['a'] . 'x+' . $p['b']);
if ($h->mode() !== 'GUIDED_REPAIR') {
    fwrite(STDERR, 'expected GUIDED_REPAIR, got ' . $h->mode() . "\n");
    exit(1);
}
$migrations = $h->app->store->db->all('SELECT version FROM schema_migrations ORDER BY version');
$meta = [
    'release' => 'a1f9e16',
    'built_by' => 'app/tools/make_upgrade_fixture.php',
    'php' => PHP_VERSION,
    'clock' => $h->clock->now()->format('Y-m-d\TH:i:s\Z'),
    'cookies' => $h->cookies,
    'csrf' => $h->csrf,
    'mode' => $h->mode(),
    'skill' => $h->skill(),
    'revision' => $h->view['revision'],
    'schema_migrations' => array_map('intval', array_column($migrations, 'version')),
];
$db = $h->dbPath;
unset($h); // close the release's connection before folding the WAL into the database file
gc_collect_cycles();
$pdo = new PDO('sqlite:' . $db);
$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
$pdo = null;
@mkdir($out, 0755, true);
copy($db, "$out/algebra.sqlite");
file_put_contents("$out/fixture.json", json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
foreach (['', '-wal', '-shm'] as $s) {
    @unlink($db . $s);
}
echo "Wrote $out (live session in {$meta['mode']} at {$meta['skill']}, revision {$meta['revision']}, migrations " . implode(',', $meta['schema_migrations']) . ")\n";
