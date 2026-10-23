# Build instructions — Algebra Engine, round 7 design (v1.5 governs the AI tutor)

This file describes the design on branch `round7-v1-5`, set by
`CONDUCTOR_ROUND_7.md` on top of `CONDUCTOR_ROUND_6_v3.md`. Where a conductor
file and this file differ, the newer conductor file governs. The beta that ran
the old host-sequenced design is frozen at commit `061239c` (tag `beta-1.0`,
branch `claude/elegant-volta-blu3uj`); do not rebuild it and do not commit to
that branch. Do not merge `round6-ai-autonomy`.

## Governing architecture

`architecture/Algebra_Constraint_Architecture_v1.5.md` governs the tutor. It is
loaded byte-identical from `app/private/resources/architecture/` (sha256
`d021eb2a1dba333a2caabc3efe73ae3cd2405d04e6a017716528ca5ac8b27e12`, no trailing
newline). Activation line:
`ACE_BOUND: architecture=1.5 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
v1.1, v1.2 and v1.3 stay in `architecture/` as history; the app loads v1.5
only. A stored session bound to an older version is rebound on its next turn.

## Working method (CONDUCTOR_ROUND_7.md §0)

1. **The AI is the tutor.** The constraint architecture governs it. Code checks
   the math, keeps the gradebook, computes goals, writes the report, and handles
   plumbing and security. Code does not write tutoring text, choose replies from
   templates, or make teaching decisions.
2. **Never edit the architecture file.** If it conflicts with the code or the
   conductor instructions, report the conflict. Do not fix it in the file and do
   not work around it in code.
3. **Behaviour guards log; they do not block.** Problems and envelopes that fail
   mathematical or structural acceptance are still repaired once, then held.
4. **No example responses** in any prompt or model-facing string.
5. Do not touch `claude/elegant-volta-blu3uj` (the deployed beta, frozen) or
   `round6-ai-autonomy` (not to be merged). Deploy nothing unless the owner asks.
6. Stop where the conductor says to stop, push, and report.

## The prompt

Each AI call is built anew: the architecture (system), a message headed
`OUTPUT CONTRACT` that defines only the envelope's fields and when each value is
valid, the accepted binding exchange, then one JSON context block. The output
contract holds no behaviour rules; the architecture carries all of them.

## What code does (architecture §5, as implemented)

- **Answers and verdicts.** Code computes every typed-answer key from a spec
  (`SpecMath`, the 14 recipes in `ItemFactory`) and records every verdict. The
  displayed wording of every scored problem, practice or check, is rendered by
  code from its verified spec; the AI's wording is not shown as the problem.
- **Problem acceptance.** Stated answer equals code's, unique solution, numbers
  in range, no self-revealing answer, check pattern, fresh numbers. A failing
  problem or envelope gets one repair; if that fails, the turn is a HOLD
  (`HOLD_LINE`, with a retry). There is no code-written fallback reply.
- **Text guards are detectors.** Answer leak, internal vocabulary, mastery and
  goal claims, help claims, verdict contradiction and the before-check-card
  phrase audit are recorded (session log and transcript, with the exact text,
  the guard and the problem and response ids). The student sees the AI's text
  unchanged.
- **Held attempts.** A check attempt is held, counting neither for nor against
  the goal, when a guard flags text before its card as containing its answer or
  a hint, method, strategy or reminder directed at it, or when the AI raises a
  verdict conflict in `issue`. A shared number alone is logged and holds nothing.
- **Open checks.** Messages during an open check go to the AI; the check stays
  open and unassisted. Help comes only from "I'm stuck" (a hint, or "I haven't
  learned this yet"), recorded as assisted before the AI call. Code never
  classifies typed text as stuck.
- **Readings.** Entries code cannot read go to the AI for a reading. Code rejects
  a reading that adds numbers or variables, drops anything joined to the answer
  by an operation sign or word, or changes grouping or operand order; then the
  student sees the AI's clarifying question. Number words are normalised up to
  999. `3 = x` is accepted as `x = 3`.
- **Goals** (architecture §5): three qualifying check problems correct without
  help; Q07 and E06 also need a solve or expansion, a choice question and a new
  form. One code-matched arithmetic-error signature pauses the run; a second in
  the run resets it. Code alone computes goals and writes the report.
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
separate interval in `BUILD_LOG.md`. Run the key-leak scan before every commit.
Do not deploy until the owner asks; the side-by-side bundle
(`dist/algebra-engine-ai-vps-upload.zip`) installs beside the live beta with its
own database and leaves the beta untouched.
