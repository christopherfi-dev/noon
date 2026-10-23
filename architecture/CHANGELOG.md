# Architecture change log

## 1.1 — 2026-10-21

- Adds three explicit entry routes and host-controlled menu actions.
- Adds bounded starting-point assessment and recommendation states.
- Separates orientation evidence from target success criteria.
- Distinguishes public teacher-task settings from protected teacher authority.
- Specifies separate text-mode activation and later JSON instructional calls.
- Keeps the initial principles, permitted/excluded reality, mathematical and
  evidence limits, recursive repair, output contract and final binding handshake.

Curriculum ACE-ALGEBRA1 1.1.0 remains unchanged. Its compatibility declaration
names architecture 1.0; this handoff explicitly declares architecture 1.1
backward compatible with those curriculum/evidence semantics. Do not rewrite
the researched package just to change that metadata string.

## 1.2 — 2026-10-21 (Conductor Addendum Beta v1, decision D1)

Created as a copy of v1.1 with exactly three changes; v1.1 is unmodified.

- Section 3, "Instructional operations": removed "worked examples".
- Section 10.D GUIDED_REPAIR: the method list is replaced with: "Teach through
  questions. Each prompt asks for one learner action. A question may use a
  concrete or visual representation, equality reasoning, a contrast pair or a
  counterexample. When the learner is stuck, ask a smaller question that
  isolates the current step. Never present a worked example, a complete
  solution, or the active item's final answer."
- Section 18: the activation handshake is now
  `ACE_BOUND: architecture=1.2 | first_principle=truth_over_approval | scope=algebra | state=AWAITING_SESSION_CONTEXT`.

Conductor Round 2 rulings 2 and 3 (same version, 1.2):

- Title line: "v1.1" → "v1.2". Status header, Compatibility line: now reads
  "v1.2. Retains v1.0 evidence and curriculum semantics and the v1.1 entry and
  orientation modes; guided repair teaches through questions only."
- Section 10.D: deleted "Use concise explanations when they bridge an actual
  conceptual gap." (line 568).
- Learner-facing output: removed "a concise explanation, " from the list of
  permitted learner-facing content (line 723).

The runtime binds this file and records its SHA-256 content hash with each
binding. Hash after round 2:
`ef03667dabf7152be63009ec6c1e327a83340b4fff65eef303e3eeb326291390`
(beta hash was `8997b6b88067ebfae26abcbe84beb4b6cdcfc55091a9e86128fc020eb5cca772`).
Curriculum ACE-ALGEBRA1 1.1.0 is unchanged and keeps the evidence semantics
declared for 1.1.

## 1.3 — 2026-10-23 (Conductor Round 6, §7: the AI is the tutor)

Created from v1.2 with its role sections rewritten for the round 6 design; v1.1
and v1.2 are unmodified.

- The AI runs the tutoring session as a conversation and decides every move
  (explain, ask, pose a problem, hint, check a slip, step back, move on,
  review, wrap up). Code is its set of instruments: calculator and answer
  checker, problem checker, problem generator, gradebook with goals, report.
- Removed: the host-sequenced workflow states and their transition table
  (v1.2 Sections 7 and 10), the menu-driven entry routes, the item-record
  envelope in which the model only proposed template parameters, and the rule
  that learner text may not contain a question.
- Added Section 5, "What code guarantees": code computes every answer and
  every verdict; code alone computes goals; check problems stand alone (code
  wording, distinct card, text before it refers only to the previous answer,
  conversation pauses); the gradebook is true; code writes the report; guards
  on every learner-visible AI text; security, privacy and storage stay code's.
- Added readings of student entries (Section 7), problem specs and check
  patterns (Section 8), slips confirmed by code with a micro-question and a
  retry that never counts, and the tutor envelope (Section 11).
- Kept the principles, including truth over approval, scoped verification
  over self-certification, coherence through correction, and no mastery
  claims (now stated as its own principle).
- No example responses: behaviour is described in words only.
- Section 16: the activation handshake is now
  `ACE_BOUND: architecture=1.3 | first_principle=truth_over_approval | scope=algebra | role=tutor`.

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

## 1.6 — 2026-10-23

Governing architecture for the AI tutor; replaces 1.5 at runtime. The handshake is
`architecture=1.6`. The reasons for each change are in
`ARCHITECTURE_CHANGES_v1.6_PROPOSED.md`, as approved by the owner.

### Changes to what the tutor does

- **New §2 principle, Recorded Help > Hidden Help.**
  - When a student asks for help on an open check, the tutor helps, starting with the
    smallest step. "I don't know how" counts as a request, in any wording.
  - A student who has an answer but is unsure of it is invited to enter it.
  - Help on a check is always recorded: the tutor marks its move as help, and the
    check becomes practice. The check's answer is never given.
- **§5 item 3, check problems.**
  - One handover sentence before a check card is allowed.
  - Help with an open check now follows Recorded Help, no longer "I'm stuck" only.
- **§4 help claims.** Describing practice or finished work as independent is excluded.
  Inviting the student to try a check on their own is allowed.
- **§4 and §7, values in text.** A question in the tutor's text that asks for a number,
  expression or equation is posed as a problem with a spec, so code checks the answer.
- **§7, correct-answer feedback.** The tutor says the answer is correct, restates it,
  and names the general rule in one sentence. It does not work the problem again: no
  intermediate step appears unless the student wrote it.
- **§8, check problems.** A check problem whose answer is a single number never
  contains that number or its opposite. This is a local design rule for the ten skills.
  Practice problems may use any numbers.
- **§11, proposed transition.** A help move on an open check makes that check practice;
  any other move changes nothing by itself.
- **§14, self-check.** Items 4, 5 and 6 are updated, and a new item 7 asks whether help
  was marked and whether value questions were posed as problems.

### Text moved out of the architecture

Nine passages were addressed to the host, not the tutor. They were moved word for word
to `HOST_REQUIREMENTS_v1.6.md`:

- the opening sentence on host enforcement;
- the opening of §5;
- §5 item 4 (gradebook);
- §5 item 7 (security);
- the practice-wording acceptance route in §8;
- the host suspension procedure in §10;
- the correction procedure in §10;
- the acceptance procedure in §11;
- the report specification in §13.

Where the tutor needs to know the result, v1.6 states it as one plain fact:

- "Code checks every answer and every problem you pose."
- "Code writes the displayed wording of every problem from its verified spec, and your
  wording of a problem is not shown."
- "Code then holds that attempt out of the counts until it is reviewed."
- "A problem or envelope that fails acceptance may come back to you as a repair request
  naming the exact failure; fix exactly that and change nothing else."

Run A showed why this matters. Because §5 opened by calling itself "requirements on the
host application", the tutor treated the check rule written inside §5 as code's
business.
