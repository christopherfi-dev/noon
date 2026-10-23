# Build instructions — Algebra Engine, round 6 design (the AI is the tutor)

This file describes the design on branch `round6-ai-tutor`, set by
`CONDUCTOR_ROUND_6_v3.md`. Where that conductor file and this file differ, the
conductor file governs. The beta that ran the old design is frozen at tag
`beta-1.0` (commit `061239c`); do not rebuild it here.

**Do not rebuild the retired design.** The beta's host state machine (target →
diagnostic probe → prerequisite check → guided repair → independent check →
return, with the model drafting inside a host-chosen operation) is retired as
the controller of the session. Do not reintroduce host-sequenced modes, fixed
repair scripts, Socratic step plans, or a rule that model text may not contain
a question mark.

## The design

The AI runs the tutoring session as a conversation, governed by the constraint
architecture (`architecture/Algebra_Constraint_Architecture_v1.3.md`). It
decides every move: explain, ask, pose a problem, hint, check a slip, step back
to an easier skill, move on, review, wrap up. It chooses its own route and when
to give a check problem.

Code is the AI's set of instruments, not its supervisor:
- a calculator and answer checker (parsers, exact checkers, recipe signatures);
- a problem checker (spec verification, check-problem pattern validators);
- a problem generator (the 14 recipes in `ItemFactory`, pre-generated);
- a gradebook that records what happened and computes goals;
- the report.

Code takes control only where the invariants below say truth requires it.

One learner turn (an answer, a free-text message, or an "I'm stuck" choice) is
one AI call in most cases. Code does what it can deterministically before the
call (safe normalisation, parsing, checking, signatures, slips), then one call
returns one JSON envelope (reply plus at most one next problem), and code
verifies the envelope after the call. An entry only the AI can read takes a
reading call first. A failed problem gets one repair call, then a code
fallback (a pre-generated recipe problem with a code-written line).

## What code still guarantees (the round 6 invariants)

1. **Code computes every answer.** Every math question whose answer the
   student types (problem, guided step, micro-check) reaches the student only
   after code computed its answer from a machine-readable spec. The AI never
   decides whether a student's math answer is correct and never overrules
   code's verdict.
2. **Code alone computes goals**, from the gradebook. The AI sees goal status
   and plans with it; it cannot mark a goal met.
3. **Check problems stand alone.** The AI decides when and which skill; code
   writes the wording from the spec and shows it in a distinct card ("Check
   question: on your own"). The AI's text before the card refers only to the
   previous answer and contains no number from the new problem and no answer.
   While a check problem is open the conversation pauses.
4. **The gradebook is true.** Every attempt records the problem, the skill,
   practice or check, the student's entry, any reading note, the verdict, and
   any help given between presentation and answer.
5. **Code writes the report** from the gradebook (UI v3 report and its rules).
6. **Guards on every learner-visible AI text:** the answer-leak guard for any
   open problem; the internal-vocabulary filter; no mastery claims; the
   support-claim phrases. The AI may ask questions.
7. **Kept as built:** security (CSRF, idempotency, revisions, cookie rules,
   provider-in-flight leases), privacy and storage; progress codes and the
   report download; no answer keys, raw envelopes or internal diagnoses in any
   public payload; no secrets in the repository; PHP 8.0 compatibility.

Goals (code): 3 check problems correct in a row without help per skill; Q07
and E06 also need a solve or expansion, a choice question and a new form. One
code-confirmed slip may pause a run (micro-check, retry, not counted); a second
slip in the run resets it. Mock or unit-test success is never reported as live
provider, deployment or learner-effectiveness validation.

Nothing else from the old invariants constrains the AI's teaching.

## Stack (unchanged)

PHP 8.0+ source compatibility (gates run on PHP 8.0.30 and 8.3), vanilla
JavaScript, CSS and HTML, same-origin API. No Node process, Python service or
database server in production; PDO SQLite private storage, failing preflight
clearly if unavailable. Account-free; reports belong to the current browser
session. Real OpenAI-compatible adapter plus a deterministic mock tutor; keys
only on the server; no raw model streaming into the UI; JSON envelope, not
function calling. Reasoning (thinking) is chosen per call by code and logged;
`reasoning_content` is never shown or stored.

## Practice

Keep the handoff (`START_HERE.md`, `specifications/`, `contracts/`,
`curriculum/`, `MANIFEST.sha256`) intact; the full 205-skill curriculum stays
browsable, and runtime coverage is the 10 beta skills, declared and tested.
Parsers never use eval or shell and fail closed. Record each round as a
separate interval in `BUILD_LOG.md`. Do not deploy until the owner asks; the
side-by-side bundle (`dist/algebra-engine-ai-vps-upload.zip`) installs beside
the live beta with its own database and leaves the beta untouched.
