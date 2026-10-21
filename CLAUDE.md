# Build instructions

Build the Algebra Engine from this folder. Begin implementation; do not merely
produce another plan. Use the existing contracts and report material conflicts.
Keep this handoff and curriculum intact. Put the app under `app/`.

## Product and stack decisions

- Student routes: topic/problem, help finding a starting point, teacher task.
- Full 205-skill curriculum remains browsable. Runtime coverage is separately
  declared and tested; implement the full core in stages, not an invented scope.
- PHP 8.3+ source compatibility; prefer a supported 8.4/8.5 deployment if the
  actual host supports it. Vanilla JavaScript, CSS, HTML; same-origin API.
- No Node process, Python service, or database server required in production.
  Build/test tools may run locally. PDO SQLite private storage; fail preflight
  clearly if unavailable rather than silently using public JSON files.
- Account-free first deployment, ephemeral sessions and public task links;
  no invented teacher identity, private classroom dashboard, or cross-session
  access. Reports belong to the current browser session.
- Real provider adapter plus deterministic mock provider. Keys only on server.
- No raw model streaming into the UI. Host gates the whole response first.

## Do not weaken these invariants to make the demo pass

1. LLM proposes; host accepts items, evidence and state transitions.
2. API loads canonical records; LLM never reads server files.
3. Build each prompt anew. Old inventories do not accumulate.
4. Mathematical equivalence is not spelling similarity or a few substitutions.
5. Item acceptance checks the task and its answer, not just JSON shape.
6. A response verdict belongs to exact item version, answer hash and event.
7. Hints/solutions change independence before they are shown.
8. Interrupted parent targets survive prerequisite repair and topic changes.
9. Observation, diagnostic hypothesis and learning claim remain separate.
10. Three independent varied successes mean an immediate criterion only.
11. Inaccessible/unsupported tasks are not student failures or passes.
12. Public teacher-task settings cannot change policies or grant trust.
13. No client payload receives answer keys, raw envelopes or internal diagnoses.
14. Idempotent mutations, revision checks and provider-in-flight leases prevent
    double scoring. See transaction algorithm before writing endpoints.
15. Mock or unit-test success is never reported as live provider/deployment or
    learner effectiveness validation.

## Implementation practice

Read the package, then inspect the actual environment. Choose maintained
dependencies whose documented capabilities match the contracts. Pin local
dependencies and bundle frontend assets for upload. Do not assume a model name,
JSON-schema decoding feature, shell access, PHP extension, or Bluehost plan.

Implement mathematical parsers without eval, shell execution or unbounded
symbolic input. Fail closed for unsupported syntax. State unsupported coverage
openly instead of inventing a generic correct/incorrect judge.

Follow `specifications/07_BUILD_AND_RELEASE.md`. Complete a working vertical
journey before broadening, then continue through the agreed full-core scope
where feasible. If a milestone cannot be completed, keep the app runnable,
mark capability gaps and explain the exact remaining work. Do not mislabel a
partial implementation as the finished 205-skill tutor.

Default choices here are sufficient to begin. Ask the owner only for a material
decision that cannot be resolved from the files. Missing provider secrets do not
block mock implementation and testing. Do not publish to a live site until
deployment is explicitly requested. Prepare all local code and upload materials.
