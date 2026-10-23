#!/usr/bin/env bash
# F1: verify the package manifest. Accepted differences: architecture/CHANGELOG.md,
# which addendum §3 requires us to edit, and TEACHER_FACILITATOR_GUIDE_DRAFT.md,
# updated to match the product by the architect's ruling on the round UI v3 report.
cd "$(dirname "$0")/../.." || exit 2
out="$(sha256sum -c MANIFEST.sha256 2>&1)"
echo "$out"
failed="$(echo "$out" | grep ': FAILED' | cut -d: -f1)"
unexpected="$(echo "$failed" | grep -v -x -e 'architecture/CHANGELOG.md' -e 'TEACHER_FACILITATOR_GUIDE_DRAFT.md' | grep -v '^$')"
echo "---"
echo "Differences: ${failed:-none}"
if [ -n "$unexpected" ]; then echo "UNEXPECTED MISMATCH: $unexpected"; exit 1; fi
echo "Only expected differences (architecture/CHANGELOG.md: v1.2 entry added per addendum §3; TEACHER_FACILITATOR_GUIDE_DRAFT.md: updated per the architect's ruling on the round UI v3 report)."
