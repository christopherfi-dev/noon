# Algebra Engine — round UI v3 report

Branch `claude/elegant-volta-blu3uj`. Instructions: `CONDUCTOR_ROUND_UI_v3.md`. Log: `BUILD_LOG.md`, section "Round UI v3". Offline only: no live run, no key, nothing deployed.

## Result

- **Commit:** `2adb239`, with the code, tests, bundles, gate results and screenshots. This report and the closing BUILD_LOG row are in the next commit.
- **`dist/algebra-engine-vps-upload.zip`:** 669 files, sha256 `5c0e8b8e1fbb2860c5bc1247b3cac9183bd1ba7adc4502fe7774d1b34cec03d0`. The Bluehost bundle was rebuilt too (681 files).

| Gate | Interval (UTC) | Result |
|---|---|---|
| PHP 8.0.30, native build | 23:43:13–23:44:16 | **PASS.** PHPUnit 228/228; browser 12/12; smoke test PASS for the Bluehost and VPS layouts and for upgrade safety; key-leak scan 0 hits |
| PHP 8.3.6 | 23:42:03–23:43:07 | **PASS**, same results |

There were no failures. I updated tests from the first UI round where v3 changed the wording; they are listed under "Added this round".

Assertion totals vary from run to run: 42,709 on 8.3 and 42,720 on 8.0, then 42,714 and 43,254 on two more 8.3 runs. The variation is in tests B2, B9 and FLOW-06, whose loops depend on numbers the mock model draws. B2 and FLOW-06 already varied in earlier gates. No test failed in any run.

## Already done in the first UI round

- **U1:** "you chose A and C" for multiple-choice answers, both on the server and in the browser.
- **U3, U4, U6, U8:** unchanged.
- **U5:** `aria-expanded` on "I'm stuck".

## Added this round

| Item | What changed | Tests |
|---|---|---|
| **U2** | **Learner part:** the four headings with the exact words, built from recorded evidence only:<br>• observations such as "Twice you multiplied only the x by the number outside the parentheses";<br>• the route as a sentence;<br>• "Practise with your teacher: … (not in this tutor yet)".<br>**Empty headings:** each has its fixed line.<br>**Earlier sessions:** results appear under "From earlier sessions" with dates, in both parts. | `ReportDownloadTest` (empty headings, B1 headings); `SummaryWordingTest` (both parts) |
| **U5** | Guided steps keep "Explain the idea". Enter or Space opens the menu and moves to the first choice; the arrow keys, Home and End move between choices. Escape or the toggle closes it, and focus returns to the answer field. | UI-04, UI-05 |
| **U7** | Dots appear only during target assessment, the independent check and the return to the target. A single building-block check shows "One question to check this building block." | `UiRoundTest`, UI-04 |
| **U9** | "Download report" saves `algebra-report-YYYY-MM-DD.html`:<br>• inline CSS, no scripts and no external requests (its own Content-Security-Policy forbids them);<br>• the four headings, the teacher details expanded, the date and the code.<br>"Print or save as PDF" prints the same content. The JSON download is now inside "Details for teachers". | `ReportDownloadTest` (content, escaping); UI-07 (the downloaded file opened in a browser makes no requests) |
| **U10** | **Creation:** the code is created at the first answer, shown under "Your code", and updated in the same transaction as every commit.<br>**Storage:** only its sha256 is stored, compared in constant time.<br>**Input:** accepted in any case, with or without spaces or dashes; every bad code gets the same message; at most 10 failed lookups per hour.<br>**The record holds:** each skill's status, date and mistake patterns, plus `route_target` and `next_skill`.<br>**Starting with a code:** a live session continues exactly. Otherwise a new session starts at `next_skill`, and "Next: …" to the route target follows through the change-topic path. If every goal on the route is met, the learner is told so and offered the beta skills.<br>**One device at a time:** entering the code elsewhere takes the session over, and the first device shows "This progress continued on another device."<br>**Also:** Start over leaves the record untouched; Forget deletes it and says so; the two privacy sentences are added; a `config.php` without the new key gets 90 days. | `ProgressCodeTest` (14 tests, one per point in conductor §3); UI-07 |
| **U11** | **Migration 004** only adds a table. Each migration now runs under a write lock and re-checks that it is still pending, so a second run changes nothing.<br>**Upgrade fixture:** made with release `a1f9e16` itself, holding a session left live mid-repair. After the upgrade:<br>• the old rows are byte-identical;<br>• the session continues at revision 4 in E06;<br>• a code is created at its next answer.<br>**Bundles:** neither contains `config.php` or any file inside `var/`; the build and the smoke test both check this.<br>**Docs and preflight:** "Updating an existing install" is in `DEPLOYMENT_VPS.md` (and `DEPLOYMENT.md`). The preflight shows a `SCHEMA` line. | `UpgradeTest` (2); smoke test |
| **Screenshots** | `test-results/browser/ui-round/`: 14 views at 390 and 1280 wide, 28 files in all | — |

## Decisions and conflicts

1. **Code strength.** 12 characters from 31 symbols give 12 × log2(31) ≈ **59.4 bits, not 60**; at least 60 bits would need 13 characters. I kept the 12-character format, because it groups as four-four-four. The length is one constant. **Please rule.**
2. **Showing a code the server cannot read back.** The code stays on the learner's device in an HttpOnly cookie, which lets "Your code", the summary and the download show it. If the response carrying a new code is lost, the code is replaced once. A code that has already been shown is never replaced.
3. **"Next:" after the summary.** It is offered as a menu option. After the summary, the change-topic action is allowed only for that offered target. The finished episode is not recorded as interrupted.
4. **Definitions.** A session counts as **live** until it reaches its summary. **"Needed help"** means a hint, explanation, stuck entry or guided step was used and the goal was not met. **Unresolved** means the skill was worked on without help and the goal was not met. Entering a valid code counts as use, which restarts the 90 days. After 10 failed lookups, even a correct code waits for the next hour.
5. **The "Welcome back" card** is built from the record alone, so its lines are coarser than the report's. For example, it says "At least once you…", because the record keeps pattern codes but not counts.
6. **No `var/` folder in the ZIPs.** The app and the preflight create it when needed. On the VPS, the guide now creates it with nginx as owner. The Bluehost folder-level `.htaccess` also covers `var/`.
7. **`TEACHER_FACILITATOR_GUIDE_DRAFT.md`** (a handoff file) still mentions the working field. I left it unchanged; it is still awaiting your decision from the first UI round.

## Addendum: architect rulings (2026-10-23)

- **(1) Code length:** kept at 12 characters. **(2), (3):** accepted; no change.
- **(4) Teacher guide:** `TEACHER_FACILITATOR_GUIDE_DRAFT.md` now matches the beta.
  - The working box is gone.
  - It describes task links as not in the beta.
  - It explains the progress code (continuing, take-over, earlier results, privacy, Forget and Start over) and the report (the four headings, the teacher details, the download and print).
  - The F1 manifest check accepts this file as a ruled difference. `MANIFEST.sha256` is unchanged.
- **Fix:** "What to work on next time" lists a skill outside the tutor only while a goal that depends on it is unmet this session. Such a goal is either the skill whose mistakes pointed to it or a skill above that one on the route.
  - When every goal is met, the heading shows "You met every goal available in this tutor so far. Ask your teacher what comes next."
  - The teacher details still list the open question.
  - Building blocks are listed there only when they gave trouble.
  - The "Welcome back" card cannot see these dependencies in the record, so it lists such a skill only while a recorded goal is unmet.
  - Test: `ReportDownloadTest::testOutsideSkillListedOnlyWhileADependentGoalIsUnmet`. Case 1: the session ends during repair, and A07 is listed. Case 2: the full B1 journey, with no A07 line and the fixed line, also checked in the downloaded HTML.
- **Gates:** both pass.
  - PHP 8.0.30 (00:38:41–00:39:41Z): PHPUnit 229/229, browser 12/12, smoke and key-leak scan PASS.
  - PHP 8.3.6 (00:37:29–00:38:35Z): the same results.
- **Commit:** `0b10a50`.
- **`dist/algebra-engine-vps-upload.zip`:** sha256 **`a1f02a0f47643e2c71e9549bd26a331a0ce79dd94815764d1c7bb1d6158efb6e`**.

Offline only; not deployed.
