# CONDUCTOR — Round 10: bind architecture v1.8, repair the route back to the target, run targeted and broad live tests

Read all of this before changing anything, and save it at the repository root. **This
file replaces `CONDUCTOR_ROUND_9.md` wherever the two differ.** §3 lists every round 9
behaviour that is reversed. The owner (Marc) decides. The architect wrote these
instructions and will audit your report against the repository.

## 0. Working method

The round 9 working method continues. In short:

1. **The AI is the tutor.** Code checks the math, keeps the record, computes goals and
   writes the report.
2. **You never edit the architecture.** Report conflicts instead.
3. **Text detectors log only.**
4. **No example responses** in any model-facing string.
5. **No cost work.**
6. **Model-facing text is exact.** Every model-facing sentence this file specifies is
   used word for word. Any other model-facing string you add or change goes into a
   quoted list in the report.
7. **Stop and ask the owner** before deciding anything unspecified that affects what the
   student sees, what counts toward a goal, or what the tutor is told. Technical choices
   are yours; list them.
8. **Do not touch** earlier branches or the `beta-1.0` tag. Deploy nothing.
9. **Stop at the end of §7**, push, and report (§8).

## 1. Branch

Create `round10-v1-8` from `round9-v1-7` at `fcb2a00` and push it.

## 2. Files

1. **`Algebra_Constraint_Architecture_v1.8.md`**, sha256
   `f2db75f4f4e4512f9299f987efb4fb3315ce73d051d83d30990592a055a79d98` (no trailing
   newline).
   - Save it byte-identical in `architecture/` and in
     `app/private/resources/architecture/`. If the hash differs, stop.
   - The app loads v1.8 only.
   - The activation line is
     `ACE_BOUND: architecture=1.8 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
     Update every version check. A session bound to 1.7 is rebound.
2. **`CHANGELOG_v1.8.md`.** Append it verbatim to `architecture/CHANGELOG.md`.
3. **`HOST_REQUIREMENTS_v1.7.md`** stays in force, unchanged.
4. **`CLAUDE.md`.** Point it to v1.8 and to §0 of this file.

## 3. Round 9 behaviours reversed (owner's decisions)

1. **A waiting original no longer blocks checks.** The rule that kept `may_pose_check`
   false while `ORIGINAL_PROBLEM` was set is removed.
2. **A problem on another skill no longer drops the original.** Remove the
   `dropOriginal()` call in `present()` for a change of skill.
3. **The run restarts when help is recorded, not when the helped check is answered.**
   This reverses the owner's round 9 decision 1. See §4.4.
4. **The model no longer writes `message_type`.** See §4.1.
5. **The result lines change.** See §4.7.
6. **The repair instruction changes.** See §4.1.

## 4. Code changes

### 4.1 The output contract (`TutorPrompt`), with exact edits

1. Replace `architecture_version is "1.7".` with `architecture_version is "1.8".`
2. Delete this line: "- message_type: QUESTION when item is not null; FEEDBACK when item
   is null; CONTROL when TURN.kind is READING. REPORT and HOLD are not used."
   - Remove `message_type` from what the model must return.
   - Code sets it on every accepted reply by the same rule: QUESTION when an item is
     attached, FEEDBACK when not, CONTROL for a READING turn.
3. In `TutorPrompt::repair()`, replace "Fix exactly these errors and change nothing
   else." with "Fix exactly these errors, update any wording they affect, and change
   nothing else."

Every other sentence of the contract is unchanged. The new context fields (§4.2 to
§4.6) carry descriptive names and no instructions. List them in the report.

### 4.2 Which open problem blocks a check (C1)

`ALLOWED.may_pose_check` is false only while an **unanswered check with no help
recorded** is open. Neither of these blocks a check:

- a problem that has become practice with help;
- an original that is waiting.

When the tutor poses a new problem while a helped problem is still open and unanswered,
that helped problem becomes the waiting original, as it does today when a smaller step
is posed.

### 4.3 Three separate records: the helped problem, the target, the route (C2, C3)

**`ORIGINAL_PROBLEM`** keeps its current meaning: one specific helped problem that the
tutor can bring back with item source `original`. It closes when any of these happens:

- it is answered;
- it is completed through connected steps (below);
- a fresh check problem on the target skill, distinct from the original, is accepted
  (not merely proposed), in which case it is set aside unfinished and ROUTE records
  `RETURN_FRESH` and `ORIGINAL_SET_ASIDE`;
- the session ends.

A practice problem of any kind, on any skill, never sets the original aside, and moving
to another skill does not close it. Setting the original aside keeps its history and the
target. It does not mark the original completed, meet a goal, or reset the run again.

**New context block `TARGET`:** `{skill_id, skill_name, goal_met, set_aside_original}`.
- The skill the session or topic is aiming at.
- `goal_met` comes from GOALS.
- `set_aside_original` is the problem id of a helped original that was set aside, or
  null.

**New context block `ROUTE`:** the session's route, oldest first, each entry
`{kind, skill_id, problem_id, at}`. The kinds are:

| Kind | Meaning |
|---|---|
| `DETOUR` | moved to a prerequisite skill |
| `RETURN_ORIGINAL` | brought the original back |
| `RETURN_TO_SKILL` | the first problem on the target skill after a `DETOUR` |
| `RETURN_FRESH` | a fresh check problem on the target skill, distinct from the original, was accepted |
| `ORIGINAL_SET_ASIDE` | the original was set aside unfinished |
| `ORIGINAL_COMPLETED_WITH_HELP` | the original was completed through connected steps |

Closing a record keeps its history.

**Completion through connected steps:**
- Code records `ORIGINAL_COMPLETED_WITH_HELP` only when the student's correct
  equation_step answers form a chain that starts from the original's equation, each step
  starting from the previous step's result, and the chain ends at the original's
  solution.
- It earns no goal credit.
- A final answer that only happens to match, or any other arithmetic, never completes
  the original.

### 4.4 When help restarts the run (C4)

`Goals::compute()` takes the `HELP_GIVEN` events into account, not only the recorded
attempts:

- Help recorded on an unanswered check restarts that skill's run at the moment the help
  is recorded.
- That check's later answer counts neither for nor against the goal, so there is no
  second reset.
- Help recorded on a check that is held for a verdict conflict also restarts the run.
- The arithmetic-error pause and the required kinds of question are unchanged.

### 4.5 Corrections update the next request (C5)

`applyCorrection()` also updates the stored record of the last answered problem
(`eng['answered']`) and anything derived from it, such as slip state, when the
corrected attempt is that problem. The next request's `ANSWERED_PROBLEM`, `GRADEBOOK`
and `GOALS` all show the corrected values.

### 4.6 What the tutor is told about goals and totals (C6, C7)

1. **New `CODE_RESULT.goal_newly_met`:** null, or `{skill_id, skill_name}`.
   - Code computes it **before the model call**, from the attempts including this
     turn's answer, compared with the goal status before the answer.
   - The `GOAL_MET` event is still written at commit.
2. **In `GRADEBOOK`:**
   - rename `by_skill` to `session_totals_by_skill`;
   - replace `check_correct` with two counts, `check_correct_no_recorded_help` and
     `check_correct_with_help`;
   - other fields are unchanged.

   GOALS stays the authority on goal status.

### 4.7 Result lines the student sees (C11; owner-approved wording)

`TutorEngine::answer()` builds three lines:

- **An ordinary answer:** "{Correct.|Not yet.} / On {problem} you answered {entry}."
  This is unchanged.
- **An answer read from a message:** "{Correct.|Not yet.} / On {problem}, read from your
  message: {reading}." It replaces "you answered {raw}. / Read as {reading}." The
  student's message stays in the conversation and is not repeated in the line.
- **An equation_step answer:** "{Correct.|Not yet.} / On the step “{step instruction}”,
  you wrote {entry}."
  - {step instruction} is the rendered wording without "Write the equation you get."
  - When it was read from a message, it becomes "…”, read from your message:
    {reading}."

### 4.8 Small items (C12; owner-approved)

1. Don't read "one" in "here is one to try" (or "one more", "one of") as the number 1.
   This affects logging only.
2. Widen the goal-claim detector, still logging only, so it catches "the goal for … is
   met" and "the … goal is met".
3. Replace the observation "matches sign-error-on-b" with "matches moving the constant
   to the other side without changing its sign".
4. A factor-of-1 product is never slip-eligible. This is unchanged.
5. Corrections during an open session are unchanged.

### 4.9 Saving requests and responses (C13)

In every live run, save the full request messages and the raw response for every model
call, including repair calls and reading calls, next to the transcripts
(`test-results/live/round10/<arm>/<session>/calls/`). Never save the key.

### 4.10 The report: three different things (C14)

Teacher view and report data:

1. **Separate:**
   - the original problem completed with help;
   - a return to the target skill after a detour, through practice, the original, or a
     fresh check (`RETURN_TO_SKILL`, `RETURN_ORIGINAL`, `RETURN_FRESH`);
   - fresh evidence on the target without help, meaning checks counted after the
     return.
2. **For each detour, record:**
   - how many problems it lasted;
   - what the student showed (correct and not-yet counts on the detour skill);
   - whether work returned to the target;
   - the student's results on the target afterwards.

   These are evidence, not a score.

Host requirement gaps 3d and 9c are re-reported against this.

## 5. Targeted tests (offline)

Integration tests for each case below, then both gates (PHP 8.0.30 and 8.3):

1. **The reset loophole:** two correct checks, then help, then the original set aside,
   then a later correct check. The earlier two don't carry over.
2. **No double reset.** This is a calculation test of `Goals::compute()` on a constructed
   event record:
   - help is recorded on check A;
   - two fresh checks are answered correctly;
   - then an answer on A is recorded with help.

   The two fresh checks still count, A's answer adds no credit, and there is no second
   reset. The live RESET-LOOPHOLE session in §6 stays, as the application-level check.
3. **A step back to a prerequisite** keeps `TARGET` and the waiting `ORIGINAL_PROBLEM`,
   and records a `DETOUR`.
4. **A fresh check on the target** becomes available while an old helped problem is
   still recorded.
5. **Setting the original aside:**
   - an accepted fresh check on the target skill, distinct from the original, sets the
     original aside, records `RETURN_FRESH` and `ORIGINAL_SET_ASIDE`, and does not mark
     it completed or reset the run again;
   - a check proposal that code rejects changes nothing;
   - an easier practice problem on the same skill, such as Expand 2(x + 3) after Expand
     7(5x + 8), does not set it aside.
6. **Completion through connected steps:**
   - a correct chain from the original equation to its solution records
     `ORIGINAL_COMPLETED_WITH_HELP`;
   - a coincidentally matching final answer does not;
   - unrelated arithmetic does not.
7. **Several prerequisite problems answered wrongly** before the student succeeds on an
   easier one: the route records each.
8. **`goal_newly_met`** is in the request before the tutor's reply on the turn a goal is
   met, and absent on the next turn.
9. **The next request after correcting the latest attempt** shows the corrected verdict
   in `ANSWERED_PROBLEM`, `GRADEBOOK` and `GOALS`.
10. **Repairs:**
    - the repair instruction carries the new sentence;
    - a repaired reply whose item changed may change its wording.
11. **`message_type`** is set by code, and a reply without it is accepted.
12. **The three result-line forms in §4.7.**
13. **The small items in §4.8.**

## 6. Live run, part 1: targeted sessions

Use arm A: `deepseek-flash`, thinking disabled, base URL `https://api.deepseek.com/v1`.
The owner provides the key.

Run each script once, with a 30-turn cap:

- **HELP-THEN-MOVE-ON:** asks for help on a check and answers the smaller steps. When
  the tutor offers a fresh problem instead of the original, answers it, then answers
  later checks correctly.
- **STUCK-DEEP:** asks for help. Answers the first two prerequisite problems it is given
  wrongly with a typical error, then answers easier ones correctly, then the target.
- **BOUNDARY:** keeps answering prerequisite problems wrongly until the tutor reaches
  the edge of its ten skills.
- **RESET-LOOPHOLE:** two correct checks, then asks for help on the third, then answers
  the next problems correctly.

## 7. Live run, part 2: the broad run, mostly wrong answers

Scripted students follow six response patterns:

1. correct setup but unreliable arithmetic;
2. difficulty choosing the inverse operation;
3. fails a prerequisite, then succeeds on an easier task;
4. succeeds on the foundation but still struggles with the target;
5. unsure despite being able to do the maths (asks first, then answers correctly);
6. keeps being unable to proceed until the tutor's skill boundary.

**Their wrong answers come from code's own error catalogue.** These are the error
signatures the recipes already define for each template:

- FIRST_TERM_ONLY, SECOND_TERM_ONLY, NOT_EVERY_TERM
- SIGN_ERROR_B, ADD_INSTEAD_OF_SUBTRACT
- WRONG_INVERSE, MULTIPLY_INSTEAD_OF_DIVIDE, DIVIDE_THEN_ADD_B, DIVIDE_FIRST_ONLY
- SIGN_FLIP, ADDED_INSTEAD, ADDED_MAGNITUDES, QUOTIENT_SIGN
- the arithmetic slips

Use the error that fits the problem shown, so that most first attempts are wrong. The
owner may replace this with his own list later.

Run each pattern twice, once starting on Q06 and once on Q07: 12 sessions, 30-turn cap.

Report per session:
- goals met;
- check answers counted, with help, and held;
- help moves on open checks, and how many carried a practice item;
- the route: detours with their length and outcome, returns (original or fresh), and
  originals set aside or completed with help;
- prerequisite problems answered wrongly before the first success;
- whether the boundary was reached, and what the tutor said there;
- readings;
- equation_step verdicts;
- slip eligibility;
- detector signals;
- both counters;
- repairs and holds by cause;
- calls per turn and latency.

Commit everything to `test-results/live/round10/A/`.

## 8. Report

Write `ROUND_10_REPORT.md` with:

- the commits;
- the changes, by section of this file;
- the quoted list of model-facing strings and context fields;
- your technical choices, and the questions you asked the owner;
- the host-requirements table;
- both gates;
- the metrics for part 1 and part 2, with transcript and saved-call paths;
- each failure quoted from the transcripts, with no proposed fixes;
- the decisions needed;
- for part 2, the sentence: "These results cover the scripted error patterns tested.
  Performance on other error patterns remains untested."

Push, then stop.
