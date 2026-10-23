#!/usr/bin/env bash
# F1: verify the package manifest. Ruled differences (MANIFEST.sha256 itself is never edited):
# - architecture/CHANGELOG.md: addendum §3 requires its edits;
# - TEACHER_FACILITATOR_GUIDE_DRAFT.md: updated to match the product (ruling on the round UI v3 report);
# - CLAUDE.md: rewritten for the round 6 design (CONDUCTOR_ROUND_6_v3.md §0.5);
# - architecture/Algebra_Constraint_Architecture_v1.3.md: new in round 6 (§7). It is not listed in
#   the manifest, so sha256sum -c never reports it; it is named here as ruled all the same (§0.6).
cd "$(dirname "$0")/../.." || exit 2
RULED=(architecture/CHANGELOG.md TEACHER_FACILITATOR_GUIDE_DRAFT.md CLAUDE.md architecture/Algebra_Constraint_Architecture_v1.3.md)
out="$(sha256sum -c MANIFEST.sha256 2>&1)"
echo "$out"
failed="$(echo "$out" | grep ': FAILED' | cut -d: -f1)"
args=()
for f in "${RULED[@]}"; do args+=(-e "$f"); done
unexpected="$(echo "$failed" | grep -v -x "${args[@]}" | grep -v '^$')"
echo "---"
echo "Differences: ${failed:-none}"
if [ -n "$unexpected" ]; then echo "UNEXPECTED MISMATCH: $unexpected"; exit 1; fi
echo "Only ruled differences (${RULED[*]})."
