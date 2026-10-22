# Conductor Round 5 (v3) — Clean Independent Evidence, Stuck Learners

**This file replaces `CONDUCTOR_ROUND_5.md` and `CONDUCTOR_ROUND_5_v2.md`.** If
you started from an earlier version, follow this one instead. Where they
differ, this file governs.

Place in the repository root and read before acting. Where this file is silent,
earlier conductor files and the package govern. Record round 5 as a separate
interval in `BUILD_LOG.md`.

**Governing line for this round.** Code decides anything that changes what the
record says about the learner, and anything where code has better information
than the model. Within code-checked limits, the model decides how to teach.

## 1. Rulings on ROUND_4_REPORT §5

| # | Ruling |
|---|---|
| 1 | Accepted. N03–N06 have three sign patterns. The architect's "four" was wrong. |
| 2 | Option (i), with a stricter rule than the report's: any of the option words positive, negative, plus or minus (including plurals) is still checked by default. It is exempt only when it is immediately followed by one of these operand nouns: sign, signs, divisor, dividend, number, numbers, factor, term, constant, coefficient. Guided sign steps stay model-written. Tests: all 8 messages rejected in round 4 (06-B3, turns 7–9) pass. These are rejected: "the quotient is negative", "the answer will be negative", "it comes out negative", "two negatives make a positive". |
| 3–5 | Accepted as implemented. |
| 6 | See §4. |
| 7 | Noted. |

## 2. R5-A: no help on independent items (highest priority)

**Finding.** In round 4, model-written strategy cues came immediately before
INDEPENDENT items at least 28 times, and at least 15 times in round 3 with
thinking disabled. These counts come from a phrase search, so they are lower
bounds. An example is "Work out the sizes first, then settle the sign." shown
before *Calculate −54 ÷ 9*. Those answers count as independent evidence, and the
learner is then told they answered "without help".

**Fix.** For every presentation of an INDEPENDENT item (target, diagnostic probe,
prerequisite check, independent check, return to target):
- The provider call still runs where the model proposes the item's parameters,
  and those parameters go through the existing gate unchanged.
- The model's learner text from that call is discarded: never shown, never
  stored as learner-visible.
- The learner sees a host line that states only what is asked (for example
  "Here is one to try on your own.") and the item. Host lines never state a
  method.

**Tests:**
- **B14:** no INDEPENDENT item in any journey test, mock or live, is shown in
  the same turn as model-written text.
- **B14b:** a criterion message containing "without help" or "on your own" is
  produced only when every item counted toward that criterion was INDEPENDENT,
  had no hint, and passed B14. Construct one session where a hint occurs and
  assert the message is not produced.

## 3. R5-B: no internal vocabulary in learner text

**Finding.** Six learner-visible messages in rounds 3 and 4 contained internal
terms, for example "That quotient matched the expected value, and it was a
guided step."

**Fix.** Reject learner text containing any of these, case-insensitive, matched
as whole words or whole phrases (word boundaries, so "ghost" never matches
"host"):
- expected value
- expected answer
- answer key
- matched the key
- guided step
- host
- assisted
- verdict
- criterion

A match goes to the bounded repair path. Host-written lines must pass the same
check. Tests cover each term and the non-matches "ghost", "the key step" and
"key idea".

## 4. R5-C: stuck and off-task entries

**Normalise before matching:** lowercase, trim, collapse spaces, remove
apostrophes, strip trailing punctuation (`.`, `!`, `?`).

**Readable answer with a trailing "?"** (for example `-2?` or `x = 3?`):
strip the "?" and score it normally. This check runs before the table.

| Entry after normalising | In GUIDED_REPAIR | On an INDEPENDENT item |
|---|---|---|
| idk, i dont know, dont know, dunno, no idea, no clue, not sure, im stuck, stuck, help, or an entry that is only "?" | Smaller question (owner decision D1) | Existing hint path; the item becomes assisted |
| can you just tell me the answer, just tell me the answer, tell me the answer, whats the answer, give me the answer | Host line "I won't give the answer, but here is a smaller step." then a smaller question | Same host line, then the existing hint path; the item becomes assisted |
| any other words that ended in "?" before normalising | Host line "This box is for your answer. If you're stuck, type help." | Same |
| number words zero to twenty, optionally preceded by "negative" or "minus" | Host line "Write the number with digits." | Same |
| `X=3` or `X = 3` on a one-variable item in x | Accept as x | Same |

No entry in this table is ever scored as incorrect. Other unreadable entries
keep their current clarification messages. Use the lists exactly as written.

**Flow after a stuck entry on an independent item.** Use only transitions that
already exist for a hint request in that mode (target, diagnostic probe,
prerequisite check, independent check, return to target). If any of these modes
has no defined follow-on transition after a hint, stop and report which ones.
Do not design one.

**Test B15:** every row in both columns, the trailing-"?" rule, and the
normalisation (for example `IDK.`, `I don't know`, `i dont know`).

## 5. Live confirmation

The owner will supply one new temporary DeepSeek key for this run only. Use
thinking disabled and `deepseek-flash`. Run:
- the 8 standard sessions (5 B1, 3 B3);
- B11 twice, with the round 4 inputs plus `idk`, `-2?`, `IDK.` and `i dont know`.

Report beside round 4:
- HOLDs;
- repairs;
- guard and other rejections, with guard rejections on sign steps listed
  separately;
- independent items shown with model text (must be 0);
- internal-vocabulary hits in learner text (must be 0);
- provider calls per session;
- learner wait, median and slowest.

When finished, delete every copy of the key and tell the owner to revoke it.

## 6. Gate, report, and stopping point

Round 5 is done when:
- every test passes, including B14, B14b and B15;
- the live run has completed or stopped with a recorded reason;
- nothing has been deployed.

Report in the usual order, plus:
- a list of every learner-facing message the model can still write, by
  operation, with one live example of each;
- for each independent mode, what happens after a stuck entry (§4 flow).

After this round, propose further changes only for a truth defect (evidence or
messages that misstate what happened) or a safety defect (answer leaks, key
exposure, data exposure). List everything else as post-beta. Whether there are
further rounds is the architect's decision.

