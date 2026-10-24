# Build instructions — Algebra Engine, round 9 design (v1.7 governs the AI tutor)

This file describes the design on branch `round9-v1-7`, set by
`CONDUCTOR_ROUND_9.md` on top of `CONDUCTOR_ROUND_8.md`, `CONDUCTOR_ROUND_7.md`
and `CONDUCTOR_ROUND_6_v3.md`. Where a conductor file and this file differ, the
newest conductor file governs; `CONDUCTOR_ROUND_9.md` §3 lists the round 8
behaviours it reverses. Do not touch `claude/elegant-volta-blu3uj` (the
deployed beta, frozen at `061239c`), the `beta-1.0` tag, `round6-ai-autonomy`,
`round6-ai-tutor`, `round7-v1-5` or `round8-v1-6`. Deploy nothing unless the
owner asks.

## Governing architecture

`architecture/Algebra_Constraint_Architecture_v1.7.md` governs the tutor. It is
loaded byte-identical from `app/private/resources/architecture/` (sha256
`afe9a01e82122f8fcb5c9b1c7e12a493d17ab307b14161656accd4e947dc9188`, no trailing
newline). Activation line:
`ACE_BOUND: architecture=1.7 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
v1.1, v1.2, v1.3, v1.5 and v1.6 stay in `architecture/` as history; the app
loads v1.7 only. A stored session bound to an older version is rebound on its
next turn.

`architecture/HOST_REQUIREMENTS_v1.7.md` holds the requirements on code. Code is
built and tested against it; it is never sent to the model.

## Working method (CONDUCTOR_ROUND_9.md §0)

1. **The AI is the tutor.** Code checks the math, keeps the gradebook, computes
   goals, writes the report, and handles plumbing and security. Code does not
   write tutoring text, choose replies, or make teaching decisions.
2. **Never edit the architecture file.** If you find a conflict, report it.
3. **Every text detector logs only.** No detector holds a check, changes a
   count or alters the tutor's text.
4. **No example responses** in any prompt or model-facing string.
5. **No cost work of any kind.**
6. **Model-facing text is exact.** Every sentence the model receives that a
   conductor file specifies is used word for word. Any other model-facing string
   added or changed (context labels, observation strings, repair messages) is
   listed, quoted, in the round's report.
7. **Stop and ask the owner** before deciding anything not specified that
   affects what the student sees, what counts toward a goal, or what the tutor
   is told. Purely technical choices, such as names or file layout, are ours;
   list them in the report.
8. **Do not touch** the branches and tag named above. Deploy nothing.
9. Stop where the conductor says to stop, push, and report.

## The prompt

Each AI call is built anew. The model receives only: the architecture (system),
a message headed `OUTPUT CONTRACT` that defines only the envelope's fields and
when each value is valid (with the round 9 §4.1 sentences word for word), the
activation request and the accepted handshake, then one JSON context block (no
`TRUSTED_SESSION` block; owner's decision). No text from the host requirements,
the changelog, this file or a conductor file reaches a model request, except
the contract sentences a conductor file specifies (tested).

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
  rule and no substitution. Code's own problems prefer parameters whose answer
  is not among the problem's numbers and accept the overlap when none fit.
- **Text detectors are signals.** Answer leak, internal vocabulary, skill
  codes, mastery and goal claims, help claims, verdict contradiction, the
  before-check-card audit and the during-check backstop are recorded with the
  exact text and hold nothing. Two counters log only:
  `RESOLVE_AFTER_CORRECT` (substitution checks left out) and
  `VALUE_QUESTION_IN_TEXT` (expression requests in, operation questions out).
- **Help on an open check.** Only HELP and HINT count. Such a reply records
  `HELP_GIVEN` before it is shown; the check becomes practice and the goal
  policy applies. It may carry a practice item, shown after the reply; the
  helped check then waits as `ORIGINAL_PROBLEM` (practice only, no check may be
  posed) until the tutor brings it back (item source `original`), poses a
  problem on another skill, the topic changes, or the student skips or chooses
  "I haven't learned this yet". "I'm stuck" records help before the AI call.
  Code never classifies typed text as help.
- **Slips.** `SLIP.eligible_for_pause`: expansions only when the other terms
  match and the constant is a near miss of the product (not a + b, b, a or the
  product's opposite); signed multiplication and division as before; a bare
  value of x never. Strings to the tutor state what the answer did, never why.
- **Holds.** Only a verdict conflict raised in `issue` holds an attempt; help
  recorded on a held check restarts the run.
- **Corrections.** `app/tools/correct_attempt.php` (private CLI) appends a
  `CORRECTION` event; the gradebook applies the latest correction per field,
  and goals, progress and the stored report are recomputed. The teacher view
  lists each correction.
- **Readings, goals and the report** as in round 8: code checks every reading,
  computes goals (three qualifying checks without help; one slip may pause the
  count) and writes the report from the gradebook.
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
