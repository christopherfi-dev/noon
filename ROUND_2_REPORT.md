# Algebra Engine — Conductor Round 2 report

Repository `christopherfi-dev/noon`, branch `claude/elegant-volta-blu3uj`.
Round 2 started 2026-10-21T21:10:29Z (separate interval from the beta; see
`BUILD_LOG.md`, "Round 2"). Nothing was deployed. Order follows §9 of the beta
addendum, then the items §5 of the round 2 file adds.

**Status in one line:** every round 2 code item is done and every test passes.
The live run stopped at §4.1: the provider accepts `deepseek-v4-flash` but does
not list it. The 8 scripted live sessions were not run, so there are no live
transcripts.

## 1. Tests run, with pass/fail counts (final gate, 21:36:14Z–21:36:33Z)

Raw output for each step is in `test-results/`. `SUMMARY.md` has times and exit codes. The gate ran with the
private live config set, so the key scan also checked the key's literal value.

| Step | Result |
|---|---|
| F1 manifest, before and after preflight | PASS. The only difference is `architecture/CHANGELOG.md` (expected) |
| Handoff preflight, curriculum validator, guard tests | PASS |
| App preflight | PASS: 1 WARN (PHP 8.3, not 8.4/8.5). Architecture hash `ef03667d…` PASS |
| PHPUnit | **160 passed, 0 failed** (96 unit, 64 integration; 25,933 assertions) |
| Browser (Playwright + Chromium, mock) | **5 passed, 0 failed**: B1 at 360×740 and 1280×800, UI-01, UI-02, UI-03 (new) |
| Bundle build + smoke in deployment layout | PASS, 12/12 |
| Key-leak scan (`09-key-leak-scan.txt`) | PASS: 252 tracked files, 671 ZIP entries, 0 hits. Key present: yes |

New or changed tests in round 2:
- **B9 extended** (ruling 4): fails if `examples/model-question.json` appears in any assembled prompt.
- **B10** (ruling 8): two tests, `testB10StuckStepGoesDirectlyToItsPrerequisite` and `testB10StuckStepAtABoundaryIsRecordedNotFailed`.
- **Key-leak test** (§1.2): `KeyLeakTest`, two tests. One scans the repository; the other plants fake keys to show the scanner finds them.
- A unit test checks that every guided step is tagged with a skill it exercises.
- Transition-guard assertions cover the new edge, which works only with its trigger.
- Existing tests now expect `PROVIDER_UNAVAILABLE` for timeouts, HTTP errors and budget stops; learner text is unchanged.
- A test checks that `reasoning_effort` is sent only when configured.
- The D1 test was updated to the new routing (§2 below).

## 2. Failures, with exact messages

**Final gate:** none.

**During development:** one expected failure, caused by ruling 8's change of route. Raw output:

```
1) AlgebraEngine\Tests\Integration\BetaGuardsTest::testD1HintAndIDontKnowGiveSmallerQuestions
Failed asserting that two strings are identical.
--- Expected
+++ Actual
@@ @@
-'INDEPENDENT_CHECK'
+'PREREQUISITE_CHECK'

/home/user/noon/app/tests/Integration/BetaGuardsTest.php:179

FAILURES!
Tests: 156, Assertions: 24248, Failures: 1.
```

The test had encoded the old route, independent check then probe. It now asserts the
ruled route: PREREQUISITE_CHECK at N18.

**Live (§4):** the run stopped by rule (raw record: `test-results/live/00-smoke-and-model-check.md`):

```
GET https://api.deepseek.com/v1/models -> HTTP 200; listed: deepseek-flash, deepseek-v4-pro
configured model 'deepseek-v4-flash' listed: no
STOPPED before any session (Round 2 §4.1): the configured model is not listed. No sessions were run.
```

## 3. What was built in round 2

**§1 Key handling**
- **History check:** `git log --all -p` has no `sk-…` match, so no key is in any commit.
- **`.gitignore`:** now ignores `config.php` (at any depth), `*.env`, `.env*` and `config.local.php`. `app/private/config.example.php` stays tracked; I added the `!` exception explicitly, although no pattern catches it.
- **Scanner and test:** `app/tools/key_leak_scan.php` and `KeyLeakTest` scan tracked files and every entry of the upload ZIP. They report locations only, never the matched text.
- **Runner:** a scan step runs after each bundle build. The key is never printed, logged or committed, and the records say only "key present: yes/no".
- **Second pattern:** the ruled pattern `sk-[A-Za-z0-9]{20,}` does not match OpenAI-style prefixed keys (`sk-proj-…`), including the OpenAI key pasted in this conversation. I added a second pattern for `sk-proj-`, `sk-svcacct-` and `sk-admin-` keys. All three keys pasted in the conversation are now detected; that was checked without printing them.

**§2 Rulings**
- **2:** the v1.2 title now reads v1.2. The status header's Compatibility line now reads "v1.2. Retains v1.0 evidence and curriculum semantics and the v1.1 entry and orientation modes; guided repair teaches through questions only."
- **3:** removed "Use concise explanations when they bridge an actual conceptual gap." and "a concise explanation, " from both v1.2 copies. Both changes are in the CHANGELOG 1.2 entry. The hash is re-recorded in the CHANGELOG, preflight and BUILD_LOG:
  - new: `ef03667dabf7152be63009ec6c1e327a83340b4fff65eef303e3eeb326291390`
  - beta: `8997b6b8…`
- **4:** B9 extended as ruled.
- **6:** `PROVIDER_UNAVAILABLE` is in the runtime copies of the issue enum (model-envelope and session schemas). It is used for provider timeouts, HTTP errors and budget stops (including the lease-time stop). `INVALID_ITEM` and `CONTRACT_CONFLICT` are unchanged.
- **8:**
  - Every guided step carries `exercises`: the repair skill or one of its declared prerequisites. 11 tags were corrected; they are listed in §5.
  - If the learner is stuck at the smallest guided question and that skill is a declared prerequisite within the beta, the engine goes straight to PREREQUISITE_CHECK on it. The interrupted parents stay on the return stack. The edge is in the runtime workflow policy as a `trigger_edges` entry (`STUCK_AT_SMALLEST_GUIDED_STEP`), so the general guard and model proposals cannot use it.
  - Otherwise a BOUNDARY is recorded as in B4: `NOT_AVAILABLE_IN_BETA`, or the new `NOT_A_DECLARED_PREREQUISITE`. It counts as not a learner failure, pushes no frame, and the report lists it as unresolved.

**§3 Skill map:** shows the 10 beta skills by default. A "Show full map" toggle (with `aria-pressed`) shows all 205 skills with unchanged status labels. Browser test UI-03 covers it.

**§4 Live**
- Optional `provider_reasoning_effort` setting, sent as `reasoning_effort` only when set.
- `app/tools/live_sessions.php` runs the 5 B1 + 3 B3 scripted sessions. It writes transcripts (learner text, tutor text, mode, verdict per turn, every rejection with the rejected text) and `METRICS.md`, and it applies the same model-list gate.
- A host audit event, `MODEL_OUTPUT_ACCEPTED` (operation, attempts), lets repairs be counted per operation.
- The runner was tried only against the simulated provider: 8 sessions, all completed. That output is not a live result and is kept outside the repository.

## 4. What was not done

- **The 8 scripted live sessions (§4.2–4.3) were not run.** The run stopped at the §4.1 gate. The runner is ready: one command, once the model question is settled (§5, item A).
- All beta deferrals still apply: teacher task links, starting-point placement, resume, review checks, graphs, and every skill outside the 10.

## 5. Conflicts and decisions for review

**A. Model name (blocks the live run).**
- The owner chose `deepseek-v4-flash` with `reasoning_effort: high`.
- With that name the provider answered all 5 smoke calls with HTTP 200, and the smoke test passed.
- `/v1/models` and `/models` list only `deepseek-flash` and `deepseek-v4-pro`.
- Options:
  1. Approve the unlisted name: the runner then needs `--accept-unlisted-model`.
  2. Use `deepseek-flash`, which is listed and was used in the beta-round runs.
  3. Use `deepseek-v4-pro`.
- I could not reach `api-docs.deepseek.com` (DNS failure), so I cannot say whether `deepseek-v4-flash` is an alias.

**B. `reasoning_effort`.**
- The provider accepted the parameter (HTTP 200).
- Reasoning tokens per JSON call were 803–1,364, but there is no baseline, so I cannot say whether `high` changed anything.
- Not checked against the docs, for the same reason as item A.

**C. Ruling 2 wording.** No header line named a version, so I put "v1.2." into the Compatibility line and rewrote that line. Please confirm the wording.

**D. Ruling 8: details the ruling left open.**
1. **After a boundary at a stuck question:** a new guided problem at the same skill, within the existing 3-problem budget. When the budget is used up, an independent check follows. B10 covers both.
2. **After the prerequisite check succeeds:** repair at the parent restarts with a fresh guided problem. The abandoned problem is not resumed.
3. **Final steps are tagged with the repair skill itself.** Being stuck there is a `NOT_A_DECLARED_PREREQUISITE` boundary.
4. **Tags corrected:**
   - N03 final steps: N02 → N03
   - N04 final: N03 → N04
   - N05 sign-of-product step: N02 → N05
   - N05 final: N02 → N05
   - N06 final: N05 → N06
   - N18 missing-factor and multiplication-fact steps: N17 → N18
   - N18 finals: N17 → N18
   - Q04 final: N03 → Q04
   - Q05 final: N06 → Q05
   - E06 final: A07 → E06

   Please review the N05 sign step: the sign rule for products is N05's own content, so I did not tag it N02 (opposites).

**E. Ruling 6.** `PROVIDER_UNAVAILABLE` is now also among the issue values the model may propose (allowed_values). The host still sets every issue itself.

**F. Gate run used the live config**, so `03-app-preflight.txt` reflects it. It shows that provider settings are present; no key is printed.

**G. Key pattern.** The scanner adds a pattern for prefixed keys (§3). It finds strictly more than the ruled pattern, never less. Please confirm.

**H. Decisions 16–20 from `BETA_STOP_REPORT.md` §6 are still open.** These are:
- empty content counts as a repair attempt;
- token headroom;
- "host" and "key" are blocked as words;
- the host rejected the model's proposals;
- wording quality.

This round brought new data on the first: in the round 2 smoke test, 1 of 4 JSON calls returned empty content (finish `stop`, 1,364 tokens, all reasoning).

## Live metrics table (§4.3)

Only the smoke test ran live, so this table covers it alone. Source: `test-results/live/00-smoke-and-model-check.md`.

| Metric | Smoke test (deepseek-v4-flash, reasoning_effort high) |
|---|---|
| Binding success on first try | yes |
| Envelope repairs per operation | PRESENT_ITEM (first Q07 item): 1; PRESENT_ITEM (probe): 0; HINT: 0 |
| HOLDs | 0 |
| Disclosure-guard rejections | 0 |
| Other host rejections | 1 (empty content) |
| Turn latency | median 9.3 s, slowest 14.7 s (3 turns with provider calls) |
| Tokens in | 56,820 (cache hit 40,832, miss 15,988) |
| Tokens out | 5,826 (reasoning 4,409) |
| Cost | not reported by the provider |
| Sessions B1 ×5, B3 ×3 | **not run** (stopped at §4.1) |

## The 8 transcripts

None exist, because the scripted live sessions did not run. `test-results/live/RUN_LOG.md` records the stop.

## Every host rejection of model output during the live run

1. Smoke step 1, PRESENT_ITEM (first Q07 item), attempt 0.
   - Reason: `empty content`.
   - Rejected text: empty. The provider's finish reason was `stop`, with 1,364 completion tokens, all reasoning.
   - The repair attempt was accepted.

## Before wider use

- Revoke all three keys that were pasted into the conversation (DeepSeek, OpenAI, Kimi).
- Put a new DeepSeek key only in the server's private `config.php`.
- The private test config with the current key is outside the repository, in this container's scratch area. It disappears when the container ends.
