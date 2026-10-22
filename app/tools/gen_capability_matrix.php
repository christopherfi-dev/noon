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
    ['Bundled math renderer (spec 01)', 'Not bundled. Math is shown as plain text with Unicode signs; each equation or expression is kept on one line (round UI, U3); no TeX is interpreted anywhere, so unsafe TeX cannot execute.'],
    ['Model-worded REPORT envelopes', 'Not used. The report is built by the host from ledger events only.'],
    ['Solution-set, interval, rational-domain checkers (golden ROOT-*, DOMAIN-01, INTERVAL-01, GRAPH-01)', 'Not implemented; no beta skill needs them.'],
    ['MySQL storage adapter', 'Not implemented; PDO SQLite only (preflight fails clearly without it).'],
    ['Working box (round UI, U4)', 'Removed until a reader exists. Nothing read the "Working (optional)" box, so the page no longer shows it and the notices no longer mention working. The API still accepts an empty or missing working field; nothing else changes.'],
];
$heuristics = [
    ['Problem named with feedback (round UI, U1)', 'HEURISTIC. When guided repair opens after a wrong or partly right answer, the host line "On <problem> you answered <answer>." comes before the opening; the problem is the item\'s longest run of numbers, x, y, operators and parentheses (one with "=" first), read from the prompt the learner saw. A guided step that does not repeat its problem is shown under a "Problem:" line (the stored prompt is unchanged). In the browser, other results name the answered item the same way. Feedback that refers to an older item is not detected.'],
    ['Plain words in the summary page (round UI, U2)', 'The learner level (titles only) and the teacher level (titles with IDs in brackets) are generated in plain words. Stored record text shown in the teacher level is rewritten from a fixed list (declared prerequisite, descent, implicated, branch, transfer variant, retention, faded support, coverage, edge and status codes). Stored wording outside that list is not rewritten; SummaryWordingTest fails if a listed term or code reaches either level in its two sessions. The report record and the JSON download keep their field names, stored codes and skill IDs.'],
    ['Answer-disclosure guard (D1)', 'HEURISTIC. Rejects model text containing the active or a remaining guided step\'s answer as a standalone number, number word, equivalent polynomial span or the key option\'s text. The word "one" used as a determiner or pronoun ("one way", "on one side") is not read as the value 1 unless a value cue precedes it ("x is one"). Paraphrases (e.g. "dividing by 3" for the option "Divide by 3") are not detected. Not read as a choice: options named together as alternatives ("positive or negative"); the learner\'s own answer to a completed step (never the active step\'s); positive, negative, plus or minus (or a plural) directly followed by an operand noun ("the negative sign", "a negative divisor"; round 5 ruling 2).'],
    ['Internal vocabulary (R5-B)', 'Learner-facing model text containing expected value, expected answer, answer key, matched the key, guided step, host, assisted, verdict or criterion (whole words or phrases, any case, plurals included) is rejected and repaired; host lines and the session summary pass the same check (round 5.1: the summary says "with help", "the answer the app computed" and "goal met: 3 correct in a row without help"; report field names and stored codes are unchanged). Model text also may not say on your own, without help, independently, by yourself or no help (round 5.1 P3); host lines may. Other internal wording is not detected.'],
    ['Independent items (R5-A)', 'Every independent item (target, probe, prerequisite check, independent check, return) is shown with the host line "Here is one to try on your own." only. The model still proposes the item\'s parameters through the same gate; any learner text it writes with them is discarded unread. Model-written learner text remains in guided steps, hints and explanations.'],
    ['Stuck and off-task entries (R5-C)', 'Fixed lists, matched after normalising (lowercase, trimmed, spaces collapsed, apostrophes removed, trailing . ! ? stripped): stuck phrases and "?" give a smaller question in guided repair or the hint path on an independent item; asking for the answer adds a line naming what follows (round 5.1 P1: "…but here is a hint.", "…but here is a smaller step.", "…but let\'s look at it another way.", "…but let\'s check something that comes before this.", or "…but let\'s try a different problem." when a boundary follows); other words ending in "?" and number words zero to twenty get a host line; a readable answer with a trailing "?" and "X = 3" on an item in x are scored normally. Other phrasings keep the clarification for unreadable input. None is scored as incorrect.'],
    ['Guided-step skill tags', 'Each guided step names the skill it exercises (the repair skill or a declared prerequisite); being stuck at the smallest step checks that prerequisite directly (Round 2 ruling 8). The tags are authored per template, not inferred.'],
    ['One action per message', 'HEURISTIC. Model text may not contain "?"; the only question shown is the host-rendered item prompt.'],
    ['Error signatures', 'Deterministic value matching of known error patterns. A match is recorded as an observation, never a confirmed cause, and only selects the probe/descent branch.'],
];

$ownerRules = [
    ['Answer after a hint resets the run (round 5.1, P2)', 'An answer given after a hint, an explanation or a stuck entry resets the count of correct answers in a row to 0, exactly as a wrong answer does. Owner-rule override of the package\'s "assisted neither advances nor resets" behaviour. Partial, unreadable, reused or invalidated answers still neither advance nor reset.'],
    ['Diagnostic probe after help (round 5.1, P4)', 'A probe answered after a hint, an explanation or a stuck entry is recorded as "Answered after a hint; inconclusive." and one more fresh probe follows. Correct with no help: the earlier error was not reproduced, resume as before. Wrong, or answered after help: the breakdown is confirmed and descent follows the branch the original error implicated, or a boundary. At most two probes per diagnosis, skips included (was three).'],
];

$counts = array_count_values(array_column($entries, 'runtime_status'));
$json = [
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'curriculum' => 'ACE-ALGEBRA1 1.1.0',
    'architecture' => '1.2',
    'counts' => $counts,
    'live_provider_smoke_test' => 'Round 5 (2026-10-22): live confirmation with thinking disabled against DeepSeek deepseek-flash: the 8 standard sessions (5 B1, 3 B3) and the B11 messy-learner session twice; all 10 sessions completed, 0 holds, no independent item shown with model-written text, no internal vocabulary in learner-visible text. Results: test-results/live/round5/ and ROUND_5_REPORT.md. Earlier runs: test-results/live/round4/, test-results/live/round3/, test-results/live/00-smoke-and-model-check.md, test-results/08-live-smoke.txt. These runs use scripted learners: they confirm provider compatibility and host gating, not teaching quality, learning or deployment. Every other result in this matrix is from mock mode (simulated model).',
    'skills' => $entries,
    'deferred' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'behaviour' => $d[1]], $deferred),
    'other_gaps' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'note' => $d[1]], $otherGaps),
    'heuristics' => array_map(fn ($d) => ['item' => $d[0], 'note' => $d[1]], $heuristics),
    'owner_rule_overrides' => array_map(fn ($d) => ['item' => $d[0], 'note' => $d[1]], $ownerRules),
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
        ? '3 consecutive independent correct covering constructed + contrast + transfer → IMMEDIATE_CRITERION_MET (an answer after a hint resets the run)'
        : '3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only (an answer after a hint resets the run)';
    $md .= '| ' . $e['skill_id'] . ' | ' . $e['title'] . ' | ' . $e['runtime_status'] . ' | ' . implode('<br>', $e['implemented_variants']) . ' | '
        . implode(', ', $e['checkers']) . ' | ' . $crit . ' | ' . implode(' ', $e['remaining']) . ' | ' . implode('<br>', array_map(fn ($t) => '`' . $t . '`', $e['test_references'])) . " |\n";
}
$md .= "\n## Heuristics and their limits\n\n| Item | Note |\n|---|---|\n";
foreach ($heuristics as [$a, $b]) {
    $md .= "| $a | $b |\n";
}
$md .= "\n## Owner-rule overrides of the package\n\n| Rule | Note |\n|---|---|\n";
foreach ($ownerRules as [$a, $b]) {
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
