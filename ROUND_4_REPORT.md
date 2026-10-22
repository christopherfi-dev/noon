# Algebra Engine — round 4 report

Repository `christopherfi-dev/noon`, branch `claude/elegant-volta-blu3uj`.
Round 4 ran from 2026-10-22T00:06:06Z (see `BUILD_LOG.md`, "Round 4").
Nothing was deployed. The order follows §9 of the beta addendum, then the
items round 4 §7 adds.

## 1. Tests run, with pass/fail counts

**Final gate, 00:31:38Z–00:32:06Z** (no key configured; the pattern scan covers all three key formats):

| Step | Result |
|---|---|
| F1 manifest, handoff preflight, curriculum validator and guards | PASS |
| App preflight | PASS. 2 WARN: PHP 8.3 rather than 8.4/8.5, and no `config.php` |
| PHPUnit | **174 passed, 0 failed** (102 unit, 72 integration; 32,485 assertions); 5 more runs straight after, all clean |
| Browser (B1 ×2 widths, UI-01, UI-02, UI-03) | **5 passed, 0 failed** |
| Bundle build and deployment-layout smoke test | PASS, 12/12 |
| Key-leak scan | PASS: 305 tracked files, 675 ZIP entries, 0 hits |

The offline gate before the live run, 00:21:18Z–00:21:49Z, also passed.

New tests in round 4:

| Test | What it checks | Where |
|---|---|---|
| B11 (offline) | messy inputs, one by one | `MessyLearnerTest`, 2 tests |
| B12 | re-asks show no model text | `BetaJourneyTest::testB12ReAskShowsNoModelText` |
| B13 | kept sign pattern | `BetaJourneyTest::testB13RepairKeepsTheFailedSignPattern`; fails without the fix |
| Narrowed guard | both exemptions pass; the round 3 real leak (05-B1 turn 6) is still rejected | `EngineUnitsTest::testDisclosureGuardHeuristic` |
| JSON error position | 3 tests, including agreement with `json_decode` on 3,000 random corruptions | `JsonSyntaxTest` |
| Position in repair request | the HOST-05 repair request carries the parser position | `HostTest` |
| Network diagnostics | logged; the real transport reports stage `connect` for a closed port | `LiveAdapterTest::testNetworkFailureDiagnosticsAreLogged` |
| Kept-feature gate rule | the gate rejects a proposal that changes a kept feature | `TemplatesTest::testGateRejectsAProposalThatChangesAKeptFeature` |
| Planner keeps features | the planner keeps sign patterns and drops a form it cannot express | `TemplatesTest::testStructuralFeaturesAreKeptByThePlanner` |

**Live (§5): 10 of 10 sessions completed.** Thinking disabled, `deepseek-flash`.
- 5 B1 + 3 B3: 00:22:21Z–00:28:24Z.
- B11 ×2: 00:28:24Z–00:29:59Z.

## 2. Failures, with exact messages

**Final gate:** none.

**During development** (both fixed before any commit):

```
1) AlgebraEngine\Tests\Unit\Items\TemplatesTest::testStructuralFeaturesAreKeptByThePlanner
N03 ++
Failed asserting that two arrays are identical.
-    'sign_a' => '+',
+    'sign_a' => '-',
```
The test asked for a sign pattern the items cannot have: N03–N06 items always contain a negative operand. The test now covers only the patterns the items can have (see §5, item 1).

```
1) AlgebraEngine\Tests\Integration\BetaJourneyTest::testB10StuckStepAtABoundaryIsRecordedNotFailed
no active item; view={"status":"READY",...,"mode":"GUIDED_REPAIR",...,"messages":[{"kind":"FEEDBACK","text":"Not yet."}]...}
```
My R4-B edit had moved the `presentStep` call out of `continueAfterStuck`. B10 caught it, and I fixed it.

**Live:** 2 HOLDs, both in confirmation session 06-B3 (raw lines from the transcript):

```
## Turn 7 … status HOLD
- Host: HOLD: provider failure INVALID_OUTPUT (The provider output failed host acceptance after 2 repair attempts.) during GUIDED_STEP
## Turn 8 (RESUME_SESSION) … status HOLD
- Host: HOLD: provider failure INVALID_OUTPUT (The provider output failed host acceptance after 2 repair attempts.) during GUIDED_STEP
## Turn 9 (RESUME_SESSION) … status READY (accepted on attempt 2)
```

The cause is analysed in §5, item 2.

## 3. What was built

- **§1, thinking disabled by default.**
  - `config.example.php`, the README and `DEPLOYMENT.md` now default to `provider_thinking = 'disabled'`; `temperature` 0.2 applies.
  - The setting is still configurable.
  - `Config` itself keeps no default, so an explicit value is still required (round 3 ruling).
- **Ruling 1, narrowed guard.** The guard ignores a value in exactly two cases:
  - **(a)** the message names two or more answer options together as alternatives ("positive or negative", "options A or B");
  - **(b)** the value is the learner's own (correct) answer to a step already completed in the current guided problem. This never applies to the step being asked now.
  Everything else is still checked.
- **Ruling 2, JSON error position.** `Support/JsonSyntax` locates the first syntax error. The repair message now reads, for example: `output is not valid JSON: Syntax error (character 47: unexpected ',' after the end of the JSON value)`.
- **Ruling 4, network diagnostics.** On a network failure the app logs:
  - the curl error number;
  - the stage reached (`connect`, `tls` or `response`);
  - the elapsed time.

  They go in the `PROVIDER_FAILURE` event and in `model_calls.transport_detail` (migration 003). They are never shown to the learner.
- **R4-A, host-only re-asks.** A re-ask of a previously failed guided step is built by the host through the same gate, with no provider call. It shows the host line and the item only.
  - This covers the return after a smaller question, and the alternate phrasing after a failed attempt.
  - On the alternate phrasing, host-written feedback facts are shown as host lines.
  - Each re-ask records a `HOST_ONLY_STEP` event.
- **R4-B, kept structure.** `Items/StructuralFeatures` defines the features to keep:
  - N03–N06: the sign of each operand;
  - E06: the sign of the inside constant;
  - Q04–Q07: the equation form and the sign of each constant.

  The engine records each skill's last failed item. The first guided problem and the next fresh guided problem keep its features; later ones may vary. A feature the guided template cannot express (for example a transfer item's `c=a(x+b)` form) is dropped, reported in `REPAIR_STARTED`, and the remaining features are still kept. The gate rejects a model proposal that changes a kept feature, which uses the bounded repair path.
- **§4, B11.** Offline tests, plus a `--b11` path in `tools/live_sessions.php` that threads the messy inputs into the B3 journey, with an input-by-input table.
- **§6, production key location.** `DEPLOYMENT.md` names the exact place for the production key: `/home/ACCOUNT/algebra-private/config.php`, line `'provider_api_key' => …`. That folder is beside `public_html`, not inside it, and the file is `chmod 600`.

## 4. What was not done

- **B13 covers 3 sign patterns, not 4** (§5, item 1).
- **Equation forms Q07 guided problems cannot express.** When the failed Q07 item is a transfer item (`c=a(x+b)` or `a(x+b)+d=c`), the form cannot be kept; the signs are. This did not occur in the live runs.
- **Beta deferrals unchanged.** All beta deferrals still apply.

## 5. Conflicts and points for the architect

1. **N06 has three sign patterns, not four.**
   - Every N03–N06 item specification requires at least one negative operand, so positive ÷ positive never occurs.
   - B13 covers + ÷ −, − ÷ + and − ÷ −.
2. **Worse metric: the narrowed guard collides with R4-B on sign steps.**
   - In 06-B3 the learner failed 72 ÷ (−9). R4-B correctly kept the pattern, so the guided problem was 12 ÷ (−3), and the sign step's key is "Negative".
   - The model wrote "bring the negative sign back in" and "a negative divisor". These describe the divisor, but they contain the option word "negative".
   - Exemption (a) covers only lists of alternatives, so 8 attempts were rejected and the session held twice. Round 3 had no HOLDs in this setting.
   - With R4-B keeping negative operands, this wording will recur. Options:
     - **(i)** Ignore the option word when it describes an operand ("negative sign", "negative divisor", "negative number"), while still checking statements about the result ("the quotient is negative").
     - **(ii)** Make sign-choice steps host-only, like re-asks.
     - **(iii)** Keep the rule and accept the HOLDs.
   - I made no change.
3. **R4-A also covers the alternate phrasing.** That is a re-ask of a failed step too. Its learner text is host-written: the feedback facts and "Here is the same question, asked another way."
4. **Exemption (b) excludes the step being asked now.** Without that exclusion the round 3 leak ("…add up to 25") would pass, because 25 is also the learner's answer to the completed smaller step. With it, the leak is still rejected, as the ruling requires.
5. **The kept-feature gate rule has no model proposal to act on yet.**
   - The kept items are guided problems, which the host plans; the model must copy their fixed parameters and cannot propose their numbers.
   - The gate rule exists for any allocation that carries kept features and is unit-tested.
6. **B11 proposals.** Nothing was changed during the run. Every unreadable input was UNSCORABLE: never scored wrong, item kept, clarification shown. Proposals:
   - **Capital X:** accept `X=3`; it is currently UNSCORABLE (offline test).
   - **"idk" / "I don't know":** route these to the existing NOT_LEARNED path, a smaller question, instead of "contains words…".
   - **"can you just tell me the answer":** answer with the hint path, or a host line saying the tutor helps with a smaller question, instead of "contains words…".
   - **"is this on the test?":** the current reply blames "a symbol" (the `?`). A host line could say the box takes answers only.
   - **Number words:** accept "three" up to twenty, or say "write the number with digits".
7. **Cost.** DeepSeek does not report cost; token counts are given instead.

## Comparison table (§5): round 4 against round 3, thinking disabled

Same model (`deepseek-flash`), same settings (max_tokens 4096, timeout 45 s, lease 90 s) and the same scripted inputs. **Worse** marks a metric that got worse.

| Metric (5 B1 + 3 B3) | Round 3 | Round 4 | |
|---|---|---|---|
| Sessions completed | 8/8 | 8/8 | |
| Binding on first try | 8/8 | 8/8 | |
| Provider calls | 141 | 140 | |
| Repairs per operation (accepted generations in brackets) | PRESENT_ITEM 3 (84); GUIDED_STEP 4 (42) | PRESENT_ITEM 3 (84); GUIDED_STEP 6 (37) | **Worse** (guided-step repairs 4 → 6) |
| HOLDs | 0 | 2 | **Worse** |
| Host rejections: answer-leak guard | 4 | 8 | **Worse** (all 8 in 06-B3, §5 item 2) |
| Host rejections: other | 3 | 3 | |
| Empty responses | 0 | 0 | |
| `finish_reason=length` | 0 | 0 | |
| Call time, median / slowest | 2.6 s / 3.7 s | 2.6 s / 3.4 s | |
| Learner wait per answer, median / slowest | 2.7 s / 6.8 s | 2.7 s / 7.7 s | **Worse** (slowest; a held turn with 3 calls) |
| Completion tokens (reasoning) | 64,778 (0) | 63,335 (0) | |
| Prompt tokens (cache hit / miss) | 1,809,289 (1,494,272 / 315,017) | 1,804,375 (1,513,600 / 290,775) | |
| Cost | not reported | not reported | |
| R4-A: re-asks with host line and item only | 0 of 5 | 5 of 5 | |
| R4-B: B3 repairs keeping the failed N06 sign pattern | 0 of 3 | 3 of 3 | |

Notes on the table:
- Guided-step generations fell from 42 to 37 because the 5 re-asks no longer call the model.
- R4-B was counted from the signed guided items after the failed N06 check. By that count none of the 9 round 3 B3 sessions kept the pattern; the round 4 B11 sessions kept it 2 of 2.
- B11 ×2 is not part of the table: 50 turns, 37 calls, 0 HOLDs, 1 rejection (answer-leak guard), call median 2.6 s and slowest 3.4 s, learner wait median 2.5 s and slowest 4.8 s.

## B11: input by input (live, 2 sessions)

S = "Exact rational comparison with the host-derived key; no tolerance; reasoning not assessed." Tutor lines are shortened with "…"; the full text is in `test-results/live/round4/b11/METRICS.md` and the transcripts.

| Session | Turn | Input | Submitted | Item | Verdict | Scope | Tutor showed | Host rejection |
|---|---|---|---|---|---|---|---|---|
| 01-B11 | 2 | idk | `idk` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains words or function names this question cannot read. Enter only numbers, the variable and operations. | none |
| 01-B11 | 3 | empty submit | `` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] Nothing was entered. Type your answer in the box. | none |
| 01-B11 | 4 | can you just tell me the answer | `can you just tell me the answer` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains words or function names this question cannot read. Enter only numbers, the variable and operations. | none |
| 01-B11 | 5 | is this on the test? | `is this on the test?` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains a symbol this question cannot read. Use digits, x, + - * / ^ and parentheses. | none |
| 01-B11 | 6 | number word ("three") | `three` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains words or function names this question cannot read. Enter only numbers, the variable and operations. | none |
| 01-B11 | 12 | "3 " with spaces | `  11 ` | Leave the signs aside for a moment. What is 55 ÷ 5? | CORRECT | S | [FEEDBACK] Yes, that is right. / [INFORMATION] Good, 55 ÷ 5 is 11. Now bring the signs back in. Think about the sizes… | none |
| 01-B11 | 14 | 3.0 form | `11.0` | What is −55 ÷ (−5)? | CORRECT | S | [FEEDBACK] Yes, that is right. / [INFORMATION] You combined the size and the sign correctly on that one. Here is a fr… | none |
| 01-B11 | 15 | 6/2 form | `24/2` | What is −132 ÷ (−11)? | CORRECT | S | [FEEDBACK] Yes, that is right. / [INFORMATION] That quotient matched the expected value, and it was a guided step. He… | none |
| 01-B11 | 16 | Unicode minus | `−6` | Calculate −54 ÷ 9. | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] That quotient was correct and you worked it independently. Here is ano… | none |
| 01-B11 | 17 | typo | `-9]` | Calculate −72 ÷ 8. | UNSCORABLE | S | [ERROR] That entry contains a symbol this question cannot read. Use digits, x, + - * / ^ and parentheses. | none |
| 01-B11 | 18 | correction after the typo | `-9` | Calculate −72 ÷ 8. | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] That quotient was correct and you worked it independently. Here is ano… | none |
| 01-B11 | 20 | x = 3 form | `x = 10` | Solve 4x = 40 for x. | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] That one-step equation was solved correctly and independently. Here is… | none |
| 01-B11 | 21 | x=3 form | `x=-6` | Solve 9x + 14 = −40 for x. | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] Your two-step equation was solved correctly and independently. Here is… | none |
| 02-B11 | 2 | idk | `idk` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains words or function names this question cannot read. Enter only numbers, the variable and operations. | none |
| 02-B11 | 3 | empty submit | `` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] Nothing was entered. Type your answer in the box. | none |
| 02-B11 | 4 | can you just tell me the answer | `can you just tell me the answer` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains words or function names this question cannot read. Enter only numbers, the variable and operations. | none |
| 02-B11 | 5 | is this on the test? | `is this on the test?` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains a symbol this question cannot read. Use digits, x, + - * / ^ and parentheses. | none |
| 02-B11 | 6 | number word ("three") | `three` | Solve 3(x + 4) = 21 for x. | UNSCORABLE | S | [ERROR] That entry contains words or function names this question cannot read. Enter only numbers, the variable and operations. | none |
| 02-B11 | 12 | "3 " with spaces | `  6 ` | Leave the signs aside for a moment. What is 18 ÷ 3? | CORRECT | S | [FEEDBACK] Yes, that is right. / [INFORMATION] Good, 18 ÷ 3 is 6. Now bring the sign back in. Work with the sizes of … | none |
| 02-B11 | 14 | 3.0 form | `-6.0` | What is −18 ÷ 3? | CORRECT | S | [FEEDBACK] Yes, that is right. / [INFORMATION] You put the size and the sign together correctly on that one. Here is … | none |
| 02-B11 | 15 | 6/2 form | `-4/2` | What is −16 ÷ 8? | CORRECT | S | [FEEDBACK] Yes, that is right. / [INFORMATION] That one is done. Here is a fresh division to try on your own. Work wi… | none |
| 02-B11 | 16 | Unicode minus | `−7` | Calculate 49 ÷ (−7). | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] That one is done. Here is a fresh division to try on your own. Work wi… | none |
| 02-B11 | 17 | typo | `-10]` | Calculate 100 ÷ (−10). | UNSCORABLE | S | [ERROR] That entry contains a symbol this question cannot read. Use digits, x, + - * / ^ and parentheses. | none |
| 02-B11 | 18 | correction after the typo | `-10` | Calculate 100 ÷ (−10). | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] That one is done. Here is a fresh division to try on your own. Work wi… | none |
| 02-B11 | 20 | x = 3 form | `x = 11` | Solve −7x = −77 for x. | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] That multiplication equation is solved. Here is a two-step equation to… | none |
| 02-B11 | 21 | x=3 form | `x=5` | Solve −6x − 12 = −42 for x. | CORRECT | S | [FEEDBACK] Correct. / [INFORMATION] The two-step equation is solved. Now here is a grouped equation to sol… | none |

## Every host rejection of model output (live, round 4)

Full rejected envelopes are in each `METRICS.md`; here are the rejected learner messages and the reasons.

| # | Session, turn, operation, attempt | Reason | Rejected learner message | Kind |
|---|---|---|---|---|
| 1 | confirmation 04-B1 t14 PRESENT_ITEM a0 | student_message must not contain a question | "…Which of these are valid first steps for solving 6(x + 4) = 42? Select every one that is." | rule violation caught |
| 2 | confirmation 06-B3 t7 GUIDED_STEP a0 | contains the expected answer | "Good, 12 ÷ 3 is 4. Now bring the negative sign back in. Work with the sizes of the numbers first, then decide the sign." | option word describing an operand (§5 item 2) |
| 3 | 06-B3 t7 a1 | same | "You handled the sizes of the numbers well. Now bring the negative sign back in. Work with the sizes first, then decide the sign." | same |
| 4 | 06-B3 t7 a2 → HOLD | same | "Now bring the negative sign back in. Work with the sizes of the numbers first, then decide the sign." | same |
| 5 | 06-B3 t8 a0 (RESUME) | same | "You found that 12 ÷ 3 is 4. Now bring the negative sign back in and decide the sign of the quotient." | same |
| 6 | 06-B3 t8 a1 | same | "You found that 12 ÷ 3 is 4. Now bring the negative sign back in and think about what a negative divisor does to the sign of the quotient." | same |
| 7 | 06-B3 t8 a2 → HOLD | same | "You found that 12 ÷ 3 is 4. Now look at the same division with a negative divisor and choose the sign of the result." | same |
| 8 | 06-B3 t9 a0 (RESUME) | same | "You found that 12 ÷ 3 is 4. Now bring the negative sign back in and decide the sign of the quotient." | same |
| 9 | 06-B3 t9 a1 | same | "You found that 12 ÷ 3 is 4. Now bring the negative sign back in and decide what sign the quotient has." | same; attempt 2 accepted: "You worked out the size of the quotient. Now decide which sign belongs with it." |
| 10 | 06-B3 t15 PRESENT_ITEM a0 | parameters reuse an item instance already presented in this session | "That one is correct. Here is a fresh one to try on your own…" | freshness rule caught |
| 11 | 07-B3 t15 PRESENT_ITEM a0 | same | "The prerequisite equation is solved correctly. Here is a fresh grouped equation for the original target…" | freshness rule caught |
| 12 | B11 01-B11 t13 GUIDED_STEP a0 | contains the expected answer | "You found the size, 11, and the sign is positive. Now put them together for the full quotient." | real catch: states both parts of the active step's answer |

Rows 2–9: the learner's own answer "12 ÷ 3 is 4" was not the trigger. Exemption (b) worked as ruled. Replaying the guard on the same steps:
- "Good, 12 ÷ 3 is 4. Work with the sizes…" passes.
- "Good, 12 ÷ 3 is 4. Now bring the negative sign back in." is rejected.

Each rejected output was repaired or held, and none was shown to a learner.

## Keys (§6)

- **Deleted.** At 00:31:06Z, after a final literal-key scan of everything staged (0 hits), all five private config files holding a copy of the temporary DeepSeek key were deleted. No file in the scratch area holds a key copy, and no further live calls were made.
- **Revoke all three now.** The owner can now revoke the DeepSeek, OpenAI and Kimi keys pasted in the conversation. The uploaded key file is still in this session's upload area and goes when the session ends.
- **Production key.** The owner places it at `/home/ACCOUNT/algebra-private/config.php` (`DEPLOYMENT.md` §3, "Where the production API key goes"). It is never given to Claude Code.

Scripted learners only: these runs confirm provider compatibility and host gating, not teaching quality, learning or deployment.
