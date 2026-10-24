# CONDUCTOR — Round 9: bind architecture v1.7, apply the approved code changes, run targeted live tests

Read all of this before changing anything, and save it at the repository root. **This
file replaces `CONDUCTOR_ROUND_8.md` wherever the two differ.** §3 lists every round 8
behaviour that is reversed. The owner (Marc) decides. The architect (a separate Claude
thread) wrote these instructions and will audit your report against the repository.

## 0. Working method

1. **The AI is the tutor.** Code checks the math, keeps the gradebook, computes goals,
   writes the report, and handles plumbing and security. Code does not write tutoring
   text, choose replies, or make teaching decisions.
2. **You never edit the architecture file.** If you find a conflict, report it.
3. **Every text detector logs only.** No detector holds a check, changes a count or
   alters the tutor's text.
4. **No example responses** in any prompt or model-facing string.
5. **No cost work of any kind.**
6. **Model-facing text is exact.** Every sentence the model receives that this file
   specifies is used word for word. Any other model-facing string you add or change
   (context labels, observation strings, repair messages) goes into a list in the
   report, quoted.
7. **Stop and ask the owner** before deciding anything not specified here that affects
   what the student sees, what counts toward a goal, or what the tutor is told. Purely
   technical choices, such as names or file layout, are yours; list them in the report.
8. **Do not touch** `claude/elegant-volta-blu3uj`, the `beta-1.0` tag, or the round 6, 7
   and 8 branches. Deploy nothing.
9. **Stop at the end of §6**, push, and report (§7).

## 1. Branch

Create `round9-v1-7` from `round8-v1-6` at `efc65da` and push it.

## 2. Files

1. **`Algebra_Constraint_Architecture_v1.7.md`**, sha256
   `afe9a01e82122f8fcb5c9b1c7e12a493d17ab307b14161656accd4e947dc9188` (no trailing
   newline).
   - Save it byte-identical in `architecture/` and in
     `app/private/resources/architecture/`. If the hash differs, stop and report.
   - The app loads v1.7 only. Remove the v1.6 runtime copy; history stays in
     `architecture/`.
   - The activation line is
     `ACE_BOUND: architecture=1.7 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
     Update every version check. A session bound to 1.6 is rebound.
2. **`HOST_REQUIREMENTS_v1.7.md`.** Save it as `architecture/HOST_REQUIREMENTS_v1.7.md`.
   It is never sent to the model. The report lists each requirement as met (with
   location) or as a gap.
3. **`CHANGELOG_v1.7.md`.** Append it verbatim to `architecture/CHANGELOG.md`.
4. **`CLAUDE.md`.** Point it to v1.7 and to §0 of this file.

The test that keeps host requirements, changelog, `CLAUDE.md` and conductor text out of
model requests stays, updated for v1.7.

## 3. Round 8 behaviours reversed

1. **The "answer appears in the question" rule is removed.**
   - Remove `ANSWER_IN_QUESTION` from acceptance, and remove the prepared-check
     substitution after a failed repair (`preparedCheck`, `CHECK_SUBSTITUTED`).
   - A problem or envelope that still fails after its one repair holds the turn.
   - In code's own prepared and generated problems, prefer parameters whose answer is
     not among the problem's numbers. Try other parameters a few times; if none fit
     while keeping the same template and kind of question, accept the overlap.
     `Recipes` no longer returns nothing because of overlap.
2. **All text detectors log only.**
   - That covers the backstop during an open check (`TextGuard::duringCheck`), every
     before-card phrase, the before-card answer detector, help claims, mastery and
     goal claims, and internal vocabulary.
   - None holds a check or changes a count.
   - Keep the findings, labelled as signals.
3. **Only HELP and HINT count as help on an open check.** Change `HELP_MOVES` to
   `['HELP', 'HINT']`. EXPLAIN and ANSWER_QUESTION no longer record help.

## 4. Code changes

### 4.1 The output contract (`TutorPrompt::OUTPUT_CONTRACT`), with exact edits

1. **Delete** this sentence: "Identity: copy session_id and state_revision from
   TRUSTED_SESSION, and reply_to_event_id from TRUSTED_SESSION.event_id, exactly."
   - Keep "architecture_version is "1.7"." as its own line.
   - Remove the three identity fields from what the model must return. Code attaches
     them (§4.2).
2. **Replace** "HELP means you helped with the open check problem." with: "During an
   open check problem, HELP or HINT means your reply helps with that check; use one of
   them for every reply that helps with it, even when the reply answers the student's
   question. ANSWER_QUESTION and EXPLAIN never mean help with an open check problem."
3. **After** the sentence beginning "Any item is valid only when
   ALLOWED.may_pose_problem is true.", **add**: "During an open check problem, a reply
   whose to_mode is HELP or HINT may also carry an item with purpose practice."
4. **Replace** "SLIP_CHECK is valid only when SLIP.confirmed is true" with "SLIP_CHECK is
   valid only when SLIP.eligible_for_pause is true".
5. **After** the numeric_step definition, **add**: "equation_step: spec is {kind,
   expression, operation, operand}; expression is a linear equation in x; operation is
   add, subtract, multiply or divide; operand is a number, not zero for multiply or
   divide; stated_answer is the resulting equation; purpose is practice."
6. **Replace** "is_answer is true when the entry is an answer to OPEN_PROBLEM; text is
   your reading of that answer in plain notation, or null;" with: "is_answer is false
   when the entry contains no offered answer to OPEN_PROBLEM; an entry may offer an
   answer together with working, a question or a request for help, and then text is
   the offered answer in plain notation; otherwise text is null;".

### 4.2 Binding replies to their request

- When code sends a request, it captures the session, the state revision, the event
  and the open problem.
- Code attaches those identity fields to the reply itself.
- If the state revision changed while the call was in flight, the reply is discarded
  before anything is committed.
- A help move binds to the open problem captured with its request.
- Add a test for a stale reply.

### 4.3 Help on an open check

When a reply during an open check carries HELP or HINT, code does all of the following:

1. Records `HELP_GIVEN` on that check before the reply is shown.
2. Applies the goal policy as now. The ledger keeps the problem as a check answered
   with help, so the answer does not count and the run starts again.
3. Accepts a practice item in the same envelope (§4.1 item 3) and shows it after the
   reply.
4. From then on, presents the helped problem to the tutor as practice. `OPEN_PROBLEM`
   or a new `ORIGINAL_PROBLEM` block carries the helped problem, its spec and its help
   status until it is answered correctly or the tutor moves on.
5. Sets `ALLOWED.may_pose_problem` to true.

Help recorded on a check that is held (from a verdict conflict) also restarts the run.

### 4.4 Slip eligibility

Rename `SLIP.confirmed` to `SLIP.eligible_for_pause` everywhere.

1. **Expansions** (POSITIVE_DISTRIBUTION, DISTRIBUTION_TRANSFER) are eligible only when
   all of these hold:
   - every term except the wrong constant matches the answer;
   - the wrong constant is a times-table neighbour of the product, a × (b ± 1) or
     (a ± 1) × b, or differs from it in exactly one digit by exactly 1;
   - the wrong constant is none of a + b, b, a, or the product with the opposite sign.

   Test cases:
   - for 7(x + 5), 7x + 25 and 7x + 30 are eligible, while 7x + 95, 7x + 12 and
     7x − 35 are not;
   - for 2(x + 3), 2x + 5 is not eligible.
2. **Linear equations.** A bare value of x is never eligible. The arithmetic-error
   signatures for equations (ARITH_FINAL_PLUS, ARITH_FINAL_MINUS, ARITH_OFF_BY_ONE)
   stay as observations. The tutor may still pose a micro-question as an ordinary
   practice problem.
3. **Signed arithmetic magnitude slips** keep their current eligibility rule.
4. **Every string code sends the tutor about an error states what the answer did, never
   why.**
   - Replace "The expansion was set out correctly; one multiplication (a × b) went
     wrong." with "The other terms match; the constant {got} is a near miss of
     {a} × {b} = {product}."
   - Replace "The steps were right; the last subtraction was off by one." with "The
     answer {got} differs from the solution by {difference}."
   - Replace "The sign was right; one multiplication fact (a × b) went wrong." with
     "The sign matches; the size is a near miss of {a} × {b} = {product}." Make the
     same change to the division version.
   - List every resulting string in the report.

### 4.5 The equation_step problem type (practice only)

- Add it to the schema, the spec reader, the renderer (wording from the spec) and the
  verifier.
- Code computes the resulting equation and rejects a spec whose stated answer differs,
  or whose operand is zero for multiply or divide.
- **Verdicts:**
  - **STEP_CORRECT:** each side of the entry equals the corresponding expected side as
    a polynomial with exact rationals. The sides may be swapped, and unsimplified
    forms are accepted.
  - **EQUIVALENT_NOT_STEP:** the entry has the same solution set as the starting
    equation, but its sides do not match. This covers the unchanged equation, a
    skipped-ahead "x = 3", and a different operation. It is recorded as not yet
    correct, with that observation for the tutor.
  - **NOT_EQUIVALENT:** anything else.
- An entry that is not an equation is a request to rewrite it, never a wrong answer.
- **Test cases**, all from 3x + 6 = 15 with "subtract 6":

  | Entry | Verdict |
  |---|---|
  | 3x = 9 | STEP_CORRECT |
  | 9 = 3x | STEP_CORRECT |
  | 3x + 6 − 6 = 15 − 6 | STEP_CORRECT |
  | 3x + 6 = 15 | EQUIVALENT_NOT_STEP |
  | 6x + 12 = 30 | EQUIVALENT_NOT_STEP |
  | x = 3 | EQUIVALENT_NOT_STEP |
  | 3x = 21 | NOT_EQUIVALENT |
  | 9 | rewrite request |

- Distribute and collect-like-terms steps are not built this round.

### 4.6 Detectors and counters (logging only)

- **Fix tokenizing so the logs are accurate:**
  - an option letter counts only when it stands alone, never inside a formula such as
    a(b + c);
  - number words count only as standalone words, so "two-step" is not 2;
  - digits inside codes such as Q07 never count.
- **Add a detector for skill codes in learner text.**
- **`RESOLVE_AFTER_CORRECT`** leaves out substitution checks, such as "4·10 + 6 = 46
  checks out".
- **`VALUE_QUESTION_IN_TEXT`** also counts requests for an expression, such as "what is
  … written as", and leaves out questions that ask for an operation.

### 4.7 Minimum correction path

- **A private CLI tool, `tools/correct_attempt.php`,** run by the owner.
  - It appends a `CORRECTION` event: session, attempt, field (help or verdict), new
    value, reason.
  - It never edits or deletes earlier events.
- **`Gradebook::attempts()` applies the latest correction for each attempt,** then
  goals and the report are recomputed. The report shows each correction.
- **Tests:**
  1. A counted check corrected to "with help" stops counting, and its goal is
     recomputed.
  2. A corrected or suspended verdict updates the counts and the report that depend on
     it.
  3. The original record and the correction both stay traceable, and nothing is
     counted twice.

## 5. Targeted tests (offline)

Add integration tests for each case, then pass both gates (PHP 8.0.30 and 8.3):

1. **Working read as an answer:** "I added 9" with a problem open is not graded and
   leaves the problem open.
2. **An answer with a question:** "My answer is 10. Why does dividing by 5 work?" is
   graded as 10.
3. **Help and a smaller step together:** a HELP or HINT reply during a check carries a
   practice item in the same envelope. Help is recorded before the reply, and the next
   request presents the helped problem as practice.
4. **ANSWER_QUESTION used for help:** an ANSWER_QUESTION reply during a check records no
   help, and its text is logged.
5. **A stale reply** is discarded.
6. **Slip eligibility:** every case in §4.4.
7. **The equation_step type:** every case in §4.5.
8. **Removed rules:**
   - a check whose answer appears in it is accepted;
   - nothing is substituted;
   - no detector holds anything.
9. **The correction path:** all three tests in §4.7.

## 6. Live run: targeted sessions, arm A

The owner gives a temporary DeepSeek key; handle it as in round 8. Use base URL
`https://api.deepseek.com/v1` and `deepseek-flash` with thinking disabled.

Write scripted learners for the cases below, each responding to whatever the tutor
actually poses:

- **WORKING:** with a problem open, sometimes types working that includes a number,
  sometimes an answer followed by a question, sometimes a plain answer.
- **HELP-ON-CHECK:** asks for a hint on every check. It answers the smaller problems it
  is given correctly, then the original.
- **SLIPS:**
  - on expansions, one eligible slip, then an a + b error, then a correct answer;
  - on equations, once an answer off by one.
- **STEP:** when an equation_step problem is open, answers in turn with a correct step,
  the unchanged equation, a skipped-ahead answer, and a wrong step.
- **HELP-SEEKER and BREAKER** from round 8, once each.

Run each script once with a 30-turn cap, changing nothing between sessions.

Report per session:
- goals met;
- check answers: counted, with help, and held;
- help moves on open checks, and how many carried a practice item;
- readings, with `is_answer` true and false;
- equation_step verdicts;
- slip eligibility outcomes;
- detector signals by type and phrase;
- both counters;
- repairs and holds by cause;
- calls per turn and latency (median and slowest).

Commit the transcripts to `test-results/live/round9/A/`.

The broad run with mostly wrong answers comes in a later round, from the owner's list
of student errors.

## 7. Report

Write `ROUND_9_REPORT.md` with:

- the commits;
- what changed, by section of this file;
- the quoted list of model-facing strings (§0.6);
- your technical choices (§0.7), and any questions you stopped to ask;
- the host-requirements table;
- both gates;
- the live metrics, with transcript paths;
- each failure quoted from the transcripts, with no proposed fixes;
- the decisions needed.

Push, then stop.
