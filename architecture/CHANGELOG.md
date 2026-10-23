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

## 1.3 — 2026-10-23 (Conductor Round 6, "The AI Tutors, Code Verifies")

Created as a copy of v1.2; v1.1 and v1.2 are unmodified. The governing line
is revised: the model interprets and teaches; the host verifies and records.
"The model proposes; the host accepts" now means the host accepts after the
checks the architecture lists, not only when a proposal equals a host plan.
Truth over approval and the exclusion of mastery claims are unchanged.

- Header: title "v1.3", revision date 2026-10-23, compatibility line.
- Section 3 (learner input): entries may mix words and mathematics; reading
  them is an interpretation the host checks.
- Section 4: a slip is never confirmed without a host structural check and a
  correct answer to the faulty sub-step; a model reading may not add a
  number or variable, change a checker result or turn a wrong value right.
- Section 5 (authority table): how an entry is read; whether an error was a slip.
- Section 10.C: with no matching host signature, the model may choose an
  in-scope declared prerequisite from the host's list.
- Section 10.D: model-proposed smaller steps (with a step expression) after
  the host's step checks; readiness between one and four guided problems.
- Section 10.E: one confirmed slip per run pauses the count instead of
  resetting it.
- Section 11: new subsection "Slips: a pause, not a reset"; learner-facing
  goal wording "3 correct without help (one slip may pause the count)".
- Section 13: replies may ask questions; envelope field `step_proposal`;
  separate INTERPRET and DECIDE JSON contracts; new subsection "Model
  operations and host checks (v1.3)" describing INTERPRET, RESPOND, SLIP and
  TEACHING DECISIONS and the checks that bind them; at most two
  instructional provider calls per learner entry, with repair bounded by
  that limit; the runtime rules that made the host write these moments are
  replaced (host-only re-asks, discarded text with independent items, host
  lines for questions, number words and requests for the answer, the
  no-question-mark rule).
- Section 18: the activation handshake is now
  `ACE_BOUND: architecture=1.3 | first_principle=truth_over_approval | scope=algebra | state=AWAITING_SESSION_CONTEXT`.

Runtime contracts (in `app/private/resources/contracts/`, the package's
`contracts/` folder is unchanged): `model-envelope.schema.json` takes
architecture_version 1.3 and the `step_proposal` field;
`interpretation.schema.json` and `teaching-decision.schema.json` are new;
`session.schema.json` and `report.schema.json` accept 1.2 (sessions and
reports created before the update) and 1.3.

The runtime binds this file and records its SHA-256 content hash with each
binding. Hash: `c538baa04aafd3b1bb363a0e4f9f94478a08e56b5f7668d350138a299a3eb410`.
Curriculum ACE-ALGEBRA1 1.1.0 is unchanged.
