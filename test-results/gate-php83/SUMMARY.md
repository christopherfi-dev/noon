# Test run summary

PHP for every PHP step: `PHP 8.3.6 (cli) (built: Oct  7 2026 18:06:38) (NTS)`

PHP for the bundle build only: `PHP 8.3.6 (cli) (built: Oct  7 2026 18:06:38) (NTS)`

| Step | Started (UTC) | Finished (UTC) | Exit | Output |
|---|---|---|---|---|
| F1 manifest (before preflight) | 2026-10-24T06:49:24Z | 2026-10-24T06:49:24Z | 0 | `00-manifest-before.txt` |
| Handoff preflight | 2026-10-24T06:49:24Z | 2026-10-24T06:49:25Z | 0 | `01-handoff-preflight.txt` |
| Curriculum validator | 2026-10-24T06:49:25Z | 2026-10-24T06:49:25Z | 0 | `02-curriculum-validate.txt` |
| Curriculum guard tests | 2026-10-24T06:49:25Z | 2026-10-24T06:49:25Z | 0 | `02-curriculum-guards.txt` |
| F1 manifest (after preflight) | 2026-10-24T06:49:25Z | 2026-10-24T06:49:25Z | 0 | `00-manifest-after.txt` |
| App preflight | 2026-10-24T06:49:25Z | 2026-10-24T06:49:25Z | 0 | `03-app-preflight.txt` |
| PHPUnit unit + integration | 2026-10-24T06:49:25Z | 2026-10-24T06:49:43Z | 0 | `04-phpunit.txt` |
| Mock tutor sessions (calls per turn) | 2026-10-24T06:49:43Z | 2026-10-24T06:49:51Z | 0 | `04b-mock-sessions.txt` |
| Browser (conversation UI, 360/390/1280) | 2026-10-24T06:49:51Z | 2026-10-24T06:50:12Z | 0 | `05-browser.txt` |
| Bundle build (side-by-side VPS) | 2026-10-24T06:50:12Z | 2026-10-24T06:50:14Z | 0 | `06-bundle-build.txt` |
| Bundle smoke (deploy layout) | 2026-10-24T06:50:14Z | 2026-10-24T06:50:15Z | 0 | `07-bundle-smoke.txt` |
| Key-leak scan (tracked files + ZIP) | 2026-10-24T06:50:15Z | 2026-10-24T06:50:15Z | 0 | `09-key-leak-scan.txt` |

Note: the F1 manifest check lists only ruled differences (architecture/CHANGELOG.md, TEACHER_FACILITATOR_GUIDE_DRAFT.md, CLAUDE.md, architecture/Algebra_Constraint_Architecture_v1.3.md, v1.5.md, v1.6.md, v1.7.md and v1.8.md, and architecture/HOST_REQUIREMENTS_v1.6.md and v1.7.md; round 6 §0.6, round 7 §2.5, rounds 8, 9 and 10 §2), and HANDOFF_PREFLIGHT_REPORT.json is restored after the preflight rewrites it; see BUILD_LOG.md.

Every result here is from mock mode (simulated tutor). Live runs need a key and are not part of this runner; the round 7, 8, 9 and 10 live runs are in `test-results/live/round7/`, `round8/`, `round9/` and `round10/` (`app/tools/tutor_sessions.php` with a live config).
