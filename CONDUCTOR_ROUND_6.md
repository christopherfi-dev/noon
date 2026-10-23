# Conductor Round 6 — The AI Tutors, Code Verifies

Read this whole file before acting. It starts a new line of work and does not change the deployed beta.
0. Freeze the beta, open a new branch

1. On `claude/elegant-volta-blu3uj`, tag commit `061239c` as `beta-1.0` and push the tag. In the tag message, record:
   * "Deployed to tools.constraintlayer.ai/educational-engines/algebra-engine/";
   * the VPS bundle sha256 `a1f02a0f47643e2c71e9549bd26a331a0ce79dd94815764d1c7bb1d6158efb6e`.
2. Create branch `round6-ai-autonomy` from `beta-1.0` and push it. If your environment requires a `claude/` prefix, use `claude/round6-ai-autonomy` and say so in the report.
3. Never commit to `claude/elegant-volta-blu3uj` again. All round 6 work goes on the new branch.
4. Start a "Round 6" interval in `BUILD_LOG.md` on the new branch.

Stage 1 (§1–§7) is offline: mock provider, no key, nothing deployed. Stage 2 (§8) needs a temporary key from the owner.
1. Why
The owner's first live test found that every failure was a moment where a tutor would have understood the student but code stood in its place:

* `9X + 8` was rejected for its capital letter. That hid the exact mistake the engine exists to catch, multiplying only the x.
* `16 because it's 4 x 4` was rejected, though the answer and the reasoning were both right.
* `4 x x`, meaning 4 times x, was read as wrong, and the student was stepped down.
* `7x + 25` for 7(x + 5) was a slip. The distribution was correct and only 7 × 5 was wrong. It reset the run to zero.

Governing line, revised: the AI interprets and teaches; code verifies and records. Code still decides:

* whether an answer is correct;
* what counts toward a goal;
* what "without help" means;
* every statement in the record and the report.

The AI decides how to read the student and how to teach. Every AI output that could affect the record passes a code check first. Every learner-visible AI text passes the existing guards.
2. Architecture v1.3
Create `architecture/Algebra_Constraint_Architecture_v1.3.md` from v1.2. Bump the handshake to `architecture=1.3`, and record the changes in the CHANGELOG. The v1.3 text must describe the four AI operations in §3–§6 and the checks that bind them. It must also replace the round 4–5 rules that made code write these moments.
Keep these unchanged:

* truth over approval;
* no mastery claims;
* "LLM proposes, host accepts". It now means: host accepts after the checks listed here, not only when the proposal equals a host plan.

3. INTERPRET: reading what the student wrote

1. Deterministic first. Code applies safe normalisation: capital letters to the item's variable when the item has exactly one variable and the entry uses no other letters, plus the existing trailing-"?" rule. Then it parses.
2. When code needs the AI. Code sends the entry to the AI when it cannot parse it, or when the entry contains any of these:
   * words;
   * `×` or the word "times";
   * the variable letter standing alone between spaces next to a number or another variable (for example `4 x 4`, `4 x x`).
3. What the AI returns:
   * `kind`: answer, stuck, question, off_task or unreadable;
   * `normalized_answer`: in the checker's format, or null;
   * `reading_note`: one short plain sentence, for example "used x as a multiplication sign".
4. Code's guard on the AI's reading. The normalised answer may only re-express what the student wrote:
   * every number in it must appear in the entry, as digits or as a number word from the existing list;
   * it may introduce no variable the entry did not contain, except as the reading of `x` used for multiplication.
A proposal that fails is rejected, and the entry is treated as unreadable, with the AI's reply asking the student to rewrite it.
5. Guided steps. The AI's reading governs. Code checks it.
6. Scored items (anything that can count toward a goal):
   * If code's own parse and the AI's reading disagree, the AI asks one short clarifying question. The student's reply is then checked.
   * Otherwise code checks the AI's reading.
   * Either way, the record marks the answer "read from the student's wording" with the `reading_note`.
7. What the AI may never do: change a checker result, or turn a wrong value into a right one.

4. RESPOND: the tutor's voice
The AI writes the tutor's reply after every checked answer and to every stuck, question or off-task entry. The reply may:

* For a correct answer: say so, describe what the student did, and name the rule they used. For example: "You're right. That's how it always works: the number outside multiplies each term inside."
* For a wrong answer: explain the error at the point where it happened, through questions where possible (owner decision D1).
* For a question from the student: answer it, without giving the active item's answer.
* For a stuck student: support them, under the existing hint rules.

Guards:

* Kept: the answer-leak guard (active item and any next item), the internal-vocabulary filter and the support-claim phrase list.
* Removed: the rule rejecting a question mark in model text. Questions are now allowed.
* Before an on-your-own item. When the next item is INDEPENDENT, the reply may only refer back:
   * it describes the student's previous answer and the rule they themselves used;
   * it must not introduce a method the student did not use, and must not give advice for the next problem.
* The item text stays host-written, exactly as in round 5. Replace B14 with a test that model text in the same turn as an INDEPENDENT item refers only to the previous answer.
* Audit list. Code cannot fully check that rule, so build an audit list of forward-looking phrases: next, try, remember, make sure, don't forget, when you. The report lists every live reply before an INDEPENDENT item that contains one.

5. SLIP: pause, not reset

1. Code finds slip candidates. Each template defines slip signatures, answers whose structure shows the skill applied correctly with one lower-level arithmetic error. For example, for `a(x + b)`: the x-term correct, the constant wrong. For `a(x + b) = c`: the result of a correct step order with one arithmetic error.
2. The AI may propose a slip when code finds none. Code accepts it only if the template's structural check agrees. Otherwise the answer is treated as wrong under the existing rules.
3. The micro-check. On a confirmed slip, the AI asks one micro-question on the faulty sub-step, for example "Wait, what is 7 × 5?". Code checks the reply.
   * Correct: the AI invites a retry ("Okay, try it again.").
   * Wrong: this is evidence about the lower skill, and the existing wrong-answer path applies.
4. The run is paused. The slipped answer and its retry do not count, and the run is not reset. At most one pause per run; a second slip in the same run resets it.
5. Goal wording, wherever it appears: "3 correct without help (one slip may pause the count)". The report notes each slip as a slip.

6. TEACHING DECISIONS

1. Smaller steps. The AI proposes each guided step for this student's specific error. Each proposal has the question plus `step_expression`, a machine-evaluable expression whose value is the step's answer. Code:
   * evaluates it;
   * checks that every number in it comes from the item or earlier steps;
   * checks the question does not state its own answer.
If the proposal fails, the existing `SocraticPlanner` step is used.
2. Readiness. The AI decides whether to give another guided problem or move to the independent check, between a code minimum of 1 and a maximum of 4 guided problems per repair.
3. Errors code doesn't recognise. When no code signature matches, the AI chooses which prerequisite to check, from the declared prerequisites in scope that code supplies. Code accepts any listed choice. When a code signature matches, its branch stands.
4. Replace the rule that adopts model proposals only when they equal a host plan. Proposals are adopted when they pass the checks in this file.

7. Tests and gate (Stage 1, offline)

* All existing tests pass on PHP 8.0.30 and 8.3, updated only where this file changes behaviour.
* Regression tests for the owner's four live cases:
   * `9X + 8` on Expand 9(x + 8): read as `9x + 8`, wrong, with the distribution-to-x-only observation.
   * `16 because it's 4 x 4` on the guided total-of-4s step: read as 16, correct.
   * `4 x x` on the guided how-many-x step: read as 4x, correct, and the reply names the rule.
   * `7x + 25` on Expand 7(x + 5): slip, micro-check, retry, run paused not reset.
* Adversarial mock tests:
   * a reading that adds a number is rejected;
   * a reading that turns a wrong value right is rejected;
   * a proposed slip that the structure contradicts is rejected;
   * a step with invented numbers is rejected;
   * a reply leaking the answer is rejected;
   * a forward-looking reply before an INDEPENDENT item appears in the audit list;
   * a scored-item disagreement produces a clarifying question;
   * a second slip in a run resets it.
* Browser tests at 390px and 1280px, with screenshots of the four live cases in `test-results/browser/round6/`.
* Calls and latency. At most 2 provider calls per learner entry, with deterministic parsing first so the common case uses 1. Report calls per session in the mock journeys.

8. Stage 2: live run (needs a temporary key from the owner)
Run with `deepseek-flash` twice, once with thinking disabled and once with thinking enabled at low effort. Each run covers:

* 5 B1 sessions;
* 3 B3 sessions;
* B11 twice;
* a new N1 "natural learner" script built from the owner's four cases, plus answers written as sentences and arithmetic slips.

Report, per setting:

* HOLDs and repairs;
* every rejected AI output with its text and reason;
* clarifying questions asked;
* slips confirmed and slips rejected;
* the forward-looking audit list;
* calls per session;
* learner wait, median and slowest.

Attach every transcript. Do not choose the setting. Delete the key afterwards.
9. Bundle for side-by-side deployment
Build `dist/algebra-engine-ai-vps-upload.zip` for a separate install, so the beta stays live and untouched:

* public: `/var/www/html/educational-engines/algebra-engine-ai/`;
* private: `/var/www/algebra-engine-ai-private/`;
* base path: `/educational-engines/algebra-engine-ai/`.

It has its own database, so it can't conflict with the beta. Report its sha256. Do not deploy.
10. Report
Give the following, in order:

* the branch and tag confirmation;
* what changed, by section;
* test results on both PHP versions;
* the live metrics per setting;
* conflicts with this file;
* decisions needed.

Keep it short, and attach the transcripts.