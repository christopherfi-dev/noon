# Conductor Round 6 (v3) — The AI Is the Tutor

**This is the only Round 6 file.** It replaces `CONDUCTOR_ROUND_6.md` and
`CONDUCTOR_ROUND_6_v2.md`, which were never sent. Save it in the repository root
on the new branch (§0) and read all of it before writing code.

**Precedence.** Where this file conflicts with `CLAUDE.md`, the 15 invariants
or earlier conductor files, this file governs. §2 lists exactly what survives.

## 0. Freeze the beta, open a new branch

1. Check out `claude/elegant-volta-blu3uj`. Tag commit `061239c` as `beta-1.0`
   and push the tag. In the tag message, record:
   - "Deployed at tools.constraintlayer.ai/educational-engines/algebra-engine/";
   - the VPS bundle sha256
     `a1f02a0f47643e2c71e9549bd26a331a0ce79dd94815764d1c7bb1d6158efb6e`.
2. Create branch `round6-ai-tutor` from `beta-1.0` and push it. If your
   environment requires a `claude/` prefix, use `claude/round6-ai-tutor` and
   say so.
3. Do not commit to `claude/elegant-volta-blu3uj` again. A fix to the live beta
   happens only if the owner asks for one, on a branch made from `beta-1.0`.
4. Start a "Round 6" interval in `BUILD_LOG.md`.
5. **Rewrite `CLAUDE.md`** on the new branch to describe this design: the §2
   invariants and the AI's role as tutor. A future session that reads only
   `CLAUDE.md` must not rebuild the old design.
6. **The handoff manifest check** will flag the edited `CLAUDE.md` and the new
   architecture file. Add both to its list of ruled differences, as was done for
   the teacher guide. Do not edit `MANIFEST.sha256`.

## 1. The design

The AI runs the tutoring session as a conversation, governed by the
constraint architecture. It decides every move:
- explain, ask, pose a problem, hint;
- check a slip;
- step back to an easier skill, move on, review, wrap up.

Code is the AI's set of instruments, not its supervisor:
- a calculator and answer checker;
- a problem checker;
- a problem generator;
- a gradebook that records what happened and computes goals;
- the report.

Code takes control only where §2 says truth requires it.

The beta's state machine, which sequences target, probe, prerequisite check,
guided repair, independent check and return, is **retired as the controller of
the session**. Reuse its parts as instruments wherever useful: checkers,
parsers, recipes, signatures, storage and security.

**Old tests that assert the retired sequencing are retired with it.** List each
retired test in the report with a one-line reason, and replace it where the
behaviour it protected still matters. Do not bend the new design to keep an old
flow test passing.

## 2. What code still guarantees (the new invariants)

1. **Code computes every answer.** Any math question whose answer the student
   types, whether a problem, a guided step or a micro-check, reaches the
   student only after code has computed its answer from a machine-readable
   spec. The AI never decides whether a student's math answer is correct, and
   never overrules code's verdict.
2. **Code alone computes goals,** from the gradebook. The AI sees goal status
   and plans with it. It cannot mark a goal met.
3. **Check problems stand alone** (§5).
4. **The gradebook is true.** For every attempt it records: the problem, the
   skill, practice or check, the student's entry, any reading note, the
   verdict, and any help given between presentation and answer.
5. **Code writes the report** from the gradebook. Keep the UI v3 report and its
   rules.
6. **Guards on every learner-visible AI text:**
   - the answer-leak guard for any open problem;
   - the internal-vocabulary filter;
   - no mastery claims (for example "mastered", "you've learned this", "you
     know this now");
   - the support-claim phrases (round 5.1).

   The old rule rejecting question marks is removed: the AI may ask questions.
7. **Kept as built:**
   - security (CSRF, idempotency, revisions, cookie rules), privacy and
     storage;
   - the progress code and the report download;
   - no answer keys in public payloads;
   - no secrets in the repository;
   - PHP 8.0 compatibility.

Nothing else from the old invariants constrains the AI's teaching.

## 3. The tutor loop: one call per turn

A learner turn is any one of:
- an answer typed into the answer box;
- a free-text message;
- an "I'm stuck" choice (hint, explain the idea, or haven't learned this),
  kept from UI v3 because the choice matters for evidence.

1. **Before the call,** code does what it can deterministically:
   - **Safe normalisation:** a capital letter becomes the item's single
     variable when the entry uses no other letters, and the trailing-"?" rule
     applies.
   - **Parse and check:** if the turn answers an open problem and the entry is
     standard notation, code parses it and checks it. Its signatures run, and
     slips are detected.
   - **Ambiguous entries:** an entry containing words, `×`, "times", or the
     variable letter standing alone between spaces next to a number or another
     variable (for example `4 x 4`, `4 x x`) is not parsed. It goes to the AI
     to read (step 4).
2. **One AI call** receives:
   - the architecture;
   - recent conversation;
   - the open problem;
   - code's verdict and signature, if any;
   - the gradebook summary and goal status;
   - the student's topic;
   - the beta curriculum slice: 10 skills, their prerequisites and
     descriptions;
   - **2 fresh recipe problems per relevant skill,** pre-generated so the AI
     never needs a round trip to get one.

   It returns one envelope: its reply, plus at most one next problem (its own
   spec or a chosen recipe problem). Code verifies the problem after the call
   (§4).
3. **Use the existing JSON envelope**, proven in rounds 3–5. Do not use
   function calling in Stage 1. Never show or store `reasoning_content`.
4. **An entry the AI must read** takes two calls, and should be rare:
   - first, the AI returns its reading;
   - code checks the reading (§4.1) and checks the answer;
   - then the reply call.
5. **Repairs.** If a proposed problem fails verification, make one repair
   call. If that also fails, code shows one of the pre-generated recipe
   problems with a code-written line, drops the AI's text for that turn, and
   records it.
6. **Reasoning per call,** decided by code before the call:
   - **Thinking enabled, low effort,** for an entry the AI must read, a wrong
     answer with no code signature, and a free-text message about the math.
   - **Thinking disabled** for everything else.
   - Log which was used on every call.
7. **Latency targets per learner turn** (provisional; the owner may change
   them): median 4 s or less, slowest 10 s or less. Report against them.

## 4. Instruments and their checks

**4.1 Reading check.** The AI's reading of a student entry may only re-express
what the student wrote:
- every number in it must appear in the entry, as digits or as a number word
  from the existing list;
- it may introduce no variable except as the reading of `x` used for
  multiplication.

A failing reading is rejected, and the student is asked to rewrite the entry.
With each reading the AI declares any other plausible readings:
- **Practice:** the AI's reading is used, and the gradebook marks it "read from
  the student's wording".
- **Check problem with another plausible reading:** the AI asks one short
  clarifying question before anything is recorded.

**4.2 Problem verification.** Every typed-answer question needs a spec.
- **Spec kinds:**
  - a linear equation in x;
  - an expression to expand;
  - signed arithmetic;
  - a numeric sub-step, for example `7*5`.
- **Code computes the answer.** It rejects the problem if:
  - the AI's stated answer differs from code's;
  - there is no unique solution;
  - numbers are out of the configured range;
  - the prompt contains its own answer.
- **Practice problems:** correct math is enough, and the AI may phrase them in
  its own words.
- **Check problems** must also match their skill's structural pattern. Build
  the validators from the 14 recipes, used in reverse.
- **Classification (choice) check problems** come only from the existing
  contrast recipes.

**4.3 Recipes:** the existing `ItemFactory`, offered through the pre-generated
problems in step 3.2.

**4.4 Slips:** code confirms a slip only when the recipe's slip signature
matches (structure correct, one lower-level arithmetic error, for example
`7x + 25` for `7(x + 5)`).

## 5. Check problems: the one place code takes the floor

1. **The AI decides when to give a check problem and which skill.** Code writes
   the problem's wording from the spec and shows it in a distinct card labelled
   "Check question: on your own".
2. **The AI's text before the card** may refer only to the previous answer.
   Code rejects it if it contains any number from the new problem, or leaks the
   answer. Forward-looking phrases (next, try, remember, make sure, don't
   forget, when you) go on an audit list in the report.
3. **While a check problem is open, the conversation pauses.**
   - The student can answer it, skip it, or choose "I'm stuck".
   - A free-text message gets the code line "Let's finish this check question
     first. Answer it, or tap I'm stuck."
   - "I'm stuck" makes the attempt with help, and the AI helps.
4. **Goals,** computed by code:
   - 3 check problems correct in a row, without help, per skill.
   - Q07 and E06 also require their three variants: a solve or expansion, a
     choice question and a new form.
5. **Slip pause.**
   - After a code-confirmed slip, the AI asks a micro-question as a practice
     spec (for example "Wait, what is 7 × 5?"). Code checks the reply; if
     correct, the AI invites a retry.
   - The slipped answer and its retry don't count, and the run is not reset.
   - At most one pause per run; a second slip resets it.
   - Goal wording: "3 correct without help (one slip may pause the count)".

## 6. What the AI is expected to do well

v1.3 describes this, and live tests judge it:
- read what the student meant: sentences, `4 x 4`, `4 x x` in context,
  capitals;
- when right, say so, describe what the student did and name the rule they
  used;
- when wrong, find the specific error and teach there through questions, with
  smaller steps when the student is stuck (owner decision D1);
- handle slips with a micro-check, not a reset;
- choose its own route: step back to the easier skill the error points to, and
  return when ready, without a fixed script;
- answer the student's own questions;
- keep the student moving, and judge when to give a check problem;
- never claim mastery, leak an answer or invent praise.

## 7. Architecture v1.3

- Create `architecture/Algebra_Constraint_Architecture_v1.3.md` from v1.2,
  rewriting the role sections for this design.
- Keep its principles:
  - truth over approval;
  - scoped verification over self-certification;
  - coherence through correction;
  - no mastery claims.
- Remove text that assumes host-sequenced modes. Bump the handshake to 1.3, and
  record the changes in the CHANGELOG.
- **No example responses in the prompt** (owner rule). Describe behaviour in
  words; do not paste sample tutor replies, including any quoted in this
  file. The placeholder-only JSON skeleton is still allowed.

## 8. UI

- **Conversation view:**
  - tutor and student messages, scrolling, newest at the bottom;
  - the open problem pinned above the input;
  - one input for an answer or a message, with Send and "I'm stuck" (its
    three choices).
- **Check problems** use the card from §5.
- **Kept from UI v3:** the progress dots (from code's goal status), the
  progress code, the report and download, the privacy notice and the mobile
  layout. The mock badge still shows in mock mode.
- **Progress codes:**
  - The record stores gradebook statuses and the AI's stated next focus.
  - On resume, the "Welcome back" card is written by code, and the AI
    continues from the record.
  - Codes do not carry between the beta and this install, because they use
    separate databases.

## 9. Stage 1: offline, then stop

1. **Milestones,** each committed with its tests passing:
   - **M1. Instruments:** reading check, problem verification and validators,
     recipes, slip confirmation, the gradebook and goal computation, each
     tested against attempts to cheat it.
   - **M2. Tutor loop:** one call per turn, pre-generated problems, per-call
     reasoning, repairs, and a mock tutor that follows scripted policies,
     including adversarial ones.
   - **M3. Check-problem card, conversation pause, guards and the
     conversation UI.**
2. **Regression tests for the owner's four live cases:**
   - `9X + 8` on 9(x + 8): read as 9x + 8, wrong, and the distribution-to-x-only
     signature reaches the AI.
   - `16 because it's 4 x 4`: the AI reads it as 16, and code marks it correct.
   - `4 x x` in a how-many-x context: read as 4x, correct.
   - `7x + 25` on 7(x + 5): slip confirmed, micro-check checked by code, retry,
     run paused.
3. **Cheating tests.** Each of these is rejected or recorded correctly:
   - the AI marks a goal;
   - the AI overrules a verdict;
   - the AI poses a problem with a wrong answer;
   - the AI poses an off-pattern check problem;
   - a reading adds numbers;
   - a slip is claimed that the structure contradicts;
   - the text before a check card contains its numbers;
   - text leaks an answer;
   - text claims mastery;
   - a free-text message is sent during an open check problem;
   - "without help" is recorded after a stuck entry.
4. All gates pass on PHP 8.0.30 and 8.3.
5. **Calls per turn.** Report mock-journey calls per turn: most turns must use
   exactly 1.
6. Build the side-by-side bundle (§11). **Stop and report.**

## 10. Stage 2: live, after the architect reviews Stage 1

The owner supplies a temporary key. There are four runs:
- (a) `deepseek-flash`, thinking disabled throughout;
- (b) `deepseek-flash`, per-call reasoning as in §3.6;
- (c) `deepseek-flash`, thinking enabled at high effort throughout;
- (d) `deepseek-v4-pro`, thinking disabled throughout.

Each run covers:
- 5 B1-style sessions;
- 3 descent sessions;
- B11 twice;
- N1 "natural learner" twice: the owner's four cases, answers in sentences,
  arithmetic slips, questions to the tutor, and going off topic.

Report, per run:
- calls and tool steps per turn;
- learner wait, median and slowest, against §3.7;
- every rejected AI output with its text and reason;
- clarifying questions asked;
- slips confirmed and slips rejected;
- the forward-looking audit list;
- HOLDs;
- tokens per session.

Attach every transcript, and mark on each turn which reasoning setting was
used. Do not choose a run. Delete the key afterwards.

## 11. Side-by-side bundle

Build `dist/algebra-engine-ai-vps-upload.zip`:
- public: `/var/www/html/educational-engines/algebra-engine-ai/`;
- private: `/var/www/algebra-engine-ai-private/`;
- base path: `/educational-engines/algebra-engine-ai/`.

It has its own database, contains no `config.php` and nothing inside `var/`.
The beta stays live and untouched. Report its sha256. Do not deploy.

## 12. Report

Give the following, in order:
- the branch and tag confirmation;
- what was built, by milestone;
- what was reused, and what was retired (including the retired tests);
- the test results;
- conflicts with this file;
- decisions needed.

Keep it short.
