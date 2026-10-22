# Test run summary

| Step | Started (UTC) | Finished (UTC) | Exit | Output |
|---|---|---|---|---|
| F1 manifest (before preflight) | 2026-10-22T09:53:50Z | 2026-10-22T09:53:50Z | 0 | `00-manifest-before.txt` |
| Handoff preflight | 2026-10-22T09:53:50Z | 2026-10-22T09:53:50Z | 0 | `01-handoff-preflight.txt` |
| Curriculum validator | 2026-10-22T09:53:50Z | 2026-10-22T09:53:50Z | 0 | `02-curriculum-validate.txt` |
| Curriculum guard tests | 2026-10-22T09:53:50Z | 2026-10-22T09:53:50Z | 0 | `02-curriculum-guards.txt` |
| F1 manifest (after preflight) | 2026-10-22T09:53:51Z | 2026-10-22T09:53:51Z | 0 | `00-manifest-after.txt` |
| App preflight | 2026-10-22T09:53:51Z | 2026-10-22T09:53:51Z | 0 | `03-app-preflight.txt` |
| PHPUnit unit + integration | 2026-10-22T09:53:51Z | 2026-10-22T09:54:37Z | 0 | `04-phpunit.txt` |
| Browser (B1 x2 widths, UI-01, UI-02, UI-03) | 2026-10-22T09:54:37Z | 2026-10-22T09:54:49Z | 0 | `05-browser.txt` |
| Bundle build | 2026-10-22T09:54:49Z | 2026-10-22T09:54:52Z | 0 | `06-bundle-build.txt` |
| Bundle smoke (deploy layout) | 2026-10-22T09:54:52Z | 2026-10-22T09:54:52Z | 0 | `07-bundle-smoke.txt` |
| Key-leak scan (tracked files + ZIP) | 2026-10-22T09:54:52Z | 2026-10-22T09:54:53Z | 0 | `09-key-leak-scan.txt` |

Note: the F1 manifest check after preflight is expected to list architecture/CHANGELOG.md (edited per addendum §3) and HANDOFF_PREFLIGHT_REPORT.json is restored after the preflight rewrites it; see BUILD_LOG.md.

The live provider smoke test needs a key and is not part of this runner. Its runs are recorded separately in `08-live-smoke.txt` (network and adapter compatibility only).
