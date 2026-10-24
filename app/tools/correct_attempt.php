<?php
declare(strict_types=1);

/*
 * Round 9 §4.7: the owner's correction path. Private CLI tool; not part of
 * the web app or the upload bundle.
 *
 *   php app/tools/correct_attempt.php --session=ID --list
 *   php app/tools/correct_attempt.php --session=ID --attempt=EVENT_ID --field=help --value=with_help|without_help --reason="..."
 *   php app/tools/correct_attempt.php --session=ID --attempt=EVENT_ID --field=verdict --value=CORRECT|INCORRECT|PARTIAL --reason="..."
 *
 * The database is the one the configuration names: ALGEBRA_CONFIG, else
 * app/private/config.php, as the app's bootstrap does.
 *
 * A correction appends one CORRECTION event (session, attempt, field, new
 * value, reason); it never edits or deletes an earlier event. The gradebook
 * applies the latest correction of each field of an attempt; goals, the
 * progress record and the stored report are recomputed in the same
 * transaction. --list prints the session's attempts with their current
 * (corrected) values and every correction.
 */

require dirname(__DIR__) . '/private/vendor/autoload.php';

use AlgebraEngine\App;
use AlgebraEngine\Config;
use AlgebraEngine\Tutor\EngineError;
use AlgebraEngine\Tutor\Gradebook;

$opts = getopt('', ['session:', 'attempt:', 'field:', 'value:', 'reason:', 'list']);
$usage = "Usage: php correct_attempt.php --session=ID --list\n"
    . "       php correct_attempt.php --session=ID --attempt=EVENT_ID --field=help|verdict --value=VALUE --reason=TEXT\n"
    . "       help: with_help | without_help; verdict: CORRECT | INCORRECT | PARTIAL\n";
if (!isset($opts['session']) || (!isset($opts['list']) && (!isset($opts['attempt'], $opts['field'], $opts['value'], $opts['reason'])))) {
    fwrite(STDERR, $usage);
    exit(2);
}

$privateDir = dirname(__DIR__) . '/private';
$configFile = getenv('ALGEBRA_CONFIG') ?: $privateDir . '/config.php';
$settings = is_file($configFile) ? require $configFile : [];
$app = new App(Config::fromArray(is_array($settings) ? $settings : [], $privateDir));
$session = (string) $opts['session'];

if (isset($opts['list'])) {
    if ($app->store->session($session) === null) {
        fwrite(STDERR, "No session with that id.\n");
        exit(1);
    }
    $out = [];
    foreach (Gradebook::attempts($app->store->events($session)) as $a) {
        $out[] = [
            'attempt_id' => $a['attempt_id'], 'skill_id' => $a['skill_id'], 'purpose' => $a['purpose'], 'prompt' => $a['prompt'], 'entry' => $a['entry'],
            'verdict' => $a['verdict'], 'help' => $a['help'] === [] ? 'without_help' : 'with_help', 'held' => $a['held'],
            'corrections' => array_map(fn ($c) => ['field' => $c['field'], 'previous' => $c['previous'], 'value' => $c['value'], 'reason' => $c['reason'], 'at' => $c['at']], $a['corrections']),
        ];
    }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    exit(0);
}

try {
    $r = $app->engine->applyCorrection($session, (string) $opts['attempt'], (string) $opts['field'], (string) $opts['value'], (string) $opts['reason']);
} catch (EngineError $e) {
    fwrite(STDERR, $e->errorCode . ': ' . $e->getMessage() . "\n");
    exit(1);
}
echo json_encode([
    'correction_event_id' => $r['event_id'], 'revision' => $r['revision'], 'field' => $opts['field'], 'previous' => $r['previous'], 'value' => $opts['value'],
    'goals' => array_values(array_map(fn ($g) => ['skill_id' => $g['skill_id'], 'count' => $g['count'], 'met' => $g['met']], $r['goals'])),
    'report_rebuilt' => $r['report_rebuilt'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
