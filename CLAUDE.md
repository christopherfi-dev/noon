# Build instructions — Algebra Engine, round 8 design (v1.6 governs the AI tutor)

This file describes the design on branch `round8-v1-6`, set by
`CONDUCTOR_ROUND_8.md` on top of `CONDUCTOR_ROUND_7.md` and
`CONDUCTOR_ROUND_6_v3.md`. Where a conductor file and this file differ, the
newest conductor file governs. Do not touch `claude/elegant-volta-blu3uj` (the
deployed beta, frozen at `061239c`), the `beta-1.0` tag, `round6-ai-autonomy`,
`round6-ai-tutor` or `round7-v1-5`. Deploy nothing unless the owner asks.

## Governing architecture

`architecture/Algebra_Constraint_Architecture_v1.6.md` governs the tutor. It is
loaded byte-identical from `app/private/resources/architecture/` (sha256
`c5591d3dfa6aa87bf79b026936abce724e71ec9dd3e7316c7c7991553d390b7b`, no trailing
newline). Activation line:
`ACE_BOUND: architecture=1.6 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
v1.1, v1.2, v1.3 and v1.5 stay in `architecture/` as history; the app loads
v1.6 only. A stored session bound to an older version is rebound on its next
turn.

`architecture/HOST_REQUIREMENTS_v1.6.md` holds the requirements on code that
were moved out of the architecture word for word. Code is built and tested
against it; it is never sent to the model.

## Working method (CONDUCTOR_ROUND_8.md §0)

1. **The AI is the tutor.** The constraint architecture governs it. Code checks
   the math, keeps the gradebook, computes goals, writes the report, and handles
   plumbing and security. Code does not write tutoring text, choose replies, or
   make teaching decisions.
2. **Never edit the architecture file.** If it conflicts with the code or the
   conductor instructions, report the conflict. Do not fix it in the file and do
   not work around it in code.
3. **Behaviour guards log; they do not block.** Problems and envelopes that fail
   mathematical or structural acceptance are repaired once, then held or
   replaced as the conductor says.
4. **No example responses** in any prompt or model-facing string.
5. **No cost work of any kind, ever.** No budgets, no estimates, no projections
   and no balance checks. Cost is not mentioned in reports.
6. **Report failures; don't fix the architecture.** For every failure seen in a
   live run, quote the exact transcript lines. Do not propose architecture
   wording; the architect traces each failure to its line.
7. Stop where the conductor says to stop, push, and report.

## The prompt

Each AI call is built anew. The model receives only: the architecture (system),
a message headed `OUTPUT CONTRACT` that defines only the envelope's fields and
when each value is valid (including the `to_mode` value HELP: the tutor helped
with the open check problem), the activation request and the accepted
handshake, then one JSON context block. No text from the host requirements, the
changelog, this file or a conductor file reaches a model request (tested).

## What code does (as implemented)

- **Answers and verdicts.** Code computes every typed-answer key from a spec
  (`SpecMath`, the 14 recipes in `ItemFactory`) and records every verdict. The
  displayed wording of every scored problem is rendered by code from its
  verified spec; the AI's wording is not shown as the problem.
- **Problem acceptance.** Stated answer equals code's, unique solution, numbers
  in range, check pattern, fresh numbers, and for check problems only: a
  single-number answer (or its opposite) is not a number in the problem. A
  failing problem or envelope gets one repair; if that fails, the turn is a
  HOLD (`HOLD_LINE`, with a retry), except a check problem that still shows its
  answer, which a prepared check problem for the same skill replaces (logged).
  Every prepared problem satisfies the check rule.
- **Text guards are detectors.** Answer leak, internal vocabulary, mastery and
  goal claims, help claims (not in the handover sentence before a check card),
  verdict contradiction and the before-check-card audit are recorded with the
  exact text. Two counters log only: `RESOLVE_AFTER_CORRECT` and
  `VALUE_QUESTION_IN_TEXT`. The student sees the AI's text unchanged.
- **Help on an open check.** A reply during an open check whose move is HELP,
  HINT, EXPLAIN or ANSWER_QUESTION records `HELP_GIVEN` before it is shown; the
  check becomes practice and the goal policy applies. "I'm stuck" records help
  before the AI call, as before. Code never classifies typed text as help.
- **Held attempts.** A check is held (counting neither for nor against the
  goal) when the text before its card has a hint, method, strategy or reminder
  phrase, or its answer from neither the previous problem nor the student's
  entry; when the tutor's text during it uses its expression, a number from it
  or its answer with no help move (the backstop); or when the AI raises a
  verdict conflict in `issue`. Neutral invitations and shared numbers are
  logged only.
- **Readings, goals and the report** as in round 7: code checks every reading,
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
