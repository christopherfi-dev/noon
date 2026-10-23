<?php
declare(strict_types=1);

/*
 * Browser-test oracle (test harness only, never deployed): reads the private
 * SQLite database to learn code's answer to the open problem so a scripted
 * learner can answer. The public API never exposes this.
 *   php oracle.php <db_path>
 */
$db = new PDO('sqlite:' . $argv[1]);
$row = $db->query('SELECT session_id, engine_json FROM sessions ORDER BY created_at DESC, rowid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo 'null';
    exit;
}
$eng = json_decode($row['engine_json'], true);
$o = $eng['open'];
$calls = (int) $db->query("SELECT COUNT(*) FROM model_calls WHERE session_id = " . $db->quote($row['session_id']))->fetchColumn();
$out = ['topic' => $eng['topic'], 'focus' => $eng['focus'], 'status' => $eng['status'], 'wrap_up' => (bool) ($eng['wrap_up'] ?? false), 'calls' => $calls, 'open' => null];
if ($o !== null) {
    $e = $o['answer']['expected'];
    $out['open'] = [
        'purpose' => $o['purpose'],
        'skill' => $o['skill_id'],
        'type' => $o['response_type'],
        'kind' => $o['answer']['kind'],
        'expected' => is_array($e) ? $e : (string) $e,
        'template' => $o['template_id'] ?? null,
        'params' => $o['params'] ?? null,
        'prompt' => $o['prompt'],
    ];
}
echo json_encode($out);
