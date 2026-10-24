# Tests retired in round 6

Conductor round 6 §1 retired the beta's state machine as the controller of the
session. The tests below asserted its sequencing (target, probe, prerequisite
check, guided repair, independent check, return) or its fixed host lines, and
were retired with it. Where the behaviour still matters, the replacement is
named.

| Retired test | Reason | Replacement |
|---|---|---|
| BetaJourneyTest::testB1DistributionJourney | Asserted the fixed probe → descent → guided → independent → return sequence. | TutorLoopTest::testJourneyStepsBackAndReturnsWithGoalsComputedByCode (FLOW-01) |
| BetaJourneyTest::testB2ProbeCorrectNoDescent | Probe step of the retired controller. | TutorLoopTest::testFlow04CorrectEasierAnswerIsNoSlipAndNoPass |
| BetaJourneyTest::testB3DeepDescentAndReturn | Descent stack of the retired controller. | TutorLoopTest::testFlow02DeeperStepBackKeepsTheTopic |
| BetaJourneyTest::testB3PrerequisiteSuccessStopsDescent | Descent stopping rule; the AI now chooses when to return. | None (the topic is kept: FLOW-02) |
| BetaJourneyTest::testB4BetaBoundary | Boundary record written by the descent. | TutorLoopTest::testMovesStayInsideTheTutorsSkills; ReportTest::testOutsideSkillIsListedOnlyWhileADependentGoalIsUnmet |
| BetaJourneyTest::testB10StuckStepGoesDirectlyToItsPrerequisite | Guided steps are retired. | None |
| BetaJourneyTest::testB10StuckStepAtABoundaryIsRecordedNotFailed | Guided steps are retired. | Set-aside problems are not failures: TutorLoopTest::testChangeTopicSetsTheOpenProblemAsideAndTheTutorContinues |
| BetaJourneyTest::testRandomScriptedJourneysCompleteWithoutHolds | Scripted the old controller. | TutorLoopTest::testRandomLearnersMostTurnsUseExactlyOneCall |
| BetaJourneyTest::testB12ReAskShowsNoModelText | Guided re-ask lines are retired. | None |
| BetaJourneyTest::testB13RepairKeepsTheFailedSignPattern | The structural-feature planner is retired; the AI chooses practice problems and code verifies them. | ProblemInstrumentsTest (problem verification) |
| BetaJourneyTest::testEveryBetaSkillCanStartAndScore | Ran through the old controller. | TutorLoopTest::testEveryBetaSkillCanStartAndScore |
| BetaGuardsTest::testB5NonPrerequisiteProposalRejected | Proposal rules of the state machine. | TutorLoopTest::testMovesStayInsideTheTutorsSkills |
| BetaGuardsTest::testB6HostKeyGovernsAndItemFlagged | Old acceptance gate. | CheatingTest::testTheAiPosesAProblemWithAWrongAnswer |
| BetaGuardsTest::testB7HintMakesItemAssisted | Old evidence events. | TutorLoopTest::testEvid01HelpIsRecordedBeforeTheHint |
| BetaGuardsTest::testB8DisclosureGuardRejectsLeak | Old repair path. | CheatingTest::testTextLeaksAnAnswer |
| BetaGuardsTest::testInternalVocabularyIsRepairedNotShown | Old repair path. | CheatingTest::testTextClaimsMasteryOrUsesInternalTerms |
| BetaGuardsTest::testSupportClaimInModelTextIsRepairedNotShown | Old repair path. | EngineUnitsTest::testSupportClaimsInModelTextOnly; LearnerInstrumentsTest::testGuardsOnLearnerVisibleText |
| BetaGuardsTest::testB8RepeatedLeakHolds | A repeated failure now uses a code fallback, not a hold (§3.5). Round 7 restores the hold. | TutorLoopTest::testHoldAfterTheRepairFailsToo |
| BetaGuardsTest::testD1HintAndIDontKnowGiveSmallerQuestions | Guided smaller questions are retired. | CheatingTest::testWithoutHelpIsNeverRecordedAfterAStuckEntry |
| BetaGuardsTest::testRepairOpeningNeverHoldsOnItsOwnFacts | Guided repair openings are retired. | None |
| BetaGuardsTest::testB9PromptsCarryNoAnchorOrExampleContent | Old prompt assembler. | EngineUnitsTest::testTutorPromptSkeletonAndShape; TutorLoopTest::testEveryCallCarriesFreshPreparedProblemsAndIsBuiltAnew |
| EvidenceTest::testEvid01HintIndependence | Old evidence ledger. | TutorLoopTest::testEvid01HelpIsRecordedBeforeTheHint |
| EvidenceTest::testEvid02Freshness | Old evidence ledger. | TutorLoopTest::testEvid02ReusedCheckQuestionIsRejected |
| EvidenceTest::testEvid03NumericOnlySkill | Old criteria calculator. | ReportTest::testEvid03NumericOnlySkill |
| EvidenceTest::testEvid04ImmediateVersusRetention | Old criteria calculator. | ReportTest::testEvid04ImmediateVersusRetention |
| EvidenceTest::testEvid05CorrectionCascade | The operator correction tool belonged to the old evidence ledger and is not rebuilt; verdicts are recomputed from the gradebook (the harness checks every verdict after every request). | None (EVID-05 retired) |
| EvidenceTest::testEvid06UntestedMap | Old report builder. | ReportTest::testEvid06UntestedSkillsCarryNoLabel |
| FlowTest::testEntry01KnownTopic | Old target episode. | TutorLoopTest::testEntry01KnownTopic |
| FlowTest::testFlow01DistributionJourney | Old controller. | TutorLoopTest::testJourneyStepsBackAndReturnsWithGoalsComputedByCode |
| FlowTest::testFlow02DeeperRepair | Old descent stack. | TutorLoopTest::testFlow02DeeperStepBackKeepsTheTopic |
| FlowTest::testFlow03SwitchTarget | Old episode suspension. | TutorLoopTest::testChangeTopicSetsTheOpenProblemAsideAndTheTutorContinues |
| FlowTest::testFlow04SingleCorrectProbe | Old probe. | TutorLoopTest::testFlow04CorrectEasierAnswerIsNoSlipAndNoPass |
| FlowTest::testFlow05AlternativeDependencies | Moved, unchanged in substance. | CurriculumPathsTest::testFlow05AlternativeDependencies |
| FlowTest::testFlow06BudgetBoundary | Repair-cycle budget of the old controller; the AI decides when to change approach. Observations stay observations. | None (FLOW-06 retired); TutorLoopTest::testFlow04CorrectEasierAnswerIsNoSlipAndNoPass checks the wording |
| FlowTest::testFlow07StopWhilePending | Old engine. | HostTest::testEndWhilePending |
| IndependentEvidenceTest::testB14IndependentItemsShowHostLineOnly | Round 6 §5.2 allows tutor text before a check card under the number and leak rules. | CheatingTest::testTextBeforeACheckCardContainsItsNumbers; ReportTest::testNoAiTextInTheReportAndTheAuditList |
| IndependentEvidenceTest::testB14DiscardedTextIsNotStored | AI text before a check card is now kept for the audit list. | ReportTest::testNoAiTextInTheReportAndTheAuditList |
| IndependentEvidenceTest::testB14bHintMeansNoWithoutHelpMessage | Old fixed lines. | CheatingTest::testWithoutHelpIsNeverRecordedAfterAStuckEntry |
| IndependentEvidenceTest::testB14bItemShownWithModelTextIsNotCounted | A check card after guarded tutor text now counts (§5.2). | None |
| MessyLearnerTest::testB11UnreadableInputsAreNeverScoredAsWrong | Old entry interpreter. | MathScenarioTest::testMath08UnsafeSyntax, testMath09Ambiguity; LearnerInstrumentsTest::testOtherEntries |
| MessyLearnerTest::testB11CorrectAnswersInLooseFormsAreAccepted | Old entry interpreter. | OwnerCasesTest; LearnerInstrumentsTest::testOwnerCasesAreClassifiedBeforeAnyCall |
| ProbeAfterHelpTest (4 tests) | Probe sequencing after help (round 5.1 P4) of the old controller. | Help still resets the run: TutorLoopTest::testEvid01HelpIsRecordedBeforeTheHint |
| StuckEntryTest::testStuckAndAskForAnswerInGuidedRepair | Guided repair is retired. | None |
| StuckEntryTest::testStuckAndAskForAnswerOnIndependentItem | Old modes. | CheatingTest::testWithoutHelpIsNeverRecordedAfterAStuckEntry |
| StuckEntryTest::testAskForAnswerLineNamesWhatFollows | Fixed host lines; the AI now writes the reply. | None |
| StuckEntryTest::testOffTaskQuestionAndNumberWords | Questions now go to the AI (§2.6 allows questions). | LearnerInstrumentsTest::testOtherEntries |
| StuckEntryTest::testCapitalXIsReadAsX | Old entry interpreter. | OwnerCasesTest::testCapitalXOnDistributionIsReadAsXAndTheSignatureReachesTheAi |
| StuckEntryTest::testTrailingQuestionMarkIsScoredNormally | Old entry interpreter. | LearnerInstrumentsTest::testOtherEntries |
| StuckEntryTest::testNormalisation | Old entry interpreter. | LearnerInstrumentsTest::testOwnerCasesAreClassifiedBeforeAnyCall |
| StuckEntryTest::testStuckEntryFlowInEachIndependentMode | Old modes. | None |
| UiRoundTest::testU1FeedbackBeforeFirstGuidedProblemNamesTheMissedProblem | Guided feedback is retired; the result line echoes the entry. | OwnerCasesTest (result lines with "Read as …") |
| UiRoundTest::testU1GuidedStepsKeepTheirProblemOnScreen | Guided steps are retired; the open problem is pinned above the input. | Browser test (M3) |
| UiRoundTest::testU7GoalLineAndReset | Old goal line. | Browser test and projection (M3) |
| UiRoundTest::testU7BuildingBlockAndNumericOnlySkill | Old goal line. | ReportTest::testEvid03NumericOnlySkill |
| UiRoundTest::testU6GuidedLabelCarriesNoNotice | Guided labels are retired. | None |
| UiRoundTest::testU8ResultWords | Old result sentences. | CheatingTest::testTheAiOverrulesAVerdict; MathScenarioTest::testMath06RequestedForm |
| UiRoundTest::testU4WorkingRemoved | The event contract has no working field. | None |
| UpgradeTest::testLiveSessionContinuesAfterTheUpgrade | Round 6 is a separate install with its own database (§11); there is no in-place upgrade from the beta. Its fixture and fixture tool are removed. | None |
| UpgradeTest::testMigrationIsSafeToRunTwice | Used the beta fixture. | HostTest::testMigrationsAreSafeToRunTwice |
| SummaryWordingTest::testSummaryProseUsesPlainWords | Old report builder. | ReportTest::testSummaryLevelsUsePlainWordsAndNoCodes |
| SummaryWordingTest::testSummaryLevelsUsePlainWordsAndNoCodes | Old report builder. | ReportTest::testSummaryLevelsUsePlainWordsAndNoCodes |
| SummaryWordingTest::testPlainWordsForStoredText | Old report builder. | ReportTest::testPlainWordsForStoredText |
| ReportDownloadTest (5 tests) | Old report builder. | ReportTest (same five behaviours) |
| EngineUnitsTest::testTransitionGuardFollowsWorkflowPolicy | The workflow policy is retired. | None |
| EngineUnitsTest::testCriteriaRequireConsecutiveVariedIndependentSuccesses | The criteria calculator is retired. | LearnerInstrumentsTest (goals) |
| EngineUnitsTest::testHostLinesUseNoInternalVocabulary | Scanned the old engine. | EngineUnitsTest::testCodeLinesUseNoInternalVocabulary |
| EngineUnitsTest::testEnvelopeSkeletonHasPlaceholdersOnly | Old prompt assembler. | EngineUnitsTest::testTutorPromptSkeletonAndShape |
| TemplatesTest::testEveryGuidedStepIsTaggedWithTheSkillItExercises | Guided plans are retired. | None |
| TemplatesTest::testStructuralFeaturesAreKeptByThePlanner | The planner is retired. | None |
| TemplatesTest::testGateRejectsAProposalThatChangesAKeptFeature | The acceptance gate is retired. | ProblemInstrumentsTest |
| TemplatesTest::testOpeningFactsNeverContainAStepAnswer | Guided plans are retired. | None |
| TemplatesTest::testGuidedPlansAreQuestionsThatNeverStateTheirAnswer | Guided plans are retired. | ProblemInstrumentsTest::testPracticeWordingMustShowTheSpecAndNotItsAnswer |

## Round 7 (CONDUCTOR_ROUND_7.md)

Tests whose behaviour round 7 changed on purpose; each is replaced, none was deleted to get green.

| Round 6 test | Why it changed | Replaced by |
| --- | --- | --- |
| TutorLoopTest::testFallbackAfterTheRepairFailsToo | §4.2: no code fallback; a repeated failure holds the turn with a retry | TutorLoopTest::testHoldAfterTheRepairFailsToo |
| CheatingTest::testAFreeTextMessageDuringAnOpenCheckProblem (pause) | §6.1: the pause is deleted; the message reaches the AI | CheatingTest::testAFreeTextMessageDuringAnOpenCheckProblem (rewritten) |
| CheatingTest::testWithoutHelpIsNeverRecordedAfterAStuckEntry | §6.3: typed "idk" is a message, not "I'm stuck" | CheatingTest::testTypedIdkDuringACheckIsAMessageNotHelp, ::testIAmStuckDuringACheckIsRecordedBeforeTheAiCall |
| CheatingTest::testTextBeforeACheckCardContainsItsNumbers | §5: a shared number is a signal, not a rejection | CheatingTest::testTextBeforeACheckCardSharesANumber, ::testTextBeforeACheckCardWithAStrategyPhraseHoldsTheAttempt, ::testTextBeforeACheckCardWithItsAnswerHoldsTheAttempt |
| CheatingTest: mastery, overrule, leak, internal terms (rejected and repaired) | §4.1: text guards log; the text is shown unchanged | The same tests, asserting the logged finding and the unchanged text |
| ProblemInstrumentsTest::testPracticeWordingMustShowTheSpecAndNotItsAnswer | §8.1: code renders practice wording from the spec | ProblemInstrumentsTest::testPracticeWordingIsRenderedByCodeFromTheSpec |
| LiveAdapterTest::testEmptyContentAndInternalWordsAreRepairedNotShown | §4: internal words are a logged finding; empty content is still repaired | LiveAdapterTest::testEmptyContentIsRepairedAndInternalWordsAreLogged |
| MathScenarioTest::testMath09Ambiguity (code rewrite line) | §7.4: unreadable entries go to the AI for a reading | MathScenarioTest::testMath09Ambiguity (rewritten) |

## Round 8 (CONDUCTOR_ROUND_8.md)

Tests whose behaviour round 8 changed on purpose; each is replaced, none was deleted to get green.

| Round 7 test | Why it changed | Replaced by |
| --- | --- | --- |
| CheatingTest::testAFreeTextMessageDuringAnOpenCheckProblem | §3.1: a help request on an open check is helped and recorded; the check becomes practice | CheatingTest::testAHelpRequestDuringAnOpenCheckIsHelpedAndRecorded, ::testHintExplainAndAnswerQuestionDuringAnOpenCheckCountAsHelp, ::testTheBackstopDuringAnOpenCheck |
| CheatingTest::testTypedIdkDuringACheckIsAMessageNotHelp | §3.1: the tutor's help move records help; code still never classifies typed text | CheatingTest::testTypedIdkDuringACheckIsAMessageNotCodeClassifiedHelp |
| CheatingTest::testTheAiPosesAProblemWhileACheckIsOpen (purpose stays check) | §3.1: a check with help is shown as practice | The same test, asserting the same open problem, shown as practice |
| HostTest::testASessionBoundTo13IsReboundTo15 | §2.1: v1.6 governs; a session bound to 1.5 is rebound | HostTest::testASessionBoundTo15IsReboundTo16 |
| ProblemInstrumentsTest::testPregeneratedProblemsRarelyShowTheirAnswer | §3.6: prepared problems must satisfy the check rule, always | ProblemInstrumentsTest::testPreparedProblemsSatisfyTheCheckRule |
| ProblemInstrumentsTest::testPracticeWordingIsRenderedByCodeFromTheSpec (practice answer rejected) | §3.6: practice problems are accepted whatever their numbers | The same test: practice accepted, check rejected with ANSWER_IN_QUESTION |
| LearnerInstrumentsTest::testTextBeforeACheckCard ("next", "try" hold) | §3.3: neutral invitations are logged, not held; §3.5: the previous answer is a signal | The same test, rewritten |
| ReportTest::testNoAiTextInTheReportAndTheAuditList ("next", "try" in the audit list) | §3.3 | The same test: only "remember" holds; "next" and "try" are logged invitations |
| Browser journey: message during the check stays unassisted | §3.1 | Browser journey: a help request during the check is recorded and the check becomes practice |


## Round 9 (CONDUCTOR_ROUND_9.md)

Tests whose behaviour round 9 changed on purpose (§3 lists the reversed round 8 behaviours); each is replaced, none was deleted to get green.

| Round 8 test | Why it changed | Replaced by |
| --- | --- | --- |
| CheatingTest::testTextBeforeACheckCardWithAStrategyPhraseHoldsTheAttempt | §3.2: every text detector logs only | CheatingTest::testTextBeforeACheckCardWithAStrategyPhraseIsASignal |
| CheatingTest::testTextBeforeACheckCardWithItsAnswerHoldsTheAttempt | §3.2 | CheatingTest::testTextBeforeACheckCardWithItsAnswerIsASignal |
| CheatingTest::testTheCheckAnswerBeforeACardFromThePreviousAnswerIsASignal (from nowhere: held) | §3.2 | CheatingTest::testTheCheckAnswerBeforeACardIsASignal |
| CheatingTest::testAnAnswerInTheQuestionAcceptedForPracticeAndReplacedForACheck | §3.1: the answer-in-question rule and the substitution are removed | CheatingTest::testAnAnswerInTheQuestionIsAcceptedForACheck, Round9Test::testRemovedRules |
| CheatingTest::testHintExplainAndAnswerQuestionDuringAnOpenCheckCountAsHelp | §3.3: only HELP and HINT count as help | CheatingTest::testOnlyHelpAndHintDuringAnOpenCheckCountAsHelp, Round9Test::testAnswerQuestionDuringACheckRecordsNoHelp |
| CheatingTest::testTheBackstopDuringAnOpenCheck | §3.2: the backstop is a signal | CheatingTest::testTheBackstopDuringAnOpenCheckIsASignal |
| CheatingTest::testAnAnswerLeakDuringAnOpenCheckHoldsTheAttempt | §3.2 | CheatingTest::testAnAnswerLeakDuringAnOpenCheckIsLogged |
| CheatingTest::testAHelpRequestDuringAnOpenCheckIsHelpedAndRecorded (help only) | §4.3: the help reply may carry a practice step; the helped check waits as the original | The same test, rewritten; Round9Test::testHelpAndASmallerStepTogether |
| CheatingTest::testTheAiPosesAProblemWhileACheckIsOpen (rejected after "I'm stuck") | §4.3.5: after help, ALLOWED.may_pose_problem is true | The same test: rejected without help, accepted after help |
| ProblemInstrumentsTest::testPracticeWordingIsRenderedByCodeFromTheSpec (check with its answer rejected) | §3.1 | The same test: the check is accepted |
| ProblemInstrumentsTest::testPreparedProblemsSatisfyTheCheckRule | §3.1: prepared problems prefer no overlap and accept it | ProblemInstrumentsTest::testPreparedProblemsPreferNoOverlapAndAcceptIt |
| LearnerInstrumentsTest::testOtherSlipSignatures (Q07 off by one confirmed as a slip) | §4.4.2: a bare value of x is never eligible | The same test: signatures only, with the new observation string |
| LearnerInstrumentsTest::testTextBeforeACheckCard (hold flag) | §3.2 | The same test, signals only |
| ReportTest::testNoAiTextInTheReportAndTheAuditList ("The answer to it is held.") | §3.2 | The same test, without the hold sentence |
| HostTest::testHost09PromptInjectionIsData (TRUSTED_SESSION values) | §4.1 and the owner's decision: TRUSTED_SESSION removed | The same test: no identity block; the revision is code's |
| HostTest::testASessionBoundTo15IsReboundTo16 | §2.1: v1.7 governs | HostTest::testASessionBoundTo16IsReboundTo17 |
| EngineUnitsTest::testTheOutputContractHoldsNoBehaviourRules (round 8 sentences) | §4.1: the exact edits | The same test, asserting each §4.1 sentence word for word |
| Browser journey: help during the check shows the check as practice | §4.3 | Browser journey: help reply, then the practice step, then the helped check back as practice |
