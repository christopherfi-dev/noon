# Conductor Round 5.1 — Truth Fixes from ROUND_5_REPORT

Place in the repository root and read before acting. Where this file is silent,
earlier conductor files govern. Record round 5.1 as a separate interval in
`BUILD_LOG.md`.

**Offline only.** There is no live run in this round, and no key is needed or
used. Every change is in host code or host text.

**Governing line (unchanged):** code decides anything that changes what the
record says about the learner, and anything where code has better information.
Within code-checked limits, the model decides how to teach.

## 1. Rulings on §5 "Conflicts and decisions"

| # | Ruling |
|---|---|
| 1 | Noted. |
| 2 | Accepted. Plurals match. |
| 3 | Accepted. Discarded text is not checked. |
| 4 | Changed. The session summary is learner-visible, so its wording follows R5-B. Use plain words in learner-visible text: "with help" (not "assisted"), "the answer the app computed" (not "host-derived key"), and "goal met: 3 correct in a row without help" (not "criterion met"). Report contract field names and stored data are unchanged. |
| 5–9 | Accepted. |

## 2. Rulings on the four proposals

**P1. Accepted, with exact wording.** Replace the single host line with one of
these, depending on what follows:
- On an independent item, when a hint follows: "I won't give the answer, but
  here is a hint."
- In guided repair, when a smaller question follows: "I won't give the answer,
  but here is a smaller step."
- In guided repair, when the same question is asked another way: "I won't give
  the answer, but let's look at it another way."
- In guided repair, when a prerequisite check follows: "I won't give the answer,
  but let's check something that comes before this."

Test: each case shows its own line.

**P2. Fix the rule, not the message.** The owner's mastery rule is three correct
in a row without help. An answer given after a hint breaks the run: the streak
resets to 0, exactly as a wrong answer does. The criterion message stays as it
is, and it is then true.

Update B14b. A hint on the third answer means the criterion is met on answers 4,
5 and 6, not on 1, 2 and 4. Record the change in the capability matrix as an
owner-rule override of the package's "assisted neither advances nor resets"
behaviour.

**P3. Accepted, for model text only.** Add these to the model-text rejection
list: "on your own", "without help", "independently", "by yourself", "no help".
Host lines are exempt from these five, because "Here is one to try on your own."
is correct there. Test with the four live lines from round 5.

**P4. Fix the routing as well as the message.** An answer to a diagnostic probe
given after a hint is inconclusive. It cannot show that the earlier error was a
slip.
- Record it as "Answered after a hint; inconclusive."
- Present one more fresh probe on its own. This is the owner's slip check of one
  or two extra problems.
- If the second probe is correct with no help, record "The earlier error was not
  reproduced on a fresh probe" and resume as now.
- If the second probe is wrong, or answered after a hint or a stuck entry, the
  breakdown is confirmed. Descend along the branch implicated by the original
  error, or reach a boundary, as now.
- No more than two probes per diagnosis.

Test B16 covers all three outcomes.

## 3. Post-beta list

The six post-beta items in the report stay post-beta. Add one more: when the
generator picks numbers, avoid making the answer equal to a number shown in the
problem (for example x = 3 in 3(x + 4) = 21). This removes the guard's
number-coincidence false positives at the source.

## 4. Gate and report

Round 5.1 is done when every test passes, including the updated B14b, the P1 and
P3 tests, and B16, and the browser and bundle checks pass. Nothing is deployed.

Report in the usual order. Keep it short: what changed, the test counts, and
anything that conflicted with this file.
