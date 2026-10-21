<?php
declare(strict_types=1);

/*
 * Browser-test oracle (test harness only, never deployed): reads the private
 * SQLite database to learn the active item's key so a scripted learner can
 * answer. The public API never exposes this.
 *   php oracle.php <db_path>
 */
$db = new PDO('sqlite:' . $argv[1]);
$row = $db->query('SELECT snapshot_json, engine_json FROM sessions ORDER BY created_at DESC, rowid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo 'null';
    exit;
}
$snap = json_decode($row['snapshot_json'], true);
$eng = json_decode($row['engine_json'], true);
$a = $eng['active'];
$out = ['mode' => $snap['mode'], 'skill' => $snap['current_skill_id'], 'stack' => array_column($snap['return_stack'], 'parent_skill_id'), 'active' => null];
if ($a !== null) {
    $e = $a['answer_spec']['expected'];
    $out['active'] = [
        'type' => $a['response_type'],
        'expected' => is_array($e) ? $e : (string) $e,
        'template' => $a['template_id'],
        'variant' => $a['variant_id'],
        'role' => $a['role'],
        'params' => $a['params'],
        'signatures' => $a['objects']['signature_answers'] ?? null,
        'letters' => $a['objects']['option_letters'] ?? null,
    ];
}
echo json_encode($out);
