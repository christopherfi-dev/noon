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

The document's title line and status header still read "v1.1" because the
addendum permits only the three changes above. The runtime binds this file and
records its SHA-256 content hash with each binding. Curriculum ACE-ALGEBRA1
1.1.0 is unchanged and keeps the evidence semantics declared for 1.1.
