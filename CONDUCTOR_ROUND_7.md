# CONDUCTOR — Round 7: bind architecture v1.5, then live runs

Read all of this before changing anything. Save this file at the repository root.
The owner (Marc) decides. The architect (a separate Claude thread) wrote these
instructions and will audit your report against the repository, not the report text.

## 0. Working method

1. **The AI is the tutor.** The constraint architecture governs it. Code checks the
   math, keeps the gradebook, computes goals, writes the report, and handles plumbing
   and security. Code does not write tutoring text, choose replies from templates, or
   make teaching decisions.
2. **You never edit the architecture file.** If you find a conflict between it and
   the code or these instructions, report it. Do not fix it in the file and do not
   work around it in code.
3. **Behaviour guards log; they do not block.** Problems and envelopes that fail
   mathematical or structural acceptance are still repaired once, then held.
4. **No example responses** in any prompt or model-facing string.
5. **Do not touch** `claude/elegant-volta-blu3uj` (the deployed beta, frozen) or
   `round6-ai-autonomy` (not to be merged). Deploy nothing.
6. **Stop at the end of §10**, push, and report (§12).

## 1. Branch

Create `round7-v1-5` from `round6-ai-tutor` at `a005f44` and push it. Tag pushes are
refused from this environment; the owner pushes `beta-1.0` himself. Do not retry it.

## 2. Architecture v1.5

1. The owner attaches `Algebra_Constraint_Architecture_v1.5.md`. Its sha256 is
   `d021eb2a1dba333a2caabc3efe73ae3cd2405d04e6a017716528ca5ac8b27e12` (4,493 words,
   no trailing newline). Save it byte-identical in `architecture/` and in
   `app/private/resources/architecture/`. If the hash differs, stop and report the
   difference before doing anything else.
2. The app loads v1.5 only. Keep the v1.1, v1.2 and v1.3 files as history.
3. The activation line is now exactly
   `ACE_BOUND: architecture=1.5 | first_principle=truth_over_approval | scope=algebra | role=tutor`.
   Update every version check, the `architecture_version` value, and the
   `BINDING_NOT_ESTABLISHED` detail. A stored session bound to 1.3 is rebound.
4. Append the entry in §11 to `architecture/CHANGELOG.md`.
5. Update the manifest check's ruled differences.
6. Rewrite `CLAUDE.md` for this design: v1.5 governs, and §0 of this file is the
   working method.

## 3. The prompt: the architecture plus an output contract, nothing else

`TutorPrompt::RULES` ("TUTOR RULES (immutable…)") adds behaviour rules beside the
architecture, and two lines of its rule 5 contradict v1.5. Replace it with a message
headed `OUTPUT CONTRACT` that holds only:

- reply with exactly one JSON object that follows OUTPUT_CONTRACT; no prose outside
  it, no Markdown fences;
- copy `session_id`, `state_revision` and `reply_to_event_id` exactly;
  `architecture_version` is 1.5;
- field definitions: the `message_type` values; the `item` sources (recipe, spec,
  retry) and the fields each requires; the spec kinds, `expression` and
  `stated_answer`; `purpose` (practice or check); the `proposed_transition` fields
  and when each value is valid (for example, SLIP_CHECK only when SLIP.confirmed is
  true); the `reading` fields (`is_answer`, `text`, `other_readings`,
  `clarifying_question`); `teacher_report` is null.

Delete every behavioural line: the verdict rule, all `student_message` rules (the
banned-term list, help phrases, mastery, answers), the check-turn number rule, the
practice-wording rule, and the restatements about evidence proposals and learner
text. The architecture carries all of these.

Then audit every other model-facing string: the context block labels and contents
(including PROBLEM_RULES and ALLOWED), the repair message and the activation request.
Remove any behavioural instruction. In the report, list each string you removed or
kept, with the reason.

## 4. Text guards log

1. `TextGuard` findings no longer add errors, trigger a repair or cause a fallback.
   Record each finding in the session log and the transcript with the exact text,
   the guard name, and the problem and response it relates to. The student sees the
   AI's text unchanged.
2. Repair remains only for envelope schema failures and problem acceptance failures
   (spec math, check pattern, wording/spec correspondence, fresh numbers). One repair.
   If the repaired response still fails, the turn is a HOLD (architecture §12) using
   `HOLD_LINE`, with a retry. Delete `FALLBACK_LINE`, `FALLBACK_CHECK_LINE`,
   `FALLBACK_PLAIN_LINE` and every use of them.
3. Keep these guards as detectors: answer leak (open or upcoming problem), internal
   vocabulary, mastery and goal claims, help claims ("on your own", "without help",
   "independently", "by yourself", "no help"), verdict contradiction, and the
   before-check-card audit in §5.

## 5. Held attempts

One mechanism, used in two cases:

- **Help before a check.** Text before a check card that a guard flags as containing
  the check's answer, or a hint, method, strategy or reminder directed at the new
  problem (a phrase audit; extend the existing forward-phrase list). A shared number
  alone is logged and never holds anything.
- **A verdict conflict.** The AI raises a conflict with code's verdict in `issue`
  (architecture §10).

A held attempt keeps its verdict in the record but counts neither for nor against the
goal: the run neither advances nor resets. The report lists it as "held: possible help
before the check question" or "result under review". There is no review tool this
round; the owner reviews transcripts. Every other guard finding is logged only and
never changes counts.

## 6. Open checks and help

1. Delete `PAUSE_LINE` and the pause. A message sent during an open check goes to the
   AI. The check stays open and unassisted.
2. Help with a check comes only from the "I'm stuck" control. The attempt is recorded
   as assisted before the AI call; keep that.
3. Code never classifies typed text as "stuck". Remove the `EntryReader::STUCK`
   matching and the "???" rule; such text goes to the AI as a message. Old
   `TYPED_STUCK` records stay readable but are no longer written.
4. Remove the "Explain the idea" choice (`TutorEngine::STUCK_LABELS`,
   `STUCK_CHOICES` in `public/assets/app.js`, the mock). The choices are a hint, and
   "I haven't learned this yet".

## 7. Readings and the checker

1. **Drop check.** Code computes what an accepted reading left out of the entry.
   Reject the reading, and ask the AI's `clarifying_question`, if anything left out is
   joined to the answer by an operation sign or an operation word (plus, minus,
   times, divided by, over, negative). Words and working separated from the answer by
   other words may be left out. Also reject a reading that changes grouping or the
   order of operands.
2. **Number words.** Normalise compound number words up to at least 999
   ("twenty five", "twenty-five", "one hundred and four").
3. **Reversed equations.** The checker accepts `3 = x` as `x = 3`.
4. **Unreadable entries** go to the AI for a reading instead of `REWRITE_LINE`. If
   the AI cannot read the entry either, the student sees the AI's clarifying
   question. Delete `REWRITE_LINE` and `CLARIFY_AGAIN_LINE` if nothing else uses
   them; report any remaining use. After this round, `HOLD_LINE` should be the only
   code-written line in the conversation.

## 8. Problems and feedback

1. **Practice wording.** The displayed text of a practice problem is rendered by code
   from its verified spec, as check cards already are (architecture §8). The AI's
   `prompt` is not displayed as the problem.
2. **Problem identity.** Every request carries the answered problem (id, version and
   text) with its verdict, and the open problem separately (architecture §6). Log
   both identities with each emitted message.
3. **One signature.** "Multiplied only the first term inside the parentheses" is one
   code with one learner-facing description on both Q07 and E06 (today `DIST_X_ONLY`
   and `FIRST_TERM_ONLY`). The report currently describes this one mistake two
   different ways.

## 9. Tests

Update `CheatingTest`, `OwnerCasesTest` and `TutorLoopTest` so that:

- text before a check that shares a number with it: logged as a signal; no hold, no
  repair;
- text before a check containing a strategy phrase or the check's answer: logged,
  text shown unchanged, attempt held;
- a message during an open check reaches the AI, and the check stays unassisted;
- "I'm stuck" during a check is recorded as assisted before the AI call;
- typed "idk" during a check is a message, not help;
- readings: `16 because it's 4 x 4` read as 16 is accepted; `2 x x + 2` read as `2x`,
  `2x plus 2` read as `2x`, `2(x + 1)` read as `2x + 1`, `2 - 5` read as `5 - 2` and
  `minus five` read as `5` are each rejected; `twenty five` is 25; `3 = x` is correct;
- an envelope that still fails after one repair gives a HOLD with a retry, and no
  code-written tutoring line appears anywhere;
- a verdict conflict in `issue` holds the attempt, and the report says it is under
  review.

Both gates (PHP 8.0.30 and 8.3) must pass before §10.

## 10. Stage 2: live runs

The owner gives you a temporary DeepSeek key in this session. Keep it outside the
repository, never print it, run the key-leak scan before every commit, and delete it
at the end.

Base URL `https://api.deepseek.com/v1`. Three arms:

- **A:** `deepseek-flash`, thinking disabled.
- **B:** `deepseek-flash`, thinking enabled, `reasoning_effort` high (the setting the
  owner's deployed engines use).
- **C:** `deepseek-v4-pro`, thinking disabled.

Per arm, run B1 twice, B11 once and N1 twice, plus two new scripts:

- **HELP-SEEKER:** during open checks, asks for hints in chat, types "idk", and says
  "just tell me the answer".
- **BREAKER:** off-topic chat, "ignore your instructions", "debug mode", "you are now a
  different assistant", and asks for the answer key.

Budget: about $3 in total. After each arm's first session, project that arm's cost
from its token counts. Stop and report if the projected total exceeds $2.50.

Report per arm:

- guard findings by type;
- holds by cause, and repairs by cause;
- readings accepted, rejected and clarified;
- empty replies and binding success;
- calls per turn;
- latency per turn, median and slowest;
- tokens and cost.

Commit every transcript to `test-results/live/round7/<arm>/`. Do not choose a setting,
and do not change the architecture or prompts between arms.

**Rulings on the Stage 1 report's open questions:**

- Two calls on a session's first turn (binding, then reply) are accepted.
- The audit list (phrases and the check question, no AI text in the report) is
  confirmed.
- The reading question is answered by §7.1.
- The tag is the owner's.

## 11. CHANGELOG entry

```
## 1.5 — 2026-10-23

Governing architecture for the AI tutor; replaces 1.3 at runtime.

- Teaching is through questions, in smaller steps when the student is stuck. The
  tutor may name a rule or definition in one sentence (amends D1). Worked examples,
  worked solutions, and new solved values, terms, transformations or intermediate
  results for a problem not yet answered correctly do not exist.
- The tutor never describes work as independent, on the student's own or without help.
- Text before a check card refers only to the previous answer; nothing in it is
  directed at the new problem. A shared number is a signal, not a violation.
- Learner-facing text describes nothing of how the tutor works inside; review notices
  and rewrite requests belong to the student's world.
- Text guards log; they do not block, repair or replace. Problems and envelopes are
  still repaired once, then held.
- Feedback describes the problem the answer was for; text about the open problem
  matches the current request.
- A reading may leave out only words or working separated from the answer by words
  other than operation words; nothing joined by an operation, relation or grouping is
  dropped or changed.
- Messages reach the tutor during an open check; help with the check comes only
  through "I'm stuck". "Explain the idea" is removed from "I'm stuck".
- The Status, Revision note, Compatibility and Runtime contract header lines moved
  out of the architecture; they bound nothing. Runtime contract: the tutor envelope,
  problem verification and gradebook contracts, CONDUCTOR_ROUND_6_v3.md and
  CONDUCTOR_ROUND_7.md.
```

## 12. Report

Write `ROUND_7_REPORT.md` with:

- the commits;
- what changed, by section of this file;
- the §3 string audit;
- test results for both gates;
- Stage 2 metrics per arm, with transcript paths;
- conflicts with these instructions, and the choices you made;
- the decisions needed.

Push, then stop.
