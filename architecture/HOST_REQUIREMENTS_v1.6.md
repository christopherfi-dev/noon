# Algebra Engine: host requirements (v1.6)

Code must meet these requirements. This file is never sent to the model; Claude Code builds and tests against it.

Each requirement below was moved word for word out of architecture v1.5 when v1.6 was made, because it addresses the host, not the tutor. Where the tutor needed to know the result, v1.6 states it as a plain fact.

## Opening paragraph

The host application enforces the guarantees stated here; a model-generated assertion of compliance is not an acceptance receipt.

## Section 5, opening

These are requirements on the host application, not facts established by this document. The implementation must declare which checks it actually performs and their scope. A requirement may be described as enforced only when implemented and tested.

## Section 5, item 4

**The gradebook is traceable and correctable.** For every attempt it records the problem, the skill, practice or check, the student's entry, any reading note, the scoped verdict and its status, and observed or reported help between presentation and answer. Unreported outside help cannot be certified absent. It also records the original target, any detours, whether the student returned, and the independent evidence on that original target. Recording these facts does not choose your teaching route. Corrections preserve the original record and recompute affected progress.

## Section 5, item 7

**Security, privacy and storage** are code's: revisions, idempotency, leases, no answer keys or internal data in anything the browser receives.

## Section 8, Practice problems

Before a practice question is scored, its wording must ask for the same operation, unknown, conditions and requested form as its verified spec, and must not reveal its answer. Merely including the spec's numbers does not verify that correspondence. The host must use an implemented task-wording acceptance route; where that correspondence cannot be established, use authoritative task wording rendered from the verified spec or an accepted prepared problem.

## Section 10, conflict

The host must suspend the affected verdict and dependent progress claims, exclude the disputed attempt from student success/failure counts pending review, and recheck the problem, its wording/spec correspondence, the reading and the checker result.

## Section 10, correction

If a correction is required, code appends it without erasing the earlier record, recomputes affected counts, goals and reports from the corrected evidence, and resumes from the corrected state. If the conflict cannot be resolved, retain the suspension and report the limit without counting it against the student.

## Section 11, acceptance

Code validates each response and applies the implemented problem-acceptance checks and observational text guards within their declared scope. A demonstrated conflict with a verdict follows Section 10. A problem or envelope that fails acceptance may produce a bounded repair request naming the exact failure; address it without changing anything unrelated. If the repaired problem or envelope still fails, code replaces that failed problem or envelope for the turn and records the replacement. Text guards produce no repair request and do not stop, rewrite or replace your text. Neither replacement nor structural acceptance certifies checks the implementation did not perform.

## Section 13, the report

Code keeps the record and writes the report, which separates: scoped in-session goals met under Section 5's policy, correct answers without observed or reported help, trouble spots and observed error patterns, practice with help, the original target and detours, whether the student returned and the independent evidence on that original target, and what remains untested or unresolved. Component goals must not read as whole-skill demonstration. Suspended or corrected verdicts and their effects on progress must be represented accurately.
