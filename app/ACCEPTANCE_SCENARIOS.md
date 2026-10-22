# Acceptance scenarios — status in the beta

Source: `contracts/acceptance-scenarios.json` (53 scenarios). Map file: `app/tests/acceptance-map.json` (checked by `tests/Integration/ScenarioMapTest.php`).

## Addendum beta tests (§6)

| ID | Scenario | Test |
|---|---|---|
| B1 | Q07 distribution signature → probe → E06 → Socratic repair → E06 criterion → fresh Q07 ×3 → report | `tests/Integration/BetaJourneyTest.php::testB1DistributionJourney; browser: tests/Browser/run_browser.mjs at 360x740 and 1280x800` |
| B2 | Wrong Q07 → probe correct → no descent; streak stays broken | `tests/Integration/BetaJourneyTest.php::testB2ProbeCorrectNoDescent` |
| B3 | Q07 → Q06 → Q05 → N06 and back up; each parent re-verified | `tests/Integration/BetaJourneyTest.php::testB3DeepDescentAndReturn, ::testB3PrerequisiteSuccessStopsDescent` |
| B4 | Descent reaching Q03/A07 → not available in beta; not a learner failure | `tests/Integration/BetaJourneyTest.php::testB4BetaBoundary (and A07 in B1)` |
| B5 | Model proposes a non-prerequisite skill → rejected | `tests/Integration/BetaGuardsTest.php::testB5NonPrerequisiteProposalRejected` |
| B6 | Model expected_answer disagrees → host key governs; item flagged | `tests/Integration/BetaGuardsTest.php::testB6HostKeyGovernsAndItemFlagged` |
| B7 | Hint during an independent item → assisted; streak does not advance | `tests/Integration/BetaGuardsTest.php::testB7HintMakesItemAssisted` |
| B8 | Disclosure guard rejects a repair message containing the answer | `tests/Integration/BetaGuardsTest.php::testB8DisclosureGuardRejectsLeak, ::testB8RepeatedLeakHolds` |
| B9 | Prompt assembly contains no anchor or example content, and no reference to `examples/model-question.json` (extended in Round 2, ruling 4) | `tests/Integration/BetaGuardsTest.php::testB9PromptsCarryNoAnchorOrExampleContent` |
| B10 | Round 2 ruling 8: stuck at the smallest guided question → PREREQUISITE_CHECK on the declared prerequisite it exercises, with no independent check or probe first; if that skill is outside the beta or not a declared prerequisite → boundary recorded as in B4 | `tests/Integration/BetaJourneyTest.php::testB10StuckStepGoesDirectlyToItsPrerequisite, ::testB10StuckStepAtABoundaryIsRecordedNotFailed` |
| B11 | Round 4 §4, updated by round 5 R5-C: messy learner. Offline: an empty submit and a typo keep their clarification; `is this on the test?` and a number word get the R5-C host lines; none is scored as wrong; the item stays open and the correction then counts once; `x = 3`, `x=3`, spaces, `3.0`, `6/2`, a Unicode minus, `X=3`, `X = 3`, `x = 3?` and `-3?` are CORRECT. `idk` and asking for the answer are B15. Live: `tools/live_sessions.php --b11=N` (round 5 adds `-2?`, `idk`, `IDK.`, `i dont know` and one Explain request) | `tests/Integration/MessyLearnerTest.php`; live: `test-results/live/round4/`, `test-results/live/round5/` |
| B12 | Round 4 R4-A: a re-ask of a previously failed guided step (after a smaller question, or in alternate phrasing) shows the host line and the item only; no model call, no model text | `tests/Integration/BetaJourneyTest.php::testB12ReAskShowsNoModelText` |
| B13 | Round 4 R4-B: for each N06 sign pattern (N06 items always have a negative operand, so + ÷ −, − ÷ +, − ÷ −), the first guided problem and the next fresh one keep the failed item's pattern; the gate rejects a proposal that changes a kept feature | `tests/Integration/BetaJourneyTest.php::testB13RepairKeepsTheFailedSignPattern`; `tests/Unit/Items/TemplatesTest.php::testStructuralFeaturesAreKeptByThePlanner, ::testGateRejectsAProposalThatChangesAKeptFeature` |
| B14 | Round 5 R5-A: no independent item (target, probe, prerequisite check, independent check, return) is shown in the same turn as model-written text. The model still proposes the item's parameters through the same gate; its learner text is discarded unread (not shown, not stored as learner-visible) and the host line "Here is one to try on your own." is shown. Checked after every request of every integration test by the harness | `tests/Support/Harness.php::checkB14` (all integration tests); `tests/Integration/IndependentEvidenceTest.php::testB14IndependentItemsShowHostLineOnly, ::testB14DiscardedTextIsNotStored`; live: runner column "Independent items shown / with model text" |
| B14b | Round 5 R5-A, updated by round 5.1 P2: a criterion message saying "without help" is produced only when every counted item was independent, had no hint and passed B14. An answer given after a hint resets the run (owner rule), so a hint on the third correct answer means the criterion is met on answers 4, 5 and 6. An item recorded as shown with model text never counts | `tests/Integration/IndependentEvidenceTest.php::testB14bHintMeansNoWithoutHelpMessage, ::testB14bItemShownWithModelTextIsNotCounted`; `tests/Integration/EvidenceTest.php::testEvid01HintIndependence` |
| B15 | Round 5 R5-C: stuck and off-task entries, every row in both columns (guided repair: smaller question; independent item: the existing hint path, item assisted), the trailing-"?" rule, the normalisation and the flow after a stuck entry in each independent mode (round 5.1: a probe then gets a second probe, B16). No table entry is scored as incorrect. Round 5.1 P1: asking for the answer shows the line for what follows (hint, smaller step, another way, prerequisite check; a boundary: "I won't give the answer, but let's try a different problem.") | `tests/Integration/StuckEntryTest.php` (P1: `::testAskForAnswerLineNamesWhatFollows`) |
| B16 | Round 5.1 P4: a probe answered after a hint (right or wrong) is inconclusive and a second fresh probe follows; correct with no help → error not reproduced, resume; wrong, or answered after a hint or a stuck entry → breakdown confirmed, descend along the original error's branch. At most two probes per diagnosis, skips included | `tests/Integration/ProbeAfterHelpTest.php` |
| P3 | Round 5.1: model text may not say on your own, without help, independently, by yourself or no help (the four live round 5 lines are rejected); host lines may | `tests/Unit/Engine/EngineUnitsTest.php::testSupportClaimsInModelTextOnly`; `tests/Integration/BetaGuardsTest.php::testSupportClaimInModelTextIsRepairedNotShown` |
| Summary wording | Round 5.1 ruling 4: every prose string in the session summary passes the R5-B check ("with help", "the answer the app computed", "goal met: 3 correct in a row without help"); field names and stored codes are unchanged | `tests/Integration/SummaryWordingTest.php`; `tests/Browser/run_browser.mjs` (B1: no internal terms on the summary page) |
| R5-B | Round 5: internal vocabulary (expected value, expected answer, answer key, matched the key, guided step, host, assisted, verdict, criterion; plurals included) is rejected in model text (bounded repair) and never appears in host lines | `tests/Unit/Engine/EngineUnitsTest.php::testInternalVocabulary, ::testHostLinesUseNoInternalVocabulary`; `tests/Integration/BetaGuardsTest.php::testInternalVocabularyIsRepairedNotShown`; `tests/Integration/LiveAdapterTest.php::testEmptyContentAndInternalWordsAreRepairedNotShown` |
| Guard (R5) | Round 5 ruling 2: positive/negative/plus/minus (and plurals) followed by an operand noun (sign, signs, divisor, dividend, number, numbers, factor, term, constant, coefficient) are exempt; all 8 messages rejected in round 4 06-B3 pass; "the quotient is negative", "the answer will be negative", "it comes out negative", "two negatives make a positive" are rejected | `tests/Unit/Engine/EngineUnitsTest.php::testGuardOperandNounExemption` |
| UI-03 | Round 2 §3: the skill map shows the 10 beta skills by default; "Show full map" shows all 205 with unchanged runtime status labels | `tests/Browser/run_browser.mjs` (UI-03, 360x740) |
| Key leak | Round 2 §1.2: no tracked file or upload ZIP entry contains `sk-[A-Za-z0-9]{20,}` or the configured key | `tests/Integration/KeyLeakTest.php`; also `tools/key_leak_scan.php` after each bundle build |

## Package scenarios

| ID | Title | Status | Test or reason |
|---|---|---|---|
| ENTRY-01 | Known topic | IMPLEMENTED | `tests/Integration/FlowTest.php::testEntry01KnownTopic` |
| ENTRY-02 | Free-text mapping | DEFERRED | Deferred: free-text topic mapping (SUGGEST_TARGET/CONFIRM_TARGET) is out of beta scope (addendum §5). |
| ENTRY-03 | Unsure | DEFERRED | Deferred: "help me find a starting point" orientation is out of beta scope; the UNSURE route answers 422 FEATURE_NOT_IN_BETA. |
| ENTRY-04 | Public task | DEFERRED | Deferred: teacher task links are out of beta scope; task endpoints answer 422. |
| ENTRY-05 | Forged task | DEFERRED | Deferred with teacher task links: no task token is accepted at all, so no forged settings can be trusted. |
| ENTRY-06 | Orientation cap | DEFERRED | Deferred: orientation budget applies only to the orientation route, which is out of beta scope. |
| ENTRY-07 | Not learned | DEFERRED | Deferred as an orientation scenario. NOT_LEARNED outside orientation is implemented and never counted as incorrect (FlowTest/engine). |
| ENTRY-08 | Orientation success | DEFERRED | Deferred: orientation is out of beta scope. |
| FLOW-01 | Distribution journey | IMPLEMENTED | `tests/Integration/FlowTest.php::testFlow01DistributionJourney` |
| FLOW-02 | Deeper repair | IMPLEMENTED | `tests/Integration/FlowTest.php::testFlow02DeeperRepair` |
| FLOW-03 | Switch target | IMPLEMENTED (beyond addendum list) | `tests/Integration/FlowTest.php::testFlow03SwitchTarget` — Implemented although not in the addendum list: CHANGE_TARGET is offered in the beta, so invariant 8 (interrupted work survives topic changes) needs it. |
| FLOW-04 | Single correct probe | IMPLEMENTED | `tests/Integration/FlowTest.php::testFlow04SingleCorrectProbe` |
| FLOW-05 | Alternative dependencies | IMPLEMENTED | `tests/Integration/FlowTest.php::testFlow05AlternativeDependencies` |
| FLOW-06 | Budget boundary | IMPLEMENTED | `tests/Integration/FlowTest.php::testFlow06BudgetBoundary` |
| FLOW-07 | Stop while pending | IMPLEMENTED | `tests/Integration/FlowTest.php::testFlow07StopWhilePending` |
| FLOW-08 | Resume | DEFERRED | Deferred: resume across sessions is out of beta scope (RESUME_SESSION of a closed session answers 422). Page reload of the live session is supported via the state endpoint. |
| EVID-01 | Hint independence | IMPLEMENTED | `tests/Integration/EvidenceTest.php::testEvid01HintIndependence` |
| EVID-02 | Freshness | IMPLEMENTED | `tests/Integration/EvidenceTest.php::testEvid02Freshness` |
| EVID-03 | Numeric-only skill | IMPLEMENTED | `tests/Integration/EvidenceTest.php::testEvid03NumericOnlySkill` |
| EVID-04 | Immediate versus retention | IMPLEMENTED (beyond addendum list) | `tests/Integration/EvidenceTest.php::testEvid04ImmediateVersusRetention` — Implemented although not listed: protects invariant 10 (three successes are an immediate criterion only). |
| EVID-05 | Correction cascade | IMPLEMENTED (beyond addendum list) | `tests/Integration/EvidenceTest.php::testEvid05CorrectionCascade` — Implemented although not listed: protects invariants 6 and 9 (results bound to evidence; corrections recompute claims). Operator-only correction, no public endpoint. |
| EVID-06 | Untested map | IMPLEMENTED | `tests/Integration/EvidenceTest.php::testEvid06UntestedMap` |
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
| HOST-07 | Missing curriculum | IMPLEMENTED | `tests/Integration/HostTest.php::testHost07MissingCurriculum` |
| HOST-08 | Answer leakage | IMPLEMENTED | `tests/Integration/HostTest.php::testHost08NoAnswerLeakage` |
| HOST-09 | Prompt injection | IMPLEMENTED | `tests/Integration/HostTest.php::testHost09PromptInjectionIsData` |
| HOST-10 | Cross-session access | IMPLEMENTED | `tests/Integration/HostTest.php::testHost10CrossSessionAccessDenied` |
| HOST-11 | CSRF | IMPLEMENTED | `tests/Integration/HostTest.php::testHost11Csrf` |
| HOST-12 | Input preservation | IMPLEMENTED | `tests/Integration/HostTest.php::testHost12InputPreservedOnTimeout` |
| HOST-13 | Binding modes | IMPLEMENTED | `tests/Integration/HostTest.php::testHost13BindingModes` |
| HOST-14 | Context replacement | IMPLEMENTED (beyond addendum list) | `tests/Integration/HostTest.php::testHost14ContextReplacement` — Implemented although not listed: protects invariant 3 (each prompt is built anew; no stale inventory). |
| HOST-15 | Counter binding | IMPLEMENTED | `tests/Integration/HostTest.php::testHost15CounterBinding` |
| HOST-16 | Forget/expiry | IMPLEMENTED | `tests/Integration/HostTest.php::testHost16ForgetAndExpiry` |
| HOST-17 | Cost limits | IMPLEMENTED | `tests/Integration/HostTest.php::testHost17CostLimits` |
| UI-01 | Mobile keyboard | IMPLEMENTED | `tests/Browser/run_browser.mjs (UI-01 mobile keyboard and status)` |
| UI-02 | Safe rendering | IMPLEMENTED | `tests/Browser/run_browser.mjs (UI-02 safe rendering)` |
| DEPLOY-01 | Private files | IMPLEMENTED | `tests/Integration/DeployCoverageTest.php::testDeploy01PrivateFilesNotServed` |
| DEPLOY-02 | Missing extension | IMPLEMENTED | `tests/Integration/DeployCoverageTest.php::testDeploy02MissingExtension` |
| COVER-01 | Capability matrix | IMPLEMENTED | `tests/Integration/DeployCoverageTest.php::testCover01CapabilityMatrix` |

Totals: 37 implemented from the addendum list, 5 implemented beyond it to protect an invariant, 11 deferred. All 37 scenarios the addendum lists are implemented.
