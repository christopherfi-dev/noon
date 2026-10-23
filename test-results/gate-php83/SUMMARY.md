# Test run summary

PHP for every PHP step: `PHP 8.3.6 (cli) (built: Oct  7 2026 18:06:38) (NTS)`

PHP for the bundle build only: `PHP 8.3.6 (cli) (built: Oct  7 2026 18:06:38) (NTS)`

| Step | Started (UTC) | Finished (UTC) | Exit | Output |
|---|---|---|---|---|
| F1 manifest (before preflight) | 2026-10-23T04:03:23Z | 2026-10-23T04:03:23Z | 0 | `00-manifest-before.txt` |
| Handoff preflight | 2026-10-23T04:03:23Z | 2026-10-23T04:03:23Z | 0 | `01-handoff-preflight.txt` |
| Curriculum validator | 2026-10-23T04:03:23Z | 2026-10-23T04:03:23Z | 0 | `02-curriculum-validate.txt` |
| Curriculum guard tests | 2026-10-23T04:03:23Z | 2026-10-23T04:03:23Z | 0 | `02-curriculum-guards.txt` |
| F1 manifest (after preflight) | 2026-10-23T04:03:23Z | 2026-10-23T04:03:23Z | 0 | `00-manifest-after.txt` |
| App preflight | 2026-10-23T04:03:23Z | 2026-10-23T04:03:23Z | 0 | `03-app-preflight.txt` |
| PHPUnit unit + integration | 2026-10-23T04:03:23Z | 2026-10-23T04:04:06Z | 0 | `04-phpunit.txt` |
| Browser (B1 x2 widths, UI-01 to UI-07, R6-01 to R6-04) | 2026-10-23T04:04:06Z | 2026-10-23T04:04:37Z | 0 | `05-browser.txt` |
| Mock journeys: calls per session (B1 x5, B3 x3, B11 x2, N1 x2) | 2026-10-23T04:04:37Z | 2026-10-23T04:04:39Z | 0 | `05b-mock-journeys.txt` |
| Bundle build | 2026-10-23T04:04:39Z | 2026-10-23T04:04:41Z | 0 | `06-bundle-build.txt` |
| Bundle smoke (deploy layout) | 2026-10-23T04:04:41Z | 2026-10-23T04:04:42Z | 0 | `07-bundle-smoke.txt` |
| Key-leak scan (tracked files + ZIP) | 2026-10-23T04:04:42Z | 2026-10-23T04:04:42Z | 0 | `09-key-leak-scan.txt` |

Note: the F1 manifest check after preflight is expected to list architecture/CHANGELOG.md (edited per addendum §3) and TEACHER_FACILITATOR_GUIDE_DRAFT.md (updated per the architect's ruling on the round UI v3 report), and HANDOFF_PREFLIGHT_REPORT.json is restored after the preflight rewrites it; see BUILD_LOG.md.

The live provider smoke test needs a key and is not part of this runner. Its runs are recorded separately in `08-live-smoke.txt` (network and adapter compatibility only).
