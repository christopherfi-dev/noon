# Round 10 report — architecture v1.8, the route back to the target, targeted and broad live runs

Branch `round10-v1-8`, created from `round9-v1-7` at `fcb2a00` and pushed. Instructions: `CONDUCTOR_ROUND_10.md` (saved at the repository root). Nothing was deployed; no earlier branch and not the `beta-1.0` tag was touched.

The architecture file was checked before anything else: `Algebra_Constraint_Architecture_v1.8.md` has sha256 `f2db75f4f4e4512f9299f987efb4fb3315ce73d051d83d30990592a055a79d98` and no trailing newline, as §2.1 says. It was saved byte-identical in both places and never edited.

Every result in "Test results" is from the mock tutor. The live sessions in §6 and §7 are scripted learners against DeepSeek: they show how this build behaves with that model, not deployment readiness and not learning.

## Corrections after review

The first version of this report (`2912b8a`) was reviewed against the v1.8 architecture and the saved calls, and corrected here. Nothing in code, tests, runs or transcripts changed; only this report did.

1. **Goal announcements.** The first version checked only whether a goal claim was true when made. v1.8 also says "Announce a goal as met only in the turn code reports it newly met". 62 of the 71 replies with a goal claim came on later turns (failure 2).
2. **Answer-leak flags.** The first version said most of part 2's 37 ANSWER_LEAK flags were disclosures and quoted "12 ÷ 6 is 2" as one. Classified one by one against the saved calls, 10 of the 43 flags in both parts are clear disclosures; that example restated a result the student had already given correctly (failure 4).
3. **False goal claims.** The request itself was ambiguous: the goal label "an expansion" followed a correct answer to an expansion of another kind (failure 1).
4. **The skill boundary.** The request already named the outside prerequisites, and v1.8 already has a rule for this; the first version's decision 4 suggested adding a field that exists (failure 9, decision 4).
5. **Questions in the tutor's text** answered by the learner and then judged by the tutor without a code check: added (failure 6).
6. **Two findings reframed.** The first version's failure 8 (questions asked before answering) and failure 9 (loops without a check) are moved to notes: help was recorded in line with the guidance given, and the loops are largely an effect of the scripted learners.
7. **The §4.2 change** (a waiting original no longer blocks a check) was not exercised live, and STUCK-DEEP does not show it ("Route records seen live").

## Commits

| Commit | What |
| --- | --- |
| `a4468a8` | §1–§5: v1.8 bound, the reversals and §4 code changes, `Round10Test`, runner learners and metrics, docs and capability matrix; both gates (PHP 8.3.6 and 8.0.30) |
| `36eeea0` | §6 part 1, arm A: four targeted sessions, `METRICS_PART1.md`, saved calls; runner options `--metrics-file` and `--metrics-scope` |
| `681cde6` | §7 part 2, arm A: twelve sessions, `METRICS_PART2.md`, `METRICS.md` over all 16, saved calls |
| `2912b8a` | `ROUND_10_REPORT.md` (first version) and the round 10 `BUILD_LOG.md` entries for the live runs and the report |
| (this commit) | `ROUND_10_REPORT.md` corrected after review (see "Corrections after review") |

## What changed, by section of `CONDUCTOR_ROUND_10.md`

### §0 Working method

Followed as written. Detectors log only; the two §4.8 detector changes are logging only. No example responses were added to any model-facing string. No cost work. The model-facing strings and context fields are listed below (§0.6). I stopped and asked the owner five questions before deciding (§0.7); they are listed under "Questions I stopped to ask". After the live runs nothing was changed in code or in any model-facing string.

### §1 Branch

`round10-v1-8` from `fcb2a00`, pushed before any change.

### §2 Files

- `architecture/Algebra_Constraint_Architecture_v1.8.md` and `app/private/resources/architecture/Algebra_Constraint_Architecture_v1.8.md`, byte-identical (hash above). The runtime copy of v1.7 is removed; v1.1–v1.7 stay in `architecture/`. The app loads v1.8 only (`App::ARCHITECTURE_FILE`; `HostTest::testHost13BindingModes` asserts the runtime folder holds only v1.8).
- Version 1.8 everywhere a version is checked: `Store::ARCHITECTURE_VERSION`, the binding detail in `TutorCoordinator`, `MockTutor`, `TutorReport` (version, `policy_version`, `architecture_version`), the `tutor-session`, `tutor-envelope` and `report` schemas, `private/bin/preflight.php` (hash and file names), `tools/bundle_smoke.php`, `tools/gen_capability_matrix.php`, `tools/manifest_check.sh` (v1.8 added to the ruled differences). The activation line is `ACE_BOUND: architecture=1.8 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
- A session bound to 1.7 is rebound on its next turn (`HostTest::testASessionBoundTo17IsReboundTo18`).
- `CHANGELOG_v1.8.md` appended verbatim to `architecture/CHANGELOG.md`, after one blank line (checked byte for byte).
- `architecture/HOST_REQUIREMENTS_v1.7.md` unchanged and still in force; never sent to the model.
- `CLAUDE.md` rewritten: it points to v1.8 and to §0 of `CONDUCTOR_ROUND_10.md`.
- The isolation test (`TutorLoopTest::testNoHostRequirementsChangelogClaudeOrConductorTextReachesAModelRequest`) checks the host requirements, the changelog, `CLAUDE.md` and every `CONDUCTOR_*.md`, now including `CONDUCTOR_ROUND_10.md`, against every model request of a scripted session.

### §3 Round 9 behaviours reversed

1. **A waiting original no longer blocks checks.** `ALLOWED.may_pose_check` is false only while an unanswered check with no help is open (`TutorEngine::reply()`: `$uncheckedOpen`). Tests: `Round10Test::testAFreshCheckIsAvailableWhileAHelpedProblemIsRecorded`; `CheatingTest` and `Round9Test::testHelpAndASmallerStepTogether` rewritten (`app/tests/RETIRED_TESTS.md`).
2. **A problem on another skill no longer drops the original.** `dropOriginal()` is gone; `present()` makes a helped open problem the waiting original and otherwise sets the open problem aside. Skip, "I haven't learned this yet" and a topic change leave the original waiting (owner's decision 1).
3. **The run restarts when help is recorded.** `Goals::compute(array $attempts, ?array $checkHelp)` with `Gradebook::checkHelp()`; every caller uses `Goals::fromEvents()` (§4.4).
4. **The model no longer writes `message_type`.** §4.1.
5. **The result lines change.** §4.7.
6. **The repair instruction changes.** §4.1.

### §4.1 The output contract

The three edits, word for word (`TutorPrompt`; tests `EngineUnitsTest::testTheOutputContractHoldsNoBehaviourRules`, `Round10Test::testMessageTypeIsSetByCode`, `Round10Test::testRepairsCarryTheNewSentenceAndMayRewordAChangedItem`):

1. `architecture_version is "1.8".`
2. The message_type line is deleted. `message_type` is removed from the envelope schema (properties and required), so it is no longer in the skeleton or in `OUTPUT_CONTRACT.allowed_values`. Code sets it on every accepted reply (`TutorCoordinator::check()`): QUESTION when an item is attached, FEEDBACK when not, CONTROL for a READING turn. It is logged in `MODEL_OUTPUT_ACCEPTED`. A reply that still carries `message_type` has it removed before validation and ignored (`TutorCoordinator::CODE_FIELDS`), like the identity fields.
3. `TutorPrompt::repair()`: "Return one corrected JSON envelope for the same turn. Fix exactly these errors, update any wording they affect, and change nothing else."

Every other sentence of the contract is unchanged.

### §4.2 Which open problem blocks a check

As §3.1. A helped problem still open and unanswered when the tutor poses a new problem becomes the waiting original (`TutorEngine::present()` → `wait()`), including when the tutor brings back the original while a newer helped problem is open (`returnOriginal()`).

### §4.3 The helped problem, the target, the route

- **`ORIGINAL_PROBLEM`** closes when it is answered (any answer; owner's decision 2), completed through connected steps, set aside by an accepted fresh check on the target skill, or at the end of the session (`PROBLEM_CLOSED` with reason `SESSION_ENDED` and `original: true`). One original waits at a time: a newer helped problem takes its place and the older is set aside (`ORIGINAL_SET_ASIDE`; owner's decision 3). Practice never sets it aside. A rejected check proposal changes nothing (acceptance happens before `present()`).
- **Setting aside** (`setAsideOriginal()`): `PROBLEM_CLOSED` with reason `SET_ASIDE` and `original_set_aside` (why), route entry `ORIGINAL_SET_ASIDE`, `TARGET.set_aside_original` set. No `HELP_GIVEN`, no goal change, no completion.
- **`TARGET`** `{skill_id, skill_name, goal_met, set_aside_original}`: the session's topic, its title, `goal_met` from GOALS.
- **`ROUTE`**: oldest first, entries `{kind, skill_id, problem_id, at}` (`route()`); each is also a `ROUTE` ledger event with the target skill. `routeOnPresent()` records `DETOUR` (a problem on a prerequisite of the target, moving from another skill), `RETURN_TO_SKILL` (the first problem on the target after a detour), `RETURN_FRESH` then `ORIGINAL_SET_ASIDE` (an accepted check on the target distinct from the waiting original); `returnOriginal()` records `RETURN_ORIGINAL`.
- **Completion through connected steps** (`advanceChain()`): a correct equation_step answer extends the chain when its starting equation is the original's equation or the previous link's result (sides compared as polynomials, in either order); when a link's result is x = the original's solution, `PROBLEM_CLOSED` `COMPLETED_WITH_HELP` and `ORIGINAL_COMPLETED_WITH_HELP`. No attempt is recorded for the original, so it earns no goal credit. A matching final answer from another chain, or any other arithmetic, never completes it.
- Tests: `Round10Test` cases 3–7.

### §4.4 When help restarts the run

`Gradebook::checkHelp()` lists help recorded on a check not yet answered (help on a problem whose attempt was corrected to "without help" is left out). `Goals::compute()` merges those events with the attempts in ledger order: the first help on a check resets that skill's run at that moment; that check's later answer counts neither for nor against; a held check is skipped as before, so help on a check that is later held also restarts the run at help time. Help known only from a correction restarts the run at the answer, as before. The slip pause and the required kinds are unchanged. Tests: `Round10Test::testTheResetLoophole`, `::testNoDoubleReset` (a calculation test on a constructed event record).

### §4.5 Corrections update the next request

`TutorEngine::applyCorrection()` updates the stored engine record (`correctedEngine()`): when the corrected attempt is the last answered problem, `eng['answered']` takes the corrected verdict and help; slip state built from that attempt is cleared when it is no longer an unhelped not-yet answer. `Store::saveSnapshot()` now also saves the engine. Tests: `Round10Test::testTheNextRequestShowsACorrectionOfTheLatestAttempt`, `::testACorrectionClearsSlipStateOfThatAttempt`.

### §4.6 What the tutor is told about goals and totals

- `CODE_RESULT.goal_newly_met`: null or `{skill_id, skill_name}`, computed in `answer()` before the model call from the goals before and after this answer; `GOAL_MET` is still written at commit.
- `GRADEBOOK.session_totals_by_skill` (renamed from `by_skill`); for checks `check_correct_no_recorded_help` and `check_correct_with_help` replace `check_correct`; the other fields are unchanged.
- Tests: `Round10Test::testGoalNewlyMetIsInTheRequestOnlyOnTheTurnTheGoalIsMet`, `::testTheNextRequestShowsACorrectionOfTheLatestAttempt`.

### §4.7 Result lines

`TutorEngine::resultLine()`: "On {problem} you answered {entry}." (unchanged); "On {problem}, read from your message: {reading}." for an answer read from a message (an AI reading, a capital X, number words); "On the step “{instruction}”, you wrote {entry}." or "…”, read from your message: {reading}." for equation_step, where {instruction} is the wording without "Write the equation you get." and without its own full stop (owner's decision 4). Test: `Round10Test::testTheThreeResultLineForms`.

### §4.8 Small items

1. "one" followed by "to", "more" or "of" is not the number 1 (`Numbers::oneAsDeterminer()`, `DisclosureGuard::oneAsDeterminer()`); logging only.
2. Goal-claim detector widened (`TextGuard::MASTERY`): "the goal for … is met" (also "has been met/reached/achieved/completed") and "the … goal is met"; logging only.
3. SIGN_ERROR_B observation: "matches moving the constant to the other side without changing its sign" (`GroupedLinear`).
4. A factor-of-1 product is never slip-eligible: unchanged.
5. Corrections during an open session: unchanged.

Test: `Round10Test::testTheSmallItems`.

### §4.9 Saving requests and responses

The runner (`app/tools/tutor_sessions.php`) wraps the live transport (`RecordingTransport`): for every model call (binding, reply, repair and reading) it writes one JSON file in `<out>/<session>/calls/NNN.json` with the turn, the URL, the full request body (model, messages, settings) and the raw response (status, error, body). Headers are never written, so the key is never saved; as a second guard any occurrence of the key is replaced before writing, and `reasoning_content` would be replaced by "[not stored]" (thinking was disabled, so none came back). Checked after the runs: the key string occurs in none of the saved files.

### §4.10 The report

`TutorReport::routeSummary()` builds, from the ledger, each detour (skill, target, problems, correct and not yet on the detour skill, whether and how work returned, target results afterwards up to the next detour), the originals (completed with help, or set aside and why), the returns, and the fresh evidence on the target without help after the first return. Report data carries it as `route`; the teacher view shows it in a "Route and return" section (owner-approved wording, decision 5), only when there is something to show, ending with "These are evidence, not a score." Host gaps 3d and 9c are re-reported below.

### §5 Targeted tests

`app/tests/Integration/Round10Test.php`, 17 tests for the 13 cases (mock tutor). Map: `app/ACCEPTANCE_SCENARIOS.md`. Rewritten round 9 tests: `app/tests/RETIRED_TESTS.md`. Both gates below.

## Model-facing strings and context fields added or changed (§0.6)

The sentences §4.1 specifies are used word for word and are not repeated here.

**Envelope schema, as the model sees it (skeleton and `OUTPUT_CONTRACT.allowed_values`)**

- Removed: `message_type` (with its values CONTROL, QUESTION, FEEDBACK, REPORT, HOLD).
- `architecture_version`: "exactly \"1.8\"".

**Context fields (descriptive names, no instructions)**

- New `TARGET`: `{skill_id, skill_name, goal_met, set_aside_original}`.
- New `ROUTE`: a list of `{kind, skill_id, problem_id, at}`; `kind` is one of `DETOUR`, `RETURN_ORIGINAL`, `RETURN_TO_SKILL`, `RETURN_FRESH`, `ORIGINAL_SET_ASIDE`, `ORIGINAL_COMPLETED_WITH_HELP`; `at` is an ISO 8601 UTC time.
- New `CODE_RESULT.goal_newly_met`: null or `{skill_id, skill_name}`.
- Renamed `GRADEBOOK.by_skill` → `GRADEBOOK.session_totals_by_skill`; `check_correct` replaced by `check_correct_no_recorded_help` and `check_correct_with_help`.
- `ALLOWED.may_pose_check`: same label, now false only while an unanswered check with no help is open.
- `ORIGINAL_PROBLEM`: same fields; it now stays set across skills, skips and topic changes.

**Repair messages (errors sent back in one repair request)**

- Changed: "for a READING turn return item null and a reading object" (was "for a READING turn return message_type CONTROL, item null and a reading object").
- Removed: "message_type must be QUESTION when item is present and FEEDBACK otherwise".

**Observation string (`CODE_RESULT.signatures[].observation`)**

- SIGN_ERROR_B: "matches moving the constant to the other side without changing its sign" (§4.8.3, as specified).

**Result lines (student-visible; they reach the model in `RECENT_CONVERSATION`)**

- "On {problem}, read from your message: {reading}." (replaces "you answered {raw}." / "Read as {reading}.")
- "On the step “{instruction}”, you wrote {entry}." and "On the step “{instruction}”, read from your message: {reading}."
- When a problem has no main expression, "Read from your message: {reading}." (my wording for that edge case; the problem wording always has one in practice).

**Not model-facing (ledger and teacher view), listed for completeness**

- `PROBLEM_CLOSED.original_set_aside`: "a fresh check question on the target skill was accepted"; "a newer helped problem became the original".
- `PROBLEM_CLOSED` note for `COMPLETED_WITH_HELP`: "Completed through connected equation steps; it earns no goal credit (round 10 §4.3)."
- Teacher view "Route and return": the owner-approved lines, with two differences from the approved text, both mine and both for the owner to confirm: every skill is named "{title} ({code})" where the approved text has a bare "{code}" (an earlier round UI rule, enforced by `ReportTest`, forbids bare skill IDs in the teacher view), and the set-aside line for decision 3 reads "The original problem “{prompt}” was set aside unfinished when help on another problem took its place."

## Questions I stopped to ask (§0.7), with the owner's answers

1. **Topic change, skip and "I haven't learned this yet" with a waiting original** (§4.3's list does not name them). Answer: only §4.3's list closes it; they leave the original waiting; skip and "not learned" apply only to the open problem; after a topic change the next accepted fresh check on the new target sets the original aside.
2. **A wrong answer to the returned original.** Answer: any answer closes it, right or wrong, recorded with help.
3. **Two helped problems and one `ORIGINAL_PROBLEM` slot.** Answer: the newer becomes the original; the older is set aside unfinished (`ORIGINAL_SET_ASIDE`), history and target kept.
4. **The equation_step result line's inner full stop.** Answer: drop it ("On the step “Subtract 6 from both sides of 3x + 6 = 15”, you wrote 3x = 9.").
5. **Teacher view wording for §4.10.** Answer: the proposed wording, a "Route and return" section in the teacher view only.

## Technical choices (§0.7)

1. A reply that still sends `message_type` is accepted; the field is removed before validation and code's value is used.
2. Only the first help on a check restarts the run; later help on the same check changes nothing (the check is already practice with help).
3. "I haven't learned this yet" on a check records `HELP_GIVEN` as before, so it restarts the run like other help.
4. A correction to "with help" restarts the run at that answer (as in round 9); a correction to "without help" removes the help-time restart for that check.
5. `DETOUR`: a problem on a skill that is a prerequisite of the target, directly or through other prerequisites in the curriculum's declared prerequisites, when the previous problem was on a different skill. Each further step back (E06 → N18) is another `DETOUR`.
6. `RETURN_TO_SKILL`: the first problem on the target after a detour, practice or check. An accepted check on the target distinct from the waiting original is `RETURN_FRESH` instead; bringing back the original is `RETURN_ORIGINAL`.
7. The target is the session's topic; a topic change starts the route again from the new topic.
8. "Distinct from the original" means a different problem fingerprint (template and parameters, or the spec's canonical form).
9. The connected chain uses correct equation_step answers only; an unconnected step does not break a chain in progress.
10. Slip state is cleared when the corrected attempt is the slipped one and it is no longer an unhelped not-yet answer.
11. In the report, each detour's "afterwards" counts target answers after the return up to the next detour; when two detours return together (E06 then N18, then back to Q07), both show the same "afterwards". The fresh-evidence line is shown even when no check was counted.
12. The teacher's route lines use "{title} ({code})" (see above).
13. Runner (`app/tools/tutor_sessions.php`): calls are recorded at the transport, so the request saved is exactly the body sent; mock runs save calls only with `--save-calls=1` (the gate's mock sessions do not); `--metrics-file` and `--metrics-scope=run` write a metrics file for one part of the run (`METRICS_PART1.md`, `METRICS_PART2.md`); `METRICS.md` covers all 16 sessions.
14. Runner, error catalogue (`class Catalogue`): candidate wrong entries are built from the problem's parameters and kept only when code's own checker (`AnswerCheck`, the recipe's `matchSignatures()`) finds them not yet correct and names a catalogue code, so every code the learner "used" is the code code recorded. Each pattern has a preference order; the n-th wrong answer to the same problem takes the n-th fitting error. A problem with no recipe (an AI spec that matches no template, or an equation_step) has no catalogue; the learner then gives the opposite sign or the answer off by one (or the step with its right side off by one), labelled `NO_RECIPE` in the transcripts. "The arithmetic slips" are ARITH_FINAL_PLUS, ARITH_FINAL_MINUS, ARITH_OFF_BY_ONE, PRODUCT_FACT and MAGNITUDE; the contrast-choice codes count as catalogue errors for choice questions.
15. Runner, boundary: the "edge" is a not-yet answer on one of the tutor's skills whose declared prerequisites all lie outside its ten skills (N18 and N03); "what the tutor said there" is the next three tutor texts. "Prerequisite" in the metrics means any skill other than the starting one.
16. Runner, learners: see "Learners" under the live runs.

## Host requirements (`architecture/HOST_REQUIREMENTS_v1.7.md`)

Each requirement, split into its parts, with where code meets it or that it is a gap. "Met" means implemented and covered by a named test. Rows changed this round are marked (round 10).

| # | Requirement (source section) | Status | Where |
| --- | --- | --- | --- |
| 1 | Opening paragraph: the host enforces the guarantees; a model-generated assertion of compliance is not an acceptance receipt | Met | Every envelope is checked by code after the call (`TutorCoordinator::run()`/`check()`); identity and (round 10) `message_type` are attached by code, not taken from the reply; the binding event says it acknowledges the contract and is not evidence of enforcement; goal, mastery and verification claims in `evidence_proposals` are rejected. Tests: `HostTest::testHost13BindingModes`, `::testHost06ForgedPromotionRejected`, `CheatingTest::testTheAiMarksAGoal`, `Round9Test::testAStaleReplyIsDiscarded`, `Round10Test::testMessageTypeIsSetByCode` |
| 2 | §5 opening: declare which checks are performed and their scope; "enforced" only when implemented and tested | Met | `app/CAPABILITY_MATRIX.md` and `capability-matrix.json`, updated this round (help restarts the run when recorded, the original/target/route, corrections in the next request, the §4.8 detector changes); `app/ACCEPTANCE_SCENARIOS.md` maps each round 10 item to its test. Test: `DeployCoverageTest::testCover01CapabilityMatrix` |
| 3a | §5.4: for every attempt, the problem, skill, practice or check, entry, reading note, scoped verdict and its status | Met | `ATTEMPT_RECORDED`, read by `Gradebook::attempts()` (now with its ledger position); status held or corrected. The test harness re-checks every recorded verdict after every request |
| 3b | §5.4: observed or reported help between presentation and answer | Met (round 10) | `HELP_GIVEN` from "I'm stuck" and from a HELP or HINT move during an open check; `Gradebook::checkHelp()` gives `Goals` the moment help was recorded. Tests: `Round10Test::testTheResetLoophole`, `::testNoDoubleReset`, `Round9Test::testHelpAndASmallerStepTogether`, `CheatingTest::testIAmStuckDuringACheckIsRecordedBeforeTheAiCall` |
| 3c | §5.4: unreported outside help cannot be certified absent | Partial | Nothing certifies absence of help, but the report's wording is "correct without help", not "without observed or reported help" (unchanged) |
| 3d | §5.4: the original target, any detours, whether the student returned, and the independent evidence on the original target | Met (round 10) | `TARGET` and `ROUTE` in the engine and the ledger (`ROUTE` events); `TutorReport::routeSummary()` gives each detour's length and outcome, whether and how work returned (practice, the original, a fresh check), the target results afterwards, originals completed with help or set aside, and the fresh evidence on the target without help after the return; report data `route`; teacher view "Route and return". Tests: `Round10Test::testSeveralPrerequisiteProblemsWrongThenSuccess`, `::testAStepBackKeepsTargetAndOriginalAndRecordsADetour`, `::testAnAcceptedFreshCheckSetsTheOriginalAside` |
| 3e | §5.4: corrections preserve the original record and recompute affected progress | Met (round 10) | `CORRECTION` appended by `tools/correct_attempt.php` → `TutorEngine::applyCorrection()`; the attempt event is unchanged; goals, progress, the stored report and now the stored engine record (last answered problem, slip state) are recomputed. Tests: `Round9Test::testCorrectionsAreTraceableAndNothingCountsTwice`, `Round10Test::testTheNextRequestShowsACorrectionOfTheLatestAttempt`, `::testACorrectionClearsSlipStateOfThatAttempt` |
| 4 | §5.7: revisions, idempotency, leases, no answer keys or internal data in anything the browser receives | Met | `TutorEngine::handle()`/`commit()`; `TutorProjection::FORBIDDEN_KEYS`, `scan()`. Tests: `HostTest::testHost01DuplicateSubmit`, `::testHost02IdempotencyConflict`, `::testHost03StaleTab`, `::testHost04LateLeaseDiscarded`, `::testHost08NoAnswerLeakage`, `::testHost10CrossSessionAccessDenied`, `::testHost11Csrf`; `KeyLeakTest` |
| 5 | §8 practice problems: wording matches the verified spec and does not reveal the answer; otherwise authoritative wording from the spec or a prepared problem | Met (second route) | Code renders every problem's wording from its verified spec; the AI's `prompt` is never shown. Tests: `ProblemInstrumentsTest::testPracticeWordingIsRenderedByCodeFromTheSpec`, `Round10Test::testRepairsCarryTheNewSentenceAndMayRewordAChangedItem` |
| 6a | §10 conflict: suspend the affected verdict and dependent progress claims; exclude the disputed attempt from success and failure counts pending review | Met | `ATTEMPT_HELD` (VERDICT_CONFLICT); `Goals::compute()` skips a held attempt; help recorded on that check restarted the run when it was recorded (round 10 §4.4); `GOAL_SUSPENDED`. Test: `CheatingTest::testAVerdictConflictHoldsTheAttempt` |
| 6b | §10 conflict: recheck the problem, its wording/spec correspondence, the reading and the checker result | Gap | No runtime recheck when a conflict is raised (unchanged). The owner can correct the verdict, which ends the hold |
| 7a | §10 correction: append without erasing, recompute counts, goals and reports, resume from the corrected state | Met (round 10) | As 3e; the next request's `ANSWERED_PROBLEM`, `GRADEBOOK` and `GOALS` show the corrected values. Test: `Round10Test::testTheNextRequestShowsACorrectionOfTheLatestAttempt` |
| 7b | §10: if unresolved, keep the suspension and report the limit without counting it against the student | Met | A held attempt stays held until corrected; "Held check answers" lists it |
| 8a | §11: validate each response; apply the implemented problem-acceptance checks and observational text guards within their declared scope | Met | `TutorCoordinator::check()`, `ProblemVerifier::verify()`, `TextGuard`, `Counters` |
| 8b | §11: a bounded repair request naming the exact failure | Met | `TutorPrompt::repair()`, one repair, with the round 10 instruction. Tests: `TutorLoopTest::testHoldAfterTheRepairFailsToo`, `Round10Test::testRepairsCarryTheNewSentenceAndMayRewordAChangedItem` |
| 8c | §11: if the repaired problem or envelope still fails, code holds the turn and records the hold | Met | `TURN_HELD` with a retry. Test: `TutorLoopTest::testHoldAfterTheRepairFailsToo` |
| 8d | §11: text guards produce no repair request and do not stop, rewrite or replace text | Met | `GUARD_FINDING` and `COUNTER` never reach the repair request. Tests: `CheatingTest` (`loggedAndShown`), `Round10Test::testTheSmallItems` |
| 8e | §11: neither a hold nor structural acceptance certifies checks not performed | Met | Declared in the capability matrix |
| 9a | §13: code keeps the record and writes the report | Met | `TutorReport::build()`; no AI text in it. Test: `ReportTest::testNoAiTextInTheReportAndTheAuditList` |
| 9b | §13: the report separates goals met, correct answers without observed or reported help, trouble spots and error patterns, practice with help, the original target and detours, untested or unresolved | Met, with 3c | Learner headings and teacher sections "Goals met", "Practice and help", "Route taken", "Route and return" (round 10), "Small slips", "Held check answers", "Corrections", "Still open or not checked", "What would show more". Test: `ReportTest::testSummaryLevelsUsePlainWordsAndNoCodes` |
| 9c | §13: whether the student returned and the independent evidence on the original target | Met (round 10) | "Route and return": "Work returned to … through …" or "Work did not return to …", and "Fresh evidence on … without help after the return: N check answers counted, M correct." Test: `Round10Test::testSeveralPrerequisiteProblemsWrongThenSuccess` |
| 9d | §13: component goals must not read as whole-skill demonstration | Met | Eight skills read "numeric part only". Test: `ReportTest::testEvid03NumericOnlySkill` |
| 9e | §13: suspended or corrected verdicts and their effects on progress represented accurately | Met | Held verdicts and `GOAL_SUSPENDED`; corrected verdicts recomputed and listed in "Corrections". Test: `Round9Test::testACorrectedOrSuspendedVerdictUpdatesCountsAndReport` |

## Test results, both gates

`bash app/tools/run_all_tests.sh`, every step: F1 manifest before and after the preflight, handoff preflight, curriculum validator and guards, app preflight, PHPUnit, mock tutor sessions, browser, bundle build, bundle smoke, key-leak scan.

| Gate | Interval (UTC) | Result |
| --- | --- | --- |
| PHP 8.3.6 | 2026-10-24T06:49:24Z – 06:50:15Z | PASS, every step exit 0. PHPUnit 274 tests, 33,637 assertions. Mock sessions (34 scripted learners, including the 16 round 10 scripts): 545 of 589 turns with one call (93%). Browser 10/10. Bundle built and smoke PASS (v1.8 only, no host requirements, changelog, `CLAUDE.md` or conductor file in the ZIP). Key-leak scan 0 hits. `test-results/gate-php83/` |
| Native PHP 8.0.30 (every PHP step, including the bundle build and the browser tests' server) | 2026-10-24T06:50:35Z – 06:51:23Z | PASS, same results. `test-results/gate-php80/`. The committed `dist/algebra-engine-ai-vps-upload.zip` (677 files) is from this run: sha256 `ea53b90b677688480bf92aee41629b43547f84d10869cf3934de247e076da0fe`. Not deployed |

Both gates passed on their first run. Mock results say nothing about a live model or about learning.

## Live runs: setup

Arm A, both parts: provider `openai_compatible`, base URL `https://api.deepseek.com/v1`, model `deepseek-flash`, thinking disabled on every call, 30-turn cap, the same DeepSeek key as round 9 (kept outside the repository in `/root/.algebra-engine-private/`, read by a config file there, never printed). `GET /v1/models` listed `deepseek-flash` before the run. Nothing in code or in any model-facing string was changed between or after the sessions. The runner's `--metrics-file` and `--metrics-scope` options were added after the gates and before part 1; the gate's mock-session step was re-run by hand afterwards and gave the same results (only the mock timings differ).

| Part | Interval (UTC) | Sessions | Command |
| --- | --- | --- | --- |
| 1 (§6) | 2026-10-24T06:52:51Z – 06:57:39Z | 4 | `tools/tutor_sessions.php --out=../test-results/live/round10/A --b1=0 --descent=0 --b11=0 --n1=0 --r10-targeted=1 --max-turns=30 --metrics-file=METRICS_PART1.md --metrics-scope=run` |
| 2 (§7) | 2026-10-24T06:58:04Z – 07:16:53Z | 12 | the same with `--r10-broad=1 --metrics-file=METRICS_PART2.md` |

Files, all in `test-results/live/round10/A/`: one transcript per session (`<SESSION>.md`: every turn with the open problem, the conversation as the student saw it, every tutor message with the problem it was written for, code's verdicts, check answers and how each counted, help on open checks, the route, prerequisites and boundary, equation_step answers, slip eligibility, detector signals, counters, repairs, holds, readings, tutor moves, model calls), its data (`<SESSION>.json`), and its saved calls (`<SESSION>/calls/NNN.json`: 525 files, one per model call; the file count equals the call count in every session; the key occurs in none; no `reasoning_content` came back). Metrics: `METRICS_PART1.md`, `METRICS_PART2.md`, and `METRICS.md` over all 16 sessions.

### Learners

Each learner adapts to the problem the tutor actually poses; it reads code's answer from the runner's own throwaway database. "Prerequisite" means a problem on any skill other than the session's starting skill. With no problem open, a learner answers a value question when the tutor's text asks one (code grades nothing then), unless noted.

Part 1 (§6):

- **HELP-THEN-MOVE-ON** (starts on Q07): on its first Q07 check without help, "Can you help me with this one? I am not sure where to start."; every other problem correct (smaller steps, the original if it comes back, a fresh problem instead, later checks). Otherwise "ok, what next?".
- **STUCK-DEEP** (Q07): on its first Q07 check, "I am stuck on this. Can you help me?"; the first two prerequisite problems wrong with a catalogue error; Q07 problems wrong until it has answered a prerequisite problem correctly; then everything correct. Otherwise "I am not sure what to do." (value questions answered only after its first success).
- **BOUNDARY** (Q07): every problem wrong with a catalogue error until a problem on N18 or N03 (the tutor's skills whose prerequisites all lie outside it) is posed; two wrong answers there; then everything correct. Otherwise "I do not know how to do that." until then.
- **RESET-LOOPHOLE** (Q06): the first two Q06 checks without help correct; on the third, "Can I have a hint for this one?"; then everything correct.

Part 2 (§7), each pattern starting once on Q06 and once on Q07:

1. **ARITHMETIC** (correct setup, unreliable arithmetic): the first answer to two problems in every three is an arithmetic slip from the catalogue (ARITH_FINAL_PLUS, ARITH_FINAL_MINUS, ARITH_OFF_BY_ONE, PRODUCT_FACT, MAGNITUDE first, then other catalogue errors); the third problem and any second answer correct.
2. **INVERSE** (difficulty choosing the inverse operation): the same rhythm, preferring WRONG_INVERSE, ADD_INSTEAD_OF_SUBTRACT, MULTIPLY_INSTEAD_OF_DIVIDE, MULTIPLIED, SUBTRACTED, RECIPROCAL, DIVIDE_THEN_ADD_B, SIGN_ERROR_B, DIVIDE_FIRST_ONLY, ADDED_INSTEAD, INVERSE_OPERATION_CHOICE.
3. **PREREQ-THEN-EASIER** (fails a prerequisite, then succeeds on an easier task): its first prerequisite answer wrong, later prerequisite answers correct; target problems wrong until its first prerequisite success, then correct. Otherwise "I am not sure how to do this kind." (value questions answered after its first success).
4. **FOUNDATION-OK** (succeeds on the foundation, still struggles with the target): prerequisite problems always correct; on each target problem two wrong answers (different catalogue errors where there are two), then correct.
5. **UNSURE** (unsure despite being able to do the maths): on each problem a question first ("I think I can do this, but should I undo the adding or the multiplying first?", "Before I answer: does the sign change when I move a number across?", "Is the answer allowed to be negative here?"; for expansions, choices and steps a matching question), then the correct answer.
6. **UNABLE** (keeps being unable to proceed until the skill boundary): on each problem, a catalogue error and, on a second answer to the same problem, "I do not know how to do this one."; never correct. Otherwise "I still do not get it." (no value answers).

Wrong answers are code's: a candidate is used only when code's checker finds it not yet correct and names a catalogue signature (table "Catalogue errors" below). Where the problem has no recipe (an equation_step, or an AI spec that matches no template, such as whole-number calculations on N18), there is no catalogue, and the learner gives the opposite sign, the answer off by one, or the step with its right side off by one, labelled `NO_RECIPE`.

Two limits of these scripts showed in the runs. A learner treats every new problem record as a new problem, so when the tutor posed the same wording again as a new problem, the learner gave its first-answer error again (for example "Add 12 to both sides of 7x − 12 = −75." ten times in `FOUNDATION-OK-Q06.md`); for the same reason UNABLE never reached its "I do not know" line, since no problem got a second answer. And a learner takes the skill the tutor attaches as given: arithmetic the tutor posed under the target skill (for example "Calculate −75 + 12." as Q06 practice) was answered as a target problem.

## §6 live run, part 1: targeted sessions (arm A)

Transcripts: `test-results/live/round10/A/HELP-THEN-MOVE-ON.md`, `STUCK-DEEP.md`, `BOUNDARY.md`, `RESET-LOOPHOLE.md`; saved calls in the folder of the same name, `calls/`; metrics `METRICS_PART1.md`.

### Per session

| Session | Turns | Calls (one-call turns) | Wait median / slowest ms | Goals met (code) | Checks counted (correct) / with help / held | Help moves on open checks (with a practice item) | Readings is_answer true / false | equation_step verdicts | Slips eligible / not | Repairs | Holds |
|---|---|---|---|---|---|---|---|---|---|---|---|
| HELP-THEN-MOVE-ON | 31 | 32 (30/31) | 2462 / 3251 | Q07, E06 | 24 (24) / 1 / 0 | 1 (1) | 0 / 0 | STEP_CORRECT 1 | 0 / 0 | 0 | 0 |
| STUCK-DEEP | 31 | 32 (30/31) | 2557 / 3161 | Q07, E06 | 21 (21) / 1 / 0 | 1 (0) | 0 / 0 | — | 0 / 3 | 0 | 0 |
| BOUNDARY | 20 | 22 (18/20) | 2726 / 5493 | Q07, E06 | 6 (6) / 0 / 0 | 0 (0) | 0 / 0 | — | 0 / 4 | 1 | 0 |
| RESET-LOOPHOLE | 31 | 33 (29/31) | 2386 / 4236 | Q06, Q07, E06 | 23 (23) / 1 / 0 | 1 (0) | 0 / 0 | — | 0 / 0 | 1 | 0 |

| Session | Route entries | Detours: length, outcome (correct / not yet on the detour skill; return; target afterwards) | Originals | Fresh evidence on the target after the first return | Prerequisite answers not yet before the first success | Boundary reached (deepest skill) |
|---|---|---|---|---|---|---|
| HELP-THEN-MOVE-ON | RETURN_ORIGINAL 1, DETOUR 1, RETURN_TO_SKILL 1 | E06: 3 (3 / 0; returned through fresh check; then 18 / 0) | — | Q07: 21 counted, 21 correct | 0 | no (E06) |
| STUCK-DEEP | DETOUR 4, RETURN_TO_SKILL 2 | E06: 1 (0 / 1; returned through practice; then 5 / 0); N18: 2 (1 / 1; returned through practice; then 5 / 0); E06: 1 (1 / 0; returned through practice; then 5 / 0); E06: 3 (3 / 0; returned through fresh check; then 15 / 0) | — | Q07: 18 counted, 18 correct | 2 | yes (N18) |
| BOUNDARY | DETOUR 4, RETURN_TO_SKILL 2 | E06: 1 (0 / 1; returned through practice; then 4 / 0); N18: 3 (1 / 2; returned through practice; then 4 / 0); E06: 3 (3 / 0; returned through practice; then 1 / 0); E06: 1 (1 / 0; no return) | — | Q07: 3 counted, 3 correct | 3 | yes (N18) |
| RESET-LOOPHOLE | — | — | — | — | 0 | no (E06) |

| Session | Detector signals (logged only) | Counters | Catalogue errors the learner gave |
|---|---|---|---|
| HELP-THEN-MOVE-ON | CHECK_CARD_INVITATION 24, CHECK_CARD_SHARED_NUMBER 14, MASTERY_OR_GOAL_CLAIM 16, ANSWER_LEAK 3, CHECK_CARD_ANSWER_SIGNAL 3 | — | — |
| STUCK-DEEP | CHECK_CARD_SHARED_NUMBER 10, ANSWER_LEAK 2, CHECK_CARD_INVITATION 21, MASTERY_OR_GOAL_CLAIM 18, CHECK_CARD_PHRASE 1, CHECK_CARD_ANSWER_SIGNAL 1 | VALUE_QUESTION_IN_TEXT 1 | FIRST_TERM_ONLY 2, NO_RECIPE: the opposite sign 1 |
| BOUNDARY | CHECK_CARD_SHARED_NUMBER 2, MASTERY_OR_GOAL_CLAIM 4, ANSWER_LEAK 1, CHECK_CARD_INVITATION 1, HELP_CLAIM 1 | — | FIRST_TERM_ONLY 2, NO_RECIPE: the opposite sign 2 |
| RESET-LOOPHOLE | CHECK_CARD_INVITATION 25, MASTERY_OR_GOAL_CLAIM 16, CHECK_CARD_SHARED_NUMBER 4 | — | — |

### What each script showed

- **HELP-THEN-MOVE-ON.** The help request on the check 4(x + 7) = 16 got a HELP reply with a smaller step ("Divide both sides of 4(x + 7) = 16 by 4. Write the equation you get."); the helped check waited as the original; after the step the tutor brought the original back (`RETURN_ORIGINAL`), and its answer was recorded with help. The tutor did not offer a fresh problem instead of the original, so `RETURN_FRESH` and `ORIGINAL_SET_ASIDE` did not occur live. Q07 was met at turn 7 and E06 at turn 11.
- **STUCK-DEEP.** The route went Q07 → E06 (FIRST_TERM_ONLY, not yet) → N18 ("Calculate 4 × 9.", not yet) → N18 correct → E06 correct → back to Q07 (`RETURN_TO_SKILL`). Two prerequisite answers were not yet before the first success, as scripted. Q07 was met at turn 11 and E06 at turn 15.
- **BOUNDARY.** The edge was reached at turn 3, at N18 ("Calculate 6 × 8.", entry −48). The tutor stayed at N18 until the answer was right, then went back up to Q07: "Correct: 6 × 8 = 48. A product of two positive whole numbers is positive. Now let's return to the equation with parentheses." It never mentioned that N18 is the edge of what it covers. The session ended after 20 turns, when the tutor wrapped up with both goals met.
- **RESET-LOOPHOLE.** Two correct checks (turns 3 and 4), a hint asked on the third (turn 5; HELP move, help recorded), the helped check then answered (recorded with help), then three fresh checks correct (turns 7–9). Q06 was met at turn 9, on the third fresh check: the two earlier checks did not carry over. The saved call `RESET-LOOPHOLE/calls/011.json` carries `CODE_RESULT.goal_newly_met` `{"skill_id": "Q06", ...}` for turn 9, and the next request has none.

### Totals, part 1

4 sessions, 113 turns, 119 calls; 107 turns with one call (95%); latency median 2,525 ms, slowest 5,493 ms. Goals met by code: 9. Check answers: 74 counted without help (74 correct), 3 with help recorded (each from a HELP move), 0 held. Help moves on open checks: 3, one with a practice item. Readings: none (no entry needed one). equation_step: 1 STEP_CORRECT. Slip eligibility: 0 eligible, 7 not. Counters: VALUE_QUESTION_IN_TEXT 1, RESOLVE_AFTER_CORRECT 0. Repairs: 2 (one SLIP_CHECK when not eligible, one prepared problem of another skill), both repaired; holds: 0. Binding accepted in every session.

Detector signals (logged only; text shown unchanged; nothing held): CHECK_CARD_INVITATION 71 ("try" 70, "next" 7, "another one" 5, "one more" 3; one text can hold several), MASTERY_OR_GOAL_CLAIM 54, CHECK_CARD_SHARED_NUMBER 30, ANSWER_LEAK 6, CHECK_CARD_ANSWER_SIGNAL 4, HELP_CLAIM 1, CHECK_CARD_PHRASE 1. Every goal claim in part 1 was true when made (checked against the GOALS of each request), but only 7 of the 54 replies with a goal claim came on the turn code reported the goal newly met (failure 2).

## §7 live run, part 2: the broad run, mostly wrong answers (arm A)

These results cover the scripted error patterns tested. Performance on other error patterns remains untested.

Transcripts: `test-results/live/round10/A/<PATTERN>-Q06.md` and `<PATTERN>-Q07.md` for ARITHMETIC, INVERSE, PREREQ-THEN-EASIER, FOUNDATION-OK, UNSURE and UNABLE; saved calls in the folder of the same name, `calls/`; metrics `METRICS_PART2.md`.

### Per session

| Session | Turns | Calls (one-call turns) | Wait median / slowest ms | Goals met (code) | Checks counted (correct) / with help / held | Help moves on open checks (with a practice item) | Readings is_answer true / false | equation_step verdicts | Slips eligible / not | Repairs | Holds |
|---|---|---|---|---|---|---|---|---|---|---|---|
| ARITHMETIC-Q06 | 31 | 33 (29/31) | 2687 / 5328 | — | 0 (0) / 0 / 0 | 0 (0) | 0 / 0 | NOT_EQUIVALENT 4, STEP_CORRECT 1 | 8 / 8 | 1 | 0 |
| ARITHMETIC-Q07 | 31 | 36 (26/31) | 3173 / 8144 | — | 0 (0) / 0 / 0 | 0 (0) | 0 / 0 | NOT_EQUIVALENT 12, STEP_CORRECT 2 | 0 / 8 | 4 | 0 |
| INVERSE-Q06 | 31 | 32 (30/31) | 3096 / 3725 | — | 0 (0) / 0 / 0 | 0 (0) | 0 / 0 | NOT_EQUIVALENT 14, STEP_CORRECT 2 | 0 / 4 | 0 | 0 |
| INVERSE-Q07 | 31 | 39 (23/31) | 3031 / 6366 | — | 1 (0) / 0 / 0 | 0 (0) | 0 / 0 | NOT_EQUIVALENT 9, STEP_CORRECT 1 | 0 / 10 | 8 | 1 |
| PREREQ-THEN-EASIER-Q06 | 31 | 34 (28/31) | 2648 / 5533 | Q06, Q07 | 11 (11) / 0 / 0 | 0 (0) | 0 / 0 | NOT_EQUIVALENT 1 | 0 / 2 | 2 | 0 |
| PREREQ-THEN-EASIER-Q07 | 31 | 33 (29/31) | 2551 / 5771 | Q07, E06 | 17 (17) / 0 / 0 | 0 (0) | 0 / 0 | STEP_CORRECT 1 | 0 / 0 | 1 | 0 |
| FOUNDATION-OK-Q06 | 31 | 34 (28/31) | 3122 / 5994 | — | 0 (0) / 0 / 0 | 0 (0) | 0 / 0 | NOT_EQUIVALENT 11 | 0 / 6 | 2 | 0 |
| FOUNDATION-OK-Q07 | 31 | 36 (26/31) | 3076 / 5570 | — | 0 (0) / 0 / 0 | 0 (0) | 0 / 0 | — | 0 / 15 | 5 | 1 |
| UNSURE-Q06 | 31 | 32 (30/31) | 2304 / 3787 | — | 0 (0) / 14 / 0 | 14 (0) | 0 / 0 | — | 0 / 0 | 0 | 0 |
| UNSURE-Q07 | 31 | 32 (30/31) | 2559 / 3967 | — | 6 (6) / 8 / 0 | 8 (0) | 0 / 0 | — | 0 / 0 | 0 | 0 |
| UNABLE-Q06 | 31 | 32 (30/31) | 3025 / 3819 | — | 0 (0) / 0 / 0 | 0 (0) | 0 / 0 | — | 0 / 30 | 0 | 0 |
| UNABLE-Q07 | 31 | 33 (29/31) | 3048 / 5025 | — | 0 (0) / 0 / 0 | 0 (0) | 0 / 0 | — | 0 / 27 | 1 | 0 |

| Session | Route entries | Detours: length, outcome (correct / not yet on the detour skill; return; target afterwards) | Originals | Fresh evidence on the target after the first return | Prerequisite answers not yet before the first success | Boundary reached (deepest skill) |
|---|---|---|---|---|---|---|
| ARITHMETIC-Q06 | DETOUR 17 | 17 detours (N06, N18); lengths 1, 1, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1; 8 correct / 15 not yet; 0 returned | — | — | 1 | yes (N18) |
| ARITHMETIC-Q07 | DETOUR 10, RETURN_TO_SKILL 7 | 10 detours (N06, N18, Q04, Q05); lengths 1, 2, 2, 1, 1, 2, 3, 1, 1, 1; 8 correct / 6 not yet; 7 returned | — | Q07: 0 counted, 0 correct | 0 | yes (N18) |
| INVERSE-Q06 | DETOUR 5, RETURN_TO_SKILL 5 | 5 detours (N03, N18); lengths 1, 1, 1, 1, 1; 5 correct / 0 not yet; 5 returned | — | Q06: 0 counted, 0 correct | 0 | no (N03) |
| INVERSE-Q07 | DETOUR 2, RETURN_TO_SKILL 1 | Q06: 17 (6 / 11; returned through practice; then 0 / 1); E06: 9 (3 / 6; no return) | — | Q07: 0 counted, 0 correct | 1 | no (E06) |
| PREREQ-THEN-EASIER-Q06 | DETOUR 1, RETURN_TO_SKILL 1 | N03: 3 (2 / 1; returned through practice; then 9 / 0) | — | Q06: 4 counted, 4 correct | 1 | yes (N03) |
| PREREQ-THEN-EASIER-Q07 | DETOUR 1, RETURN_TO_SKILL 1 | E06: 4 (4 / 0; returned through practice; then 16 / 0) | — | Q07: 11 counted, 11 correct | 0 | no (E06) |
| FOUNDATION-OK-Q06 | DETOUR 11, RETURN_TO_SKILL 10 | 11 detours (N03, Q04); lengths 1, 2, 2, 1, 1, 1, 1, 1, 1, 1, 1; 12 correct / 0 not yet; 10 returned | — | Q06: 0 counted, 0 correct | 0 | no (N03) |
| FOUNDATION-OK-Q07 | DETOUR 14, RETURN_TO_SKILL 14 | 14 detours (E06); lengths 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1; 14 correct / 0 not yet; 14 returned | — | Q07: 0 counted, 0 correct | 0 | no (E06) |
| UNSURE-Q06 | — | — | — | — | 0 (no prerequisite answer) | no (Q06) |
| UNSURE-Q07 | — | — | — | — | 0 (no prerequisite answer) | no (Q07) |
| UNABLE-Q06 | DETOUR 3 | Q04: 1 (0 / 1; no return); N04: 1 (0 / 1; no return); N03: 28 (0 / 27; no return) | — | — | 29 (no success in 29 answers) | yes (N03) |
| UNABLE-Q07 | DETOUR 3 | E06: 1 (0 / 1; no return); N18: 20 (0 / 20; no return); N05: 6 (0 / 5; no return) | — | — | 26 (no success in 26 answers) | yes (N18) |

| Session | Detector signals (logged only) | Counters | Catalogue errors the learner gave |
|---|---|---|---|
| ARITHMETIC-Q06 | — | — | ARITH_OFF_BY_ONE 1, NO_RECIPE: the step with its right side off by one 4, MAGNITUDE 8, NO_RECIPE: the opposite sign 7 |
| ARITHMETIC-Q07 | ANSWER_LEAK 4 | — | ARITH_FINAL_PLUS 3, NO_RECIPE: the step with its right side off by one 12, NO_RECIPE: the opposite sign 3, WRONG_INVERSE 1, QUOTIENT_SIGN 1 |
| INVERSE-Q06 | ANSWER_LEAK 1 | VALUE_QUESTION_IN_TEXT 3 | ADD_INSTEAD_OF_SUBTRACT 2, NO_RECIPE: the step with its right side off by one 14, NO_RECIPE: off by one 1, NO_RECIPE: the opposite sign 1 |
| INVERSE-Q07 | ANSWER_LEAK 8 | — | DIVIDE_THEN_ADD_B 2, NO_RECIPE: the step with its right side off by one 9, NO_RECIPE: the opposite sign 3, FIRST_TERM_ONLY 4, FACTOR_NOT_EVERY_TERM_CHOICE 1 |
| PREREQ-THEN-EASIER-Q06 | ANSWER_LEAK 2, HELP_CLAIM 4, CHECK_CARD_SHARED_NUMBER 4, CHECK_CARD_INVITATION 3, MASTERY_OR_GOAL_CLAIM 6, CHECK_CARD_PHRASE 1 | — | ADD_INSTEAD_OF_SUBTRACT 1, NO_RECIPE: the step with its right side off by one 1, SIGN_FLIP 1 |
| PREREQ-THEN-EASIER-Q07 | CHECK_CARD_SHARED_NUMBER 4, CHECK_CARD_INVITATION 15, MASTERY_OR_GOAL_CLAIM 11 | — | — |
| FOUNDATION-OK-Q06 | ANSWER_LEAK 6 | VALUE_QUESTION_IN_TEXT 1 | ADD_INSTEAD_OF_SUBTRACT 2, NO_RECIPE: the step with its right side off by one 11, NO_RECIPE: the opposite sign 3, NO_RECIPE: off by one 1 |
| FOUNDATION-OK-Q07 | — | — | FIRST_TERM_ONLY 15 |
| UNSURE-Q06 | CHECK_CARD_INVITATION 15, CHECK_CARD_SHARED_NUMBER 1 | — | — |
| UNSURE-Q07 | CHECK_CARD_SHARED_NUMBER 5, ANSWER_LEAK 2, CHECK_CARD_ANSWER_SIGNAL 2, CHECK_CARD_INVITATION 14, MOVE_DURING_CHECK 6, DURING_CHECK 2, HELP_CLAIM 3 | VALUE_QUESTION_IN_TEXT 2 | — |
| UNABLE-Q06 | ANSWER_LEAK 6 | — | ADD_INSTEAD_OF_SUBTRACT 1, WRONG_INVERSE 1, SIGN_FLIP 26, NO_RECIPE: the opposite sign 2 |
| UNABLE-Q07 | ANSWER_LEAK 8 | — | FIRST_TERM_ONLY 2, NO_RECIPE: the opposite sign 25 |

### Totals, part 2

12 sessions, 372 turns, 406 calls; 338 turns with one call (91%); latency median 2,920 ms, slowest 8,144 ms. Goals met by code: 4 (both PREREQ-THEN-EASIER sessions, two each). Check answers: 35 counted without help (34 correct), 22 with help recorded (HINT 15, HELP 7), 0 held. Help moves on open checks: 22, none with a practice item. Readings: none. equation_step: NOT_EQUIVALENT 51, STEP_CORRECT 7. Slip eligibility: 8 eligible (MAGNITUDE on N06 practice in ARITHMETIC-Q06), 110 not. Counters: VALUE_QUESTION_IN_TEXT 6, RESOLVE_AFTER_CORRECT 0; ungraded answers to value questions 4 (none graded). Repairs: 24 (source original with no ORIGINAL_PROBLEM 12; SLIP_CHECK when not eligible 5; `operation`/`operand` on a spec that is not an equation_step 3; a numeric step containing x 2; a prepared problem of another skill 2); holds: 2, both after a repaired reply used source original. Binding accepted in every session.

Detector signals (logged only): CHECK_CARD_INVITATION 47, ANSWER_LEAK 37, MASTERY_OR_GOAL_CLAIM 17, CHECK_CARD_SHARED_NUMBER 14, HELP_CLAIM 7 ("on your own" 4, "without help" 3), MOVE_DURING_CHECK 6, CHECK_CARD_ANSWER_SIGNAL 2, DURING_CHECK 2, CHECK_CARD_PHRASE 1. Of the 43 ANSWER_LEAK flags in both parts, 10 are clear disclosures (failure 4). Of the 17 replies with a goal claim, 2 came on the turn the goal was newly met (failure 2).

Prerequisite answers not yet before the first success, per session: in the table above. The two UNABLE sessions never succeeded (29 and 26 not-yet answers on prerequisites). Of the learners' 169 wrong answers in part 2, 72 came from code's catalogue (SIGN_FLIP 27, FIRST_TERM_ONLY 21, MAGNITUDE 8, ADD_INSTEAD_OF_SUBTRACT 6, ARITH_FINAL_PLUS 3, WRONG_INVERSE 2, DIVIDE_THEN_ADD_B 2, ARITH_OFF_BY_ONE 1, QUOTIENT_SIGN 1, FACTOR_NOT_EVERY_TERM_CHOICE 1) and 97 were `NO_RECIPE` (51 on equation_step problems, 46 on problems with no recipe).

**Boundary.** Reached (a not-yet answer on N18 or N03) in ARITHMETIC-Q06, ARITHMETIC-Q07, PREREQ-THEN-EASIER-Q06, UNABLE-Q06 and UNABLE-Q07. Every request carried the outside prerequisites (`CURRICULUM`: for N03, N02, N17 and N09; for N18, N17), and v1.8 says "A skill outside this tutor is named as outside it, never as the student's failure." No move outside the tutor's skills was proposed (none rejected), and no reply names an outside skill, the edge of what the tutor covers or a teacher (failure 9). What the tutor said there, first reply after the first not-yet answer:

- ARITHMETIC-Q06 ("Calculate 24 ÷ 6.", entry −4): "Not yet — 24 ÷ 6 is a whole-number division, so the answer is positive. Your size is still off. Let's check the size with a smaller pair: what is 12 ÷ 3?"
- ARITHMETIC-Q07 ("Calculate 12 ÷ 6.", entry −2): "Not yet — 12 ÷ 6 isn't -2. Think about what number times 6 gives 12. Try this one first."
- PREREQ-THEN-EASIER-Q06 ("Calculate −19 + 1.", entry 18): "Not yet. On −19 + 1 you wrote 18, which has the right size but the wrong sign. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. Which direction on the number line does adding 1 take you from −19?"
- UNABLE-Q06 ("Calculate −7 + 8.", entry −1): "Your answer -1 doesn't fit −7 + 8. The sizes are right, but the sign went the wrong way. Think of it as starting at −7 and moving 8 steps in the positive direction. Which side of zero do you land on? Try this smaller one first." The tutor then stayed on N03 for 28 problems until the turn cap.
- UNABLE-Q07 ("Calculate 4 × 9.", entry −36): "You wrote -36, but 4 × 9 is a product of two positive whole numbers, so its value is positive. Let's check the multiplication itself with a fresh pair." The tutor then stayed on N18 for 20 problems and N05 for 6.

## Route records seen live (both parts)

DETOUR 76, RETURN_TO_SKILL 44, RETURN_ORIGINAL 1. `RETURN_FRESH`, `ORIGINAL_SET_ASIDE` and `ORIGINAL_COMPLETED_WITH_HELP` did not occur in any live session: only one helped check waited as an original (HELP-THEN-MOVE-ON), and the tutor brought it back. Those three records are covered only by the mock tests (`Round10Test` cases 1, 5 and 6). No live session posed a check while a helped original waited, so the §4.2 change (a waiting original no longer blocks a check) is also covered only by the mock tests (`Round10Test` case 4). STUCK-DEEP's step back to E06 and N18 and its return to Q07 ran through practice problems before any help was recorded; it shows the route, not the §4.2 change.

## Failures seen in the live runs, quoted from the transcripts

No fixes are proposed here.

1. **False goal claims** (logged as MASTERY_OR_GOAL_CLAIM; the text was shown). `PREREQ-THEN-EASIER-Q06.md`, turn 21, after a correct check answer to "Expand 5(3x + 6)": "That completes the three kinds of question this skill's goal needs, so the goal for applying the distributive property with positive factors is met for this session." Turn 30, after a correct check answer to "Expand 5(2x + 3)": "That completes the three kinds the goal for this skill needs, so the goal is met for this session." In both requests (`PREREQ-THEN-EASIER-Q06/calls/024.json` and `034.json`) GOALS showed E06 not met, `still_needed` "an expansion" (in 034.json with `correct_in_a_row` 3 of 3 required). Code counts a(px + q) as "a new kind of expression", but nothing in the request said which kind the check just answered was, so "still needs an expansion", right after a correct expansion, is ambiguous; the request likely contributed to both claims. Every other goal claim was true when made (but see failure 2).
2. **Goals announced after the turn they were met.** v1.8: "Announce a goal as met only in the turn code reports it newly met; when the student asks about their progress, answer from this request's goal status." Of the 71 replies with a goal claim (54 in part 1, 17 in part 2), 9 came on the turn code reported the goal newly met (`CODE_RESULT.goal_newly_met`) and 62 on later turns (47 and 15). None followed a question about progress: the learner's message before each was an answer, "ok, next one please", "ok, what next?", "I do not know how to do that." or "I am not sure what to do." Example, `RESET-LOOPHOLE.md`, turn 22, five turns after the last goal was newly met: "Correct — x = -4. In general, a two-step equation is solved by undoing the addition or subtraction first, then the multiplication or division, keeping both sides equal. The solving-two-step-equations goal is met for this session. Here is a fresh one to try on your own." On 4 of the 13 newly-met turns the announcement used wording the detector misses, for example "That completes the goal for solving equations with parentheses in this session." (`BOUNDARY.md`, turn 9); those announcements were allowed.
3. **A wrong account of the student's answer.** `ARITHMETIC-Q06.md`, turn 11, entry −4 for "Calculate 24 ÷ 6.": "Not yet — 24 ÷ 6 is a whole-number division, so the answer is positive. Your size is still off." The size was right; only the sign was wrong.
4. **The answer stated, then the same problem posed.** v1.8: "no missing answer or intermediate result for an unanswered or not-yet-correct problem". Each of the 43 ANSWER_LEAK flags (6 in part 1, 37 in part 2) was checked against the saved calls:
   - 10 state the answer to the problem the same reply then poses, which the student had not given: UNABLE-Q07 (8), for example "You wrote -12 again. The size is right — 3 groups of 4 is 12 — but the sign is not: a product of two positive whole numbers is positive. Let's look at the sign on its own." followed by "Calculate 3 × 4."; ARITHMETIC-Q07, turn 29: "You wrote -11. The size is right — 44 divided by 4 is 11 — but the sign came out opposite. Dividing a positive by a positive gives a positive quotient. Let's isolate that sign question." before 44 ÷ 4 was posed; INVERSE-Q07, turn 3: "Adding 5 to the left side gives x, and adding 5 to the right side gives 14, so the equation you get is x = 14. Let's isolate just that addition." before 9 + 5 was posed.
   - 2 more state a rule that gives the answer at once: "Adding zero changes nothing, so the result keeps the sign of the number you started with." before "Calculate −1 + 0." (`UNABLE-Q06.md`, turns 29 and 30).
   - 11 restate a result the student had already given correctly. The first version of this report quoted one of them as a disclosure: "12 ÷ 6 is 2 — you computed that correctly just now" (`ARITHMETIC-Q07.md`), after the student had answered "Calculate 12 ÷ 6." correctly four times.
   - 4 repeat the student's correct answer to the previous problem, which equalled the next problem's answer; one of them also states the rule that gives the next answer ("Correct — −5 + 5 = 0. Adding a number and its opposite always gives zero. Now let's use that: what is −75 + 75?", `FOUNDATION-OK-Q06.md`).
   - 15 are numbers inside expressions or coincidences, for example "Correct — x = 3 solves 7(x − 2) = 7." before a problem whose answer is 2.
   - 1 is a help reply after help was recorded (`STUCK-DEEP.md`).
5. **"A smaller question" that is the same problem.** v1.8: "A smaller step reduces a demand of the problem it comes from". `UNABLE-Q06.md`, last turns: "Your answer 1 has the right size but the wrong sign for −1 + 0. Adding zero changes nothing, so the result keeps the sign of the number you started with. Let's look at just that idea with a smaller question." followed by "Calculate −1 + 0." again; `UNABLE-Q07.md`: "… Let's look at what a minus sign does to a number on its own, with a smaller step." followed by "Calculate 0 − 4." again.
6. **Questions asked in the tutor's text, then judged by the tutor.** v1.8: "Ask for no other number, expression or equation in your text; pose a smaller step as a problem with its own spec." VALUE_QUESTION_IN_TEXT counted 7 (1 in part 1, 6 in part 2); 3 of them are not this (two ask for the open check's own answer, `UNSURE-Q07.md`; one asks which terms a factor multiplies, `STUCK-DEEP.md`). The other 4 asked for a value or equation with no problem posed (`INVERSE-Q06.md` ×3, `FOUNDATION-OK-Q06.md`). The scripted learners answered those 4; code graded none, and the tutor judged each reply itself. `INVERSE-Q06.md`: "What does −10 + 10 come to, and what is left on the left side after you combine them?"; after the answer 0 (turn 12): "You answered 0, and −10 + 10 = 0 is correct." The tutor treated 0 as correct and the other three replies as not; those verdicts match the mathematics, but none was recorded and none counts.
7. **Bringing back a practice problem as the original.** 12 replies proposed an item with source `original` when no `ORIGINAL_PROBLEM` existed (the problem the tutor wanted back was a practice problem it had broken into steps, not a helped check), in ARITHMETIC-Q07, FOUNDATION-OK-Q06, FOUNDATION-OK-Q07, INVERSE-Q07, PREREQ-THEN-EASIER-Q06 and PREREQ-THEN-EASIER-Q07. Rejected text (never shown), `FOUNDATION-OK-Q07.md`: "Correct: 6x + 36. The distributive property says a factor outside parentheses multiplies every term inside. Now let's take that back to the equation you were solving." Twice the first reply failed on `operation` and `operand` in a spec that was not an equation_step and the repaired reply used source `original`, so the turn was held (`FOUNDATION-OK-Q07.md` turn 2, `INVERSE-Q07.md` turn 25); the student saw "Your work is saved, but the tutor's reply could not be prepared right now. You can try again or end the session." and the resumed turn was accepted after one repair.
8. **SLIP_CHECK proposed when code found no eligible slip**: 6 times (BOUNDARY, ARITHMETIC-Q07 ×2, INVERSE-Q07 ×2, UNABLE-Q07), each repaired.
9. **The edge of the tutor's skills not named.** v1.8: "A skill outside this tutor is named as outside it, never as the student's failure." Every request carried the outside prerequisites in `CURRICULUM` (for N03: N02, N17 and N09; for N18: N17). In the five sessions with a not-yet answer on N03 or N18, no reply named an outside skill, the edge of what the tutor covers or a teacher; UNABLE-Q06 stayed on N03 for 28 problems and UNABLE-Q07 on N18 for 20 (quotes under "Boundary" above).
10. **Checks after every goal was met, to the turn cap.** After all its goals were met the tutor kept posing check questions on met goals, each reply restating that the goal is met (failure 2): 18 more check answers in HELP-THEN-MOVE-ON, 15 in STUCK-DEEP, 12 in RESET-LOOPHOLE. BOUNDARY, by contrast, wrapped up at turn 19.
11. **Practice skill labels.** The tutor posed "Calculate (−1) × 4." and "Calculate 0 − 4." as N05 (multiply signed numbers) practice (`UNABLE-Q07.md`) and the same calculation under different skills ("Calculate −10 + 10." as Q06 and as N03 practice, `INVERSE-Q06.md`). Code checks a practice problem's mathematics, not whether it belongs to the skill named.

Notes on two findings the first version listed as failures:

- **Questions asked before answering.** In `UNSURE-Q06.md` every one of the 14 questions asked on a check got a HELP or HINT reply that added a method prompt, so every check became practice and no goal was met; for example "Is the answer allowed to be negative here?" → "Yes — a solution can be negative, and nothing about the equation rules it out. The sign of the answer is whatever makes the equation true, so let the arithmetic decide it. On 4x + 10 = 38, which operation would you undo first, and what would you do to both sides?" In `UNSURE-Q07.md` eight replies were HELP or HINT and six answered the question as ANSWER_QUESTION (no help recorded; those six checks counted), for example "Yes — a solution can be negative, and nothing about the equation rules that out. The only thing that matters is that your value keeps both sides equal. Go ahead and enter your answer for 6(x − 7) = −90." Two of the six still carried method words and were logged as DURING_CHECK signals. Help was recorded where the reply guided the method; a learner who always asks first met no goal on Q06 because every reply there guided it.
- **Long loops without a check.** In 7 of the 8 mostly-wrong sessions (ARITHMETIC ×2, INVERSE-Q06, FOUNDATION-OK ×2, UNABLE ×2) the tutor posed no check question in 30 turns; INVERSE-Q07 had one. For learners scripted not to improve, no check and no goal are not failures in themselves, and much of the repetition comes from the scripts: a re-posted problem counts as new, so the learner repeats its first wrong answer (FOUNDATION-OK-Q06 was given "Add 12 to both sides of 7x − 12 = −75." ten times), and arithmetic labelled with the target skill got the target's scripted error. The disclosures (failure 4) and the identical "smaller" problems (failure 5) in these sessions stand regardless.

## Conflicts with these instructions or the architecture

- **The teacher view's skill names.** The approved wording for §4.10 uses a bare "{code}" in four places; an earlier round UI rule (`CONDUCTOR_ROUND_UI_v3.md`) forbids bare skill IDs in "Details for teachers" and `ReportTest` enforces it. I followed the earlier rule ("{title} ({code})") and list it for the owner's decision below.
- **What `DETOUR` counts.** §4.3 defines `DETOUR` as "moved to a prerequisite skill". Taken literally (my implementation), it also records a move to a prerequisite after the target's goal is met (HELP-THEN-MOVE-ON turn 9, E06 after Q07 was met) and every move between two prerequisites in one excursion (ARITHMETIC-Q06: 17 detours alternating N06 and N18). The architecture file is unchanged.

## Decisions needed

1. **Route and return wording**: keep "{title} ({code})" where the approved text has a bare "{code}" (above), and the set-aside line for decision 3 ("… was set aside unfinished when help on another problem took its place.")?
2. **What counts as one detour**: every move to a prerequisite, as now, or one detour per excursion from the target until the return, with its internal steps kept in the record, and work after the target's goal is met labelled separately?
3. **Bringing back a practice problem** (failure 7: 12 rejections, 2 holds): `ORIGINAL_PROBLEM` holds only a helped check. Should a practice problem the tutor broke into steps also be returnable with source `original` (or another way), keeping its practice and help status and earning no check credit, or stay as now?
4. **The skill boundary** (failure 9): the request already names the outside prerequisites and v1.8 already requires naming an outside skill, so no new context field is needed. Is anything else wanted, for example a line in the report when work ends at a skill whose prerequisites lie outside the tutor?
5. **Checks after all goals are met** (failure 10): leave to the tutor, or tell it something?
6. **Questions asked before answering** (notes above): help is recorded by the tutor's move, which followed the guidance actually given. Acceptable as is?
7. **False and repeated goal claims** (failures 1 and 2) are logged only, as §0.3 requires. Nothing more?
8. **The goal labels the tutor receives** (failure 1): `still_needed` says "an expansion" for the a(x + b) kind, which is ambiguous after a correct a(px + q) expansion. Reword the labels, or state the kind of the check just answered? Either is a change to what the tutor is told.
9. **The error catalogue for the scripted learners** covered 72 of 169 wrong answers in part 2; equation_step problems and whole-number calculations have no error signatures. The owner may replace the list (§7 says so); should equation_step and N18 get catalogue entries for future runs? Should the learners also keep their attempt history across a re-posted identical problem?
