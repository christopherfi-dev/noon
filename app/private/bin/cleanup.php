<?php
declare(strict_types=1);

/*
 * Cron cleanup: deletes expired sessions (idle 24 h / absolute 7 days by
 * default) and expired counters. Example cron line (hourly):
 *   php /home/ACCOUNT/algebra-private/bin/cleanup.php
 */

$config = require dirname(__DIR__) . '/bootstrap.php';
$app = new AlgebraEngine\App($config);
$deleted = 0;
do {
    $n = $app->store->cleanupExpired($app->clock->now(), 200);
    $deleted += $n;
} while ($n === 200);
echo "Deleted $deleted expired session(s).\n";
