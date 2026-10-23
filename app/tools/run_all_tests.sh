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
step "Browser (B1 x2 widths, UI-01 to UI-07, R6-01 to R6-04)" 05-browser.txt node tests/Browser/run_browser.mjs "$OUT/browser"
# Round 6 (§7): the scripted journeys on the simulated model, for calls per session and per learner entry (exits 4 above 2).
MOCKCFG="$(mktemp /tmp/ae-mock-XXXXXX.php)"
echo "<?php return ['provider' => 'mock'];" > "$MOCKCFG"
rm -rf "$OUT/round6-mock-journeys"
step "Mock journeys: calls per session (B1 x5, B3 x3, B11 x2, N1 x2)" 05b-mock-journeys.txt env ALGEBRA_CONFIG="$MOCKCFG" php tools/live_sessions.php --out="$OUT/round6-mock-journeys" --b1=5 --b3=3 --b11=2 --n1=2
rm -f "$MOCKCFG"
step "Bundle build" 06-bundle-build.txt "$BUILD_PHP" tools/build_bundle.php
step "Bundle smoke (deploy layout)" 07-bundle-smoke.txt php tools/bundle_smoke.php "$REPO/dist/stage"
# After the bundle build, so the ZIP just built is scanned. Uses ALGEBRA_CONFIG for the configured key when set.
step "Key-leak scan (tracked files + ZIP)" 09-key-leak-scan.txt php tools/key_leak_scan.php
echo "" >> "$SUMMARY"
echo "Note: the F1 manifest check after preflight is expected to list architecture/CHANGELOG.md (edited per addendum §3) and TEACHER_FACILITATOR_GUIDE_DRAFT.md (updated per the architect's ruling on the round UI v3 report), and HANDOFF_PREFLIGHT_REPORT.json is restored after the preflight rewrites it; see BUILD_LOG.md." >> "$SUMMARY"
echo "" >> "$SUMMARY"
echo "The live provider smoke test needs a key and is not part of this runner. Its runs are recorded separately in \`08-live-smoke.txt\` (network and adapter compatibility only)." >> "$SUMMARY"
exit $overall
