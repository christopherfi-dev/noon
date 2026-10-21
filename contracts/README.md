# Contract usage

These files specify interfaces the future application must implement. JSON
Schema handles structure; it does not express every trust or semantic rule.

## Required semantic checks after structural validation

- A QUESTION requires an item; other envelope types require item=null.
- A REPORT requires teacher_report; other types require teacher_report=null.
- Missing session/revision/event identity is allowed only for non-scoring
  CONTROL/HOLD responses with no evidence promotion or state transition.
- Every identity must match exact current host context and allocation.
- Per-action payload keys/item requirements come from public-actions.json.
- Canonical skill IDs and versions must exist and be permitted in context.
- Item role, template parameters, domain and requested performance must match
  the accepted target/component and actual implemented checker capabilities.
- Host determines item freshness, support, independence and accepted status.
- State changes must satisfy workflow-policy and its evidence preconditions.
- Checker results are produced by the server and bound to exact item/event/
  response identity, never accepted from the public client or model envelope.
- Public projection excludes private keys, answer specs, hidden rubric/diagnoses
  and raw control data. Validate outgoing projections, not just incoming data.
- Report claims must have actual evidence references and bounded review scope.

Initial public start body: idempotency_key, expected_revision, entry_route
(TOPIC/UNSURE/TEACHER_TASK), target_skill_id|null, task_token|null,
replace_current:boolean. Bootstrap first creates an anonymous browser container
and CSRF token. With no instructional session, revision is zero. TOPIC without
a target opens selection; UNSURE opens orientation; TEACHER_TASK needs a valid
public task token. Existing session replacement must be explicit. Scope start
idempotency to that browser container, so a lost start response cannot create
two sessions. Reject unrecognized fields and conflicting route fields.

create_task accepts only target_skill_id, title, instructions, maximum_minutes
and an idempotency_key; the host supplies version/timestamps/signature. It does
not advance instructional state. forget requires CSRF and idempotency, deletes
the current session, and expires credentials. State/report reads do not mutate
learning evidence. Future endpoint schemas should encode these action-specific
requirements in the application as well as documenting them.

runtime-capability-baseline.json starts entirely UNAVAILABLE because this ZIP
does not implement an app. Claude must create a tested actual registry and
explicit remaining coverage. The curriculum's requirements are not an inventory
of installed runtime functions.

The included preflight validates the subset of schema keywords present here
and named semantic invariants. Use a maintained full JSON Schema validator in
production and register relative $ref resources locally. Disable arbitrary
network schema resolution and runtime schema changes from untrusted input.
