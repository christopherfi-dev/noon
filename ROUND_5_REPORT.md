# Algebra Engine — round 5 report

Repository `christopherfi-dev/noon`, branch `claude/elegant-volta-blu3uj`.
Round 5 ran from 2026-10-22T06:59:22Z (see `BUILD_LOG.md`, "Round 5"),
following `CONDUCTOR_ROUND_5_v3.md`. Only v3 of the instructions reached this
repository. Nothing was deployed. The order follows §9 of the beta addendum,
then the items round 5 §5–§6 add.

## 1. Tests run, with pass/fail counts

**Final gate, 09:53:50Z–09:54:53Z** (no key configured):

| Step | Result |
|---|---|
| F1 manifest, handoff preflight, curriculum validator and guards | PASS |
| App preflight | PASS. 2 WARN: PHP 8.3 rather than 8.4/8.5; the scratch mock config is mode 644 (it holds no key) |
| PHPUnit | **189 passed, 0 failed** (105 unit, 84 integration; 38,530 assertions) |
| Browser (B1 ×2 widths, UI-01, UI-02, UI-03) | **5 passed, 0 failed** |
| Bundle build and deployment-layout smoke test | PASS, 12/12 |
| Key-leak scan | PASS: 0 hits over all tracked files and upload ZIP entries |

Earlier gates also passed:
- **Offline gate, 07:23:26Z–07:24:08Z:** mock config. PHPUnit then ran 3 more times in a row, all clean.
- **Pre-live gate, 09:36:39Z–09:37:50Z:** live config, so the key-leak scan also searched for the literal key: 0 hits.

New tests in round 5:

| Test | What it checks | Where |
|---|---|---|
| B14 (every journey) | After every successful request of every integration test, an independent item on screen means every message in that turn is host-written | `tests/Support/Harness.php::checkB14`. It fails as soon as the fix is switched off (B1: "independent item … shown with non-host text: Work out the sizes first, then settle the sign.") |
| B14 | 12 B1/B3 journeys. Every independent item shows only the host line. The model's cue (the mock now writes one on purpose) is discarded every time. More than 100 independent presentations are checked | `IndependentEvidenceTest::testB14IndependentItemsShowHostLineOnly` |
| B14 | Discarded text is absent from messages, history, events and the snapshot; only its character count is recorded; it causes no repair | `IndependentEvidenceTest::testB14DiscardedTextIsNotStored` |
| B14b | A hint on the third correct answer: no "without help" message and no criterion; the streak stays at 2. The next unhelped answer meets the criterion, and each counted item is independent, unhinted and was shown with host text | `IndependentEvidenceTest::testB14bHintMeansNoWithoutHelpMessage` |
| B14b (ledger rule) | An item recorded as shown with model text never counts | `IndependentEvidenceTest::testB14bItemShownWithModelTextIsNotCounted` |
| B15 | Rows 1–2 in guided repair: every listed phrase, `?`, and normalised variants give a smaller question; row 2 shows its host line first; nothing is scored | `StuckEntryTest::testStuckAndAskForAnswerInGuidedRepair` |
| B15 | Rows 1–2 on an independent item: the hint path; the item becomes assisted; the later correct answer does not count | `StuckEntryTest::testStuckAndAskForAnswerOnIndependentItem` |
| B15 | Rows 3–4 in both columns: host line, same item, UNSCORABLE (never INCORRECT); other number words keep the current clarification | `StuckEntryTest::testOffTaskQuestionAndNumberWords` |
| B15 | Row 5 in both columns: `X=3`, `X = 3` and `X=3?` are read as x; an arithmetic item is not "in x" | `StuckEntryTest::testCapitalXIsReadAsX` |
| B15 | Trailing "?": scored normally, right or wrong; the verdict is bound to the text as submitted | `StuckEntryTest::testTrailingQuestionMarkIsScoredNormally` |
| B15 | Normalisation (`IDK.`, `I don't know`, `i dont know`, a curly apostrophe, extra spaces and punctuation) | `StuckEntryTest::testNormalisation` |
| §4 flow | A stuck entry in each of the five independent modes, then the next transition | `StuckEntryTest::testStuckEntryFlowInEachIndependentMode` |
| R5-B | Each term matches; "ghost", "the key step", "key idea" (and "hosting", "unassisted", "matched the keys") do not | `EngineUnitsTest::testInternalVocabulary` |
| R5-B | Host lines: every `say()` literal, re-ask lead, verdict sentence, template, plan and input clarification | `EngineUnitsTest::testHostLinesUseNoInternalVocabulary` |
| R5-B | Model text with internal terms is repaired and never shown | `BetaGuardsTest::testInternalVocabularyIsRepairedNotShown`; `LiveAdapterTest` (now on a hint) |
| Ruling 2 | The 8 round 4 messages pass; the 4 listed phrases are rejected; every operand noun is exempt; other words after a sign word are still checked | `EngineUnitsTest::testGuardOperandNounExemption` |

**Live (§5): 10 of 10 sessions completed.** Thinking disabled, `deepseek-flash`, listed by the provider. 0 HOLDs.
- 5 B1 + 3 B3: 09:37:57Z–09:43:44Z.
- B11 ×2: 09:43:57Z–09:46:34Z.

## 2. Failures, with exact messages

**The final gate had no failures, and nothing failed in the live run.** Every model output the host rejected was repaired and accepted; all are listed below.

Two expected failures during development:
- **Round 4 B11 test.** The round 4 B11 test pinned the old handling of `idk` and `X=3`, so it failed once the new handling went in. It was updated to the round 5 behaviour.
  ```
  1) MessyLearnerTest::testB11UnreadableInputsAreNeverScoredAsWrong
  "idk"
  Failed asserting that two strings are identical.
  -'UNSCORABLE'
  +'-'
  2) MessyLearnerTest::testB11CorrectAnswersInLooseFormsAreAccepted
  -'UNSCORABLE'
  +'CORRECT'
  ```
- **Browser UI-02.** UI-02 injected hostile model text into the next item's framing, which round 5 now discards. It now injects the text through a hint, the one place model text still reaches the learner there. The hostile markers no longer carry digits, so the answer guard cannot reject them by chance.

## 3. What was built

### §1 ruling 2: sign words before an operand noun

`DisclosureGuard` now ignores positive, negative, plus or minus (or a plural) only when it is directly followed by one of these nouns: sign, signs, divisor, dividend, number, numbers, factor, term, constant, coefficient. Every other use is still checked. "Negatives" in "two negatives make a positive" still matches the option "Negative".

### §2 R5-A: no help on independent items

Covered presentations: target, diagnostic probe, prerequisite check, independent check and return to target.

- **What is shown.** The learner sees "Here is one to try on your own." and the item, and nothing else.
- **The model's call.** The provider call still runs. Its parameters go through the same acceptance gate, unchanged.
- **What the model is told.** The allocation tells the model the text is not shown and asks for an empty string.
- **Any text returned anyway.** It is discarded unread. A `MODEL_TEXT_DISCARDED` event records only its length. It is never shown, never kept in history and never checked, so it cannot cause a repair or a hold.
- **Source tags.** Every message carries an internal source (host or model) that is never sent to the browser. `ITEM_PRESENTED` records `model_text_shown`.
- **Evidence rule.** The criteria calculator and the session report never count an item recorded as shown with model text. This makes B14b hold even for older ledgers.

### §3 R5-B: no internal vocabulary

`LearnerText` holds the term list. It is applied to:
- **Model text:** a match goes to the bounded repair path. The repair message names the terms.
- **Host lines:** `Turn::say` refuses a host line that contains a term.
- **Progress labels:** `PublicProjection` checks each label.

The adapter rule 5 now lists the terms. The old "host/key" check is gone, so "the key step" is allowed.

### §4 R5-C: stuck and off-task entries

`EntryInterpreter` decides what an entry is, in this order:
1. An entry the item's checker can read is scored as it stands.
2. A readable answer with a trailing "?" is scored without the "?".
3. Otherwise the table is matched on the normalised entry.

What each kind does:
- **Stuck entries (row 1) and asking for the answer (row 2):** no checker result. In guided repair they take the existing smaller-question path (D1). On an independent item they take the existing hint path, so the item becomes assisted. Row 2 first shows "I won't give the answer, but here is a smaller step."
- **Other words ending in "?" (row 3) and number words (row 4):** these keep the UNSCORABLE checker result, and the table's host line replaces the clarification.
- **Trailing "?" and capital X (row 5):** scored. The verdict stays bound to the text exactly as submitted. The checker result notes how the entry was read.

An `ENTRY_INTERPRETED` event records each decision.

### Live runner

- **New B11 inputs.** `-2?`, `idk` and `IDK.` in guided repair, `i dont know` on an independent check, and one Explain request (see §5 item 9).
- **New metrics.** Sign-step guard rejections, internal-vocabulary rejections, independent items shown with model text, discarded model text, internal-vocabulary hits, calls per session and learner wait.
- **Transcripts.** Each tutor line is labelled host text or model text.

### Documents

`ACCEPTANCE_SCENARIOS.md` has rows for B14, B14b, B15, R5-B and the ruling 2 guard. The capability matrix describes all four changes.

## 4. What was not done

- **Ruling 2 was not exercised live.** In round 5 the model wrote "Now bring the sign back in" rather than "the negative sign", so no live message tested the new exemption. It is covered offline, including all 8 round 4 messages.
- **The summary page is outside R5-B.** The session summary keeps its defined evidence terms ("criterion", "assisted"). See §5 item 4.
- **Possible truth defects are proposed, not fixed.** See "Proposals" below.
- **Beta deferrals are unchanged.**

## 5. Conflicts and decisions

1. **Key.** §5 said a new key would be supplied. You said to use the same temporary key as before. I took only the DeepSeek key from the earlier upload (`4f254c70-config_3.php`); its OpenAI and Kimi keys were not read into any config. See "Keys" for deletion.
2. **Plurals in R5-B.** "Guided steps", "criteria", "hosts" and "verdicts" also match. This is stricter than the list. "Unassisted" and "hosting" do not match.
3. **Discarded text is not checked.** Text that is never shown cannot mislead. Checking it would only cause repairs and holds. Live, the model returned an empty string for all 114 independent items, so nothing needed discarding.
4. **The session summary page.** Its headings and policy notes use "criterion", "assisted" and "host-derived key" as defined report vocabulary (report contract fields `immediate_criteria`, `criterion_status`). R5-B names learner text in tutoring. I applied it to tutor messages, progress labels, items and input clarifications, and left the summary unchanged. **Your call.**
5. **Two progress labels reworded.**
   - Guided repair: "Guided steps are practice, not independent evidence." became "Practice with help is not counted as independent work."
   - Independent check: "On your own: …" became "Checking again: …" because the old label stayed on screen after a hint.
   - The data-deletion note now says "the hosting provider's own retention".
6. **Scope of the table rows.**
   - **Row 3:** "words" means at least one run of two or more letters, so `x?` keeps the current clarification.
   - **Row 4:** applies to number and expression items only; option items have no answer box in the UI.
   - **Row 5:** applies to single-number items whose question names x and no other one-letter variable (Q04–Q07 and guided "What is x?" steps), not to arithmetic items.
   - **"Only ?":** `??` also counts.
7. **How a stuck entry is recorded.** The learner's text is kept as `RESPONSE_SUBMITTED`. It has no checker result, so it can never become a verdict. A hint caused by an entry is a normal `SUPPORT_GIVEN`, marked `trigger: STUCK_ENTRY`.
8. **Guided column, row 1.** "Smaller question (D1)" uses the existing stuck sequence: the smaller question; if none is left, the same question asked another way; then the prerequisite check or boundary (round 2 ruling 8). Live, all four guided stuck entries were on steps with a smaller question.
9. **One input beyond the list.** B11 includes one Explain request (`REQUEST_EXPLANATION`). §6 asks for a live example of every model-written message, and no scripted session otherwise asks for an explanation. The 8 standard sessions are unchanged.

## Proposals (truth defects only)

There is no safety defect to report apart from the key copy in the conversation (see "Keys"). Four points could make a message or record misstate what happened. Each needs the architect's decision; none was changed.

1. **"I won't give the answer, but here is a smaller step."** On an independent item what follows is a hint, not a smaller step. That was so both times live (01-B11 t4 and 02-B11 t4). In guided repair it can be the same question asked another way, or a prerequisite check, when no smaller question is left.
   - Proposal: "…but here is a hint." on independent items. In guided repair, keep "smaller step" only when a smaller question follows.
2. **"…correctly in a row, without help".** Assisted answers neither advance nor reset the run (spec). So the three counted answers can be 1, 2 and 4 when answer 3 had a hint. That is shown in B14b: the criterion is met on items 1, 2 and 4. Every counted item satisfies B14b, but "in a row" overstates it.
   - Proposal: drop "in a row", or say "with no wrong answer in between".
3. **Model text on guided problems says "on your own".** Seen 4 times live: 01-B1 t9, 07-B3 t9, and 02-B11 t16 and t21 ("Here is a fresh one to try on your own."). Each was shown with an assisted guided problem that counts as practice with help.
   - Proposal: add "on your own", "without help" and "independently" to the R5-B list for model text.
4. **A probe answered after a hint.** `routeProbe` does not look at support. An assisted correct probe records "The earlier error was not reproduced on a fresh probe" and resumes the target, as B15's flow test shows. The checker result is marked not independent, but the observation does not say help was given.
   - Proposal: add "(answered after a hint)" to that observation. Routing would be unchanged.

**Post-beta (not proposed now):**
- One explanation drew on the previous item's numbers: 02-B11 t25, about −21 ÷ (−3), said "48 divided by 6 gives 8".
- The model often repeats the host's "I won't give the answer".
- The hint repair rate is high: 4 repairs in 6 hints. The causes were "?" in hints and the guard's number-coincidence limit ("3 times the group" when x = 3).
- `X =` on expression items.
- Wider stuck phrase lists ("i have no idea how").
- Freshness repairs when the model re-proposes the opening target numbers.

## Comparison (§5): round 5 against round 4, thinking disabled

Same model (`deepseek-flash`), same settings (max_tokens 4096, timeout 45 s, lease 90 s) and the same scripted inputs for the 8 standard sessions. **Worse** marks a metric that got worse.

| Metric (5 B1 + 3 B3) | Round 4 | Round 5 | |
|---|---|---|---|
| Sessions completed | 8/8 | 8/8 | |
| Binding on first try | 8/8 | 8/8 | |
| HOLDs | 2 | **0** | |
| Repairs per operation (accepted generations in brackets) | PRESENT_ITEM 3 (84); GUIDED_STEP 6 (37) | PRESENT_ITEM 2 (84); GUIDED_STEP 0 (37) | |
| Guard rejections (on sign steps) | 8 (8) | **0 (0)** | |
| Other rejections (internal vocabulary) | 3 (0): 1 "?" rule, 2 freshness rule | 2 (0): both the freshness rule | |
| Independent-item turns also showing model-written text | 84 of 84 | **0 of 84** | |
| Internal-vocabulary hits in learner-visible text | 1 ("That guided step is done…", 08-B3 t10) | **0** | |
| Provider calls (per session) | 140 (17.5) | 131 (16.4) | |
| Empty responses / `finish_reason=length` | 0 / 0 | 0 / 0 | |
| Call time, median / slowest | 2.6 s / 3.4 s | 2.6 s / 3.8 s | **Worse** (slowest only: one accepted PRESENT_ITEM call of 3.79 s with 683 output tokens, 06-B3 t13) |
| Learner wait, median / slowest | 2.7 s / 7.7 s | 2.7 s / 5.8 s | |
| Tokens in / out (reasoning) | 1,804,375 / 63,335 (0) | 1,675,707 / 56,162 (0) | |
| R4-A: re-asks with host line and item only | 5 of 5 | 5 of 5 | |
| R4-B: B3 repairs keeping the failed N06 sign pattern | 3 of 3 | 3 of 3 | |

**B11 ×2.** The round 5 inputs differ (stuck entries now call the model for hints, and there are more turns), so these columns are not like for like.

| Metric (B11 ×2) | Round 4 | Round 5 | |
|---|---|---|---|
| Sessions completed / HOLDs | 2/2 / 0 | 2/2 / 0 | |
| Turns / provider calls (per session) | 50 / 37 (18.5) | 70 / 62 (31.0) | more inputs |
| Repairs per operation | PRESENT_ITEM 0 (26); GUIDED_STEP 1 (8) | PRESENT_ITEM 1 (30); HINT 4 (6); GUIDED_STEP 0 (16); EXPLANATION 1 (2) | **Worse** (hints: new operation in these sessions) |
| Guard rejections (on sign steps) | 1 (0) | 4 (0) | **Worse** (3 number coincidences, 1 real catch) |
| Other rejections (internal vocabulary) | 0 (0) | 2 (0) | **Worse** ("?" in a hint; freshness rule) |
| Independent-item turns also showing model-written text | 26 of 38 | **0 of 30** | |
| Internal-vocabulary hits in learner-visible text | 2 (one message: "matched the expected value… a guided step") | **0** | |
| Call time, median / slowest | 2.6 s / 3.4 s | 2.5 s / 3.3 s | |
| Learner wait, median / slowest | 2.5 s / 4.8 s | 2.6 s / 6.2 s | **Worse** (02-B11 t4: a hint repaired twice, 3 calls) |

Round 4's "independent-item turns with model text" was counted from its transcripts: any non-host tutor line in a turn that showed an independent item. Round 5's comes from the message source tags; a transcript count agrees (0 of 84, 0 of 30). The model returned an empty string for all 114 independent items.

## B11: input by input (live, 2 sessions)

S = "Exact rational comparison with the host-derived key; no tolerance; reasoning not assessed." Long tutor lines are shortened with "…"; the full text is in `test-results/live/round5/b11/`.

| Session | Turn | Input | Submitted | Item | Verdict | Scope | Tutor showed | Host rejection |
|---|---|---|---|---|---|---|---|---|
| 01-B11 | 2 | idk | `idk` | Solve 3(x + 4) = 21 for x. | none (hint path) | – | [host] This question now counts as practice with help… / [model] That is okay. Look at the number just outside the parentheses… | HINT a0: "?" in text; guard (3 = x) |
| 01-B11 | 3 | empty submit | `` | same | UNSCORABLE | S | [host] Nothing was entered. Type your answer in the box. | none |
| 01-B11 | 4 | can you just tell me the answer | as typed | same | none (hint path) | – | [host] I won't give the answer, but here is a smaller step. / [host] This question now counts… / [model] I won't give you the answer, but I can help you get there… | none |
| 01-B11 | 5 | is this on the test? | as typed | same | UNSCORABLE | S | [host] This box is for your answer. If you're stuck, type help. | none |
| 01-B11 | 6 | number word | `three` | same | UNSCORABLE | S | [host] Write the number with digits. | none |
| 01-B11 | 12 | idk (guided repair) | `idk` | Leave the signs aside for a moment. What is 55 ÷ 5? | none | – | [model] Let's start with the sizes of the numbers… → smaller question "5 times what number is 55?" | none |
| 01-B11 | 13 | "3 " with spaces | `  11 ` | 5 times what number is 55? | CORRECT | S | [host] Yes, that is right. / [host] Now try the earlier question again. | none |
| 01-B11 | 14 | 3.0 form | `11.0` | What is 55 ÷ 5? | CORRECT | S | [host] Yes… / [model] Good, the sizes are settled… | none |
| 01-B11 | 16 | 6/2 form | `22/2` | What is −55 ÷ (−5)? | CORRECT | S | [host] Yes… / [model] You settled the sizes and the sign… | none |
| 01-B11 | 17 | IDK. (guided repair) | `IDK.` | What is −132 ÷ (−11)? | none | – | [model] That is fine. Let us take it one piece at a time. → smaller question "What is 132 ÷ 11?" | none |
| 01-B11 | 18 | typo | `12]` | What is 132 ÷ 11? | UNSCORABLE | S | [host] That entry contains a symbol this question cannot read… | none |
| 01-B11 | 19 | correction | `12` | same | CORRECT | S | [host] Yes… / [model] You handled the sizes… | none |
| 01-B11 | 22 | Unicode minus | `−6` | What is −54 ÷ 9? | CORRECT | S | [host] Yes, that is right. / [host] Here is one to try on your own. | none |
| 01-B11 | 23 | i dont know (independent check) | `i dont know` | Calculate −32 ÷ (−8). | none (hint path) | – | [host] This question now counts… / [model] You said you are not sure yet. Start with the sizes… | HINT a0: "?" in text |
| 01-B11 | 25 | -2? | `-11?` | Calculate 121 ÷ (−11). | CORRECT | S | [host] Correct. / [host] Here is one to try on your own. | none |
| 01-B11 | 26 | Explain | (button) | Calculate 72 ÷ (−6). | none | – | [host] This question now counts… / [model] You asked for an explanation of how the sign works here… | none |
| 01-B11 | 30 | x = 3 form | `x = -6` | Solve 4x = −24 for x. | CORRECT | S | [host] Correct. / [host] Here is one to try on your own. | none |
| 01-B11 | 31 | x=3 form | `x=1` | Solve −6x + 14 = 8 for x. | CORRECT | S | same | PRESENT_ITEM a0: freshness rule |
| 02-B11 | 2 | idk | `idk` | Solve 3(x + 4) = 21 for x. | none (hint path) | – | [host] This question now counts… / [model] Thanks for saying where you are… | none |
| 02-B11 | 3 | empty submit | `` | same | UNSCORABLE | S | [host] Nothing was entered… | none |
| 02-B11 | 4 | can you just tell me the answer | as typed | same | none (hint path) | – | [host] I won't give the answer, but here is a smaller step. / [host] This question now counts… / [model] I won't give the answer, but I'll help you get there… | HINT a0, a1: guard (3 = x) |
| 02-B11 | 5 | is this on the test? | as typed | same | UNSCORABLE | S | [host] This box is for your answer… | none |
| 02-B11 | 6 | number word | `three` | same | UNSCORABLE | S | [host] Write the number with digits. | none |
| 02-B11 | 12 | idk (guided repair) | `idk` | What is 22 ÷ 2? | none | – | [model] Let's take it one small step at a time… → smaller question "2 times what number is 22?" | none |
| 02-B11 | 13 | "3 " with spaces | `  11 ` | 2 times what number is 22? | CORRECT | S | [host] Yes… / [host] Now try the earlier question again. | none |
| 02-B11 | 14 | 3.0 form | `11.0` | What is 22 ÷ 2? | CORRECT | S | [host] Yes… / [model] You found the size of the quotient… | none |
| 02-B11 | 16 | 6/2 form | `22/2` | What is −22 ÷ (−2)? | CORRECT | S | [host] Yes… / [model] You found the size and the sign. Now try a fresh one on your own. | none |
| 02-B11 | 17 | IDK. (guided repair) | `IDK.` | What is −40 ÷ (−10)? | none | – | [model] Let's step back to just the sizes… → smaller question "What is 40 ÷ 10?" | none |
| 02-B11 | 18 | typo | `4]` | What is 40 ÷ 10? | UNSCORABLE | S | [host] That entry contains a symbol… | none |
| 02-B11 | 19 | correction | `4` | same | CORRECT | S | [host] Yes… / [model] You handled the sizes… | none |
| 02-B11 | 23 | i dont know (independent check) | `i dont know` | Calculate −48 ÷ (−6). | none (hint path) | – | [host] This question now counts… / [model] You said you are not sure, so let us take it in two parts… | none |
| 02-B11 | 25 | Explain | (button) | Calculate −21 ÷ (−3). | none | – | [host] This question now counts… / [model] You set the sizes aside and found that 48 divided by 6 gives 8… | EXPLANATION a0: guard (real catch: "21 divided by 3 gives 7") |
| 02-B11 | 27 | Unicode minus | `−2` | Calculate −4 ÷ 2. | CORRECT | S | [host] Correct. / [host] Here is one to try on your own. | none |
| 02-B11 | 28 | -2? | `-6?` | Calculate 18 ÷ (−3). | CORRECT | S | same | none |
| 02-B11 | 30 | x = 3 form | `x = -12` | Solve −8x = 96 for x. | CORRECT | S | same | none |
| 02-B11 | 31 | x=3 form | `x=5` | Solve 4x + 7 = 27 for x. | CORRECT | S | same | none |

**None of these inputs was scored as wrong.**
- **Stuck entries:** all ten (five per session) became a hint or a smaller question.
- **Loose forms:** every loosely written correct answer was accepted.
- **Off-task and unreadable entries:** every one kept the item open.

## Every host rejection of model output (live, round 5)

| # | Session, turn, operation, attempt | Reason | Rejected learner message | Kind |
|---|---|---|---|---|
| 1 | confirmation 06-B3 t15 PRESENT_ITEM a0 | parameters reuse an item instance already presented | (empty; text not shown with independent items) | freshness rule |
| 2 | confirmation 07-B3 t15 PRESENT_ITEM a0 | same | (empty) | freshness rule |
| 3 | B11 01-B11 t2 HINT a0 | must not contain a question; contains the expected answer | "That is okay. Look at the number just outside the parentheses. It multiplies the whole group inside, not only the x. What does 3 times the whole group (x + 4) become when you write it without parentheses?" | "?" rule; guard false positive (3 is the factor and also x) |
| 4 | 01-B11 t23 HINT a0 | must not contain a question | "You said you are not sure yet. Start with the sizes: what is 32 divided by 8? Then decide the sign the same way you would for a multiplication of two negative numbers." | "?" rule |
| 5 | 01-B11 t31 PRESENT_ITEM a0 | parameters reuse an item instance | (empty) | freshness rule |
| 6 | 02-B11 t4 HINT a0 | contains the expected answer | "I won't give the answer, but I'll help you get there. The number outside the parentheses multiplies the whole group, not only the x. So think about what 3 times the group (x + 4) gives you, and what that has to equal." | guard false positive (3 = x) |
| 7 | 02-B11 t4 HINT a1 | same | "…So think about what the group inside the parentheses must be, given that the whole thing is multiplied by 3." | same; attempt 2 accepted |
| 8 | 02-B11 t25 EXPLANATION a0 | contains the expected answer | "You set the sizes aside and found that 21 divided by 3 gives 7. Now bring the signs back: both numbers in this problem are negative, so think about what that does to the result." | real catch: 7 is the answer to −21 ÷ (−3) |

No rejection was on a sign step and none named an internal term. Every rejected output was repaired, and none was shown to a learner.

## Model-written learner messages, by operation (§6)

Everything else the learner reads is written by the host. That covers item framing, verdicts, re-asks, the alternate phrasing, clarifications, the R5-C lines, the criterion message and errors.

| Operation | When the learner sees model text | Live example (round 5) |
|---|---|---|
| GUIDED_STEP, repair opening | The first guided step, often restating the host's feedback fact | confirmation 01-B1 t4: "Your answer multiplied only the x by 8. Picture the parentheses as equal groups, each holding the same terms." |
| GUIDED_STEP, smaller question | After a wrong step, a hint request, "I don't know" or a stuck entry (R5-C) | 01-B11 t12, after `idk`: "Let's start with the sizes of the numbers and set the signs aside for now." |
| GUIDED_STEP, next step | After a correct step that is not a re-ask | confirmation 01-B1 t7: "You found the total of the 8s. Now look at the x in each group." |
| GUIDED_STEP, fresh guided problem | The faded problem after a guided problem | confirmation 01-B1 t9: "You put the number part and the x-part together correctly. Here is a fresh one to try on your own." (see Proposal 3) |
| HINT | Hint button, or a stuck entry on an independent item (R5-C); shown after the host line saying the item now counts as practice with help | 01-B11 t23, after `i dont know`: "You said you are not sure yet. Start with the sizes: divide 32 by 8 first. Then decide the sign the same way you would for multiplying two negative numbers." |
| EXPLANATION | Explain button on an independent item; same host line first | 01-B11 t26: "You asked for an explanation of how the sign works here. Take the sizes first: 72 divided by 6 gives the size of the quotient. Then decide the sign the same way you would for multiplying two numbers with opposite signs." |
| PRESENT_ITEM | Never (R5-A): text is discarded unread | — (the model returned an empty string for all 114 items) |
| Binding | Never: a text-mode reply checked by the host, not shown | — |

A hint or explanation requested during guided repair goes to the smaller-question path, so its text is GUIDED_STEP text.

## Stuck entry on an independent item: what happens in each mode (§4 flow)

**Stop check:** every independent mode already has a defined follow-on after a hint, so no stop was needed.

In each mode the stuck entry uses the existing hint path:
- the item stays open and becomes assisted;
- the host shows "This question now counts as practice with help, not as independent work.", then a model-written hint;
- for an "ask for the answer" entry, the host first shows "I won't give the answer, but here is a smaller step."

The learner's next answer then follows the transition the mode already defines for an assisted answer.

| Mode | After the hint, a correct answer | After the hint, a wrong answer | Tested |
|---|---|---|---|
| TARGET_ASSESSMENT | The next target item; the criterion does not advance | Diagnostic probe (as for any wrong target answer) | B15 flow test; live 01/02-B11 t2–t7 |
| DIAGNOSTIC_PROBE | "Error not reproduced": back to TARGET_ASSESSMENT (or a guided repair below the target). See Proposal 4 | Descent along the implicated branch, or a boundary | B15 flow test |
| PREREQUISITE_CHECK | Guided repair at that skill: an assisted check does not return up | Descent along the implicated branch, or a boundary | B15 flow test |
| INDEPENDENT_CHECK | The next independent item; the criterion does not advance | Guided repair continues; not counted as a failed cycle | B15 flow test; live 01/02-B11 t23 |
| RETURN_TO_TARGET | The same kind of item again; the parent is not re-verified | Diagnostic probe | B15 flow test |

In guided repair a stuck entry gives a smaller question (live: 4 of 4). If none is left, the same question is asked another way; then the prerequisite check or boundary follows (D1, round 2 ruling 8).

## Keys (§5)

- **Key used.** You said to use the same temporary DeepSeek key as before. It was read from the earlier upload into one private config outside the repository (mode 600). The OpenAI and Kimi keys in that file were not used.
- **Deleted at 09:47:47Z, after the live runs.** First, a literal search for the key covered the repository (including the new transcripts), the scratch area, `/tmp` and the session's upload and settings folders. It found 3 copies:
  - my private config;
  - the uploaded file `4f254c70-config_3.php`;
  - this conversation's own history file, 4 occurrences, from when the key was given in the chat earlier.

  I deleted the first two. A search afterwards finds no key-shaped string in the scratch area or the upload folder.
- **Not deleted: the conversation history.** It belongs to the session, and I cannot remove it.
- **Please revoke this DeepSeek key now.** Revoke the OpenAI and Kimi keys from the same file too, if you have not already.
- **Production key.** The owner places it at `/home/ACCOUNT/algebra-private/config.php` (`DEPLOYMENT.md`). It is never given to Claude Code.

Scripted learners only: these runs confirm provider compatibility and host gating, not teaching quality, learning or deployment.
