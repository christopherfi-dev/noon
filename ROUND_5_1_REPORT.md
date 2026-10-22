# Algebra Engine — round 5.1 report

Repository `christopherfi-dev/noon`, branch `claude/elegant-volta-blu3uj`. Round 5.1 ran from 2026-10-22T10:56:51Z
(`BUILD_LOG.md`, "Round 5.1"), following `CONDUCTOR_ROUND_5_1.md`. It was offline only: no live run, no key used, nothing deployed.

## 1. Tests

**Gate, 11:10:21Z–11:11:28Z** (mock config, no key):

| Check | Result |
|---|---|
| Manifest, handoff preflight, curriculum validator and guards, app preflight | PASS |
| PHPUnit | **198 passed, 0 failed** (106 unit, 92 integration); 2 more runs straight after, clean |
| Browser (B1 ×2 widths, UI-01, UI-02, UI-03) | **5 passed, 0 failed** |
| Bundle build and deployment-layout smoke test | PASS |
| Key-leak scan | PASS, 0 hits |

New or changed tests:

| Test | Covers |
|---|---|
| B14b, updated | A hint on the third answer: no criterion, streak 0; the criterion is then met on answers 4, 5 and 6 |
| EVID-01, updated | An answer after an explanation resets the streak to 0 |
| P1 | `StuckEntryTest::testAskForAnswerLineNamesWhatFollows`: hint, smaller step, another way, prerequisite check, and the boundary case |
| P3 | `EngineUnitsTest::testSupportClaimsInModelTextOnly` (the four live round 5 lines; host line exempt); `BetaGuardsTest::testSupportClaimInModelTextIsRepairedNotShown` |
| B16 | `ProbeAfterHelpTest`, 4 tests: correct with no help resumes; wrong descends; answered after a hint or a stuck entry descends; at most two probes, skips included |
| Summary wording | `SummaryWordingTest`: every prose string in two summaries (a completed B1; a B3 path with hints, a hold and a topic change). The browser B1 test also checks the rendered summary page. Both fail if the plain-words step is removed |

**Failures during the round:** none outside the tests that pinned the old behaviour, all updated as listed:
- B14b and EVID-01: "assisted correct neither advances nor resets".
- The B15 flow row for the probe.
- Two EvidenceTest wording checks: "in-session classification only" and "Fresh independent Q07 work".

## 2. What changed

| Item | Change |
|---|---|
| Ruling 4: summary | The summary's own text uses plain words: "with help", "the answer the app computed", "goal met: 3 correct in a row without help". Text copied from stored records (checker scopes, event reasons, issue details, observations) passes through `ReportBuilder::plain()`. On the page: the goal column and the goal headings use plain words. Field names and stored codes such as `IMMEDIATE_CRITERION_MET` are unchanged |
| P1 | `stuck()` now reports what follows it. The "won't give the answer" line names it: hint (independent item), smaller step, another way, or a check of something that comes before this. The line is placed before what follows |
| P2 | `CriteriaCalculator`: an answer given after a hint, an explanation or a stuck entry resets the run, as a wrong answer does. The capability matrix has a new section, "Owner-rule overrides of the package", recording it |
| P3 | Model text may not contain on your own, without help, independently, by yourself or no help; any such text goes to the bounded repair path. Host lines are exempt. Adapter rule 5 now names the five phrases |
| P4 | A probe answered after help records "Answered after a hint; inconclusive." and one more fresh probe follows. Correct with no help: "The earlier error was not reproduced on a fresh probe", resume as before. Wrong, or answered after help: the breakdown is confirmed and descent follows the original error's branch. Assisted work changes no hypothesis. At most two probes per diagnosis |
| §3 | Post-beta list extended (below) |

## 3. Conflicts with the instructions, and decisions

1. **P1 has a fifth case.** In guided repair, being stuck at the smallest question can end at a boundary instead: the skill is outside the beta or not a declared prerequisite. What follows is a fresh guided problem, or an independent check once the problem budget is used up. None of the four lines is true there. I show "I won't give the answer." alone and add no new claim. **Please confirm or give a line.**
2. **"No more than two probes per diagnosis" also limits skips.** The probe limit was 3 for skipped probes. It is now 2: one skip gets one replacement probe, and a second skip starts guided repair.
3. **What counts as "after a hint".** In P2 and P4 an explanation request and a stuck entry count the same as a hint, because all three record support before the answer.
   - A partially correct answer after support also resets the run.
   - An unreadable entry after support does not reset it: it is not an answer, and the next readable answer resets it.
4. **Summary scope.** I applied ruling 4 to every prose string in the summary payload, including the downloadable JSON's scopes and notes, not only what the page shows. Checker results and the event ledger keep their wording, because they are stored data.

## 4. Post-beta (not proposed now)

- When the generator picks numbers, avoid an answer equal to a number shown in the problem, such as x = 3 in 3(x + 4) = 21. This removes the guard's number-coincidence false positives at the source.
- An explanation drew on the previous item's numbers (round 5, 02-B11 t25).
- The model repeats the host's "I won't give the answer".
- High hint repair rate ("?" in hints and the guard's number-coincidence limit).
- `X =` on expression items.
- Wider stuck phrase lists.
- Freshness repairs when the model re-proposes the opening target numbers.
