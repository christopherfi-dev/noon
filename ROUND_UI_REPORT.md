# Algebra Engine — round UI report

Branch `claude/elegant-volta-blu3uj`; `CONDUCTOR_ROUND_UI.md`; `BUILD_LOG.md`, "Round UI". Offline only: no live run, no key, nothing deployed.

## Result

- **Commit:** `9475c6d` (code, tests, bundles, gate results, screenshots). This report and the closing BUILD_LOG row are in the next commit.
- **`dist/algebra-engine-vps-upload.zip`:** 666 files, sha256 `72e279b63bb51f1756a6e90525d8f510b2120198ac9c2325ac23e9849063ab5c`. The Bluehost bundle was also rebuilt (679 files).

| Gate | Interval (UTC) | Result |
|---|---|---|
| PHP 8.0.30 (native build) | 22:55:18–22:56:11 | PASS: PHPUnit 208/208, browser 10/10, smoke PASS for both bundles, key-leak scan 0 hits |
| PHP 8.3.6 | 22:54:08–22:55:02 | PASS: same results |

There were no failures apart from one existing assertion, updated as noted under decision 1.

## What changed

| Item | Change | Tests |
|---|---|---|
| U1 | When guided repair opens after a wrong answer, the host line "On 8(x + 5) = 64 you answered 13." comes before the opening. A guided step that doesn't repeat its problem is shown under a "Problem:" line. After other results, the browser names the item that was just answered. | `UiRoundTest` (the B1 feedback before the first guided problem; every number in it is visible on screen), `ProblemTextTest`, browser B1 and UI-04 |
| U2 | Learner level with four parts: what you worked on, goals met, what was hard, what to practise next. It shows skill titles only. "Details for teachers" is collapsed by default and holds the full record, with skill IDs in brackets. Both levels use plain words, show no raw codes and repeat no line. Under 600px the table becomes cards. | `SummaryWordingTest` (both levels: new terms, codes, IDs, duplicates, truth rules), UI-04 (no word broken across lines in any cell) |
| U3 | Each option label carries the full option text, and the question no longer repeats the options. Equations stay on one line. The question text is normal weight; only the main expression is bold. | UI-04 |
| U4 | The working box is removed. The privacy notices no longer mention working. The API still accepts an empty or missing `working` field. Capability matrix: "removed until a reader exists". | `UiRoundTest`, UI-04 |
| U5 | Submit, "I'm stuck" and Skip. "I'm stuck" offers hint, explain the idea and haven't learned it yet; in guided steps, hint and I don't know. Each choice sends the same action as before, and typed stuck entries still work. | UI-04, UI-05 (actions captured from the network) |
| U6 | The guided-work notice now appears only as the tag on the item. | `UiRoundTest`, UI-04 |
| U7 | Three dots, a line such as "2 of 3 in a row without help", and "Still needed: …". The dots empty when the run resets. A probe question does not count, so it shows a note instead of dots. | `UiRoundTest`, UI-04 (0/1/2), UI-05 (reset) |
| U8 | "Not yet." replaces "That is not correct.". Correct answers show a green block with ✓ and "Correct."; "Not yet." answers show a neutral block. Both meet WCAG AA in light and dark mode. | `UiRoundTest`, UI-04, UI-06 (measured contrast) |

Screenshots: `test-results/browser/ui-round/`, 10 views at 390 and at 1280 wide (20 files), from the PHP 8.0 gate.

## Decisions and conflicts

1. **Guided steps now say "Correct."** (was "Yes, that is right."), as U8 requires. B12 is the only existing test whose assertion changed: one literal.
2. **The public view contract is unchanged** (it is the package contract). The progress label carries its parts as lines. The dots use the existing `independent_successes` and `criterion_required` fields.
3. **The report record is unchanged.** The JSON download keeps its codes and skill IDs, because existing tests pin them. The page shows a new `summary` object with the two levels, and the download also contains it.
4. **"What was hard"** lists only skills where help was used, as defined in U2. Wrong answers without help are not listed there.
5. **`TEACHER_FACILITATOR_GUIDE_DRAFT.md`** (a handoff file) still tells facilitators to use the working field. I left it intact; please confirm whether to update it.
6. **The browser's answer line** for results other than the repair opening is lost on reload. The server line in the repair opening survives reloads.
