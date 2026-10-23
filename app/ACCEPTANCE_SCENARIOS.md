# Acceptance scenarios — status in the AI tutor (round 6, updated for rounds 7 and 8)

Source: `contracts/acceptance-scenarios.json` (53 scenarios). Map file: `app/tests/acceptance-map.json` (checked by `tests/Integration/ScenarioMapTest.php`). Every result is from mock mode (the scripted mock tutor); none is a live-provider, deployment or learning result.

The beta's own tests (B1–B16, P3, the round UI checks) asserted the retired state machine's sequencing or its fixed lines; they are listed with one-line reasons and their replacements in `tests/RETIRED_TESTS.md`.

## Round 8 tests (CONDUCTOR_ROUND_8.md §4)

| Item | What is checked | Test |
|---|---|---|
| Help request on an open check | The reply is shown; HELP_GIVEN (source TUTOR_MOVE) is recorded before it; the check becomes practice; the answer does not count | `CheatingTest::testAHelpRequestDuringAnOpenCheckIsHelpedAndRecorded`; browser journey |
| HINT, EXPLAIN, ANSWER_QUESTION on an open check | Each counts as help in the same way; the same move with no check open records nothing | `CheatingTest::testHintExplainAndAnswerQuestionDuringAnOpenCheckCountAsHelp` |
| Backstop | Text during an open check with its numbers or expression and no help move holds the check (HELP_DURING_CHECK); a refusal with no numbers records nothing and the answer counts | `CheatingTest::testTheBackstopDuringAnOpenCheck`, `::testAnAnswerLeakDuringAnOpenCheckHoldsTheAttempt`; `LearnerInstrumentsTest::testTextDuringAnOpenCheck` |
| Handover before a check card | "Here is one more to try on your own." holds nothing (CHECK_CARD_INVITATION logged, phrase by phrase) and raises no help claim; support words elsewhere are flagged | `CheatingTest::testANeutralHandoverBeforeACheckCard`; `LearnerInstrumentsTest::testHelpClaimsAndTheHandoverSentence`, `::testTextBeforeACheckCard` |
| Answer before a check card | Equal to the check's answer and from the previous answer: logged only (CHECK_CARD_ANSWER_SIGNAL); from nowhere: held | `CheatingTest::testTheCheckAnswerBeforeACardFromThePreviousAnswerIsASignal`, `::testTextBeforeACheckCardWithItsAnswerHoldsTheAttempt` |
| Answer in the question | A practice problem is accepted; a check problem is repaired once, then replaced by a prepared check problem, logged, with no hold; prepared problems satisfy the rule | `CheatingTest::testAnAnswerInTheQuestionAcceptedForPracticeAndReplacedForACheck`; `ProblemInstrumentsTest::testPracticeWordingIsRenderedByCodeFromTheSpec`, `::testPreparedProblemsSatisfyTheCheckRule` |
| Counters | RESOLVE_AFTER_CORRECT and VALUE_QUESTION_IN_TEXT fire on built cases and log only; plain feedback records nothing | `TutorLoopTest::testTheTwoCountersLogOnly` |
| What the model receives | Only the architecture, the output contract, the activation exchange and the JSON block; no host-requirements, changelog, CLAUDE.md or conductor text | `TutorLoopTest::testNoHostRequirementsChangelogClaudeOrConductorTextReachesAModelRequest`; bundle smoke (no such file in the upload) |
| v1.6 binding | The app loads v1.6 only; a session bound to 1.5 is rebound; HELP is a field value in the output contract | `HostTest::testHost13BindingModes`, `HostTest::testASessionBoundTo15IsReboundTo16`, `EngineUnitsTest::testTheOutputContractHoldsNoBehaviourRules` |

## Round 7 tests (CONDUCTOR_ROUND_7.md §9)

Where round 8 changed the behaviour (messages during an open check, "next" and "try", the v1.5 binding), the round 8 rows above replace these.

| Item | What is checked | Test |
|---|---|---|
| Shared number before a check | Logged as a signal (CHECK_CARD_SHARED_NUMBER); no hold, no repair; the attempt counts | `CheatingTest::testTextBeforeACheckCardSharesANumber` |
| Strategy phrase or answer before a check | Logged, text shown unchanged, attempt held (counts neither for nor against the goal) | `CheatingTest::testTextBeforeACheckCardWithAStrategyPhraseHoldsTheAttempt`, `::testTextBeforeACheckCardWithItsAnswerHoldsTheAttempt` |
| Message during an open check | Reaches the AI (round 8: a help move records help; numbers without one hold the check) | `CheatingTest::testAHelpRequestDuringAnOpenCheckIsHelpedAndRecorded`, `::testTheBackstopDuringAnOpenCheck` |
| "I'm stuck" during a check | Recorded as assisted before the AI call (also when the call fails) | `CheatingTest::testIAmStuckDuringACheckIsRecordedBeforeTheAiCall` |
| Typed "idk" during a check | A message; code never classifies it as help (only the tutor's help move records help) | `CheatingTest::testTypedIdkDuringACheckIsAMessageNotCodeClassifiedHelp` |
| Readings | `16 because it's 4 x 4` → 16 accepted; `2 x x + 2` → 2x, `2x plus 2` → 2x, `2(x + 1)` → 2x + 1, `2 - 5` → 5 - 2, `minus five` → 5 rejected; `twenty five` is 25; `3 = x` is correct | `OwnerCasesTest::testReadingsAreAcceptedOnlyWhenTheyReExpressTheEntry`, `::testNumberWordsAndReversedEquations`; `LearnerInstrumentsTest` |
| Hold after a failed repair | HOLD with a retry; HOLD_LINE is the only code-written line | `TutorLoopTest::testHoldAfterTheRepairFailsToo`, `HostTest::testHost05EmptyAndTruncatedOutput`, `EngineUnitsTest::testCodeLinesUseNoInternalVocabulary` |
| Verdict conflict | An `issue` of type VERIFICATION_CONFLICT holds the attempt; the report says "result under review" | `CheatingTest::testAVerdictConflictHoldsTheAttempt` |
| Text guards log | Each finding recorded with the exact text, guard, problem and response; text shown unchanged; never repaired | `CheatingTest` (`loggedAndShown`), `LiveAdapterTest::testEmptyContentIsRepairedAndInternalWordsAreLogged` |
| Output contract | The second system message holds only the output contract | `EngineUnitsTest::testTheOutputContractHoldsNoBehaviourRules` |
| Problem identity | ANSWERED_PROBLEM and OPEN_PROBLEM carry id, version and text; both logged with each AI message | `TutorLoopTest::testAnsweredAndOpenProblemIdentities` |
| v1.5 binding (round 7; now v1.6, above) | The app loaded v1.5 only; a session bound to 1.3 was rebound | `HostTest::testHost13BindingModes` (now v1.6) |

## Round 6 Stage 1 tests (conductor §9)

Rows below describe round 6; where round 7 changed the behaviour (the pause, the code fallback, typed stuck phrases, rejected guard text), the round 7 rows above replace them.

| Item | What is checked | Test |
|---|---|---|
| Owner case 1 | `9X + 8` on 9(x + 8): read as 9x + 8, wrong, and the distribution-to-x-only signature reaches the AI | `tests/Integration/OwnerCasesTest.php::testCapitalXOnDistributionIsReadAsXAndTheSignatureReachesTheAi` |
| Owner case 2 | `16 because it's 4 x 4`: the AI reads 16, code marks it correct | `tests/Integration/OwnerCasesTest.php::testAnswerInASentenceIsReadByTheAiAndMarkedCorrectByCode` |
| Owner case 3 | `4 x x` in a how-many-x context: read as 4x, correct | `tests/Integration/OwnerCasesTest.php::testFourXXInAHowManyXContextIsReadAsFourX` |
| Owner case 4 | `7x + 25` on 7(x + 5): slip confirmed, micro-check checked by code, retry, run paused | `tests/Integration/OwnerCasesTest.php::testSlipIsConfirmedMicroCheckedRetriedAndTheRunPaused`; browser: slip pause at 390 and 1280 |
| Cheating 1–11 | The AI marks a goal; overrules a verdict; poses a problem with a wrong answer; poses an off-pattern check problem; a reading adds numbers; a slip the structure contradicts; text before a check card has its numbers; text leaks an answer; text claims mastery (and internal terms); a message during an open check problem; "without help" after a stuck entry. Also: a problem posed while a check is open | `tests/Integration/CheatingTest.php` (12 tests) |
| Tutor loop | One call per turn, pre-generated problems, the context built anew, reasoning per call (logged), one repair then code's fallback, holds and resume, every skill starts and scores, topic change, moves only among the ten skills | `tests/Integration/TutorLoopTest.php` |
| Calls per turn | Scripted mock sessions (5 B1, 3 descent, 2 B11, 2 N1): most turns use exactly one call | `tools/tutor_sessions.php` (gate step 04b, `test-results/mock-sessions/METRICS.md`); `TutorLoopTest::testRandomLearnersMostTurnsUseExactlyOneCall` |
| Instruments | Spec math, recipes in reverse, problem verification, reading check, entry classification, slips, gradebook, goals, text guards, each tried with an attempt to cheat it | `tests/Unit/Tutor/ProblemInstrumentsTest.php`, `tests/Unit/Tutor/LearnerInstrumentsTest.php` |
| Report | Code writes the report: four learner headings with fixed empty lines, plain words, no codes, titles (ID) for teachers, the audit list without AI text, download and escaping | `tests/Integration/ReportTest.php` |
| Conversation UI | Conversation journey, check card, pause, help, wrap-up and summary; slip pause; UI-01 to UI-03, UI-06, UI-07 at 360, 390 and 1280 wide | `tests/Browser/run_browser.mjs` |
| VPS bundle | `dist/algebra-engine-ai-vps-upload.zip` has exactly `algebra-engine-ai/` (only `index.html`, `api.php`, `assets/`) and `algebra-engine-ai-private/`; no dotfiles, no config.php, nothing in var/; its own private folder, database and cookie path; served at `/educational-engines/algebra-engine-ai/` with private paths refused | `tools/bundle_smoke.php` (gate step 07) |
| PHP 8.0 | The whole gate runs on PHP 8.0.30 and on 8.3 | `test-results/gate-php80/`, `test-results/gate-php83/` |
| Key leak | No tracked file or upload ZIP entry contains `sk-[A-Za-z0-9]{20,}` or the configured key | `tests/Integration/KeyLeakTest.php`; `tools/key_leak_scan.php` after each bundle build |

## Package scenarios

| ID | Title | Status | Test or reason |
|---|---|---|---|
| ENTRY-01 | Known topic | IMPLEMENTED | `tests/Integration/TutorLoopTest.php::testEntry01KnownTopic` |
| ENTRY-02 | Free-text mapping | DEFERRED | Deferred: free-text topic mapping (SUGGEST_TARGET/CONFIRM_TARGET) is out of beta scope (addendum §5). |
| ENTRY-03 | Unsure | DEFERRED | Deferred: "help me find a starting point" orientation is out of beta scope; the UNSURE route answers 422 FEATURE_NOT_IN_BETA. |
| ENTRY-04 | Public task | DEFERRED | Deferred: teacher task links are out of beta scope; task endpoints answer 422. |
| ENTRY-05 | Forged task | DEFERRED | Deferred with teacher task links: no task token is accepted at all, so no forged settings can be trusted. |
| ENTRY-06 | Orientation cap | DEFERRED | Deferred: orientation budget applies only to the orientation route, which is out of beta scope. |
| ENTRY-07 | Not learned | DEFERRED | Deferred as an orientation scenario. NOT_LEARNED outside orientation is implemented and never counted as incorrect (FlowTest/engine). |
| ENTRY-08 | Orientation success | DEFERRED | Deferred: orientation is out of beta scope. |
| FLOW-01 | Distribution journey | IMPLEMENTED | `tests/Integration/TutorLoopTest.php::testJourneyStepsBackAndReturnsWithGoalsComputedByCode` |
| FLOW-02 | Deeper repair | IMPLEMENTED | `tests/Integration/TutorLoopTest.php::testFlow02DeeperStepBackKeepsTheTopic` — Round 6: the AI steps back; code keeps the topic (invariant 8) and each goal counts only its own check questions. |
| FLOW-03 | Switch target | IMPLEMENTED (beyond addendum list) | `tests/Integration/TutorLoopTest.php::testChangeTopicSetsTheOpenProblemAsideAndTheTutorContinues` — Implemented although not in the addendum list: CHANGE_TOPIC is offered, so invariant 8 needs it. |
| FLOW-04 | Single correct probe | IMPLEMENTED | `tests/Integration/TutorLoopTest.php::testFlow04CorrectEasierAnswerIsNoSlipAndNoPass` |
| FLOW-05 | Alternative dependencies | IMPLEMENTED | `tests/Unit/Curriculum/CurriculumPathsTest.php::testFlow05AlternativeDependencies` — Round 6: the curriculum reader is kept as an instrument; moved to a unit test. |
| FLOW-06 | Budget boundary | RETIRED | Retired in round 6: the repair-cycle budget belonged to the retired state machine; the AI decides when to change approach (conductor round 6 §1). Observations stay observations: TutorLoopTest::testFlow04CorrectEasierAnswerIsNoSlipAndNoPass. |
| FLOW-07 | Stop while pending | IMPLEMENTED | `tests/Integration/HostTest.php::testEndWhilePending` |
| FLOW-08 | Resume | DEFERRED | Deferred: resume across sessions is out of beta scope (RESUME_SESSION of a closed session answers 422). Page reload of the live session is supported via the state endpoint. |
| EVID-01 | Hint independence | IMPLEMENTED | `tests/Integration/TutorLoopTest.php::testEvid01HelpIsRecordedBeforeTheHint` |
| EVID-02 | Freshness | IMPLEMENTED | `tests/Integration/TutorLoopTest.php::testEvid02ReusedCheckQuestionIsRejected` |
| EVID-03 | Numeric-only skill | IMPLEMENTED | `tests/Integration/ReportTest.php::testEvid03NumericOnlySkill` |
| EVID-04 | Immediate versus retention | IMPLEMENTED (beyond addendum list) | `tests/Integration/ReportTest.php::testEvid04ImmediateVersusRetention` — Implemented although not listed: protects invariant 10 (three successes are an immediate criterion only). |
| EVID-05 | Correction cascade | RETIRED | Retired in round 6: the operator correction tool belonged to the retired evidence ledger and is not rebuilt. Verdicts are recomputed from the gradebook; the test harness checks every recorded verdict against code after every request. |
| EVID-06 | Untested map | IMPLEMENTED | `tests/Integration/ReportTest.php::testEvid06UntestedSkillsCarryNoLabel` |
| MATH-01 | Exact rational | IMPLEMENTED | `tests/Integration/MathScenarioTest.php::testMath01ExactRational` |
| MATH-02 | Near number | IMPLEMENTED | `tests/Integration/MathScenarioTest.php::testMath02NearNumber` |
| MATH-03 | Polynomial expansion | IMPLEMENTED | `tests/Integration/MathScenarioTest.php::testMath03PolynomialExpansion` |
| MATH-04 | Complete roots | DEFERRED | Deferred: no beta skill uses solution sets; the solution-set checker is not implemented. |
| MATH-05 | Domain preservation | DEFERRED | Deferred: no beta skill uses rational expressions; domain-preserving simplification is not implemented. |
| MATH-06 | Requested form | IMPLEMENTED | `tests/Integration/MathScenarioTest.php::testMath06RequestedForm` |
| MATH-07 | Unsupported representation | DEFERRED | Deferred: no beta skill needs graphs. The capability boundary itself is enforced: out-of-beta skills are refused (422) and descent to them records "not available in beta" (B4). |
| MATH-08 | Unsafe syntax | IMPLEMENTED | `tests/Integration/MathScenarioTest.php::testMath08UnsafeSyntax` |
| MATH-09 | Ambiguity | IMPLEMENTED | `tests/Integration/MathScenarioTest.php::testMath09Ambiguity` |
| HOST-01 | Duplicate submit | IMPLEMENTED | `tests/Integration/HostTest.php::testHost01DuplicateSubmit` |
| HOST-02 | Idempotency conflict | IMPLEMENTED | `tests/Integration/HostTest.php::testHost02IdempotencyConflict` |
| HOST-03 | Stale tab | IMPLEMENTED | `tests/Integration/HostTest.php::testHost03StaleTab` |
| HOST-04 | Late lease | IMPLEMENTED (beyond addendum list) | `tests/Integration/HostTest.php::testHost04LateLeaseDiscarded` — Implemented although not listed: protects invariant 14 (leases prevent late results from changing state). |
| HOST-05 | Empty/truncated provider | IMPLEMENTED | `tests/Integration/HostTest.php::testHost05EmptyAndTruncatedOutput` |
| HOST-06 | Forged model promotion | IMPLEMENTED | `tests/Integration/HostTest.php::testHost06ForgedPromotionRejected` |
| HOST-07 | Missing curriculum | IMPLEMENTED | `tests/Integration/TutorLoopTest.php::testMissingCurriculumRecordHoldsWithoutInventingOne` |
| HOST-08 | Answer leakage | IMPLEMENTED | `tests/Integration/HostTest.php::testHost08NoAnswerLeakage` |
| HOST-09 | Prompt injection | IMPLEMENTED | `tests/Integration/HostTest.php::testHost09PromptInjectionIsData` |
| HOST-10 | Cross-session access | IMPLEMENTED | `tests/Integration/HostTest.php::testHost10CrossSessionAccessDenied` |
| HOST-11 | CSRF | IMPLEMENTED | `tests/Integration/HostTest.php::testHost11Csrf` |
| HOST-12 | Input preservation | IMPLEMENTED | `tests/Integration/HostTest.php::testHost12InputPreservedOnTimeout` |
| HOST-13 | Binding modes | IMPLEMENTED | `tests/Integration/HostTest.php::testHost13BindingModes` |
| HOST-14 | Context replacement | IMPLEMENTED (beyond addendum list) | `tests/Integration/HostTest.php::testHost14ContextReplacement` — Implemented although not listed: protects invariant 3 (each prompt is built anew; no stale inventory). |
| HOST-15 | Counter binding | IMPLEMENTED | `tests/Integration/HostTest.php::testHost15VerdictBinding` |
| HOST-16 | Forget/expiry | IMPLEMENTED | `tests/Integration/HostTest.php::testHost16ForgetAndExpiry` |
| HOST-17 | Cost limits | IMPLEMENTED | `tests/Integration/HostTest.php::testHost17CostLimits` |
| UI-01 | Mobile keyboard | IMPLEMENTED | `tests/Browser/run_browser.mjs (UI-01 mobile keyboard and status)` |
| UI-02 | Safe rendering | IMPLEMENTED | `tests/Browser/run_browser.mjs (UI-02 safe rendering)` |
| DEPLOY-01 | Private files | IMPLEMENTED | `tests/Integration/DeployCoverageTest.php::testDeploy01PrivateFilesNotServed` |
| DEPLOY-02 | Missing extension | IMPLEMENTED | `tests/Integration/DeployCoverageTest.php::testDeploy02MissingExtension` |
| COVER-01 | Capability matrix | IMPLEMENTED | `tests/Integration/DeployCoverageTest.php::testCover01CapabilityMatrix` |

Totals: 36 implemented from the addendum list, 4 implemented beyond it to protect an invariant, 11 deferred, 2 retired in round 6 (FLOW-06, EVID-05: they belonged to the retired state machine).
