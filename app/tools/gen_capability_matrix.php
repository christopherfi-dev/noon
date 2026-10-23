<?php
declare(strict_types=1);

/*
 * Generates CAPABILITY_MATRIX.md and capability-matrix.json from the tested
 * runtime registry (never from the curriculum's required-capability list).
 *   php app/tools/gen_capability_matrix.php
 */

require dirname(__DIR__) . '/private/vendor/autoload.php';

use AlgebraEngine\Capability\CapabilityRegistry;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Items\ItemFactory;

$app = dirname(__DIR__);
$curriculum = CurriculumRepository::fromFile($app . '/private/resources/curriculum/algebra-map.json');
$registry = new CapabilityRegistry($curriculum, new ItemFactory());
$entries = $registry->all();

$deferred = [
    ['Teacher task links (create_task / task endpoints, signed public settings)', 'Endpoints return 422 FEATURE_NOT_IN_BETA.'],
    ['"Help me find a starting point" orientation', 'start with entry_route UNSURE returns 422 FEATURE_NOT_IN_BETA.'],
    ['Free-text topic mapping', 'The learner picks a topic from the beta list (CHANGE_TOPIC takes a listed skill only); the tutor cannot add a topic.'],
    ['Resume of a closed session', 'A closed session cannot be reopened. A progress code starts a new session that continues from its record; RESUME is used only to retry a held turn inside a live session.'],
    ['Review and retention checks', 'Never posed; reports state that no retention evidence exists.'],
    ['New-form check questions outside Q07 and E06', 'Only Q07 and E06 have a new-form check pattern; the other eight goals cover the numeric part only.'],
    ['Graph, table and reasoning-rubric inputs and checkers', 'Not implemented; no beta skill uses them.'],
    ['All 195 skills outside the beta list', 'Browsable only; runtime_status UNAVAILABLE. The tutor may not pose problems there; a step back to one is listed in the report as "Practise with your teacher".'],
    ['Localisation', 'English only.'],
    ['Accounts', 'Account-free; browser-session ownership only.'],
];
$otherGaps = [
    ['Bundled math renderer (spec 01)', 'Not bundled. Math is shown as plain text with Unicode signs; no TeX is interpreted anywhere, so unsafe TeX cannot execute.'],
    ['AI-worded reports', 'Not used. Code writes the report from the gradebook; no AI text appears in it (round 6 §2.5).'],
    ['Solution-set, interval, rational-domain checkers (golden ROOT-*, DOMAIN-01, INTERVAL-01, GRAPH-01)', 'Not implemented; no beta skill needs them.'],
    ['MySQL storage adapter', 'Not implemented; PDO SQLite only (preflight fails clearly without it).'],
    ['Live provider run of the round 6 tutor', 'Stage 2 (conductor §10). Stage 1 is offline: every round 6 result is from the mock tutor.'],
];
$heuristics = [
    ['Answers (round 6 §2.1)', 'Code computes every answer: from a recipe, from the AI\'s spec (linear_equation, expand, signed_arithmetic, numeric_step) or from the slipped check problem for a retry. A proposal whose stated answer differs from code\'s, that has no unique solution, whose numbers are out of range (|n| ≤ spec_number_limit, answers ≤ spec_answer_limit, denominators ≤ spec_denominator_limit) or whose wording contains its own answer is rejected. The AI never states a verdict; text that contradicts code\'s verdict is rejected.'],
    ['Check problems (round 6 §2.3, §4.2, §5)', 'A check problem must match a recipe of its skill (the recipes used in reverse: exact structure, then the recipe\'s own validate()), use a number set not used in this session, and is worded by code. Choice check problems come only from the contrast recipes. The card reads "Check question: on your own". Text before it may not contain a number from the new problem or its answer; forward-looking phrases (next, try, remember, make sure, don\'t forget, when you) are not rejected but go on the audit list. While a check problem is open, a message is not sent to the AI: the app answers "Let\'s finish this check question first. Answer it, or tap I\'m stuck."'],
    ['Reading an entry (round 6 §4.1)', 'HEURISTIC. Code reads standard notation itself. Entries with words, "×", "times" or a letter standing alone between spaces ("4 x 4", "4 x x") are read by the AI; the reading may only use numbers the student wrote (as digits or number words) and the problem\'s variables the student wrote, at most 80 characters, and code then checks it. A rejected reading asks the student to rewrite the entry; nothing is recorded. The shown result says "Read as …".'],
    ['Slips (round 6 §4.4, §5.5)', 'A slip is confirmed only when a recipe\'s slip signature matches: the structure is right and one lower-level calculation went wrong (7x + 25 for 7(x + 5)). The tutor may then ask one micro-question (code checks it) and offer a retry; the first slip in a run pauses the count, a second resets it; the slipped answer and the retry never count. A slip the structure contradicts (9x + 8 for 9(x + 8)) is never confirmed.'],
    ['Text guards (round 6 §2.6)', 'HEURISTIC. Every learner-visible AI text is checked before it is shown: it may not contain the answer to any open problem (as a number, number word, equivalent polynomial span or the key option\'s text; "one" as a determiner is not read as 1), internal vocabulary (expected answer, answer key, host, verdict, criterion and the like), a mastery or goal claim, or a claim that work was done with or without help. Questions are allowed. Paraphrased answers are not detected. A failing text gets one repair; if that fails too, the turn uses a fixed fallback with a code line.'],
    ['Stuck entries', 'Typed stuck phrases ("idk", "i dont know", "dont know", "dunno", "no idea", "no clue", "not sure", "im stuck", "stuck", "help", "???") are the "I\'m stuck" choice: help is recorded before any text is generated, and an answer after it resets the run.'],
    ['Error signatures', 'Deterministic matching of each recipe\'s known error patterns (e.g. the factor multiplied only the x). A match is recorded as an observation, never a confirmed cause; the AI sees it and chooses what to do.'],
    ['Calls per turn (round 6 §3)', 'One AI call per turn with the full context, built anew; two when an entry must be read first; one repair at most. The first turn of a session also makes the architecture-binding call. Reasoning per call (per_call): enabled at low effort for a reading, a wrong answer with no signature, or a free-text message about maths; disabled otherwise. Each call is logged with its purpose and reasoning setting.'],
    ['Progress codes (round UI v3, U10)', 'A code is created with the first recorded answer of a session and its record is updated in the same database transaction as every later commit. The record holds, per skill, a status (goal met, needed help, unresolved, not in the beta), a date and observed mistake-pattern codes, plus the route target, the tutor\'s stated next focus and the next skill; no answers, names or addresses. The server stores only the sha256 of a code. 12 characters from 31 symbols give about 59.4 bits. One live session per code; entering it on another device takes the session over. Lookups: one generic message, at most 10 failures per hour per browser session. Records are deleted progress_retention_days (default 90) after their last use. Results from earlier sessions never count toward this session\'s goals. Codes do not carry between the beta and this install (separate databases).'],
    ['Downloaded report (round UI v3, U9)', 'One HTML file (algebra-report-YYYY-MM-DD.html) with inline CSS, no scripts and no external requests; the four learner headings, the teacher details expanded, the audit list, the date and the progress code. Every sentence is written by code from the gradebook.'],
];

$ownerRules = [
    ['Answer after help resets the run (round 5.1, P2)', 'A check answer given after a hint, an explanation or a stuck entry resets the count of correct answers in a row to 0, exactly as a wrong answer does.'],
    ['One slip may pause the count (round 6 §5.5)', 'The first code-confirmed slip in a run pauses the count instead of resetting it; goal wording "3 correct without help (one slip may pause the count)".'],
];

$counts = array_count_values(array_column($entries, 'runtime_status'));
$json = [
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'curriculum' => 'ACE-ALGEBRA1 1.1.0',
    'architecture' => '1.3',
    'counts' => $counts,
    'live_provider_smoke_test' => 'Round 6 Stage 1: no live run. The round 6 tutor has been run only against the mock tutor; the live runs come in Stage 2 (conductor §10). Earlier live runs (rounds 3–5: test-results/live/round5/, round4/, round3/) used the retired state machine; they confirmed provider compatibility and host gating of that design, not teaching quality, learning or deployment, and say nothing about the round 6 tutor. Every result in this matrix is from mock mode (simulated tutor).',
    'skills' => $entries,
    'deferred' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'behaviour' => $d[1]], $deferred),
    'other_gaps' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'note' => $d[1]], $otherGaps),
    'heuristics' => array_map(fn ($d) => ['item' => $d[0], 'note' => $d[1]], $heuristics),
    'owner_rule_overrides' => array_map(fn ($d) => ['item' => $d[0], 'note' => $d[1]], $ownerRules),
];
file_put_contents($app . '/capability-matrix.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$md = "# Capability matrix — Algebra Engine AI tutor (round 6, Stage 1)\n\n";
$md .= "Generated from the tested runtime registry (`app/private/src/Capability/CapabilityRegistry.php`) by `app/tools/gen_capability_matrix.php`. "
    . "Curriculum ACE-ALGEBRA1 1.1.0 (205 skills, all browsable); architecture 1.3.\n\n";
$md .= "Runtime status counts: AVAILABLE_SCORED " . ($counts['AVAILABLE_SCORED'] ?? 0) . ", PARTIAL_COMPONENTS " . ($counts['PARTIAL_COMPONENTS'] ?? 0)
    . ", UNAVAILABLE " . ($counts['UNAVAILABLE'] ?? 0) . ". No all-skills operational claim is made.\n\n";
$md .= "**Live provider smoke test:** " . $json['live_provider_smoke_test'] . "\n\n";
$md .= "## Beta skills\n\n| Skill | Title | Status | Check patterns (recipe, response type) | Checkers | Criterion | Remaining | Tests |\n|---|---|---|---|---|---|---|---|\n";
foreach ($entries as $e) {
    if ($e['runtime_status'] === 'UNAVAILABLE') {
        continue;
    }
    $crit = $e['runtime_status'] === 'AVAILABLE_SCORED'
        ? '3 check questions correct in a row without help, covering a solve or expansion, a choice question and a new form (one slip may pause the count; help resets it)'
        : '3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it)';
    $md .= '| ' . $e['skill_id'] . ' | ' . $e['title'] . ' | ' . $e['runtime_status'] . ' | ' . implode('<br>', $e['implemented_variants']) . ' | '
        . implode(', ', $e['checkers']) . ' | ' . $crit . ' | ' . implode(' ', $e['remaining']) . ' | ' . implode('<br>', array_map(fn ($t) => '`' . $t . '`', $e['test_references'])) . " |\n";
}
$md .= "\n## Heuristics and their limits\n\n| Item | Note |\n|---|---|\n";
foreach ($heuristics as [$a, $b]) {
    $md .= "| $a | $b |\n";
}
$md .= "\n## Owner rules\n\n| Rule | Note |\n|---|---|\n";
foreach ($ownerRules as [$a, $b]) {
    $md .= "| $a | $b |\n";
}
$md .= "\n## Deferred — NOT IMPLEMENTED\n\n| Item | Behaviour in this install |\n|---|---|\n";
foreach ($deferred as [$a, $b]) {
    $md .= "| $a | $b |\n";
}
$md .= "\n## Other gaps against the package — NOT IMPLEMENTED\n\n| Item | Note |\n|---|---|\n";
foreach ($otherGaps as [$a, $b]) {
    $md .= "| $a | $b |\n";
}
$md .= "\n## All 205 curriculum skills\n\n| Skill | Title | Role | Runtime status |\n|---|---|---|---|\n";
foreach ($entries as $e) {
    $md .= '| ' . $e['skill_id'] . ' | ' . $e['title'] . ' | ' . $e['curricular_role'] . ' | ' . $e['runtime_status'] . " |\n";
}
file_put_contents($app . '/CAPABILITY_MATRIX.md', $md);
echo "Wrote CAPABILITY_MATRIX.md and capability-matrix.json (" . count($entries) . " skills)\n";
