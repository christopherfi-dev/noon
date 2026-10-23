# Algebra Engine — round 6 report, stage 1 (offline)

Instructions: `CONDUCTOR_ROUND_6.md`. Log: `BUILD_LOG.md`, section "Round 6". Stage 1 only: mock provider, no key, nothing deployed. Stage 2 (live runs) waits for the owner's temporary key.

## 1. Branch and tag

- **Tag `beta-1.0`:** created on `061239c`, annotated with the deployment path and VPS sha256 `a1f02a0f…0fd6`. **Pushing it was refused** (HTTP 403 from this environment's git policy), so it exists only locally. **The owner must push it.**
- **Branch `round6-ai-autonomy`:** created from `beta-1.0` and pushed. No `claude/` prefix was needed. Nothing was committed to `claude/elegant-volta-blu3uj`.

## 2. What changed, by section

| § | Change |
|---|---|
| 2 | **Architecture v1.3:** copied from v1.2. It describes the four model operations and the checks that bind them, and replaces the round 4–5 host-written moments. The handshake is `architecture=1.3`. The CHANGELOG has a 1.3 entry. Runtime contracts: the envelope takes `step_proposal`; INTERPRET and DECIDE have their own small schemas. |
| 3 | **INTERPRET:** the host reads the entry first.<br>• A capital letter of the item's only variable is read as that variable. The trailing-"?" rule is kept.<br>• The model is asked only on the listed triggers.<br>• A reading is accepted only if its numbers and sign are in the entry, it adds no variable, and the checker can read it.<br>• On a scored item, a disagreement with the host's parse gets one clarifying question.<br>• Accepted readings are marked "read from the student's wording". |
| 4 | **RESPOND:** the model writes every reply. The question-mark rule is gone. Before an independent item the reply may only refer back:<br>• a number outside the previous problem or answer is rejected;<br>• the answer-leak guard covers the next item;<br>• the item text stays host-written;<br>• forward-looking phrases are recorded in an audit list.<br>B14 is replaced by this refer-back check, which the test harness runs after every request. |
| 5 | **Slips:** each template declares slip signatures on its arithmetic sub-steps. A model proposal is accepted only when the structural check agrees.<br>• A slip leads to a micro-question ("What is 7 × 5?"), then a retry; the run is paused, not reset.<br>• One pause per run; a second slip resets it.<br>• New goal wording everywhere; reports name each slip. |
| 6 | **Teaching decisions:**<br>• The model may propose a smaller step with a `step_expression`. The host evaluates it, checks its numbers and checks the question does not give its own answer.<br>• Readiness is decided within one to four guided problems.<br>• When no error pattern matched, the model chooses a prerequisite from the host's in-scope list. |
| 7 | **At most 2 provider calls per learner entry**, enforced by the host. Detail in §3 below. |
| 8 | **Live runner prepared:** the N1 natural-learner script is built from the owner's four cases, plus sentences and slips. It records the round 6 metrics. **No live run yet.** |
| 9 | **`dist/algebra-engine-ai-vps-upload.zip`:**<br>• folders `algebra-engine-ai/` and `algebra-engine-ai-private/` (paths `/var/www/html/educational-engines/algebra-engine-ai/` and `/var/www/algebra-engine-ai-private/`);<br>• base path `/educational-engines/algebra-engine-ai/`;<br>• its own database.<br>674 files, **sha256 `b7a0338f7d552f0057e8922f41ba6cf2445bfcfdf7a0d51a67c28a2c29b6ea90`**. Install guide: `app/DEPLOYMENT_VPS_AI.md`. **Not deployed.** |

## 3. Tests (stage 1)

| Gate | Interval (UTC) | Result |
|---|---|---|
| PHP 8.0.30, native | 04:04:55–04:06:13 | **PASS.** PHPUnit 245/245; browser 18/18; smoke and key-leak PASS |
| PHP 8.3.6 | 04:03:22–04:04:42 | **PASS**, same results |

**Coverage:**
- The owner's four live cases are covered in `Round6Test` and in browser checks R6-01 to R6-04, at 390 and 1280 px. The 14 screenshots are in `test-results/browser/round6/`.
- All eight adversarial cases pass, as do the readiness and prerequisite decisions.
- Existing tests changed only where round 6 changes behaviour: B8, B9, B11, B12, B14, B15, HOST-05, EVID-02, the live-adapter repair cases and the goal wording.

**Calls in the mock journeys** (`test-results/round6-mock-journeys/`, transcripts included):

| Script | Calls per session |
|---|---|
| B1 | 18 |
| B3 | 21 |
| B11 | 38 |
| N1 | 20 |
| **All 12 sessions** | 269 in total (22.4 per session) |

- No learner entry used more than 2 calls.
- An ordinary answer uses 1.
- An entry that needs a reading or a decision uses 2.

These are simulated results: they say nothing about live-model behaviour or learning.

## 4. Live metrics per setting

**Not run.** Stage 2 needs a temporary `deepseek-flash` key. Per setting (thinking disabled; thinking enabled at low effort) the runner produces:
- every metric §8 asks for;
- the transcripts.

## 5. Conflicts with the instructions, and my choices

1. **Call limit vs repairs.** Repairs count toward the 2 calls, so a rejected output is repaired only while a call remains; otherwise the operation holds. There is one exception: when only the model's choice of parameters fails, the host picks fresh parameters instead of holding. The binding call is not counted.
2. **No package contract change.** The new fields and schemas are in the runtime copies only; `contracts/` is unchanged.
3. **Exact stuck phrases stay host-matched.** "idk" and "just tell me the answer" are matched by the host, which saves a call. The model still writes the reply. The round 5 host lines are kept only as fallbacks when no reading can be asked for.
4. **A question answered on a scored item counts as help.** The item becomes practice with help before the answer is shown (invariant 7). The instructions do not say this.
5. **Where slips apply.** Slips are considered only where answers build a run. Each of these makes the original answer count as wrong:
   - a wrong retry;
   - an unanswered micro-question (hint, skip, stuck);
   - an unanswered retry.
6. **`proposed_transition` in the envelope is still only recorded.** Mode changes the model may decide go through the checked DECIDE call.
7. **Strict number guard.** "twenty five" is not accepted: only the number words zero to twenty count. Such an entry is unreadable, not wrong.
8. **The beta's ZIPs are not built on this branch.** The beta's two upload ZIPs are not rebuilt on this branch and are kept in `beta-1.0`. A Bluehost ZIP is not built.

## 6. Decisions needed

1. **Push the tag:** `git push origin beta-1.0`.
2. **Stage 2:** provide a temporary `deepseek-flash` key. I will run both settings, report, attach the transcripts and delete the key.
3. **Rule on choices 4, 5 and 7 above**, if you want them changed.
