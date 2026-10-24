#!/usr/bin/env bash
# F1: verify the package manifest. Ruled differences (MANIFEST.sha256 itself is never edited):
# - architecture/CHANGELOG.md: addendum §3 requires its edits (round 7 appends the 1.5 entry, §2.4;
#   round 8 appends the 1.6 entry, CONDUCTOR_ROUND_8.md §2.3; round 9 the 1.7 entry, CONDUCTOR_ROUND_9.md §2.3; round 10 the 1.8 entry,
#   CONDUCTOR_ROUND_10.md §2.2);
# - TEACHER_FACILITATOR_GUIDE_DRAFT.md: updated to match the product (ruling on the round UI v3 report);
# - CLAUDE.md: rewritten for the round 6 design (CONDUCTOR_ROUND_6_v3.md §0.5), then for v1.5
#   (CONDUCTOR_ROUND_7.md §2.6), then for v1.6 (CONDUCTOR_ROUND_8.md §2), then for v1.7 (CONDUCTOR_ROUND_9.md §2.4),
#   then for v1.8 (CONDUCTOR_ROUND_10.md §2.4);
# - architecture/Algebra_Constraint_Architecture_v1.3.md: new in round 6 (§7), kept as history;
# - architecture/Algebra_Constraint_Architecture_v1.5.md: the governing architecture of round 7
#   (§2.1), kept as history from round 8;
# - architecture/Algebra_Constraint_Architecture_v1.6.md: the governing architecture of round 8, kept as history;
# - architecture/Algebra_Constraint_Architecture_v1.7.md: the governing architecture of round 9, kept as history;
# - architecture/Algebra_Constraint_Architecture_v1.8.md: the governing architecture from round 10 (§2.1),
#   saved byte-identical to the owner's file;
# - architecture/HOST_REQUIREMENTS_v1.6.md and v1.7.md: the owner's files from rounds 8 and 9; never sent to the model.
# The architecture files and the host requirements are not listed in the manifest, so sha256sum -c never
# reports them; they are named here as ruled all the same (round 6 §0.6, round 7 §2.5, round 8 §2).
cd "$(dirname "$0")/../.." || exit 2
RULED=(architecture/CHANGELOG.md TEACHER_FACILITATOR_GUIDE_DRAFT.md CLAUDE.md architecture/Algebra_Constraint_Architecture_v1.3.md architecture/Algebra_Constraint_Architecture_v1.5.md
       architecture/Algebra_Constraint_Architecture_v1.6.md architecture/HOST_REQUIREMENTS_v1.6.md
       architecture/Algebra_Constraint_Architecture_v1.7.md architecture/HOST_REQUIREMENTS_v1.7.md
       architecture/Algebra_Constraint_Architecture_v1.8.md)
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
