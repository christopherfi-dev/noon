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
    ['Live provider validation', 'Rounds 7, 8 and 9 ran scripted sessions against DeepSeek (test-results/live/round7/, test-results/live/round8/, test-results/live/round9/); those are runs of scripted learners, not deployment validation and not evidence of learning. Unit and mock results are mock results.'],
    ['Review and correction tool', 'Minimum path only (round 9 §4.7): the owner runs app/tools/correct_attempt.php to append a CORRECTION event (help or verdict) to one attempt; the gradebook applies the latest correction per field and goals, the progress record and the stored report are recomputed, and the next request\'s ANSWERED_PROBLEM, GRADEBOOK, GOALS and slip state show the corrected values (round 10 §4.5); the teacher view lists each correction. There is no review screen; held attempts and verdict conflicts are found from the report and transcripts.'],
];
$heuristics = [
    ['Answers (round 6 §2.1)', 'Code computes every answer: from a recipe, from the AI\'s spec (linear_equation, expand, signed_arithmetic, numeric_step, and equation_step for practice: the equation after one step, round 9 §4.5) or from the slipped check problem for a retry. A proposal whose stated answer differs from code\'s, that has no unique solution, whose numbers are out of range (|n| ≤ spec_number_limit, answers ≤ spec_answer_limit, denominators ≤ spec_denominator_limit), or (equation_step) whose operand is zero for multiply or divide, is rejected. There is no rule against an answer that appears in the problem (round 9 §3.1). An equation_step entry is STEP_CORRECT (each side equal to the expected side as a polynomial, sides in either order), EQUIVALENT_NOT_STEP (same solution set, recorded not yet correct with that observation) or NOT_EQUIVALENT; an entry that is not an equation is a request to rewrite it. The AI never states a verdict; text that contradicts code\'s verdict is logged (round 7 §4), never changes the verdict, and is shown unchanged after code\'s own result line.'],
    ['Check problems (round 6 §2.3, §4.2, §5; round 7 §5, §6; round 8 §3; round 9 §3, §4.3; round 10 §4.2, §4.3)', 'A check problem must match a recipe of its skill (the recipes used in reverse: exact structure, then the recipe\'s own validate()), use a number set not used in this session, and is worded by code. A problem or envelope that still fails after one repair holds the turn; nothing is substituted (round 9 §3.1). Prepared problems prefer parameters whose answer is not among the problem\'s numbers and accept the overlap when none fit. Choice check problems come only from the contrast recipes. The card reads "Check question: on your own". HEURISTIC audit before the card, logged as signals only (round 9 §3.2): hint, method, strategy or reminder phrases, neutral invitations, numbers shared with the check, and the check\'s answer with where it may come from. Help during an open check (round 9 §3.3, §4.3; round 10 §4.2–§4.4): only a reply whose move is HELP or HINT records help, before the reply is shown; the check becomes practice, the run starts again when the help is recorded, and the check\'s later answer counts neither for nor against. Such a reply may carry a practice problem, shown after it; a helped problem still open when a new problem is posed waits as the original (ORIGINAL_PROBLEM). Only an unanswered check without help blocks a new check. The original closes when it is answered, completed through connected equation steps (ORIGINAL_COMPLETED_WITH_HELP, no goal credit), set aside by an accepted fresh check on the target skill (RETURN_FRESH, ORIGINAL_SET_ASIDE) or by a newer helped problem, or when the session ends; practice never sets it aside, and skipping, "I haven\'t learned this yet" and a topic change leave it waiting. The context carries TARGET and ROUTE (DETOUR, RETURN_ORIGINAL, RETURN_TO_SKILL, RETURN_FRESH, ORIGINAL_SET_ASIDE, ORIGINAL_COMPLETED_WITH_HELP); the teacher view reports the route as evidence, not a score. A reply with no help move whose text contains the check\'s expression, a number from it or its answer, and a reply whose move is ANSWER_QUESTION or EXPLAIN, are logged as signals; nothing is held. Only a verdict conflict raised in the envelope holds an attempt.'],
    ['Reading an entry (round 6 §4.1; round 7 §7)', 'HEURISTIC. Code reads standard notation itself, a number written in words (up to 999), and "3 = x" as x = 3. Every other entry (words, "×", "times", a letter standing alone between spaces, or anything code cannot read) is read by the AI. Code checks the reading: only numbers the student wrote (digits or number words), only the problem\'s variables the student wrote, at most 80 characters, and the drop check: the reading must re-express one stretch of the entry token for token, and nothing joined to it by an operation sign or word (plus, minus, times, divided by, over, negative), implicit multiplication or grouping may be left out; a change of grouping or operand order is rejected. Content joined only by "=" or a relation word may be left out (declared scope). When the reading fails, or plausible readings would be scored differently, nothing is graded and the student sees the AI\'s clarifying question. The shown result says "Read as …".'],
    ['Slips (round 6 §4.4, §5.5; round 9 §4.4)', 'Code decides SLIP.eligible_for_pause. Expansions: every term except the constant matches, and the constant is a times-table neighbour of the product a × b (a × (b ± 1) or (a ± 1) × b) or differs from it in exactly one digit by exactly 1, and is none of a + b, b, a or −(a × b): for 7(x + 5), 7x + 25 and 7x + 30 are eligible, 7x + 95, 7x + 12 and 7x − 35 are not; a product with a factor of 1 never is. Signed multiplication and division: the right sign and a times-table neighbour of the right size. A bare value of x is never eligible; the off-by-one signatures stay observations. The detail sent to the tutor states what the answer did ("The other terms match; the constant 25 is a near miss of 7 × 5 = 35."). After an eligible slip the tutor may ask one micro-question (code checks it) and offer a retry; the first slip in a run pauses the count, a second resets it; the slipped answer and the retry never count.'],
    ['Text guards (round 6 §2.6; round 7 §4; round 9 §3.2, §4.6; round 10 §4.8)', 'HEURISTIC DETECTORS; every one logs a signal and none blocks, holds or changes a count. Every learner-visible AI text is checked for the answer to an open or newly posed problem (as a number, a standalone number word, an equivalent polynomial span, a standalone option letter or the key option\'s text; "one" as a determiner (including "one to try", "one more" and "one of"), "two-step", digits inside codes such as Q07 and letters inside a formula such as a(b + c) are not read as answers), internal vocabulary (expected answer, answer key, host, verdict, criterion and the like), skill codes (Q07, E06), a mastery or goal claim (including "the goal for … is met" and "the … goal is met"), a claim that work was done with or without help (not flagged in the last sentence before a check card), and a contradiction of code\'s verdict. Two counters log only: RESOLVE_AFTER_CORRECT (correct-answer feedback with an equation in the text on the way from the problem to its answer, or for linear equations a digit value of one of its sub-expressions at the solution, other than the problem, the student\'s answer and the student\'s numbers; substitution checks such as "4·10 + 6 = 46 checks out" are left out) and VALUE_QUESTION_IN_TEXT (a question sentence with a value cue and some mathematics, or a request for an expression such as "what is … written as", while no problem is posed; questions that ask for an operation are left out). Each finding is recorded with the exact text, the guard and the problem and response it relates to; the student sees the text unchanged. Paraphrased answers are not detected. Only envelope and problem-acceptance failures are repaired (once); if the repair fails too, the turn is held with a retry.'],
    ['"I\'m stuck"', 'Two choices: a hint, or "I haven\'t learned this yet" (round 7 §6.4). Help is recorded before any AI call and restarts the run then; a later answer to that check counts neither for nor against (round 10 §4.4). Typed text is never classified as stuck: "idk" or "???" is a message to the tutor (round 7 §6.3); help is then recorded only if the tutor\'s reply carries a HELP or HINT move (round 9 §3.3).'],
    ['Error signatures', 'Deterministic matching of each recipe\'s known error patterns (e.g. the factor multiplied only the x). A match is recorded as an observation, never a confirmed cause; the AI sees it and chooses what to do.'],
    ['Calls per turn (round 6 §3)', 'One AI call per turn with the full context, built anew; two when an entry must be read first; one repair at most. The first turn of a session also makes the architecture-binding call. Reasoning per call (per_call): enabled at low effort for a reading, a wrong answer with no signature, or a free-text message about maths; disabled otherwise. Each call is logged with its purpose and reasoning setting.'],
    ['Progress codes (round UI v3, U10)', 'A code is created with the first recorded answer of a session and its record is updated in the same database transaction as every later commit. The record holds, per skill, a status (goal met, needed help, unresolved, not in the beta), a date and observed mistake-pattern codes, plus the route target, the tutor\'s stated next focus and the next skill; no answers, names or addresses. The server stores only the sha256 of a code. 12 characters from 31 symbols give about 59.4 bits. One live session per code; entering it on another device takes the session over. Lookups: one generic message, at most 10 failures per hour per browser session. Records are deleted progress_retention_days (default 90) after their last use. Results from earlier sessions never count toward this session\'s goals. Codes do not carry between the beta and this install (separate databases).'],
    ['Downloaded report (round UI v3, U9)', 'One HTML file (algebra-report-YYYY-MM-DD.html) with inline CSS, no scripts and no external requests; the four learner headings, the teacher details expanded, the audit list, the date and the progress code. Every sentence is written by code from the gradebook.'],
];

$ownerRules = [
    ['Help restarts the run when it is recorded (round 5.1, P2; round 9 §3.3; round 10 §4.4)', 'Help recorded on an unanswered check ("I\'m stuck", or a tutor reply with a HELP or HINT move during the check) restarts that skill\'s count of correct answers in a row at the moment it is recorded; the check\'s later answer counts neither for nor against, so there is no second reset. Help known only from a correction restarts the run at the answer. Round 6 records of an explanation or a typed stuck phrase count the same way.'],
    ['One slip may pause the count (round 6 §5.5)', 'The first code-confirmed slip in a run pauses the count instead of resetting it; goal wording "3 correct without help (one slip may pause the count)".'],
];

$counts = array_count_values(array_column($entries, 'runtime_status'));
$json = [
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'curriculum' => 'ACE-ALGEBRA1 1.1.0',
    'architecture' => '1.8',
    'counts' => $counts,
    'live_provider_smoke_test' => 'Scripted learners ran against DeepSeek in rounds 7, 8 and 9 (test-results/live/round7/, round8/, round9/); they are runs of scripted learners: not deployment validation, not teaching quality and not evidence of learning. Earlier live runs (rounds 3–5) used the retired state machine and say nothing about this tutor. Every test result in this matrix is from mock mode (simulated tutor).',
    'skills' => $entries,
    'deferred' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'behaviour' => $d[1]], $deferred),
    'other_gaps' => array_map(fn ($d) => ['item' => $d[0], 'status' => 'NOT IMPLEMENTED', 'note' => $d[1]], $otherGaps),
    'heuristics' => array_map(fn ($d) => ['item' => $d[0], 'note' => $d[1]], $heuristics),
    'owner_rule_overrides' => array_map(fn ($d) => ['item' => $d[0], 'note' => $d[1]], $ownerRules),
];
file_put_contents($app . '/capability-matrix.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$md = "# Capability matrix — Algebra Engine AI tutor (round 10)\n\n";
$md .= "Generated from the tested runtime registry (`app/private/src/Capability/CapabilityRegistry.php`) by `app/tools/gen_capability_matrix.php`. "
    . "Curriculum ACE-ALGEBRA1 1.1.0 (205 skills, all browsable); architecture 1.8.\n\n";
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
