# Algebra Engine beta — stop report (Conductor Addendum Beta v1, §9)

Repository `christopherfi-dev/noon`, branch `claude/elegant-volta-blu3uj`.
Session start 2026-10-21T11:39:36Z; Beta Gate passed 2026-10-21T13:00:08Z
(elapsed 1 h 20 min 32 s, computed from `BUILD_LOG.md`). Nothing was deployed.
All results are **mock mode** (simulated model). No live provider test ran.

## 1. Tests run, with pass/fail counts (latest gate run, 13:04:51Z–13:05:09Z)

Raw output for every step is in `test-results/` (`SUMMARY.md` has start and
finish times and exit codes).

| Step | Result |
|---|---|
| F1 `sha256sum -c MANIFEST.sha256`, before and after preflight | PASS. 71/72 match; the one difference is `architecture/CHANGELOG.md`, which addendum §3 told me to edit. `HANDOFF_PREFLIGHT_REPORT.json` is restored after the preflight rewrites it (regenerated copy kept in `test-results/`). |
| `tools/validate_handoff.py` | PASS (10 check groups, 53 future scenarios listed) |
| `curriculum/validate_curriculum.py`, `test_validation_guards.py` | PASS (205 skills, 493 edges; 8 mutation guards) |
| App preflight `app/private/bin/preflight.php` | PASS: 15 PASS, 2 WARN (PHP 8.3 rather than 8.4/8.5; no `config.php`, so defaults) |
| PHPUnit unit + integration | **152 passed, 0 failed** (95 unit, 57 integration; 24,206 assertions). The gate itself passed at 13:00:08Z with 150; two live-adapter tests were added afterwards. |
| Browser, Playwright + Chromium, mock mode | **4 passed, 0 failed**: B1 at 360×740, B1 at 1280×800, UI-01, UI-02 |
| Bundle build + smoke test in the two-folder deployment layout | PASS, 12/12 smoke checks |

Section 6 coverage (`app/ACCEPTANCE_SCENARIOS.md`, enforced by
`ScenarioMapTest`):
- All 37 listed scenarios are implemented and pass.
- B1–B9 are implemented and pass. B1 also runs in the browser at both widths.
- 5 unlisted scenarios are implemented anyway because deferring them would weaken an invariant inside the beta scope: FLOW-03 (invariant 8), EVID-04 (10), EVID-05 (6 and 9), HOST-04 (14), HOST-14 (3).
- The other 11 are deferred, each with a one-line reason (section 4 below).

Repeatability check: the full PHPUnit suite ran 12 times in a row with no
failures, and the browser suite 3 times with no failures. Item parameters
vary from run to run, so this is a check for flaky tests.

## 2. Failures, with exact messages

**No failures in the final gate run.** I found and fixed these earlier in the
session. The raw messages are as they appeared.

- **Gate run 1, 12:56:01Z, manifest step:**
  ```
  architecture/CHANGELOG.md: FAILED
  sha256sum: WARNING: 1 computed checksum did NOT match
  ```
  The edit is the one addendum §3 requires. The step now accepts that file
  and nothing else (`app/tools/manifest_check.sh`).
- **Gate run 1, PHPUnit 148/149, HOST-08:**
  ```
  Failed asserting that '{"item_id":"itm_8e5c55893bc0e5f1d59b6b34","item_version":1,"response_type":"scalar","input_help":"Enter a single number, for example 7, -3 or 5\/2.","support_status":"INDEPENDENT"}' does not match PCRE pattern "/(?<![\d.])7(?![\d.])/".
  ```
  The test was flaky. When the key happened to be 7 it matched the static help
  text, and in other runs it matched digits inside the random hex item ID or
  `item_version`. The test now checks that no public text field equals the
  key and that the item exposes exactly its six public fields.
- **Product defects that tests caught before the gate.** All are fixed, and
  each has a test.
  - **Concurrency:** the pending-operation guard read a stale snapshot field. As a result END_SESSION did not cancel an in-flight lease, and a concurrent request was not refused (FLOW-07; a new concurrent-request test was added).
  - **Schema validation:** the envelope and event payloads lost the `{}` versus `[]` distinction before schema validation (B7).
  - **Example text in item wording:** the prompt "Give the value of x." duplicated text from `examples/model-question.json` (B9). The wording is now "Solve … for x."
  - **Report wording:** "durable mastery" appeared in report disclaimers. It is now reworded; the report never contains "master".
  - **A guided step printed its own answer:** one N03 step did ("What is the difference between 9 and 18?"). The planner now regenerates such problems.
  - **Disclosure-guard false positives:**
    - the grouped problem expression, such as "3(x + 4)", counted as a leak of 3x + 12
    - "80 ÷ 10" counted as a leak of 8
    - "x − 4" counted as a leak of −4

## 3. What was built (`app/`)

- **PHP 8.3 host and vanilla JS UI.** Same-origin API at `api.php?action=…` with bootstrap, start, event, state, report and forget. Storage is private PDO SQLite.
- **Session security:** HttpOnly cookie ownership, a session-bound CSRF token plus origin checks, revision and idempotency checks, a lease on every provider operation, and late results discarded.
- **Architecture v1.2** (`architecture/Algebra_Constraint_Architecture_v1.2.md`), with exactly the three D1 changes and a CHANGELOG entry. The runtime binds it and records its SHA-256 `8997b6b8…cca772` with every binding.
- **Provider protocol:**
  - one text-mode binding call, which must return the exact v1.2 line, with one bounded activation repair allowed
  - then JSON envelope calls validated by full JSON Schema (opis/json-schema, local `$ref`) and host semantic checks
  - at most 2 bounded repairs per operation, then a HOLD the learner can retry
- **Adapters:** a mock provider (default; no network code) and a configurable OpenAI-compatible adapter (HTTPS only, certificate verification, size cap). DeepSeek is the intended live provider and is not yet chosen.
- **F2:**
  - The output-shape example is a skeleton generated from the envelope schema, with type placeholders only.
  - `anchor_item` is stripped from every skill record sent to the model.
  - B9 checks every prompt against all 205 anchors and against the example strings.
- **Ten beta skills, 14 server-owned templates.** The host renders every prompt and derives every key. A model may only propose parameters; the host validates them. A model-proposed key that disagrees with the host key is flagged, and the host key governs.
- **Q07 and E06, three variants each (D3).**
  - Q07 constructed is `a(x+b)=c`. Q07 transfer is `a(x+b)+d=c` or `c=a(x+b)`.
  - The Q07 contrast item asks the learner to "select every valid first step" from four options. The host derives the key by solving each displayed equation, and option order is randomised.
  - E06 has constructed expansion, a contrast on whether the factor reaches every term, and transfer to `a(px+q)` or `a(x+y+q)`.
  - The criterion is three consecutive independent correct items covering all three variants, giving IMMEDIATE_CRITERION_MET.
  - The other 8 skills report COMPONENT_CRITERION_MET and PARTIAL_COMPONENTS.
- **Error signatures for GROUPED_LINEAR:**
  - distributing to x only, which selects the E06 branch
  - sign error on b
  - dividing c by a then adding b
  - an arithmetic error in the final step (matched only as ±1)

  The last three select the Q06 branch. Signatures also exist for the other templates. Every match is recorded as "matches …, observation, not a confirmed cause".
- **Slip check.** After an incorrect Q07, the learner gets one fresh probe that varies the implicated component. If the probe is correct, "error not reproduced" is recorded, there is no descent, and the streak stays broken.
- **D2 descent.** The engine descends one declared edge at a time along the branch the signature implicates, until the learner succeeds independently or the beta boundary is reached. Skills outside the beta are recorded as "not available in beta", the branch stays unresolved, and this is never reported as a learner failure. The return stack is preserved, and each parent is re-verified with fresh work.
- **D1 Socratic repair.**
  - Every guided step is a host-built question asking for one action.
  - A wrong step, a hint request or "I don't know" leads to a smaller question, then an alternate phrasing.
  - Support fades: the whole problem is asked first, and the steps are used only if needed.
  - Model text in repair is rejected if it contains "?", state names, mastery or verification claims, or the answer to the active step or any remaining step. The last check is the disclosure guard, which is a heuristic and is labelled as one.
- **Evidence.** Criteria are recomputed from the append-only ledger on every commit. Results are bound to the exact item version, response hash and event. A hint makes the item assisted before it is shown. Reused instances are refused by fingerprint. An operator-only correction recomputes claims and preserves the original records.
- **Report** (§7). It is built from the ledger only. Per skill it lists items attempted, independent versus assisted, verdicts with checking scope, observed signatures, descent and return, and criterion status. Separate sections cover boundaries, untested items and next evidence. It is labelled MOCK, never says "mastered", and can be downloaded as JSON or printed.
- **Deployment.**
  - Preflight; it fails clearly without PDO SQLite and has no text-file fallback.
  - Cron cleanup, forget, idle and absolute expiry.
  - Per-session and global daily call budgets, and start rate limits.
  - `dist/algebra-engine-bluehost-upload.zip` with `algebra-private/` and `public_html/algebra/`, production dependencies only.
  - `app/DEPLOYMENT.md` covers cPanel placement, the preflight, cron, the smoke test and rollback.
- **Capability matrix** (`app/CAPABILITY_MATRIX.md` and `.json`). It is generated from the tested runtime registry, not from the curriculum. All 205 skills are browsable in the UI with their runtime status: 2 AVAILABLE_SCORED, 8 PARTIAL_COMPONENTS, 195 UNAVAILABLE.

## 4. What was deferred (listed as NOT IMPLEMENTED in the capability matrix)

**Addendum §5 deferrals:**
- Teacher task links: the endpoints return 422.
- "Help me find a starting point" orientation: the UNSURE route returns 422.
- Free-text topic mapping.
- Resume across sessions: resuming a closed session returns 422.
- Review and retention checks.
- Transfer checks outside Q07 and E06.
- Graph, table and reasoning-rubric inputs.
- All skills outside the beta list.
- Localisation.
- Accounts.

**Package scenarios deferred** (one line each in `app/ACCEPTANCE_SCENARIOS.md`):
- ENTRY-02 to ENTRY-08: orientation, free text and task links
- FLOW-08: resume a closed session
- MATH-04 and MATH-05: solution sets and rational domains, which no beta skill uses
- MATH-07: graphs. The capability boundary itself is enforced and tested by B4.

**Other gaps:**
- No bundled math renderer: math is plain Unicode text, and no TeX is interpreted.
- No model-worded REPORT envelopes: the report is host-built.
- No solution-set, interval or domain checkers.
- No MySQL adapter.
- No purge from backups.
- **Live provider smoke test: NOT RUN.** The `config.php` supplied after the build has an empty provider key. DeepSeek is reachable from the build environment (HTTP 401 without a key). The adapter is tested against a simulated OpenAI-compatible server (`LiveAdapterTest`). Once a key exists, `app/tools/live_smoke.php` runs the check in one command.

## 5. Conflicts with the addendum or the package, and decisions for review

**Package and addendum conflicts.** I did not resolve these silently; each one needs your confirmation.

1. **`architecture_version` const "1.1".** `contracts/model-envelope.schema.json`, `session.schema.json` and `report.schema.json` pin it, but the runtime binds 1.2. The package contracts are unchanged. The app's runtime copies in `app/private/resources/contracts/` change only that constant to "1.2". If the architect prefers, the contracts can be versioned instead.
2. **v1.2 title still reads "v1.1".** The title and status header of the v1.2 file say v1.1, because the addendum allows only three changes.
3. **Leftover sentence in v1.2 §10.D.** It still says "Use concise explanations when they bridge an actual conceptual gap". This sits in tension with D1 ("teaches only through questions"). The beta follows D1: in repair, an explanation request is answered with a smaller question.
4. **Prompt template still points at the example.** `prompts/INSTRUCTIONAL_CONTEXT_TEMPLATE.md` still says "Example shape is examples/model-question.json" (the package file is untouched). The app follows F2 and uses the schema skeleton instead, which also stands in for spec 03's "schema-shaped example".
5. **Q07 response types.** The curriculum record lists only `scalar`. D3's contrast item is a classification item; I took the addendum as overriding the curriculum here.
6. **No provider-failure issue type.** The issue enum has none. Provider timeouts and budget stops map to ITEM_VERIFICATION_UNAVAILABLE, invalid output after repairs to INVALID_ITEM, and a binding failure to CONTRACT_CONFLICT. The learner sees "your work is saved; try again".
7. **No retry action.** `public-actions.json` has no RETRY, so `RESUME_SESSION` retries a held generation inside a live session. Closed-session resume stays deferred.
8. **No edge from GUIDED_REPAIR to PREREQUISITE_CHECK.** The workflow policy has none. If a learner is stuck at the smallest guided question, the problem is set aside (no answer is shown) and an independent check follows. Its failure leads to a probe and then descent along the implicated skill, which is recorded (test `testD1HintAndIDontKnowGiveSmallerQuestions`).

**Design decisions taken under D2, D3 and the slip check, for the architect to confirm:**

9. **Re-verification on the way back up.**
   - An intermediate parent is re-verified with one fresh independent correct item before the engine climbs further (`Engine::PARENT_REVERIFY_REQUIRED = 1`).
   - The repair point needs its own three-in-a-row criterion.
   - The original target needs its full criterion.
10. **Where the slip check applies.**
    - Every incorrect independent item at the target, on return, or at an independent check gets one fresh probe.
    - An incorrect prerequisite check counts as confirming the breakdown, and descent continues without an extra probe.
11. **Overlapping signatures.** For `a(x+b)=c`, "sign error on b" and "dividing c by a then adding b" always give the same value. Both labels are recorded together as indistinguishable on that item.
12. **After the criterion.** Once the original target's criterion is met, the session goes to SESSION_REPORT ("authorized work complete"). The separate TRANSFER_CHECK mode is not entered, because the transfer variant is part of the Q07 and E06 criterion.
13. **Repair limits.**
    - At most 3 guided problems per repair cycle.
    - Support fades to an independent check after a faded problem is answered without breakdown.
    - After 3 failed cycles the repair pauses with REPAIR_LIMIT_REACHED (FLOW-06).
14. **Mock parameters.** The mock provider proposes parameters with the host's own generators, since it is a simulated model. The key-disagreement path (B6) and all other model faults are exercised by injecting faults.
15. **Snapshot event list.** `accepted_event_ids` in the snapshot holds the most recent 2,000 IDs (the schema's maxItems). The full ledger is in the `events` table.

The architecture and the curriculum's mathematical facts gave no conflict
that blocked the build.
