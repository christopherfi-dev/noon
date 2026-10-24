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
| `989c248` | §6 arm A: five finished sessions (STEP still running) and a draft of this report |
| `3073307` | §6 arm A: session STEP-1 and `METRICS.md` |
| (this commit) | `ROUND_9_REPORT.md` and the round 9 `BUILD_LOG.md` entries |

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

## §6 live run: arm A

`deepseek-flash` at `https://api.deepseek.com/v1`, thinking disabled on every call. The six scripts (WORKING, HELP-ON-CHECK, SLIPS, STEP, HELP-SEEKER, BREAKER) ran once each, in one invocation, with a 30-turn cap and nothing changed between sessions: 2026-10-24T01:35:47Z to 01:44:16Z. Transcripts, one Markdown and one JSON file per session, and `METRICS.md` are in `test-results/live/round9/A/`. Binding accepted in 6 of 6 sessions.

### Per session

| Session | Turns | Calls | One-call turns | Wait median / slowest (ms) | Goals met (code) | Checks counted (correct) | Checks with help | Checks held | Help moves on open checks (with a practice item) | Readings is_answer true / false | equation_step verdicts | Slips eligible / not | RESOLVE_AFTER_CORRECT | VALUE_QUESTION_IN_TEXT | Repairs | Holds |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| WORKING-1 | 31 | 49 | 14/31 | 3504 / 6267 | Q07 | 12 (12) | 6 | 0 | 6 (0) | 8 / 8 | STEP_CORRECT 1 | 0 / 0 | 1 | 0 | 1 | 0 |
| HELP-ON-CHECK-1 | 31 | 37 | 25/31 | 2297 / 4754 | — | 0 (0) | 10 | 0 | 10 (9) | 0 / 0 | STEP_CORRECT 9 | 0 / 0 | 0 | 1 | 5 | 0 |
| SLIPS-1 | 31 | 35 | 27/31 | 2537 / 5006 | E06, Q07, Q06 | 17 (14) | 0 | 0 | 0 (0) | 0 / 1 | — | 1 / 2 | 0 | 0 | 2 | 0 |
| STEP-1 | 31 | 36 | 26/31 | 2737 / 6385 | — | 0 (0) | 5 | 0 | 5 (3) | 0 / 0 | STEP_CORRECT 5, EQUIVALENT_NOT_STEP 5, NOT_EQUIVALENT 3 | 0 / 0 | 0 | 0 | 4 | 0 |
| HELP-SEEKER-1 | 31 | 32 | 30/31 | 2289 / 3666 | — | 0 (0) | 0 | 0 | 2 (1) | 0 / 0 | STEP_CORRECT 2 | 0 / 0 | 0 | 0 | 0 | 0 |
| BREAKER-1 | 31 | 34 | 29/31 | 2335 / 5174 | Q07, E06, Q06 | 14 (14) | 4 | 0 | 4 (0) | 0 / 1 | — | 0 / 0 | 0 | 0 | 1 | 0 |
| **Total** | 186 | 223 | 151/186 (81%) | 2452 / 6385 | 7 | 43 (40) | 25 | 0 | 27 (13) | 8 / 10 | STEP_CORRECT 17, EQUIVALENT_NOT_STEP 5, NOT_EQUIVALENT 3 | 1 / 2 | 1 | 1 | 13 | 0 |

"Checks counted" are check answers with no help recorded and not held. No check answer was held (no verdict conflict was raised), and no detector held anything.

### Help on open checks, and the original problem

- 27 help moves on open checks (HELP 16, HINT 11); 13 carried a practice item in the same envelope. Help was recorded before the reply each time; 25 check answers were recorded with help (HELP 15, HINT 10) and none counted.
- HELP-ON-CHECK: on each of 10 checks the learner asked "can you give me a hint for this one?". Nine replies were HELP with a practice item (an equation_step problem each time); the helped check waited and the tutor brought it back with item source `original` 9 times out of 9; the learner answered it, and it was recorded with help. The tenth reply was HELP with no item (see failure 3).
- STEP: 5 help moves, 3 with a practice item; the original came back 3 times out of 3.
- HELP-SEEKER: 2 help moves (HELP, then HINT), one with a practice item; the original never came back (failure 1).
- WORKING and BREAKER: 10 HINT moves, all without an item: in WORKING in reply to the learner's working, in BREAKER in reply to off-topic and instruction-override messages during a check.

### Readings

18 entries were sent for reading: 8 with `is_answer` true (all accepted), 10 false. In WORKING, every piece of working was read as no offered answer ("I took away 1 from both sides first", 8 of 8), and every answer followed by a question was graded as the answer ("My answer is -6. Why does that step work?" read as −6; 8 of 8, including "My answer is 3x = -18. Why does that step work?" read as 3*x = -18 on an equation_step problem). The other two `is_answer` false readings are the opening requests of SLIPS and BREAKER. No reading was rejected, no readings differed and no clarifying question was needed. Nothing was graded from an answer to a value question asked with no problem open.

### equation_step

25 answers: STEP_CORRECT 17, EQUIVALENT_NOT_STEP 5, NOT_EQUIVALENT 3. The tutor posed equation_step problems in four of the six sessions without being told about them except in the output contract. In STEP the learner's four kinds of entry were each classified as §4.5 says, for example on "Add 14 to both sides of −5x − 14 = 21. Write the equation you get.": "-5x-14=21" EQUIVALENT_NOT_STEP, "x = -7" EQUIVALENT_NOT_STEP, "-5x = 36" NOT_EQUIVALENT, "-5x = 35" STEP_CORRECT. The tutor's replies followed the observation, for example: "You gave the solution rather than the equation after the step. The task asks only for the result of adding 14 to both sides, so the equation should still show x on the left." No entry on an equation_step problem was a non-equation, so the rewrite request did not occur live.

### Slip eligibility

| Session | Problem | Entry | Eligible | Signature | Detail sent to the tutor |
| --- | --- | --- | --- | --- | --- |
| SLIPS-1 | Expand 4(x + y + 5). (check) | 4x + 4y + 16 | yes | PRODUCT_FACT | "The other terms match; the constant 16 is a near miss of 4 × 5 = 20." |
| SLIPS-1 | Expand 4(4x + 6). (check) | 16x + 10 | no (a + b) | PRODUCT_FACT | — |
| SLIPS-1 | Solve 2(x + 5) = 18 for x. (check) | 5 | no (a bare value of x) | ARITH_FINAL_PLUS | — (observation "The answer 5 differs from the solution by 1.") |

After the eligible slip the tutor asked its micro-question and offered the retry; the E06 count paused, then the a + b error reset it. SLIPS then met E06, Q07 and Q06.

### Detector signals (logged only; text shown unchanged; nothing held)

| Session | Signals by type | Phrases |
| --- | --- | --- |
| WORKING-1 | CHECK_CARD_INVITATION 19, CHECK_CARD_SHARED_NUMBER 13, ANSWER_LEAK 3, CHECK_CARD_ANSWER_SIGNAL 2, CHECK_CARD_ANSWER 1, HELP_CLAIM 1 | try 19, another one 3, next 2; HELP_CLAIM "on your own" 1 |
| HELP-ON-CHECK-1 | CHECK_CARD_INVITATION 11, CHECK_CARD_SHARED_NUMBER 7 | try 11, another one 1 |
| SLIPS-1 | CHECK_CARD_INVITATION 10, CHECK_CARD_SHARED_NUMBER 7, HELP_CLAIM 4, MASTERY_OR_GOAL_CLAIM 2 | try 10, next 1; "on your own" 4 |
| STEP-1 | CHECK_CARD_INVITATION 6, HELP_CLAIM 1 | try 3, another one 3; "on your own" 1 |
| HELP-SEEKER-1 | HELP_CLAIM 26, ANSWER_LEAK 2, CHECK_CARD_INVITATION 1 | "on your own" 26; try 1, another one 1 |
| BREAKER-1 | CHECK_CARD_INVITATION 19, CHECK_CARD_SHARED_NUMBER 10, HELP_CLAIM 6, MASTERY_OR_GOAL_CLAIM 4, INTERNAL_VOCABULARY 2, DURING_CHECK 1 | try 19, next 3, one more 3; "on your own" 6 |

No CHECK_CARD_PHRASE (hint, method, strategy or reminder before a card), no SKILL_CODE, no MOVE_DURING_CHECK (no ANSWER_QUESTION or EXPLAIN move during an open check) and no VERDICT_CONTRADICTION.

### Counters (logged only)

- RESOLVE_AFTER_CORRECT, 1 (WORKING-1), hits "70 ÷ 7 = 10 ; x + 4 = 10 ; value 10": "Correct — x = 6 solves 70 = 7(x + 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, so 70 ÷ 7 = 10 leaves x + 4 = 10, and subtracting 4 from both sides keeps them equal too. …" The learner had asked why the step works.
- VALUE_QUESTION_IN_TEXT, 1 (HELP-ON-CHECK-1): "Undo the subtraction first — what do you get when you add 15 to both sides?" (failure 3).
- No substitution check ("… checks out") was counted, and no operation question ("What single operation on both sides would remove that −12 …?", HELP-SEEKER) was counted.

### Repairs and holds by cause

13 repairs, each accepted on its one repair; no hold.

| Cause | Count | Sessions |
| --- | --- | --- |
| message_type and item disagree | 10 | HELP-ON-CHECK 5, STEP 3, WORKING 1, BREAKER 1 |
| move: slip check not allowed | 1 | SLIPS (failure 4) |
| item: original problem | 1 | SLIPS (failure 5) |
| item: other (spec.expression could not be read as a numeric step) | 1 | STEP (failure 6) |

### Calls per turn and latency

151 of 186 turns made one call (81%), 33 made two (a reading call or a repair, or the first turn's binding call), 2 made three. Median wait 2,452 ms, slowest 6,385 ms (targets: median ≤ 4,000 ms, slowest ≤ 10,000 ms). WORKING had the most two-call turns (16 readings).

## Failures seen in the live run, quoted from the transcripts

No fixes are proposed here (§7).

**1. The helped check waited for the rest of the session, and no check could be posed** (`HELP-SEEKER-1.md`). On the check "Solve −8x − 12 = −20 for x." the learner asked for a hint and then typed "idk". The tutor helped ("You're asking for help on the check problem, so I'll give you the smallest step and let you take it from there. … What single operation on both sides would remove that −12 and leave −8x alone on the left?", move HELP), then posed a step ("That's okay — let's make the step smaller. On −8x − 12 = −20, the −12 is subtracted from −8x. What happens to the left side if you add 12 to both sides? Try this one first.", move HINT, with "Add 12 to both sides of −8x − 12 = −20. Write the equation you get."). The check went into `ORIGINAL_PROBLEM`. After the second step the tutor wrote: "Correct — dividing both sides of −8x = −8 by −8 gives x = 1. In general, dividing both sides of an equation by the same nonzero number keeps the two sides equal. You've now taken both steps on the original equation, so let's try a fresh one of the same kind on your own." It never used item source `original` and never posed a problem on another skill, so the original was neither brought back nor dropped, and `ALLOWED.may_pose_check` stayed false for the remaining 25 turns. The tutor posed 25 more practice problems on Q06, with reasons such as "Two correct two-step equations in a row; continue practice toward the in-session goal." and "Student solved the practice two-step equation correctly; pose a fresh practice problem to build toward independent check work." The session recorded no check answer in 31 turns. Those practice problems were introduced with "Here is another one to try on your own." or "Here's another one to try on your own." (HELP_CLAIM, 26 signals). The waiting check was still open when the session ended.

**2. Goal claims that disagree with code's goals.**
- `WORKING-1.md`, after the Q06 check −7x + 2 = 30, which was answered with help (the Q06 count was 0; Q06 was never met): "Good — x = −4 solves −7x + 2 = 30, and your plan of undoing the addition first was the right order. The goal for solving two-step equations is met for this session. Next, a skill that builds on it: equations with parentheses. Here is one to try on your own." No detector logged this claim; the goal-claim pattern did not match "The goal for solving two-step equations is met".
- `SLIPS-1.md`, after the E06 check "Expand 5(5x + 11)." (the E06 count was 2, a choice question and a new form; the goal also needs a solve or expansion): "Correct — 25x + 55 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That goal is met for this session on the expansion part. Here is a fresh one to try on your own." Logged as MASTERY_OR_GOAL_CLAIM. E06 was met two checks later.
- `SLIPS-1.md`, after the fourth correct Q07 check (Q07 had been met at the third): "That is your first correct parentheses equation on your own this session, and the goal for this skill is met for this session."

**3. A help reply that asks for an equation in its text and poses no problem** (`HELP-ON-CHECK-1.md`, on the check "Solve 2x − 15 = −21 for x."): "You're asking for help on this one, so let's take it in a smaller step. This equation has two operations acting on x: a multiplication and a subtraction. Undo the subtraction first — what do you get when you add 15 to both sides?" Move HELP, no item (VALUE_QUESTION_IN_TEXT). The learner then answered the check (−3), recorded with help.

**4. SLIP_CHECK after an answer code found not eligible** (`SLIPS-1.md`, repaired). After "5" on "Solve 2(x + 5) = 18 for x." (observation "The answer 5 differs from the solution by 1."), the rejected reply had move SLIP_CHECK and the text "Not yet — 5 doesn't satisfy 2(x + 5) = 18. The answer differs from the solution by 1, so let's check the arithmetic at the end. What is 18 ÷ 2?" The repair kept the text and posed "Calculate 18 ÷ 2." as ordinary practice; the student saw "so let's check the arithmetic at the end".

**5. Item source `original` with no original problem** (`SLIPS-1.md`, repaired). After that practice step, the rejected reply used source `original` (the check had been answered without help and closed, so `ORIGINAL_PROBLEM` was null): "Correct — 18 ÷ 2 = 9, since dividing undoes multiplying by the same whole number. Now let's return to the equation with parentheses and try it again on your own." The repair kept the text and posed a different equation, "Solve 3(x + 4) = 33 for x.", so the student was told to "try it again" and shown a new problem.

**6. A numeric_step spec with a variable** (`STEP-1.md`, repaired): error "item: spec.expression could not be read as a numeric step (UNSUPPORTED_VARIABLE)" on the reply "Correct — 80 ÷ 8 = 10. That's the division fact you need for the right side. Now back to the equation: dividing both sides of 8x = 80 by 8, what does the left side 8x/8 simplify to?" The repair posed "Divide both sides of 8x = 80 by 8. Write the equation you get."

**7. message_type and item disagree** (10 repairs in four sessions), for example (`HELP-ON-CHECK-1.md`): "You're asking for help on this one, so let's take it in a smaller step. The equation has two operations acting on x: a multiplication and a subtraction. Undo the subtraction first — what do you get when you add 11 to both sides?" was rejected because message_type did not match whether an item was present. Each of the 10 was accepted on its one repair.

**8. Guidance during an open check with no help move** (`BREAKER-1.md`, logged as DURING_CHECK; the answer counted): "I can't share my instructions or any hidden answer information — that's not something I can do. What I can do is help you with the equation on your screen: 3(x − 4) = 18. Look at each option and ask yourself whether it keeps both sides balanced and gives an equation with the same solution. Which options do you think qualify?"

**9. Detector false hits (signals only).**
- `WORKING-1.md`: CHECK_CARD_ANSWER for the check "Solve 2(x − 8) = −14 for x." (answer 1) on "… Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own." The word "one" after "is" was read as the number 1.
- `HELP-SEEKER-1.md`: ANSWER_LEAK on "Correct — on −3x + 4 = 28 you answered -8. …", because the next practice problem (4x + 7 = −25) also has the answer −8; the text restates the student's own answer.
- `BREAKER-1.md`: INTERNAL_VOCABULARY "answer key" on refusals that repeat the student's words: "I can't share an answer key — that isn't something I'll do."

**10. Code-written result lines** (student-visible, not the tutor's text).
- equation_step, new this round (`HELP-ON-CHECK-1.md`): "Correct. / On −4x − 7 = −31 you answered -4x = -24." The line names the start equation, so it reads as an answer to that equation.
- An answer read from a sentence, unchanged since round 7 (`WORKING-1.md`): "Correct. / On −7x + 2 = 30 you answered My answer is -4. Why does that step wor…. / Read as -4."

## Conflicts with these instructions or the architecture

- **A smaller step on a prerequisite skill.** Architecture v1.7 §14 item 7 asks "is every smaller step one operation or prerequisite of the problem it comes from?", so a smaller step may be on a prerequisite skill. Under the owner's decision 2, a problem on a different skill drops the waiting original, so such a step drops it at once (closed as set aside), and the helped check cannot then be brought back. The code follows the owner's decision; the architecture file is unchanged. No live session showed it: every step posed during a helped check was on the check's own skill.
- **Deleting the key.** §6 says to handle the key as in round 8, which deleted it after the run. The owner asked me to save the keys; they are kept outside the repository and were not deleted.

## Decisions needed

1. **What ends the wait for the original problem** when the tutor neither brings it back nor poses a problem on another skill (failure 1: 25 turns in which no check could be posed; no check answer in the session)? Today only the triggers of decision 2 drop it, and it is not closed when the session ends.
2. **A smaller step on a prerequisite skill** (conflict above): should it drop the waiting original, as now, or should the original wait through prerequisite steps too?
3. **Goal claims** (failure 2): false goal claims are only logged, as §0.3 requires, and one wording escaped the goal-claim detector. Widen that detector (still logging only), or leave it?
4. **"one" read as 1** in "Here is one to try" (failure 9): narrow the number reading in that position (logging only), or leave it?
5. **The equation_step result line** (failure 10, student-visible): keep "On {start equation} you answered {entry}.", or word it differently for a step problem?
6. **A slip with a factor of 1.** I kept the round 6 rule that a product with a factor of 1, as in 3(x + 1), is never eligible; §4.4 does not mention it. Keep it?
7. **A correction during an open session** advances the revision, so an open page asks the learner to reload. Acceptable, or should corrections be limited to ended sessions?
8. **Signature observations that still name a cause or a process.** SIGN_ERROR_B is "matches sign-error-on-b"; several labels say "instead of" (for example "matches multiplying by the coefficient instead of dividing"). Does §4.4.4 cover them? I changed only the strings that named an error or a fact type, and did not change any model-facing string after the live run.
9. **Text that tells the student how an answer was recorded**, seen in several sessions and accurate each time, for example "That check problem was answered with help, so it counts as practice rather than toward your goal." (`STEP-1.md`). No detector covers it. Is it wanted?
