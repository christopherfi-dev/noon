# Algebra 1 Curriculum Package v1.1

Research-informed author draft. Read CURRICULUM_GUIDE.md first.

## Main files

| File | Purpose |
|---|---|
| algebra-map.json | Canonical full curriculum resource for the future API |
| algebra-map.schema.json | Resource schema |
| prerequisite-graph.json | Typed dependency graph |
| skill-inventory.csv | Reviewable export; not a separate authority |
| CURRICULUM_GUIDE.md | Human-readable full skill map |
| SCOPE_AND_SEQUENCE.md | Proposed 36-week route and unit blueprints |
| unit-assessment-blueprints.json | Ten illustrative integrated checkpoints and answer expectations |
| TEACHING_AND_ASSESSMENT_GUIDE.md | Diagnostic use, conventions and rubric |
| standards-crosswalk.json / STANDARDS_CROSSWALK.md | Scoped standard contributions |
| curriculum-sources.json | Primary source registry |
| validation_report.json | Structural and explicit mathematical check results |
| validate_curriculum.py | Reusable audit; Python standard library only |
| test_validation_guards.py / validation_guard_report.json | Eight consequential corruption-rejection checks |
| change-log.json | Original IDs, additions and classification decisions |
| authoring/ | Rebuild source and source-code index |

## Validate

Run:

~~~bash
python validate_curriculum.py
python test_validation_guards.py
~~~

The audit uses the subset of JSON Schema keywords present in this package; it is not a general-purpose JSON Schema conformance suite. Production integration should use an appropriate schema validator and actual task-specific answer checkers.

## Rebuild

From this directory:

~~~bash
python authoring/build_curriculum.py
python authoring/build_supporting_files.py
python validate_curriculum.py
~~~

The uploaded original summary is not needed after original_skill_id_snapshot.json is created; the builder falls back to that retained snapshot.

## Suggested future server placement

Store algebra-map.json under the algebra engine's curriculum folder. The PHP loader should select a target record, relevant prerequisites and accepted session state. It should rebuild the active curriculum block rather than append stale inventories.

Do not treat a whole-map topological sort as a student's mandatory learning route.

## Design and evidence limits

All 185 prior IDs are preserved, with twenty new nodes. The prerequisite graph, pacing and diagnostic directions are authored designs informed by sources. They have not been empirically calibrated.

One illustrative anchor per node is included. This is not a complete operational item bank. The architecture permits fresh generation within the declared node bounds.

The three-independent-correct rule is an immediate criterion and does not establish durable mastery. Graph and reasoning tasks retain explicit capability and review requirements.

This package contains curriculum specifications and validation tools, not a deployed tutor, production answer checker or Noon-certified curriculum.
