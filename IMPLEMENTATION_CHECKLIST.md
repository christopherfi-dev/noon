# Implementation checklist

This tracks work Claude Code must do. Unchecked boxes are deliberately not
presented as implemented features.

- [ ] Environment preflight; PHP, extensions, local server and dependencies.
- [ ] Public/private folder separation and private configuration.
- [ ] SQLite migrations, event ledger, immutable item versions, snapshots.
- [ ] Same-origin session ownership, CSRF, revision and idempotency checks.
- [ ] Provider leases, cancellation and late-result rejection.
- [ ] Three entry routes, canonical topic search and target confirmation.
- [ ] Bounded orientation and provisional recommendation.
- [ ] Public signed teacher-task settings without privileged identity.
- [ ] Curriculum repository with conditional/alternative edge handling.
- [ ] Rebuilt prompt context; exact text binding then JSON envelope calls.
- [ ] Mock provider using the real host acceptance path.
- [ ] Real provider adapter, private key setup and bounded failure handling.
- [ ] Safe mathematical parsers and independently derived item keys.
- [ ] Item semantic/skill acceptance and supported response checking.
- [ ] Hint exposure and item freshness tracked before evidence promotion.
- [ ] Recursive repair, parent return and episode interruption/resume.
- [ ] Component/whole-skill distinction and variant coverage enforcement.
- [ ] Report export reconstructed from accepted evidence.
- [ ] Capability matrix for all 205 nodes, backed by actual tests.
- [ ] Application tests for the 53 acceptance scenarios.
- [ ] Mobile/desktop browser review and safe rendering checks.
- [ ] Expiry, forgetting, cleanup, private pilot access and spend limits.
- [ ] Deployable public/private upload ZIP and rollback instructions.
- [ ] Live provider smoke test when a key is configured.
- [ ] Teacher content review and learner pilot before effectiveness claims.

The handoff package's own preflight is separate. Its passing status must not
be used to check off these application implementation tasks.
