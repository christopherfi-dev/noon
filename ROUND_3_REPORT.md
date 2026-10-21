# Algebra Engine — round 3 report (reasoning settings)

Repository `christopherfi-dev/noon`, branch `claude/elegant-volta-blu3uj`. The
ruling is in `CONDUCTOR_ROUND_3_REASONING.md`, and `BUILD_LOG.md` ("Round 3") has
the times. Nothing was deployed. The order follows §9 of the beta addendum.
The three result sets come after §5. **No configuration is chosen here**
(ruling 6).

## 1. Tests run, with pass/fail counts

**Final gate, 23:19:34Z–23:20:09Z** (after the live runs and the fixes in §2):
- every step exit 0;
- PHPUnit **164 passed, 0 failed** (97 unit, 67 integration; 29,017 assertions);
- browser 5/5;
- bundle smoke 12/12;
- key-leak scan 0 hits over 285 tracked files and 672 ZIP entries (key present: yes);
- 10 more suite runs straight after: all clean.

Pre-live gate, 21:49:19Z–21:49:45Z, on code commit `cead84f`:

| Step | Result |
|---|---|
| F1 manifest, handoff preflight, curriculum validator and guards | PASS |
| App preflight with the thinking-disabled config | PASS, including the new THINKING check |
| PHPUnit | **161 passed, 0 failed** |
| Browser (B1 ×2 widths, UI-01, UI-02, UI-03) | **5 passed, 0 failed** |
| Bundle build and deployment-layout smoke test | PASS, 12/12 |
| Key-leak scan | PASS, 0 hits. Key present: yes |

New tests:
- `testThinkingSettingsAreExplicit` checks:
  - thinking is sent on every call;
  - `reasoning_effort` is sent only when thinking is enabled;
  - `temperature` is sent only when thinking is disabled;
  - missing or inconsistent settings are refused.
- `testReasoningContentIsNeverKeptAndTruncationIsRecorded`: the simulated server returns a marked `reasoning_content`, and the test checks that the marker is absent from:
  - every database table;
  - the public view and the report;
  - every later request, including repair requests.

  It also checks that token counts and every `finish_reason=length` are logged.

**Live runs.** All three ran to the end:

| Configuration | Time (UTC) | Sessions completed |
|---|---|---|
| Thinking disabled | 21:50:04–21:56:08 | 8/8 |
| Thinking enabled, low | 21:56:18–22:11:17 | 8/8 |
| Thinking enabled, high | 22:11:17–22:33:24 | 8/8 |

All 24 sessions reached the session report (status COMPLETED). The final gate after the live runs is recorded in `BUILD_LOG.md`.

## 2. Failures, with exact messages

**Pre-live gate:** no failures.

**Gate after the live runs: three failures, all fixed.** None was in the code path the live runs measured. Raw output:

```
✘ Cover 01 capability matrix
  Failed asserting that 'Round 3 (2026-10-21): scripted live runs ... not teaching quality, learning or deployment. ...'
  contains "adapter compatibility only".
  /home/user/noon/app/tests/Integration/DeployCoverageTest.php:45
```
The matrix text was reworded for round 3. The test now checks for the limit statement "not teaching quality" and still checks for "mock mode".

```
file:///home/user/noon/app/tests/Browser/run_browser.mjs:51
TypeError: Cannot read properties of null (reading 'expected')
    at file:///home/user/noon/app/tests/Browser/run_browser.mjs:90:54
```
```
✘ Flow 01 distribution journey
  no active item; view={"status":"HOLD",...,"mode":"RETURN_TO_TARGET",...}
  /home/user/noon/app/tests/Integration/FlowTest.php:70
```

These two only fail on some random paths. A sweep of hundreds of random journeys found two root causes:

1. **A host collision between the opening fact and a step answer.** About 2% of B1 paths were affected.
   - Repair opens with the fact "Your answer multiplied only the x by 8", where 8 is the factor from the learner's previous item.
   - When a step of the new guided problem also had the answer 8, the disclosure guard rejected every attempt, and the session held.
   - This would have happened with the live model too. It did not occur in the live runs: none of their guard rejections came at a repair opening.
   - **Fix:** the planner now also avoids step answers that appear in the facts shown with the opening step.
   - **Regression tests:** `testRepairOpeningNeverHoldsOnItsOwnFacts` fails without the fix (seed `opening118`) and passes with it; `testOpeningFactsNeverContainAStepAnswer` is the unit version.
2. **A simulated-provider defect.** About 1.5% of journeys were affected.
   - The mock picked item numbers from a seed tied to the item id. So after a "parameters reuse an item instance" rejection it proposed the same numbers on every repair and held.
   - **Fix:** the mock now varies its proposal per repair request, as a cooperative model does. The live model already did this (2 such repairs at low effort, both accepted).
   - **Regression test:** `testRandomScriptedJourneysCompleteWithoutHolds` runs 40 random B1/B3 journeys.
   - After both fixes, a sweep of 400 journeys (200 B1, 200 B3) had 0 HOLDs.

The test helper now prints the last rejection reasons when no item is active.

**Repeat runs:**
- 20 more full-suite runs found one more intermittent problem, in a test.
  - In 1 run of 20, B9 raised warnings: `Undefined array key "TRUSTED_CURRICULUM"` (BetaGuardsTest.php:243).
  - B9 read the last message of each provider call. When a random path contained a repair, that message was the repair request, not the trusted context.
  - **Fix:** B9 now finds the single trusted context in every call. That is stricter: it also checks repair calls. B9 then passed 30 of 30 isolated runs.
- 25 further runs caught the remaining intermittent failure, also in a test:
  ```
  1) AlgebraEngine\Tests\Integration\HostTest::testHost16ForgetAndExpiry
  Failed asserting that 0 is identical to 1.
  /home/user/noon/app/tests/Integration/HostTest.php:452
  ```
  - `Api.php` runs an opportunistic cleanup on a random 1 in 50 requests. HOST-16 made two requests after the session expired, then asserted that its explicit cleanup removed exactly one session. In about 4% of runs the opportunistic cleanup had already removed it.
  - **Fix:** the test now checks that the session is gone. It checks the cleanup count separately, with no request in between. HOST-16 then passed 150 of 150 runs.
  - This is probably also the earlier one-off failure whose name I did not capture: one assertion, the same rate.

**Live:** every host rejection of model output and every HOLD is listed with its exact reason and rejected text:
- in each configuration's `METRICS.md`, under "Every host rejection of model output";
- in place, in the transcripts.

They are classified under "Rejections by kind" below. Nothing rejected reached a learner: the host checks each output before it is shown.

## 3. What was built (rulings 1–4)

- **Thinking settings** (ruling 1):
  - `provider_thinking` (`disabled`/`enabled`) is required for the live provider.
  - `provider_reasoning_effort` (`low`/`high`/`max`) is allowed only with thinking enabled.
  - `"thinking": {"type": ...}` is sent on every call; nothing is left to the provider default.
  - Preflight fails if the settings are missing or inconsistent.
- **Temperature** (ruling 2): `temperature` is sent only when thinking is disabled.
- **Reasoning text** (ruling 3): `reasoning_content` is never read, stored, shown or sent back. `model_calls` (migration 002) logs only, per call:
  - finish reason;
  - prompt, completion and reasoning token counts;
  - duration.
- **Output limit** (ruling 4): `max_tokens` is set per configuration: disabled 4,096, low 8,192, high 16,384. Every `finish_reason=length` is logged, and there were none.
- **After the live runs** (from the gate failures above):
  - the planner avoids opening facts;
  - the mock varies its proposal on repair;
  - two regression tests were added.
- **Session runner** (`app/tools/live_sessions.php`): now reports per-call time, empty responses, the `finish_reason=length` count and the model name returned in each response. It seeds the host random source per session.
  - After the runs it was changed in one way: it now also writes each binding reply into the transcript (see §5, item 4).

## 4. What was not done

- **The model's proposed item numbers still varied between runs.** The scripted learner's inputs were identical in all three runs: the same error at the same mode and skill, and correct answers otherwise. The host random source was seeded the same way. But the model proposes most item numbers, so items were not identical across runs.
- **No cost figure.** DeepSeek does not return cost in the response. The token counts and cache-hit/miss counts are given instead.
- **Beta deferrals unchanged.** All beta deferrals still apply.

## 5. Conflicts and points for the architect (proposals only; nothing was tuned during the runs)

1. **Answer-leak guard false positives on sign steps.** In sign-step messages the guard rejected:
   - "decide whether the result is negative or positive" (both options named, neither chosen);
   - "put the negative sign back on the divisor" (describing a number, not choosing an option);
   - restatements of the learner's own just-entered answer ("72 ÷ 9 gives 8"). That number is also the answer to an unused smaller step, so it counts as protected.

   **Proposal:**
   - flag an option only when it is named as the choice;
   - stop protecting smaller steps whose parent step the learner has already answered.
2. **The invalid-JSON repair message is not specific.** In the low-effort run the model sent the same unbalanced closing brace (`}]},` after `evidence_proposals`) three times. That led to a HOLD. The repair request said only "output is not valid JSON". **Proposal:** include the parser's error position.
3. **Empty responses at high effort.** 16 calls returned no content: finish `stop`, with all 485–2,277 output tokens spent on reasoning. Each one costs a bounded repair attempt (beta decision 16, still open). There were none at disabled or low.
4. **Binding reply not recorded.** At high effort, one binding (session 04-B1) failed on the first try and passed on the second. The runner did not keep the text of the rejected binding reply, so I cannot show it. The runner now writes binding replies into transcripts; this run's text is lost.
5. **Network HOLDs.** There was one at low (session 05-B1, 0.06 s) and one at high (01-B1, 5.3 s). Both were HTTP 0 at the transport level, with no model output. Traffic passes through this build environment's proxy, so the cause cannot be attributed. RESUME recovered both.
6. **Model name.** Every response reported `deepseek-flash` (441 of 443 calls; the other 2 are the network failures).

## The three result sets

All three runs used model `deepseek-flash`, provider_timeout 45 s and lease 90 s, with the same scripted learner and 5 B1 + 3 B3 sessions each. Raw data and transcripts:

- `test-results/live/round3/thinking-disabled/` (8 transcripts, `METRICS.md`, `RUN_LOG.md`)
- `test-results/live/round3/thinking-low/`
- `test-results/live/round3/thinking-high/`

| Metric (totals over 8 sessions) | Thinking disabled | Thinking enabled, low | Thinking enabled, high |
|---|---|---|---|
| max_tokens | 4,096 | 8,192 | 16,384 |
| Sessions reaching the report | 8/8 | 8/8 | 8/8 |
| Binding on first try | 8/8 | 8/8 | 7/8 |
| Provider calls | 141 | 146 | 156 |
| Repairs per operation, with accepted generations in brackets | PRESENT_ITEM 3 (84); GUIDED_STEP 4 (42) | PRESENT_ITEM 8 (84); GUIDED_STEP 2 (42) | PRESENT_ITEM 16 (84); GUIDED_STEP 4 (42) |
| HOLDs | 0 | 2 (1 invalid JSON ×3, 1 network) | 1 (network) |
| Host rejections: answer-leak guard | 4 | 2 | 2 |
| Host rejections: other | 3 | 9 | 18 |
| Empty responses | 0 | 0 | 16 |
| `finish_reason=length` | 0 | 0 | 0 |
| Call time, median / slowest | 2.6 s / 3.7 s | 6.0 s / 13.0 s | 7.9 s / 24.3 s |
| Learner wait per answer (API request), median / slowest | 2.7 s / 6.8 s | 6.3 s / 21.4 s | 8.5 s / 30.4 s |
| Completion tokens (of which reasoning) | 64,778 (0) | 182,535 (102,923) | 276,593 (193,988) |
| Prompt tokens (cache hit / miss) | 1,809,289 (1,494,272 / 315,017) | 1,872,775 (1,544,320 / 328,455) | 1,994,039 (1,678,720 / 315,319) |
| Cost | not reported by provider | not reported by provider | not reported by provider |

Per-session figures are in each `METRICS.md`.

### Rejections by kind

Every rejected output was repaired or held. None was shown to a learner.

| Kind | Disabled | Low | High |
|---|---|---|---|
| Invalid JSON | 0 | 3 (same turn → HOLD) | 0 |
| Empty content | 0 | 0 | 16 |
| Field or parameter outside the contract (`parameters.options` value, `item.role`) | 0 | 2 | 0 |
| Item rule: reused item instance / error patterns not distinct | 0 | 2 | 1 |
| Binding not exact on first reply | 0 | 0 | 1 |
| Rule: question mark in the learner message | 1 | 0 | 0 |
| Rule: mentions "the host" or "key" ("matched the key exactly", "the host is showing you") | 2 | 2 | 1 |
| Answer-leak guard: the message gave the step's answer ("five fives gives 25") | 1 | 0 | 0 |
| Answer-leak guard: borderline (restates the learner's two earlier answers, which together give the current answer: "size 6 and sign negative") | 0 | 1 | 0 |
| Answer-leak guard: false positive (see §5, item 1) | 3 | 1 | 2 |

The rejected text and reason for each one are in the `METRICS.md` of its configuration.

## Key handling

- The provider key is only in private configs outside the repository.
- Transcripts contain no key and no reasoning text.
- The key-leak scan passes on the committed files and the upload ZIP.
- Please revoke the temporary keys pasted earlier in the conversation once testing ends.
