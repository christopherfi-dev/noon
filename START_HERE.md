# Algebra Engine — Claude Code handoff v1.0

Prepared 2026-10-21. Architecture 1.1; curriculum ACE-ALGEBRA1 1.1.0.

## What this download is

An implementation-ready handoff: the researched curriculum, full constraint
architecture, explicit product and server decisions, contracts, fixtures,
acceptance scenarios, and build instructions. This is not a deployed app or a
completed production checker. The package preflight tests its own documents,
schemas, fixtures, and curriculum; it cannot establish a future application's
behavior or learning effectiveness.

## Give this to Claude Code

1. Unzip the complete folder into a local project directory.
2. Open that directory in Claude Code.
3. Paste the contents of `CLAUDE_CODE_START_PROMPT.md`.
4. Let Claude implement, run local tests, and prepare a Bluehost upload bundle.
5. Add the provider key privately in the generated server configuration when
   ready for a live-model smoke test. Mock mode must work without a key.

No existing Algebra app, student records, school account, or further curriculum
writing is required to start. Existing Math Skills PHP scripts are historical
examples, not implementation dependencies and are not included in this ZIP.
No personal resume, learner work, API key, or unrelated source is included.

## Read order

1. `CLAUDE.md` — implementation priorities and non-negotiable invariants.
2. `architecture/Algebra_Constraint_Architecture_v1.1.md` — operating contract.
3. `specifications/01_PRODUCT_AND_FLOW.md` — student/teacher experience.
4. `specifications/02_RUNTIME_AND_STATE.md` — host transaction semantics.
5. `specifications/03_API_AND_MODEL.md` — public API and private model envelope.
6. `specifications/04_MATH_AND_CAPABILITIES.md` — supported scoring boundaries.
7. Remaining specifications, contracts, examples, and acceptance scenarios.

## Ready now / still to implement

| Ready in this package | Claude Code must implement |
|---|---|
| 205 stable skill records and typed dependencies | Mobile-friendly student and task-setup screens |
| Research references, scope and sequence | PHP API, provider adapter and private storage |
| Explicit entry and repair rules | Orientation selector and curriculum loader |
| Machine contracts and representative payloads | Input parsing, mathematical checkers, item factories |
| Test expectations and offline preflight | Transactional state, evidence, reports and corrections |
| Deployment requirements | Automated application tests and deployment bundle |

The full continuum is the intended product scope. Implement in vertical stages
so each advertised capability is real. Do not describe curriculum availability
as 205 operationally assessed skills. See the release gates and capability plan.

## Existing package checks

From this folder: `python3 tools/validate_handoff.py`.
Then: `python3 curriculum/validate_curriculum.py` and
`python3 curriculum/test_validation_guards.py`.

The curriculum validators use Python's standard library. Their own reports
state the exact scope of mathematical checks. Run results for this delivery
are recorded in `HANDOFF_VALIDATION_REPORT.json`.
