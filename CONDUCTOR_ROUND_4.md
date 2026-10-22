# Conductor Round 4 — Setting Choice, Transcript Defects, Messy Learner

Place in the repository root and read before acting. Where this file is silent,
earlier conductor files and the package govern. Record round 4 start and end in
`BUILD_LOG.md` as a separate interval.

## 1. Reasoning setting: thinking disabled

Set `provider_thinking = disabled` as the default in `config.example.php`, the
README and `DEPLOYMENT.md`. Keep `temperature` at 0.2 (it applies with thinking
off). Keep the setting configurable.

Basis (round 3, 8 sessions per setting): thinking off had the fewest repairs
(7 of 126), 0 HOLDs, 0 empty responses, and a median learner wait of 2.7 s
against 6.3 s (low) and 8.5 s (high). High produced 16 empty responses and a
slowest wait of 30.4 s. No setting truncated output.

## 2. Rulings on ROUND_3_REPORT points

| # | Ruling |
|---|---|
| 1 | Accepted, narrowed. The guard ignores a value only in these two cases: (a) the message names two or more answer options together as alternatives ("positive or negative"); (b) the value is the learner's own submitted answer to an already-completed step. Everything else is still checked. Tests: both exemptions pass; the round 3 real leak (05-B1 turn 6, "the fives across all the groups add up to 25") is still rejected. |
| 2 | Accepted. The invalid-JSON repair message includes the parser's error message and character position. |
| 3 | Noted. No action beyond the runner change already made. |
| 4 | Accepted. On a network failure, log the curl error number, the stage reached (connect, TLS, response) and elapsed time. No other change. |

## 3. Defects found in the transcripts

**R4-A: Framing contradicts the step when a failed step is re-asked.** After the
learner solves the smaller step, the host shows "Now try the earlier question
again" and re-asks the step they failed. The model's added sentence often
describes a different step. Example, thinking-disabled 01-B1 turn 6: "Now use
that same grouping idea on the whole expression", while the item shown asks
only for the total of the 8s. This happened in 3 of 4 re-asks with thinking off.

Fix: for a re-ask of a previously failed guided step, show the host line and the
item only. Do not request or display model framing for that transition.
Test B12: a re-ask shows no model-written text.

**R4-B: Repair does not practise the pattern the learner failed.** In 8 of 9 B3
sessions, across all three settings, the guided N06 items used a different sign
pattern from the item the learner got wrong. Example, thinking-disabled 06-B3:
the learner failed 72 ÷ (−9) (positive ÷ negative); every guided item was
negative ÷ negative.

Fix in the host, not the prompt:
- The first guided step sequence, and the first fresh guided item after it,
  keep the structural features of the failed item:
  - N03–N06: the sign of each operand.
  - E06: the sign of the inside constant.
  - Q04–Q07: the equation form (for example `a(x+b)=c` against `c=a(x+b)`)
    and the sign of each constant.
- Later guided and independent items may vary those features.
- Reject a model parameter proposal that changes a feature that must be kept,
  and use the bounded repair path.

Test B13: for each of the four N06 sign patterns, a failed item produces guided
items with the same pattern.

## 4. Messy-learner session (B11)

Add a scripted live session whose learner inputs are realistic rather than
clean. Include at least:
- `x = 3` and `x=3`
- `3 ` with spaces
- `3.0`
- `6/2`
- a Unicode minus (`−3`)
- `three`
- `idk`
- an empty submit
- `can you just tell me the answer`
- `is this on the test?`
- a correct answer with a typo then a correction

Record, for each input: the checker's verdict and its scope, what the tutor
showed, and any host rejection. Do not change the parser or the prompts during
the run; propose changes in the report.

## 5. Live confirmation

After §1–§4 pass offline, run with thinking disabled:
- the 8 round 3 sessions (5 B1, 3 B3) with the same scripted inputs;
- B11 twice.

Report the round 3 metrics table for this run beside the round 3
thinking-disabled column, and flag any metric that got worse. Save transcripts
to `test-results/live/round4/`.

## 6. Keys

After §5, stop using all temporary keys and tell the owner they can be revoked.
The production key is placed on Bluehost by the owner and is never given to
Claude Code. `DEPLOYMENT.md` must state the exact file and path where the owner
puts it, outside `public_html`.

## 7. Gate and report

Round 4 is done when:
- every test passes, including B11–B13 and the narrowed guard tests;
- the live confirmation has run or stopped with a recorded reason;
- nothing has been deployed.

Report in the usual order. Then add:
- the comparison table;
- the B11 input-by-input table;
- every host rejection with its text and reason.
