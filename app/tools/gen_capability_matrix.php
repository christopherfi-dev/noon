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
    ['"Help me find a starting point" orientation (START_ORIENTATION, recommendation)', 'start with entry_route UNSURE returns 422 FEATURE_NOT_IN_BETA.'],
    ['Free-text topic mapping (SUGGEST_TARGET / CONFIRM_TARGET)', 'Actions are not offered; the learner picks from the beta list.'],
    ['Resume across sessions (RESUME_SESSION of a closed session)', 'Returns 422 FEATURE_NOT_IN_BETA. RESUME_SESSION is used only to retry a held generation inside a live session.'],
    ['Review and retention checks (REVIEW_CHECK)', 'Never entered; reports state that no retention evidence exists.'],
    ['Transfer checks outside Q07 and E06 (TRANSFER_CHECK mode)', 'Q07/E06 transfer variants are part of their criterion; no separate transfer mode.'],
    ['Graph, table and reasoning-rubric inputs and checkers', 'Not implemented; no beta skill uses them.'],
    ['All 195 skills outside the beta list', 'Browsable only; runtime_status UNAVAILABLE; descent to them records "not available in beta".'],
    ['Localisation', 'English only.'],
    ['Accounts', 'Account-free; browser-session ownership only.'],
];
$otherGaps = [
    ['Bundled math renderer (spec 01)', 'Not bundled. Math is shown as plain text with Unicode signs; no TeX is interpreted anywhere, so unsafe TeX cannot execute.'],
    ['Model-worded REPORT envelopes', 'Not used. The report is built by the host from ledger events only.'],
    ['Solution-set, interval, rational-domain checkers (golden ROOT-*, DOMAIN-01, INTERVAL-01, GRAPH-01)', 'Not implemented; no beta skill needs them.'],
    ['MySQL storage adapter', 'Not implemented; PDO SQLite only (preflight fails clearly without it).'],
];
$heuristics = [
    ['Answer-disclosure guard (D1)', 'HEURISTIC. Rejects model text containing the active or a remaining guided step\'s answer as a standalone number, number word, equivalent polynomial span or the key option\'s text. The word "one" used as a determiner or pronoun ("one way", "on one side") is not read as the value 1 unless a value cue precedes it ("x is one"). Paraphrases (e.g. "dividing by 3" for the option "Divide by 3") are not detected.'],
    ['Internal vocabulary', 'Learner-facing model text that mentions the host or keys is rejected and repaired (word match on "host", "key", "keys").'],
    ['Guided-step skill tags', 'Each guided step names the skill it exercises (the repair skill or a declared prerequisite); being stuck at the smallest step checks that prerequisite directly (Round 2 ruling 8). The tags are authored per template, not inferred.'],
    ['One action per message', 'HEURISTIC. Model text may not contain "?"; the only question shown is the host-rendered item prompt.'],
    ['Error signatures', 'Deterministic value matching of known error patterns. A match is recorded as an observation, never a confirmed cause, and only selects the probe/descent branch.'],
];

$counts = array_count_values(array_column($entries, 'runtime_status'));
$json = [
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'curriculum' => 'ACE-ALGEBRA1 1.1.0',
    'architecture' => '1.2',
    'counts' => $counts,
    'live_provider_smoke_test' => 'Round 4 (2026-10-22): live confirmation with thinking disabled (the chosen setting) against DeepSeek deepseek-flash: the 8 round 3 sessions (5 B1, 3 B3) and the B11 messy-learner session twice; all 10 sessions completed. Results: test-results/live/round4/ and ROUND_4_REPORT.md. Earlier runs: test-results/live/round3/, test-results/live/00-smoke-and-model-check.md, test-results/08-live-smoke.txt. These runs use scripted learners: they confirm provider compatibility and host gating, not teaching quality, learning or deployment. Every other result in this matrix is from mock mode (simulated model).',
    'skills' => $entries,
    'deferred' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'behaviour' => $d[1]], $deferred),
    'other_gaps' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'note' => $d[1]], $otherGaps),
    'heuristics' => array_map(fn ($d) => ['item' => $d[0], 'note' => $d[1]], $heuristics),
];
file_put_contents($app . '/capability-matrix.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$md = "# Capability matrix — Algebra Engine beta\n\n";
$md .= "Generated from the tested runtime registry (`app/private/src/Capability/CapabilityRegistry.php`) by `app/tools/gen_capability_matrix.php`. "
    . "Curriculum ACE-ALGEBRA1 1.1.0 (205 skills, all browsable); architecture 1.2.\n\n";
$md .= "Runtime status counts: AVAILABLE_SCORED " . ($counts['AVAILABLE_SCORED'] ?? 0) . ", PARTIAL_COMPONENTS " . ($counts['PARTIAL_COMPONENTS'] ?? 0)
    . ", UNAVAILABLE " . ($counts['UNAVAILABLE'] ?? 0) . ". No all-skills operational claim is made.\n\n";
$md .= "**Live provider smoke test:** " . $json['live_provider_smoke_test'] . "\n\n";
$md .= "## Beta skills\n\n| Skill | Title | Status | Implemented variants (template, response type) | Checkers | Criterion | Remaining | Tests |\n|---|---|---|---|---|---|---|---|\n";
foreach ($entries as $e) {
    if ($e['runtime_status'] === 'UNAVAILABLE') {
        continue;
    }
    $crit = $e['runtime_status'] === 'AVAILABLE_SCORED'
        ? '3 consecutive independent correct covering constructed + contrast + transfer → IMMEDIATE_CRITERION_MET'
        : '3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only';
    $md .= '| ' . $e['skill_id'] . ' | ' . $e['title'] . ' | ' . $e['runtime_status'] . ' | ' . implode('<br>', $e['implemented_variants']) . ' | '
        . implode(', ', $e['checkers']) . ' | ' . $crit . ' | ' . implode(' ', $e['remaining']) . ' | ' . implode('<br>', array_map(fn ($t) => '`' . $t . '`', $e['test_references'])) . " |\n";
}
$md .= "\n## Heuristics and their limits\n\n| Item | Note |\n|---|---|\n";
foreach ($heuristics as [$a, $b]) {
    $md .= "| $a | $b |\n";
}
$md .= "\n## Deferred (addendum §5) — NOT IMPLEMENTED\n\n| Item | Behaviour in the beta |\n|---|---|\n";
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
