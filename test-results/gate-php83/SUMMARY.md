# Test run summary

PHP for every PHP step: `PHP 8.3.6 (cli) (built: Oct  7 2026 18:06:38) (NTS)`

PHP for the bundle build only: `PHP 8.3.6 (cli) (built: Oct  7 2026 18:06:38) (NTS)`

| Step | Started (UTC) | Finished (UTC) | Exit | Output |
|---|---|---|---|---|
| F1 manifest (before preflight) | 2026-10-23T04:35:12Z | 2026-10-23T04:35:12Z | 0 | `00-manifest-before.txt` |
| Handoff preflight | 2026-10-23T04:35:12Z | 2026-10-23T04:35:13Z | 0 | `01-handoff-preflight.txt` |
| Curriculum validator | 2026-10-23T04:35:13Z | 2026-10-23T04:35:13Z | 0 | `02-curriculum-validate.txt` |
| Curriculum guard tests | 2026-10-23T04:35:13Z | 2026-10-23T04:35:13Z | 0 | `02-curriculum-guards.txt` |
| F1 manifest (after preflight) | 2026-10-23T04:35:13Z | 2026-10-23T04:35:13Z | 0 | `00-manifest-after.txt` |
| App preflight | 2026-10-23T04:35:13Z | 2026-10-23T04:35:13Z | 0 | `03-app-preflight.txt` |
| PHPUnit unit + integration | 2026-10-23T04:35:13Z | 2026-10-23T04:35:22Z | 0 | `04-phpunit.txt` |
| Mock tutor sessions (calls per turn) | 2026-10-23T04:35:22Z | 2026-10-23T04:35:23Z | 0 | `04b-mock-sessions.txt` |
| Browser (conversation UI, 360/390/1280) | 2026-10-23T04:35:23Z | 2026-10-23T04:35:40Z | 0 | `05-browser.txt` |
| Bundle build (side-by-side VPS) | 2026-10-23T04:35:40Z | 2026-10-23T04:35:41Z | 0 | `06-bundle-build.txt` |
| Bundle smoke (deploy layout) | 2026-10-23T04:35:41Z | 2026-10-23T04:35:42Z | 0 | `07-bundle-smoke.txt` |
| Key-leak scan (tracked files + ZIP) | 2026-10-23T04:35:42Z | 2026-10-23T04:35:42Z | 0 | `09-key-leak-scan.txt` |

Note: the F1 manifest check lists only ruled differences (architecture/CHANGELOG.md, TEACHER_FACILITATOR_GUIDE_DRAFT.md, CLAUDE.md and architecture/Algebra_Constraint_Architecture_v1.3.md; round 6 §0.6), and HANDOFF_PREFLIGHT_REPORT.json is restored after the preflight rewrites it; see BUILD_LOG.md.

Every result here is from mock mode (simulated tutor). Live runs need a key and are not part of this runner; the round 6 live runs are Stage 2 (`app/tools/tutor_sessions.php` with a live config).
