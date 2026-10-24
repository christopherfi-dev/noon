#!/usr/bin/env bash
# Runs every check and saves raw output to test-results/ with UTC times.
#   bash app/tools/run_all_tests.sh
set -u
APP="$(cd "$(dirname "$0")/.." && pwd)"
REPO="$(cd "$APP/.." && pwd)"
OUT="$REPO/test-results"
mkdir -p "$OUT"
SUMMARY="$OUT/SUMMARY.md"
echo "# Test run summary" > "$SUMMARY"
echo "" >> "$SUMMARY"
# Round PHP80: record the PHP that runs the gate. BUILD_PHP (default: php) runs only the bundle build,
# which packages files and needs the zip extension; every other PHP step uses \`php\` from PATH.
BUILD_PHP="${BUILD_PHP:-php}"
echo "PHP for every PHP step: \`$(php -v | head -1)\`" >> "$SUMMARY"
echo "" >> "$SUMMARY"
echo "PHP for the bundle build only: \`$("$BUILD_PHP" -v | head -1)\`" >> "$SUMMARY"
echo "" >> "$SUMMARY"
# The tracked-file list for the key-leak checks, written with this host's git (the PHP may run where git is absent).
ALGEBRA_TRACKED_FILES="$(mktemp /tmp/ae-tracked-XXXXXX)"
git -C "$REPO" ls-files -z > "$ALGEBRA_TRACKED_FILES"
export ALGEBRA_TRACKED_FILES
trap 'rm -f "$ALGEBRA_TRACKED_FILES"' EXIT
echo "| Step | Started (UTC) | Finished (UTC) | Exit | Output |" >> "$SUMMARY"
echo "|---|---|---|---|---|" >> "$SUMMARY"
overall=0
step() {
  local name="$1" file="$2"; shift 2
  local start end code
  start="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  ( "$@" ) > "$OUT/$file" 2>&1
  code=$?
  end="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  [ $code -ne 0 ] && overall=1
  echo "| $name | $start | $end | $code | \`$file\` |" >> "$SUMMARY"
  printf '%-34s exit=%s  %s\n' "$name" "$code" "$file"
}
cd "$REPO"
step "F1 manifest (before preflight)" 00-manifest-before.txt bash "$APP/tools/manifest_check.sh"
step "Handoff preflight" 01-handoff-preflight.txt python3 tools/validate_handoff.py
cp HANDOFF_PREFLIGHT_REPORT.json "$OUT/preflight-HANDOFF_PREFLIGHT_REPORT.regenerated.json"
git checkout -- HANDOFF_PREFLIGHT_REPORT.json 2>/dev/null
step "Curriculum validator" 02-curriculum-validate.txt python3 curriculum/validate_curriculum.py
step "Curriculum guard tests" 02-curriculum-guards.txt python3 curriculum/test_validation_guards.py
git checkout -- curriculum/validation_report.json curriculum/validation_guard_report.json 2>/dev/null
step "F1 manifest (after preflight)" 00-manifest-after.txt bash "$APP/tools/manifest_check.sh"
step "App preflight" 03-app-preflight.txt php "$APP/private/bin/preflight.php" --public="$APP/public"
cd "$APP"
step "PHPUnit unit + integration" 04-phpunit.txt php private/vendor/bin/phpunit --testdox --log-junit "$OUT/04-phpunit-junit.xml"
# Round 6 §9.5: calls per turn on scripted mock sessions (never a live config here).
step "Mock tutor sessions (calls per turn)" 04b-mock-sessions.txt env -u ALGEBRA_CONFIG php tools/tutor_sessions.php --out="$OUT/mock-sessions" --help-seeker=1 --breaker=1 --working=1 --help-on-check=1 --slips=1 --step=1 --r10-targeted=1 --r10-broad=1
step "Browser (conversation UI, 360/390/1280)" 05-browser.txt node tests/Browser/run_browser.mjs "$OUT/browser"
step "Bundle build (side-by-side VPS)" 06-bundle-build.txt "$BUILD_PHP" tools/build_bundle.php
step "Bundle smoke (deploy layout)" 07-bundle-smoke.txt php tools/bundle_smoke.php "$REPO/dist"
# After the bundle build, so the ZIP just built is scanned. Uses ALGEBRA_CONFIG for the configured key when set.
step "Key-leak scan (tracked files + ZIP)" 09-key-leak-scan.txt php tools/key_leak_scan.php
echo "" >> "$SUMMARY"
echo "Note: the F1 manifest check lists only ruled differences (architecture/CHANGELOG.md, TEACHER_FACILITATOR_GUIDE_DRAFT.md, CLAUDE.md, architecture/Algebra_Constraint_Architecture_v1.3.md, v1.5.md, v1.6.md, v1.7.md and v1.8.md, and architecture/HOST_REQUIREMENTS_v1.6.md and v1.7.md; round 6 §0.6, round 7 §2.5, rounds 8, 9 and 10 §2), and HANDOFF_PREFLIGHT_REPORT.json is restored after the preflight rewrites it; see BUILD_LOG.md." >> "$SUMMARY"
echo "" >> "$SUMMARY"
echo "Every result here is from mock mode (simulated tutor). Live runs need a key and are not part of this runner; the round 7, 8, 9 and 10 live runs are in \`test-results/live/round7/\`, \`round8/\`, \`round9/\` and \`round10/\` (\`app/tools/tutor_sessions.php\` with a live config)." >> "$SUMMARY"
exit $overall
