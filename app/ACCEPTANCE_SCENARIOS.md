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
| B9 | Prompt assembly contains no anchor or example content | `tests/Integration/BetaGuardsTest.php::testB9PromptsCarryNoAnchorOrExampleContent` |

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
