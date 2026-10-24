# Round 9 report — architecture v1.7, the approved code changes, targeted live sessions

Branch `round9-v1-7`, created from `round8-v1-6` at `efc65da` and pushed. Instructions: `CONDUCTOR_ROUND_9.md` (saved at the repository root). Nothing was deployed; no branch or tag named in §0.8 was touched.

The architecture file was checked before anything else: `Algebra_Constraint_Architecture_v1.7.md` has sha256 `afe9a01e82122f8fcb5c9b1c7e12a493d17ab307b14161656accd4e947dc9188` and no trailing newline, as §2.1 says. It was saved byte-identical in both places and never edited.

Every result in "Test results" is from the mock tutor. The live sessions in §6 are scripted learners against DeepSeek: they show how this build behaves with that model, not deployment readiness and not learning.

## Commits

| Commit | What |
| --- | --- |
| `3d49013` | §2, §3, §4.1–§4.3, §4.5 (work in progress, committed to protect it) |
| `073b03e` | §4.4, §4.6, §4.7; round 8 tests rewritten for the §3 reversals |
| `bb79fef` | §5 targeted tests (`Round9Test`) |
| `a035248` | Runner learners and metrics for §6, docs, capability matrix; both gates (PHP 8.3.6 and 8.0.30) |
@@LIVE_COMMITS@@

## What changed, by section of `CONDUCTOR_ROUND_9.md`

### §0 Working method

Followed as written. Every text detector now logs only (§0.3). No example responses were added to any model-facing string (§0.4). No cost work (§0.5). The model-facing strings are listed below (§0.6). I stopped and asked the owner five questions before deciding (§0.7); they are listed under "Questions I stopped to ask".

### §1 Branch

`round9-v1-7` from `efc65da`, pushed before any change.

### §2 Files

- `architecture/Algebra_Constraint_Architecture_v1.7.md` and `app/private/resources/architecture/Algebra_Constraint_Architecture_v1.7.md`, byte-identical (hash above). The runtime copy of v1.6 is removed; v1.1–v1.6 stay in `architecture/`. The app loads v1.7 only (`App::ARCHITECTURE_FILE`).
- Version 1.7 everywhere a version is checked: `Store::ARCHITECTURE_VERSION`, the binding detail in `TutorCoordinator`, `MockTutor`, `TutorReport` (three places), the `tutor-session`, `tutor-envelope` and `report` schemas, `private/bin/preflight.php` (hash and file names), `tools/bundle_smoke.php`, `tools/gen_capability_matrix.php`, `tools/manifest_check.sh`. The activation line is `ACE_BOUND: architecture=1.7 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
- A session bound to 1.6 is rebound on its next turn (`TutorEngine::upgrade()`, `ensureBinding()`; test `HostTest::testASessionBoundTo16IsReboundTo17`).
- `architecture/HOST_REQUIREMENTS_v1.7.md` saved; never sent to the model.
- `CHANGELOG_v1.7.md` appended verbatim to `architecture/CHANGELOG.md` (checked byte for byte with `cmp`).
- `CLAUDE.md` rewritten: it points to v1.7 and to §0 of `CONDUCTOR_ROUND_9.md`.
- The isolation test stays and is updated for v1.7 (`TutorLoopTest::testNoHostRequirementsChangelogClaudeOrConductorTextReachesAModelRequest`): it checks `HOST_REQUIREMENTS_v1.7.md` and `v1.6.md`, the changelog, `CLAUDE.md` and every `CONDUCTOR_*.md`. The only conductor text allowed to reach the model is a sentence that §4.1 specifies for the output contract, which by design is sent word for word.

### §3 Round 8 behaviours reversed

1. **Answer in the question.** `ProblemVerifier::ANSWER_IN_QUESTION`, the rule and `preparedCheck()` are removed, and so is the substitution in `TutorCoordinator` (`CHECK_SUBSTITUTED` is no longer written). A problem or envelope that still fails after its one repair holds the turn (`TURN_HELD`). `Recipes::fresh()` tries up to 30 parameter sets of the same template, returns the first fresh one whose answer is not among its numbers, and otherwise the first fresh one with the overlap; it never returns nothing because of overlap. Tests: `Round9Test::testRemovedRules`, `CheatingTest::testAnAnswerInTheQuestionIsAcceptedForACheck`, `ProblemInstrumentsTest::testPreparedProblemsPreferNoOverlapAndAcceptIt`.
2. **Every text detector logs only.** The during-check backstop, every before-card phrase, the before-card answer, help claims, mastery and goal claims and internal vocabulary are recorded as `GUARD_FINDING` with `signal: true` and the note "Logged as a signal only (round 9 §3.2); the text was shown unchanged, nothing is held and no count changes." No detector writes `CHECK_HELD` any more; `TextGuard::beforeCheckCard()` no longer returns a hold flag; `CHECK_CARD_AUDIT` has no `held` field. The backstop is now the `DURING_CHECK` signal. Tests: `Round9Test::testRemovedRules`, `CheatingTest::testTextBeforeACheckCardWithAStrategyPhraseIsASignal`, `::testTextBeforeACheckCardWithItsAnswerIsASignal`, `::testTheCheckAnswerBeforeACardIsASignal`, `::testTheBackstopDuringAnOpenCheckIsASignal`, `::testAnAnswerLeakDuringAnOpenCheckIsLogged`.
3. **Only HELP and HINT count as help on an open check.** `TutorEngine::HELP_MOVES = ['HELP', 'HINT']`. An ANSWER_QUESTION or EXPLAIN reply during an open check records no help and is logged as a `MOVE_DURING_CHECK` signal. Tests: `Round9Test::testAnswerQuestionDuringACheckRecordsNoHelp`, `CheatingTest::testOnlyHelpAndHintDuringAnOpenCheckCountAsHelp`.

### §4.1 The output contract

The six edits, word for word (`TutorPrompt::OUTPUT_CONTRACT`; test `EngineUnitsTest::testTheOutputContractHoldsNoBehaviourRules` asserts each sentence and each removal):

1. The identity sentence is deleted; `architecture_version is "1.7".` is its own line. The three identity fields are removed from the envelope schema, so they are not in the skeleton the model receives.
2. "HELP means you helped with the open check problem." is replaced by the specified two sentences.
3. The specified sentence follows "Any item is valid only when ALLOWED.may_pose_problem is true."
4. "SLIP_CHECK is valid only when SLIP.eligible_for_pause is true".
5. The equation_step definition follows the numeric_step definition.
6. The specified is_answer sentence replaces the old one.

Two further changes to the contract are mine and are listed with the other model-facing strings below: the semicolon before the old HELP sentence became a full stop, and a line defining `source original` was added (needed for the owner's decision on §4.3).

### §4.2 Binding replies to their request

- When code sends a request it captures the session, the state revision, the triggering event and the open problem (`TutorEngine::reply()`, `$expect`).
- Code attaches them to the reply (`TutorCoordinator::check()` returns `identity`); identity fields a reply still carries are removed before schema validation and ignored (owner's decision 5: no repair).
- A reply whose state revision changed while the call was in flight is discarded before anything is committed: `TutorEngine::commit()` compares the base revision and the provider-in-flight lease inside the commit transaction and discards the turn (`OPERATION_CANCELLED`, operation `DISCARDED`).
- A help move binds to the open problem captured with its request: help is recorded only when the captured open problem is the check still open.
- Test: `Round9Test::testAStaleReplyIsDiscarded` (the revision is advanced during the call; nothing is committed, no help is recorded; identity fields in a reply are ignored without a repair).
- `TRUSTED_SESSION` is removed from the context block (owner's decision 5).

### §4.3 Help on an open check

1. A HELP or HINT reply during an open check records `HELP_GIVEN` (source `TUTOR_MOVE`) before the reply is shown (`TutorEngine::helpMove()`).
2. The goal policy applies as before: the answer to that check is recorded with help, does not count, and restarts the run.
3. A practice item in the same envelope is accepted (`TutorCoordinator::check()`, `practice_with_help`) and shown after the reply. `AI_MOVE` records `with_practice_item`.
4. From then on the helped problem is presented to the tutor as practice: in `OPEN_PROBLEM` while it is open (purpose `practice`, `help_given`), and, when a smaller step is shown, in a new `ORIGINAL_PROBLEM` block (the same fields plus `spec` {kind, expression}). Owner's decisions 1 and 2: the helped check waits (`PROBLEM_WAITING`), the tutor brings it back with item source `original` (shown as practice with help; its answer is recorded on that check with help), and it is dropped (`PROBLEM_CLOSED` SET_ASIDE with `original_dropped`) when the tutor poses a problem on another skill, the topic changes, or the student skips or chooses "I haven't learned this yet". While it waits, `ALLOWED.may_pose_check` is false.
5. After help, `ALLOWED.may_pose_problem` is true.
- Help recorded on a check held from a verdict conflict restarts the run (`Goals::compute()`).
- Tests: `Round9Test::testHelpAndASmallerStepTogether`, `CheatingTest::testAHelpRequestDuringAnOpenCheckIsHelpedAndRecorded`, `::testTheAiPosesAProblemWhileACheckIsOpen`; browser journey (help reply, practice step, the helped check back as practice with help).

### §4.4 Slip eligibility

`SLIP.confirmed` is renamed `SLIP.eligible_for_pause` everywhere (context, contract, coordinator, mock, tests). `SlipCheck::eligible()` (was `confirm()`):

1. **Expansions** (POSITIVE_DISTRIBUTION, DISTRIBUTION_TRANSFER): eligible only when every term except the constant matches, the constant is a × (b ± 1), (a ± 1) × b, or differs from a × b in exactly one digit by exactly 1 (same sign and number of digits), and the constant is none of a + b, b, a or −(a × b). For 7(x + 5): 7x + 25 and 7x + 30 eligible; 7x + 95, 7x + 12, 7x − 35 not; for 2(x + 3), 2x + 5 not.
2. **Linear equations**: a bare value of x is never eligible. ARITH_FINAL_PLUS, ARITH_FINAL_MINUS and ARITH_OFF_BY_ONE stay as observations.
3. **Signed arithmetic** magnitude slips keep their rule.
4. The strings are replaced as specified; every resulting string is listed below.

Tests: `Round9Test::testSlipEligibility` (every §4.4 case, through the checker and through the loop), `LearnerInstrumentsTest::testOtherSlipSignatures`, `::testProductSlipConfirmedWithItsMicroStep`.

### §4.5 equation_step (practice only)

- Schema (`spec.kind` equation_step, `spec.operation`, `spec.operand`), spec reader and calculator (`SpecMath::equationStep()`: operation and operand checked, zero refused for multiply and divide, unique solution, the resulting equation computed side by side with exact rationals), renderer (`ProblemFactory::fromEquationStep()`, owner's wording: "Subtract 6 from both sides of 3x + 6 = 15. Write the equation you get."), verifier (`ProblemVerifier::equationStep()`: purpose practice, stated answer must be code's equation) and checker (`Checking\EquationStepCheck`).
- Verdicts STEP_CORRECT (CORRECT), EQUIVALENT_NOT_STEP (recorded INCORRECT, with that observation for the tutor), NOT_EQUIVALENT (INCORRECT). The verdict class is stored on the attempt (`step_verdict`).
- An entry that is not an equation is a request to rewrite it: `ENTRY_NOT_AN_EQUATION` is logged, nothing is graded, and the tutor is told why (`TURN.not_read_because`).
- Every case in the §4.5 table passes (`Round9Test::testEquationStep`, through the checker and through the loop), as do the wording for the four operations and the verifier's rejections.

### §4.6 Detectors and counters (logging only)

- Option letters count only standing alone: parenthesised groups that hold an operator are removed before letters are looked for, and "X)" counts only after a space or "(" that does not follow an operator (`DisclosureGuard::classificationHit()`, `withoutFormulas()`).
- Number words count only as standalone words: a phrase joined by a hyphen to another word ("two-step") is not a number (`Numbers::phrases()`; `DisclosureGuard::scalarHit()`).
- Digits inside codes never count: a digit run preceded by a letter is not a number (`Numbers::digits()`).
- New `SKILL_CODE` detector (`TextGuard::skillCodes()`): a capital letter from the curriculum's prefixes followed by two digits, standing alone.
- `RESOLVE_AFTER_CORRECT` leaves out substitution checks: a chain of equal sides in which one side is a side of the problem with the answer written in for x is left out entirely, with the digit values inside it (`Counters::substitutionCheck()`).
- `VALUE_QUESTION_IN_TEXT` also counts "what is … written as" and "how would you write … as …", and leaves out "what is the opposite/inverse of …" and "which/what (single) operation" questions.
- Tests: `Round9Test::testDetectorsAndCounters` (the round 8 false hits among them: "a(b + c)", "a(x + b) = c", "two-step", "Q07").

### §4.7 Minimum correction path

- `app/tools/correct_attempt.php` (private CLI; not in the upload bundle) appends one `CORRECTION` event (session, attempt, field `help` or `verdict`, new value, previous value, reason) through `TutorEngine::applyCorrection()`, in one transaction that also recomputes goals (`GOAL_MET` / `GOAL_SUSPENDED`), the progress record and, when the session has a stored report, a new report. It never edits or deletes an event; `--list` shows the current values and every correction.
- `Gradebook::attempts()` applies the latest correction of each field; the original values stay in the attempt (`original`) and every correction is listed (`corrections`). A verdict correction ends a verdict-conflict hold (owner's decision 4).
- The teacher view has a "Corrections" section (only when there is one): problem, field, old and new value, date and reason. The learner view uses the corrected counts only.
- Tests: `Round9Test::testACountedCheckCorrectedToWithHelpStopsCounting`, `::testACorrectedOrSuspendedVerdictUpdatesCountsAndReport`, `::testCorrectionsAreTraceableAndNothingCountsTwice`, `::testTheCorrectionCliTool`.

### §5 Targeted tests

All nine cases are in `app/tests/Integration/Round9Test.php` (13 tests), with the cross-references in `app/ACCEPTANCE_SCENARIOS.md`. Round 8 tests whose behaviour §3 reversed were rewritten, not deleted; each is listed in `app/tests/RETIRED_TESTS.md` with its replacement.

## Model-facing strings added or changed (§0.6)

The sentences §4.1 specifies are used word for word and are not repeated here. Every other model-facing string added or changed this round:

**Output contract (`TutorPrompt::OUTPUT_CONTRACT`)**

- Punctuation: the old text read "to_mode is one of the values in OUTPUT_CONTRACT.allowed_values; HELP means you helped with the open check problem." The replacement sentences follow a full stop: "to_mode is one of the values in OUTPUT_CONTRACT.allowed_values. During an open check problem, HELP or HINT means …".
- New line, for the owner's decision on §4.3: "  - source original: purpose practice, valid only when ORIGINAL_PROBLEM is not null; skill_id is ORIGINAL_PROBLEM.skill_id; recipe_ref, spec and stated_answer are null."

**Envelope schema, as the model sees it (skeleton and `OUTPUT_CONTRACT.allowed_values`)**

- Removed: `session_id`, `state_revision`, `reply_to_event_id`.
- `architecture_version`: "exactly \"1.7\"".
- `item.source`: "one of recipe, spec, retry, original".
- `item.spec.kind`: "one of linear_equation, expand, signed_arithmetic, numeric_step, equation_step".
- New `item.spec.operation`: "one of add, subtract, multiply, divide" (or null); new `item.spec.operand`: a string of up to 20 characters, a number, or null. Both appear in the skeleton as type placeholders.

**Context block labels**

- `TRUSTED_SESSION`: removed (owner's decision 5).
- `SLIP.confirmed` renamed `SLIP.eligible_for_pause`.
- New `ORIGINAL_PROBLEM`: null, or `{problem_id, version, purpose, skill_id, text, response_type, variables, options, answer, help_given, spec: {kind, expression}}`; `purpose` is "practice".
- `OPEN_PROBLEM.purpose` is "practice" for a check with help recorded.
- `ALLOWED.may_pose_check` is now false while a check is open or an original waits (it was equal to `may_pose_problem`).
- `OPEN_PROBLEM.response_type` may be "equation" (an equation_step problem).
- `TURN.not_read_because`, for a non-equation entry on an equation_step problem: "the entry is not an equation; the open problem asks for the equation after the step".

**Repair messages (errors sent back in one repair request)**

- Changed: "no problem may be posed this turn: a check question is still open (ALLOWED.may_pose_problem is false); during an open check problem only a reply whose to_mode is HELP or HINT may carry an item, with purpose practice"
- New: "a check item is valid only when ALLOWED.may_pose_check is true"
- Changed: "SLIP_CHECK is only possible right after an answer that code found eligible for the slip pause (SLIP.eligible_for_pause is false)"
- Changed: "item.source must be recipe, spec, retry or original"
- New: "source original is valid only when ORIGINAL_PROBLEM is not null, with purpose practice"
- New: "source original repeats ORIGINAL_PROBLEM, whose skill is {skill}"
- New: "an equation_step problem has purpose practice"
- New: "the stated answer {stated} differs from the equation code computed from the spec"
- New: "an equation_step spec needs operation and operand"
- New: "spec.operation must be add, subtract, multiply or divide"
- New: "spec.operand must be a number"
- New: "spec.operand must not be zero for multiply or divide"
- New: "spec.operand is outside the configured range"
- New: "spec.expression could not be read as an equation step ({code})"
- Reused for equation_step: "spec.expression must be 1 to {n} characters", "spec.expression must be a linear equation in x with exactly one solution"
- Removed: "session_id, state_revision and reply_to_event_id must equal the values in TRUSTED_SESSION"; "the question shows its own answer: …"

**What code tells the tutor about an error (§4.4.4); every resulting string**

Slip details (`CODE_RESULT.slip.detail`, only for an eligible slip):

- "The other terms match; the constant {got} is a near miss of {a} × {b} = {product}."
- "The sign matches; the size is a near miss of {a} × {b} = {product}."
- "The sign matches; the size is a near miss of {a} ÷ {b} = {quotient}." (the division version, with "÷" and the quotient)
- Removed: "The steps were right; the last subtraction was off by one." and "The steps were right; the last division was off by one." A bare value of x is never eligible, so these are no longer slip details; their replacement, "The answer {got} differs from the solution by {difference}.", is now the observation for ARITH_FINAL_PLUS, ARITH_FINAL_MINUS and ARITH_OFF_BY_ONE (below).

Signature observations (`CODE_RESULT.signatures[].observation`), as they now stand; changed ones marked:

- ARITH_FINAL_PLUS, ARITH_FINAL_MINUS, ARITH_OFF_BY_ONE (changed): "The answer {got} differs from the solution by {difference}." (was "matches an arithmetic error in the final step" / "matches an arithmetic error of one in the final step")
- PRODUCT_FACT (changed): "matches the expected terms with at least one coefficient different" (was "matches the correct structure with a multiplication-fact error in a coefficient")
- MAGNITUDE (changed, both): "matches the correct sign with a different size" (was "… (multiplication fact)" and "… (division fact)")
- ADD_INSTEAD_OF_SUBTRACT (changed): "matches adding the constant instead of undoing it" (was "… (sign error on b)")
- QUOTIENT_SIGN (changed, both): "matches the correct size with the opposite sign" (was "… (sign of a quotient)")
- EQUIVALENT_NOT_STEP (new): "has the same solution as the starting equation, but its sides are not the equation after this step"
- Unchanged: FIRST_TERM_ONLY "matches distribution-to-first-term-only (the factor multiplied only the first term inside the parentheses)"; SECOND_TERM_ONLY "matches distribution-to-second-term-only (the factor multiplied only the constant)" and "matches distribution-to-second-term-only"; NOT_EVERY_TERM "matches the factor reaching some but not all terms"; SIGN_ERROR_B "matches sign-error-on-b"; DIVIDE_THEN_ADD_B "matches dividing-c-by-a-then-adding-b"; DIVIDE_FIRST_ONLY "matches dividing only the right side by the coefficient before removing the constant"; MULTIPLY_INSTEAD_OF_DIVIDE and MULTIPLIED "matches multiplying by the coefficient instead of dividing"; RECIPROCAL "matches dividing the coefficient by the constant"; SUBTRACTED "matches subtracting the coefficient instead of dividing"; WRONG_INVERSE "matches applying the same operation instead of its inverse"; SIGN_FLIP "matches the correct size with the opposite sign"; ADDED_INSTEAD "matches adding the second number instead of its opposite"; ADDED_MAGNITUDES "matches adding the sizes of numbers with opposite signs"; FIRST_TERM_ONLY_CHOICE "matches distribution-to-first-term-only (chose a step that multiplied only the first term inside the parentheses)"; INVERSE_OPERATION_CHOICE "matches accepting a step that does not keep both sides equal"; FACTOR_NOT_EVERY_TERM_CHOICE "matches accepting an expression where the factor does not reach every term".

Signature descriptions (`CODE_RESULT.signatures[].description`), unchanged: "Your answer multiplied only the first term inside the parentheses by {a}."; "Your answer did not multiply every term in the parentheses by {a}."; "Your answer multiplied only the {b} by {a}."; "Your answer moved the {b} to the other side without changing its sign."; "Your answer has the right size but the opposite sign."; "Your answer is what you get by multiplying {b} by {a}."; "Your answer is what you get by adding {b} on the right side when it needed to be undone."

One unchanged label still names an error: SIGN_ERROR_B, "matches sign-error-on-b". It is listed under decisions needed rather than changed after the live run.

**Signals and audit notes (ledger only; never sent to the model)** — listed for completeness: the `GUARD_FINDING` note "Logged as a signal only (round 9 §3.2); the text was shown unchanged, nothing is held and no count changes."; the `CHECK_CARD_AUDIT` note "Text before a check question. Signals only (round 9 §3.2): nothing is held and no count changes."; `DURING_CHECK` "uses the open check's {expression|numbers|answer} with no help move"; `MOVE_DURING_CHECK` "to_mode {move} during an open check; not recorded as help".


## Questions I stopped to ask (§0.7), with the owner's answers

1. **The helped check while a smaller step is shown, and when the run restarts.** Answer: keep it as the original. The step becomes the open problem; the helped check waits in `ORIGINAL_PROBLEM`; the tutor brings it back with item source `original` (the same problem, shown as practice with help); the student's answer is recorded on that check with help, so the run restarts then; if it is never answered, the run is not restarted.
2. **What the tutor may pose while the original waits, and what counts as moving on.** Answer: practice only (`ALLOWED.may_pose_check` false). The original is dropped (closed as set aside) when the tutor poses a problem on a different skill, the topic changes, or the student skips or chooses "I haven't learned this yet".
3. **equation_step wording.** Answer: operation first, for example "Subtract 6 from both sides of 3x + 6 = 15. Write the equation you get."; "Add 4 to both sides of …"; "Multiply both sides of … by 3."; "Divide both sides of … by 3.", each followed by "Write the equation you get."
4. **How corrections appear, and whether one ends a hold.** Answer: a teacher line for each correction (problem, field, old and new value, reason, date); the learner view uses the corrected counts only; a verdict correction on an attempt held for a verdict conflict ends the hold, and the attempt counts with the corrected verdict.
5. **TRUSTED_SESSION.** Answer: remove it from the context; identity fields in a reply are ignored (no repair).

At §6 I asked for the DeepSeek key. The owner sent the same key file as in round 8 and asked me to save the keys. They are saved in a private folder outside the repository (mode 700, files mode 600); nothing from them is in the repository or any commit (key-leak scan with the key configured before every commit), and they were never printed. They were not deleted after the run, at the owner's request.

## Technical choices (§0.7)

- **Identity.** `TutorCoordinator::IDENTITY_FIELDS` are stripped from a reply before schema validation. The stale-reply discard is the existing commit check (base revision and provider-in-flight lease, inside the commit transaction), so no separate compare was added; the captured open problem is what a help move binds to.
- **Names.** Events `PROBLEM_WAITING`, `CORRECTION`, `ENTRY_NOT_AN_EQUATION`; `GUARD_FINDING` types `DURING_CHECK`, `MOVE_DURING_CHECK`, `SKILL_CODE`; item source `original`; `AI_MOVE.with_practice_item`; `ATTEMPT_RECORDED.step_verdict`; `SlipCheck::confirm()` renamed `eligible()`; `TextGuard::HOLD_PHRASES` renamed `CARD_PHRASES`.
- **The original problem** is the same problem record (same id and version) presented again (`PROBLEM_PRESENTED` with `returned_original: true`); its help stays recorded. Its spec in `ORIGINAL_PROBLEM` is `{kind, expression}` with the expression as shown to the student.
- **equation_step.** Each side is computed as a polynomial with exact rationals; the expected answer is "L = R". Its fingerprint is the start equation, operation and operand. The response type is "equation", with the existing input hint ("Type your answer, or a message to the tutor."). An entry with no "=" that is plain mathematics goes straight to the rewrite request without a reading call; anything with words still goes to the AI's reading. Division in the shown equation uses "÷" as everywhere else ("Multiply both sides of x ÷ 3 = 4 by 3.").
- **Recipes.** 30 tries per prepared problem: the first fresh one without overlap, else the first fresh one with it.
- **Slips.** "Differs in exactly one digit by exactly 1" requires the same sign and number of digits. The slip code stays `PRODUCT_FACT`. The round 6 rule that a product with a factor of 1 is never a slip is kept (see decisions needed).
- **Detector tokenizing.** Option letters: parenthesised groups holding an operator are removed first; "X)" counts only after a space or "(" that does not follow an operator; "the answer is a(b + c)" does not count. Number words: a hyphen joining a number word to another word cancels it ("two-step", "one-to-one"); compounds such as "twenty-five" still count. Digits: a run preceded by a letter is not a number. Skill codes: one of the curriculum's prefix letters (A C D E F G H I L M N P Q R S U X) and two digits, standing alone.
- **Corrections.** The latest correction per attempt and field applies. Values: help `with_help` / `without_help`; verdict `CORRECT` / `INCORRECT` / `PARTIAL`. A help correction to `with_help` adds the marker `CORRECTION` to the attempt's help list. A correction is refused while a provider call is in flight. It advances the session revision once (so an open page asks to reload), keeps the session's activity times (`Store::saveSnapshot()`), rebuilds the stored report only when one exists (a session that has ended), and records `GOAL_SUSPENDED` with the reason "A correction changed an attempt this goal counted; the goal is recomputed with the correction." The "Corrections" section appears only when there is a correction. The CLI is in `app/tools/` and not in the upload bundle; it reads the database from the same config as the app (`ALGEBRA_CONFIG`).
- **Runner.** WORKING starts on Q06 and cycles, per answerable problem: working with a number from the problem ("I took away {n} from both sides first"), "My answer is {answer}. Why does that step work?", then the plain answer. HELP-ON-CHECK starts on Q06 and asks "can you give me a hint for this one?" on a check with no help yet, at most twice per check, and answers everything else correctly. SLIPS starts on Q07 and first asks "Can we practise expanding brackets first, like 3(x + 4)?", then on expansion check problems gives one eligible slip and then an a + b error, and on the first equation check problem one answer off by one. STEP starts on Q06 and first asks "Can we go one step at a time? I get lost in two-step equations."; on an equation_step problem it gives, in turn, the correct step, the unchanged equation, "x = {solution}" and a wrong step (right side plus 1); on a check with no help it asks once "can you show me the first step on this one?". HELP-SEEKER and BREAKER are round 8's. The gate's mock-session step runs all of them in mock mode.
- **Mock tutor.** It no longer reads `TRUSTED_SESSION`; it answers a help request on an open check with HELP and a practice problem on the same skill, brings the original back after a correct answer, and reads working such as "I added 9" as no offered answer.

## Host requirements (`architecture/HOST_REQUIREMENTS_v1.7.md`)

Each requirement, split into its parts, with where code meets it or that it is a gap. "Met" means implemented and covered by a named test.

| # | Requirement (source section) | Status | Where |
| --- | --- | --- | --- |
| 1 | Opening paragraph: the host enforces the guarantees; a model-generated assertion of compliance is not an acceptance receipt | Met | Every envelope is checked by code after the call (`TutorCoordinator::run()`/`check()`); identity is attached by code, not taken from the reply (§4.2); the binding event says it acknowledges the contract and is not evidence of enforcement (`TutorEngine::ensureBinding()`); goal, mastery and verification claims in `evidence_proposals` are rejected (`TutorCoordinator::reviewProposals()`). Tests: `HostTest::testHost13BindingModes`, `::testHost06ForgedPromotionRejected`, `CheatingTest::testTheAiMarksAGoal`, `Round9Test::testAStaleReplyIsDiscarded` |
| 2 | §5 opening: declare which checks are performed and their scope; "enforced" only when implemented and tested | Met | `app/CAPABILITY_MATRIX.md` and `capability-matrix.json` (generated by `tools/gen_capability_matrix.php`), updated this round: equation_step, slip eligibility, detectors as signals, help with a practice step and the original problem, the correction path; `app/ACCEPTANCE_SCENARIOS.md` maps each round 9 item to its test. Test: `DeployCoverageTest::testCover01CapabilityMatrix` |
| 3a | §5.4: for every attempt, the problem, skill, practice or check, entry, reading note, scoped verdict and its status | Met | `ATTEMPT_RECORDED` (`TutorEngine::answer()`, now with `step_verdict` for equation_step), read by `Gradebook::attempts()`; status: held (`ATTEMPT_HELD`, and `CHECK_HELD` in round 8 records) and corrected (`CORRECTION`). The test harness re-checks every recorded verdict after every request |
| 3b | §5.4: observed or reported help between presentation and answer | Met | `HELP_GIVEN` from "I'm stuck" (before the AI call) and from a HELP or HINT move during an open check (before the reply is shown); help on a returned original stays recorded; a help correction. Tests: `Round9Test::testHelpAndASmallerStepTogether`, `::testAnswerQuestionDuringACheckRecordsNoHelp`, `CheatingTest::testOnlyHelpAndHintDuringAnOpenCheckCountAsHelp`, `::testIAmStuckDuringACheckIsRecordedBeforeTheAiCall` |
| 3c | §5.4: unreported outside help cannot be certified absent | Partial | Nothing certifies absence of help, but the report's wording is "correct without help", not "without observed or reported help". Gap in wording only (unchanged from round 8) |
| 3d | §5.4: the original target, any detours, whether the student returned, and the independent evidence on the original target | Partial | Topic and focus in the snapshot; detours as `AI_MOVE`; "Route taken" names each move; goals per skill. No explicit "returned / not returned" line and no separate statement of independent evidence on the original target (unchanged from round 8). The new `ORIGINAL_PROBLEM` is the helped check, not the original target in this sense |
| 3e | §5.4: corrections preserve the original record and recompute affected progress | Met (minimum path) | `CORRECTION` appended by `tools/correct_attempt.php` → `TutorEngine::applyCorrection()`; the attempt event is unchanged; `Gradebook::attempts()` keeps `original` and lists `corrections`; goals, progress and the stored report recomputed in the same transaction. Tests: `Round9Test::testACountedCheckCorrectedToWithHelpStopsCounting`, `::testCorrectionsAreTraceableAndNothingCountsTwice`, `::testTheCorrectionCliTool` |
| 4 | §5.7: revisions, idempotency, leases, no answer keys or internal data in anything the browser receives | Met | `TutorEngine::handle()`/`commit()`; `TutorProjection::FORBIDDEN_KEYS`, `scan()`, `tutor-view.schema.json` (now with response type "equation"). Tests: `HostTest::testHost01DuplicateSubmit`, `::testHost02IdempotencyConflict`, `::testHost03StaleTab`, `::testHost04LateLeaseDiscarded`, `::testHost08NoAnswerLeakage`, `::testHost10CrossSessionAccessDenied`, `::testHost11Csrf`; `KeyLeakTest`; `Round9Test::testAStaleReplyIsDiscarded` |
| 5 | §8 practice problems: wording asks for the same operation, unknown, conditions and requested form as the verified spec and does not reveal the answer; where correspondence cannot be established, authoritative wording rendered from the spec or an accepted prepared problem | Met (second route) | Code always renders the wording from the verified spec (recipe wording, default wording for the kind, or the equation_step wording) or uses the prepared problem's; the AI's `prompt` is recorded (`MODEL_TEXT_DISCARDED`) and never shown. Tests: `ProblemInstrumentsTest::testPracticeWordingIsRenderedByCodeFromTheSpec`, `Round9Test::testEquationStep` |
| 6a | §10 conflict: suspend the affected verdict and dependent progress claims; exclude the disputed attempt from success and failure counts pending review | Met | `ATTEMPT_HELD` (VERDICT_CONFLICT); `Goals::compute()` skips a held attempt unless help was recorded on it (then the run restarts, §4.3); `GOAL_SUSPENDED`; the report lists it as "result under review". Test: `CheatingTest::testAVerdictConflictHoldsTheAttempt`, `Round9Test::testHelpAndASmallerStepTogether` |
| 6b | §10 conflict: recheck the problem, its wording/spec correspondence, the reading and the checker result | Gap | No runtime recheck when a conflict is raised (unchanged). The owner can now correct the verdict (§4.7), which ends the hold |
| 7a | §10 correction: append without erasing, recompute counts, goals and reports, resume from the corrected state | Met (minimum path) | As 3e; the next turn computes goals from the corrected ledger (`Round9Test::testACountedCheckCorrectedToWithHelpStopsCounting` checks the next request's `GOALS`). A correction advances the revision, so an open page reloads first |
| 7b | §10: if unresolved, keep the suspension and report the limit without counting it against the student | Met | A held attempt stays held until corrected; "Held check answers" and `unresolved_or_untested` list it |
| 8a | §11: validate each response; apply the implemented problem-acceptance checks and observational text guards within their declared scope | Met | `TutorCoordinator::check()`, `ProblemVerifier::verify()`, `TextGuard`, `Counters` |
| 8b | §11: a bounded repair request naming the exact failure | Met | `TutorPrompt::repair()`, one repair. Test: `TutorLoopTest::testHoldAfterTheRepairFailsToo` |
| 8c | §11 (changed in v1.7): if the repaired problem or envelope still fails, code holds the turn and records the hold | Met | `TURN_HELD`, `HOLD_LINE` with a retry; nothing is replaced (§3.1). Tests: `TutorLoopTest::testHoldAfterTheRepairFailsToo`, `Round9Test::testRemovedRules` |
| 8d | §11: text guards produce no repair request and do not stop, rewrite or replace text | Met | `GUARD_FINDING` (signals) and `COUNTER` never reach the repair request. Tests: `CheatingTest` (`loggedAndShown` asserts no repair), `Round9Test::testRemovedRules` |
| 8e | §11: neither a hold nor structural acceptance certifies checks not performed | Met | Declared in the capability matrix (heuristics and gaps) |
| 9a | §13: code keeps the record and writes the report | Met | `TutorReport::build()`; no AI text in it. Test: `ReportTest::testNoAiTextInTheReportAndTheAuditList` |
| 9b | §13: the report separates goals met, correct answers without observed or reported help, trouble spots and error patterns, practice with help, the original target and detours, untested or unresolved | Met, with 3c and 3d | Learner headings and teacher sections "Goals met", "Practice and help", "Route taken", "Small slips", "Held check answers", "Corrections", "Still open or not checked", "What would show more" |
| 9c | §13: whether the student returned and the independent evidence on the original target | Partial | See 3d |
| 9d | §13: component goals must not read as whole-skill demonstration | Met | Eight skills read "numeric part only". Test: `ReportTest::testEvid03NumericOnlySkill` |
| 9e | §13: suspended or corrected verdicts and their effects on progress represented accurately | Met | Held verdicts and `GOAL_SUSPENDED`; corrected verdicts recomputed into the counts and goals, each listed in "Corrections" with its old and new value. Test: `Round9Test::testACorrectedOrSuspendedVerdictUpdatesCountsAndReport` |

## Test results, both gates

`bash app/tools/run_all_tests.sh`, every step: F1 manifest before and after the preflight, handoff preflight, curriculum validator and guards, app preflight, PHPUnit, mock tutor sessions, browser, bundle build, bundle smoke, key-leak scan.

| Gate | Interval (UTC) | Result |
| --- | --- | --- |
| PHP 8.3.6 | 2026-10-24T00:59:19Z – 00:59:54Z | PASS, every step exit 0. PHPUnit 257 tests, 31,775 assertions. Mock sessions (18 scripted learners, including the six round 9 scripts): 218 of 246 turns with one call (89%). Browser 10/10 (conversation journey with help, practice step and original at 390 and 1280; slip pause; UI-01, UI-02, UI-03, UI-06, UI-07). Bundle built and smoke PASS. Key-leak scan 0 hits. `test-results/gate-php83/` |
| Native PHP 8.0.30 (every PHP step, including the bundle build and the browser tests' server) | 2026-10-24T01:00:11Z – 01:00:46Z | PASS, same results. `test-results/gate-php80/`. The committed `dist/algebra-engine-ai-vps-upload.zip` (677 files) is from this run: sha256 `5ef7dd5aa6e40a72bdee07d66d35479913b6633cebbb88cf237a80882cda7fbe`. Not deployed |

The first 8.3 run (00:58:06Z–00:58:43Z) failed two steps, both in test expectations I had changed this round: `DeployCoverageTest` requires the matrix's live-run line to say "not teaching quality" (restored), and the browser journey looked for the goal note "You had help on this one" while the practice step was open (that note shows for a helped check; the assertion moved to when the original comes back). Both are recorded in `BUILD_LOG.md`.
