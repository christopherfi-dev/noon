# Build instructions — Algebra Engine, round 10 design (v1.8 governs the AI tutor)

This file describes the design on branch `round10-v1-8`, set by
`CONDUCTOR_ROUND_10.md` on top of `CONDUCTOR_ROUND_9.md`, `CONDUCTOR_ROUND_8.md`,
`CONDUCTOR_ROUND_7.md` and `CONDUCTOR_ROUND_6_v3.md`. Where a conductor file and
this file differ, the newest conductor file governs; `CONDUCTOR_ROUND_10.md` §3
lists the round 9 behaviours it reverses. Do not touch
`claude/elegant-volta-blu3uj` (the deployed beta, frozen at `061239c`), the
`beta-1.0` tag, `round6-ai-autonomy`, `round6-ai-tutor`, `round7-v1-5`,
`round8-v1-6` or `round9-v1-7`. Deploy nothing unless the owner asks.

## Governing architecture

`architecture/Algebra_Constraint_Architecture_v1.8.md` governs the tutor. It is
loaded byte-identical from `app/private/resources/architecture/` (sha256
`f2db75f4f4e4512f9299f987efb4fb3315ce73d051d83d30990592a055a79d98`, no trailing
newline). Activation line:
`ACE_BOUND: architecture=1.8 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
v1.1, v1.2, v1.3, v1.5, v1.6 and v1.7 stay in `architecture/` as history; the
app loads v1.8 only. A stored session bound to an older version is rebound on
its next turn.

`architecture/HOST_REQUIREMENTS_v1.7.md` stays in force, unchanged: it holds the
requirements on code. Code is built and tested against it; it is never sent to
the model.

## Working method (CONDUCTOR_ROUND_10.md §0)

1. **The AI is the tutor.** Code checks the math, keeps the record, computes
   goals and writes the report. Code does not write tutoring text, choose
   replies, or make teaching decisions.
2. **Never edit the architecture.** Report conflicts instead.
3. **Text detectors log only.**
4. **No example responses** in any model-facing string.
5. **No cost work.**
6. **Model-facing text is exact.** Every model-facing sentence a conductor file
   specifies is used word for word. Any other model-facing string added or
   changed goes into a quoted list in the round's report.
7. **Stop and ask the owner** before deciding anything unspecified that affects
   what the student sees, what counts toward a goal, or what the tutor is told.
   Technical choices are ours; list them in the report.
8. **Do not touch** earlier branches or the `beta-1.0` tag. Deploy nothing.
9. **Stop at the end of the conductor's last run section**, push, and report.

## The prompt

Each AI call is built anew. The model receives only: the architecture (system),
a message headed `OUTPUT CONTRACT` that defines only the envelope's fields and
when each value is valid (with the round 9 §4.1 and round 10 §4.1 sentences
word for word), the activation request and the accepted handshake, then one
JSON context block (no `TRUSTED_SESSION` block; owner's decision). The model no
longer returns `message_type`: code sets it on every accepted reply (QUESTION
with an item, FEEDBACK without, CONTROL for a reading turn). No text from the
host requirements, the changelog, this file or a conductor file reaches a model
request, except the contract sentences a conductor file specifies (tested).
The repair request says: "Fix exactly these errors, update any wording they
affect, and change nothing else."

## What code does (as implemented)

- **Identity.** Code captures the session, state revision, triggering event and
  open problem with each request and attaches them to the reply; identity
  fields in a reply are ignored. A reply whose revision changed while the call
  was in flight is discarded before anything is committed. A help move binds
  to the open problem captured with its request.
- **Answers and verdicts.** Code computes every typed-answer key from a spec
  (`SpecMath`, the recipes in `ItemFactory`, and `equation_step`, practice
  only: STEP_CORRECT, EQUIVALENT_NOT_STEP, NOT_EQUIVALENT; a non-equation is a
  rewrite request) and records every verdict. The displayed wording of every
  problem is rendered by code from its verified spec.
- **Problem acceptance.** Stated answer equals code's, unique solution, numbers
  in range, check pattern, fresh numbers. A failing problem or envelope gets
  one repair; if that fails, the turn is a HOLD. There is no answer-in-question
  rule and no substitution.
- **Text detectors are signals.** Answer leak, internal vocabulary, skill
  codes, mastery and goal claims (including "the goal for … is met" and "the …
  goal is met"), help claims, verdict contradiction, the before-check-card
  audit and the during-check backstop are recorded with the exact text and
  hold nothing. "one" in "one to try", "one more" or "one of" is not the
  number 1. Two counters log only: `RESOLVE_AFTER_CORRECT` and
  `VALUE_QUESTION_IN_TEXT`.
- **Help on an open check.** Only HELP and HINT count. Such a reply records
  `HELP_GIVEN` before it is shown; the check becomes practice. "I'm stuck"
  records help before the AI call. Code never classifies typed text as help.
- **Which open problem blocks a check (round 10 §4.2).**
  `ALLOWED.may_pose_check` is false only while an unanswered check with no help
  recorded is open. A helped problem still open when a new problem is posed
  becomes the waiting `ORIGINAL_PROBLEM`.
- **Original, target and route (round 10 §4.3).** `ORIGINAL_PROBLEM` closes
  when it is answered, completed through connected equation steps
  (`ORIGINAL_COMPLETED_WITH_HELP`, no goal credit), set aside by an accepted
  fresh check on the target skill (`RETURN_FRESH`, `ORIGINAL_SET_ASIDE`), or the
  session ends; a newer helped problem replaces an older one, which is set
  aside (owner's decision). Practice never sets it aside; skip, "I haven't
  learned this yet" and a topic change leave it waiting (owner's decision).
  Context blocks `TARGET {skill_id, skill_name, goal_met, set_aside_original}`
  and `ROUTE` (entries `{kind, skill_id, problem_id, at}`: DETOUR,
  RETURN_ORIGINAL, RETURN_TO_SKILL, RETURN_FRESH, ORIGINAL_SET_ASIDE,
  ORIGINAL_COMPLETED_WITH_HELP).
- **Goals (round 10 §4.4).** `Goals::compute()` takes the `HELP_GIVEN` events:
  help on an unanswered check restarts that skill's run when it is recorded,
  and that check's later answer counts neither for nor against (no second
  reset). Three qualifying checks without help; one slip may pause the count.
- **Slips.** `SLIP.eligible_for_pause`: expansions only when the other terms
  match and the constant is a near miss of the product; signed multiplication
  and division as before; a bare value of x never; a factor-of-1 product never.
  Strings to the tutor state what the answer did, never why.
- **What the tutor is told (round 10 §4.6).** `CODE_RESULT.goal_newly_met`
  (computed before the model call); `GRADEBOOK.session_totals_by_skill` with
  `check_correct_no_recorded_help` and `check_correct_with_help`.
- **Result lines (round 10 §4.7, owner-approved).** "On {problem} you answered
  {entry}."; "On {problem}, read from your message: {reading}."; "On the step
  “{instruction}”, you wrote {entry}." (or "…”, read from your message:
  {reading}.").
- **Holds.** Only a verdict conflict raised in `issue` holds an attempt.
- **Corrections.** `app/tools/correct_attempt.php` (private CLI) appends a
  `CORRECTION` event; the gradebook applies the latest correction per field;
  goals, progress, the stored report and the stored engine record of the last
  answered problem (and slip state derived from it) are updated, so the next
  request shows the corrected values.
- **Readings, goals and the report**: code checks every reading, computes goals
  and writes the report from the gradebook. The teacher view has a "Route and
  return" section (owner-approved wording): detours with their length and
  outcome, returns, originals completed with help or set aside, and fresh
  evidence on the target after the return, as evidence, not a score.
- **Live runs** save every model call's request messages and raw response
  under `test-results/live/<round>/<arm>/<session>/calls/`; never the key,
  never `reasoning_content`.
- **Kept as built:** CSRF, idempotency, revision checks, cookie rules,
  provider-in-flight leases, privacy and storage, progress codes, the report
  download, no answer keys or internal data in public payloads, no secrets in
  the repository, PHP 8.0 source compatibility.

Mock or unit-test success is never reported as live provider, deployment or
learner-effectiveness validation.

## Stack (unchanged)

PHP 8.0+ source compatibility (gates run on PHP 8.0.30 and 8.3), vanilla
JavaScript, CSS and HTML, same-origin API. No Node process, Python service or
database server in production; PDO SQLite private storage, failing preflight
clearly if unavailable. Account-free; reports belong to the current browser
session. Real OpenAI-compatible adapter plus a deterministic mock tutor; keys
only on the server; no raw model streaming into the UI; JSON envelope, not
function calling. `reasoning_content` is never shown or stored.

## Practice

Keep the handoff (`START_HERE.md`, `specifications/`, `contracts/`,
`curriculum/`, `MANIFEST.sha256`) intact; the full 205-skill curriculum stays
browsable, and runtime coverage is the 10 beta skills, declared and tested.
Parsers never use eval or shell and fail closed. Record each round as a
separate interval in `BUILD_LOG.md`. Run the key-leak scan before every commit;
keep any provider key outside the repository and never print it. Do not deploy
until the owner asks; the side-by-side bundle
(`dist/algebra-engine-ai-vps-upload.zip`) installs beside the live beta with its
own database and leaves the beta untouched.
