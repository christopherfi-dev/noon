# CONDUCTOR — Round 8: bind architecture v1.6, align the code, rerun arm A

Read all of this before changing anything, and save it at the repository root. The owner
(Marc) decides. The architect (a separate Claude thread) wrote these instructions and
will audit your report against the repository.

## 0. Working method

1. **The AI is the tutor.** The constraint architecture governs it. Code checks the
   math, keeps the gradebook, computes goals, writes the report, and handles plumbing
   and security. Code does not write tutoring text, choose replies, or make teaching
   decisions.
2. **You never edit the architecture file.** If you find a conflict between it, the
   code or these instructions, report it. Do not fix it in the file or work around it
   in code.
3. **Behaviour guards log; they do not block.** Problems and envelopes that fail
   mathematical or structural acceptance are repaired once, then held or replaced as
   §3 says.
4. **No example responses** in any prompt or model-facing string.
5. **No cost work of any kind.** No budgets, no estimates, no projections and no
   balance checks. Do not mention cost in the report.
6. **Report failures; don't fix the architecture.** For every failure you see in the
   live run, quote the exact transcript lines. Do not propose architecture wording; the
   architect traces each failure to its line.
7. **Do not touch** `claude/elegant-volta-blu3uj` (the deployed beta), the `beta-1.0`
   tag, `round6-ai-autonomy`, `round6-ai-tutor` or `round7-v1-5`. Deploy nothing.
8. **Stop at the end of §5**, push, and report (§6).

## 1. Branch

Create `round8-v1-6` from `round7-v1-5` at `f5bc6b0` and push it.

## 2. The files the owner attaches

1. **`Algebra_Constraint_Architecture_v1.6.md`**, sha256
   `c5591d3dfa6aa87bf79b026936abce724e71ec9dd3e7316c7c7991553d390b7b` (no trailing
   newline).
   - Save it byte-identical in `architecture/` and in
     `app/private/resources/architecture/`. If the hash differs, stop and report the
     difference.
   - The app loads v1.6 only. Remove the v1.5 runtime copy from
     `app/private/resources/architecture/`; v1.1–v1.5 stay in `architecture/` as
     history.
   - The activation line is now exactly
     `ACE_BOUND: architecture=1.6 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
     Update every version check, `architecture_version` and the schemas. A stored
     session bound to 1.5 is rebound.
2. **`HOST_REQUIREMENTS_v1.6.md`.** Save it as `architecture/HOST_REQUIREMENTS_v1.6.md`.
   - It holds requirements on code, moved out of the architecture word for word. It is
     never sent to the model.
   - In the report, list each requirement with where the code meets it, or state that
     it is a gap. Do not build a review or correction tool this round; report that
     gap.
3. **`CHANGELOG_v1.6.md`.** Append it verbatim to `architecture/CHANGELOG.md`.

**What the model receives.** It receives only the architecture file, the output
contract, the activation request and handshake, and the JSON context block. Add a test
that fails if any text from `HOST_REQUIREMENTS_v1.6.md`, the changelog, `CLAUDE.md` or a
conductor file appears in a model request.

Rewrite `CLAUDE.md`: v1.6 governs, §0 of this file is the working method, and no cost
work is ever done.

## 3. Code changes

1. **Help move.**
   - Add `HELP` to `proposed_transition.to_mode`, and define it in the output contract
     as a field value: the tutor helped with the open check problem.
   - While a check is open, `HINT`, `EXPLAIN` and `ANSWER_QUESTION` count as help too.
   - When a reply during an open check carries one of these, code records `HELP_GIVEN`
     for that check before the reply is shown. The check becomes practice, and the goal
     policy applies.
   - "I'm stuck" works as before.
2. **Backstop.** During an open check, if the tutor's text contains the check's
   expression, or a number from it, and carries no help move, hold the check
   (`HELP_DURING_CHECK`). Help counts whether or not the student asked for it. Text
   without the check's expression or numbers, such as a refusal, records nothing.
3. **Hold triggers before a check card.**
   - Remove "next", "try" and other neutral invitations from the hold list, and keep
     logging them.
   - Hold only on the check's answer (subject to item 5) or a hint, method, strategy or
     reminder phrase.
   - Report hits phrase by phrase, so false triggers such as "when you" can be seen.
4. **Help-claim guard.** Do not flag "on your own", "by yourself", "independently",
   "without help" or "no help" in the last sentence of text that is followed directly
   by a check card. Flag them everywhere else, as now.
5. **Answer before a check card.** A number in the text before a card that equals the
   check's answer holds the check only if it comes from neither the previous problem
   (its numbers and answer) nor the student's entry. Otherwise log it as a signal.
6. **Answer that appears in the question.**
   - This acceptance rule applies to check problems only. Practice problems are
     accepted whatever their numbers.
   - If the tutor's check problem still fails the rule after one repair, substitute a
     prepared check problem for the same skill instead of holding the turn, and log
     the substitution.
   - Prepared check problems must satisfy the rule; add a test.
7. **Counters (logging only, never blocking):**
   - `RESOLVE_AFTER_CORRECT`: correct-answer feedback that contains an intermediate step
     the tutor supplied. That means an equation or value on the way from the problem to
     its answer, other than the problem, the student's answer and the student's own
     working.
   - `VALUE_QUESTION_IN_TEXT`: a question in `student_message` that asks for a number,
     expression or equation while `item` is null.
8. **Scripted learner.** When the tutor's text asks for a value and no problem is posed,
   the learner types an answer. Code treats it as a message and grades nothing, and the
   runner counts it as an ungraded answer.

## 4. Tests

Update the integration tests so that:

- a help request on an open check gets a reply that is shown; `HELP_GIVEN` is recorded
  before the reply; and the check becomes practice;
- a reply during an open check with `HINT`, `EXPLAIN` or `ANSWER_QUESTION` behaves the
  same way;
- a reply during an open check that uses the check's numbers with no help move holds the
  check, while a refusal with no numbers records nothing;
- "Here is one more to try." before a card holds nothing, and "on your own" in that
  sentence raises no help-claim finding;
- a number before a card that equals the check's answer and was the previous answer is
  logged only, while the same number from nowhere holds the check;
- a practice problem whose answer appears in the question is accepted; a check problem
  whose answer appears in it is repaired once, then replaced by a prepared check problem,
  with no hold;
- both counters fire on test cases built to trigger them;
- no host-requirements or changelog text reaches a model request.

Both gates (PHP 8.0.30 and 8.3) must pass before §5.

## 5. Live run: arm A only

The owner gives you a temporary DeepSeek key in this session. Keep it outside the
repository, never print it, run the key-leak scan before every commit, and delete it at
the end.

Use base URL `https://api.deepseek.com/v1`, model `deepseek-flash`, with thinking
disabled on every call. Run the same seven scripts as round 7: B1 twice, B11 once, N1
twice, HELP-SEEKER and BREAKER. Change nothing between sessions.

Report per session and in total:

- goals met;
- check answers counted unassisted, with help recorded, and held, each with its cause;
- help moves on open checks, and backstop holds;
- guard findings by type, and by phrase for the phrase guards;
- both counters;
- repairs and holds by cause, and prepared-problem substitutions;
- readings, and ungraded answers to value questions;
- calls per turn, latency (median and slowest), empty replies, and binding.

Commit every transcript to `test-results/live/round8/A/`.

## 6. Report

Write `ROUND_8_REPORT.md` with:

- the commits;
- what changed, by section of this file;
- the host-requirements table (met, with location, or gap);
- test results for both gates;
- the live metrics, with transcript paths;
- each failure you saw, quoted from the transcripts, with no proposed fixes;
- conflicts with these instructions, and the choices you made;
- the decisions needed.

Push, then stop.
