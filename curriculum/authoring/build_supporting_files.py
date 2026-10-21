#!/usr/bin/env python3
"""Build schema and human-readable documents. Standard library only."""
from pathlib import Path
import json
from collections import Counter,defaultdict

ROOT=Path(__file__).resolve().parents[1]
m=json.loads((ROOT/"algebra-map.json").read_text())
skills=m["skills"]
index={s["skill_id"]:s for s in skills}
sid={"type":"string","pattern":"^[A-Z][0-9]{2}$"}
ids={"type":"array","items":sid,"uniqueItems":True}
text={"type":"string","minLength":1}
obj={"type":"object"}
def object_schema(props,required=None,extra=False):
 return {"type":"object","properties":props,"required":list(props) if required is None else required,"additionalProperties":extra}
schema={
 "$schema":"https://json-schema.org/draft/2020-12/schema",
 "$id":"urn:ace:algebra-curriculum:1.0","title":"ACE algebra curriculum map",
 "description":"Resource schema, not a learner-response schema or a certification of curriculum validity.",
 "type":"object","required":["metadata","reference_profile","policies","sources","domains","units","flex_review_weeks","skills"],
 "additionalProperties":False,
 "properties":{
  "metadata":object_schema({"curriculum_id":text,"curriculum_version":text,"schema_version":text,
    "architecture_compatibility":text,"title":text,"research_date":text,"language":text,"scope":text,
    "review_status":{"enum":["RESEARCH_INFORMED_AUTHOR_DRAFT","EXTERNALLY_REVIEWED","EMPIRICALLY_CALIBRATED"]},
    "original_skill_ids_preserved":{"type":"integer"},"new_skill_ids":ids,"skill_count":{"type":"integer"},
    "domain_count":{"type":"integer"},"role_counts":obj,"coverage_claim":text,
    "not_claimed":{"type":"array","items":text},"prerequisite_graph_status":text,"runtime_warning":text}),
  "reference_profile":obj,"policies":obj,
  "sources":{"type":"array","minItems":1,"items":{"type":"object","required":["source_id","title","url","publisher","accessed","source_type","use"]}},
  "domains":{"type":"array","minItems":1,"items":{"type":"object","required":["domain_id","name"]}},
  "units":{"type":"array","minItems":1,"items":{"type":"object","required":["unit_id","title","planning_weeks","primary_skill_ids","assessment_blueprint"]}},
  "flex_review_weeks":{"type":"integer"},
  "skills":{"type":"array","minItems":1,"items":{"$ref":"#/$defs/skill"}}},
 "$defs":{}}
schema["$defs"]["skill"]=object_schema({
 "skill_id":sid,"statement":text,"domain_id":{"enum":[d["domain_id"] for d in m["domains"]]},
 "domain_name":text,"curricular_role":{"enum":["FOUNDATION","CORE","EXTENSION"]},
 "required_in_reference_profile":{"type":"boolean"},
 "first_instructional_unit":{"enum":["ON_DEMAND","OPTIONAL"]+[u["unit_id"] for u in m["units"]]},
 "added_in_version":text,"identity_note":text,"observable_performance":text,
 "scope":object_schema({"included":text,"guardrail":text,"language_level":text}),
 "prerequisites":object_schema({
  "all_of":ids,
  "alternative_paths":{"type":"array","items":object_schema({"path_id":text,"all_of":ids})},
  "conditional":{"type":"array","items":object_schema({"when":text,"requires":ids,"rationale":text,"status":text})},
  "edge_status":text,"routing_semantics":text}),
 "out_of_scope_redirects":{"type":"array","items":object_schema({"when":text,"target_skill_id":sid,"reason":text})},
 "alignment":{"type":"array","minItems":1,"items":object_schema({"standard":text,
   "relation":{"enum":["PREREQUISITE_COMPONENT","CONTRIBUTION","RELATED_EXTENSION"]},
   "source_id":text,"scope_note":text})},
 "assessment":{"type":"object","required":["required_variants","constructed_performance","conceptual_evidence",
   "transfer_requirement","accepted_response_types","required_checker_capabilities","progress_policy_ref","anchor_item"],
   "properties":{"anchor_item":{"type":"object","required":["item_id","primary_skill_id","role","prompt","response_type","expected_answer","additional_prerequisites_for_this_anchor","answer_review_status","coverage_limit"],
    "properties":{"item_id":text,"primary_skill_id":sid,"prompt":text,
      "expected_answer":{"anyOf":[{"type":"string"},{"type":"number"},{"type":"boolean"},{"type":"array"},{"type":"object"}]},
      "additional_prerequisites_for_this_anchor":ids}}}},
 "diagnostic_guidance":{"type":"object","required":["status","probe_focus","interpretation_rule"]},
 "instructional_guidance":{"type":"object","required":["bridge","method_freedom","repair_return","research_basis","status"]},
 "implementation_requirements":object_schema({"graph_evidence_required":{"type":"boolean"},
   "reasoning_review_required":{"type":"boolean"},"production_scoring_implemented":{"type":"boolean"}})})
(ROOT/"algebra-map.schema.json").write_text(json.dumps(schema,ensure_ascii=False,indent=2)+"\n")

def answer(v):
 return json.dumps(v,ensure_ascii=False) if not isinstance(v,str) else v
def code(t):return chr(96)+str(t).replace(chr(96),"'")+chr(96)
def prose(t):return str(t).replace("*",chr(92)+"*").replace("_",chr(92)+"_")
def esc(t):return str(t).replace("|","/").replace("\n"," ")
counts=Counter(s["curricular_role"] for s in skills)
new=[s for s in skills if s["added_in_version"]=="1.1.0"]
scope_lines=[
 "# Algebra 1 — Scope and Sequence v1.1",
 "",
 "Research-informed author draft · 20 October 2026",
 "",
 "This is an original 36-week planning sequence for the declared reference profile. It is not a fixed intervention dosage or a claim that every school assigns identical content to Algebra 1.",
 "",
 "The scope uses official Common Core content pages and Illustrative Mathematics Algebra 1 as references. The unit order and pacing below are author decisions. Numerical foundations are accessed from target evidence, not taught as a compulsory opening block.",
 "",
 "| Unit | Focus | Planning weeks | Core skill IDs |",
 "|---|---|---:|---|"]
for u in m["units"]:
 scope_lines.append("| "+u["unit_id"]+" | "+u["title"]+" | "+str(u["planning_weeks"])+" | "+(", ".join(u["primary_skill_ids"]) or "Revisit: "+", ".join(u["revisit_skill_ids"]))+" |")
scope_lines += ["| Flex | Review, assessment, consolidation and local adjustments | 3 | Selected from current evidence |",
 "", "Total: 33 proposed unit weeks plus 3 flexible weeks.",
 "", "## How this sequence interacts with the graph",
 "",
 "Course order is a planning route. The dependency graph is a set of authored component relationships for diagnosis. Neither requires testing every earlier node before asking a target-level question.",
 "",
 "A learner can enter at an appropriate target, follow a relevant prerequisite branch and return. Cross-cutting modeling and justification begin early and recur; their full-scope records are not a reason to postpone all contextual work to Unit 10.",
 "",
 "Primary unit assignment is the first planned introduction. Full-scope evidence may be completed through later revisits as new function families and required components become available. Advanced variants remain inactive until their named components and checker capabilities are available.",
 ""]
for u in m["units"]:
 scope_lines += ["## Unit "+u["unit_id"]+": "+u["title"],"",u["learning_goal"],"",
 "Entry: "+u["assessment_blueprint"]["entry"],"",
 "Formative: "+u["assessment_blueprint"]["formative"],"",
 "Independent: "+u["assessment_blueprint"]["independent"],"",
 "Transfer: "+u["assessment_blueprint"]["transfer"],"",
 "Illustrative checkpoint: "+prose(u["illustrative_unit_checkpoint"]["task"]),"",
 "Answer expectations: "+code(json.dumps(u["illustrative_unit_checkpoint"]["expected"],ensure_ascii=False)),"",
 "Mandatory evidence: "+"; ".join(u["illustrative_unit_checkpoint"]["mandatory_evidence"]),"",
 "Teacher check: apply the actual required variants in the linked skill records. An anchor or illustrative checkpoint is not a complete calibrated unit assessment.",
 ""]
(ROOT/"SCOPE_AND_SEQUENCE.md").write_text("\n".join(scope_lines)+"\n")

teacher = """# Teaching and Assessment Guide v1.1

## Status and use

This curriculum is designed for a constrained algebra engine and for teacher review. Its scope, questions, diagnostic directions and prerequisite edges are authored design work informed by the cited sources. They are not empirically calibrated diagnostic findings.

The architecture governs behavior. The curriculum supplies the domain. The eventual API supplies files, accepted state and declared verification results. This package does not implement a production grader or graph interface.

## Begin with the learner's target

Use a representative target task first. Ask prerequisite questions when the response evidence makes them relevant. Do not make a student complete all numerical foundations, every previous unit or an entire transitive dependency closure.

The graph labels have different meanings:

- all_of: component skills used by the declared performance, not an instruction to pretest every component.
- alternative_paths: a selected legitimate method route; the learner does not need to use all methods on each item.
- conditional: requirements of a particular item variant.
- out_of_scope_redirects: another target's territory, not a prerequisite loop.

The displayed topological order is for structural integrity checking. It is not a prescribed learner itinerary.

## Use the model's instructional freedom

The LLM may derive fresh examples, connect representations, use an area model, explain an equality, choose a counterexample or compare methods within the node's bounds.

The practice-guide references support analyzing solved work, attending to algebraic structure and choosing strategies intentionally. They do not validate a rigid script or prove this product's effectiveness.

Use concise language and correct terminology with explanations. Reduce unnecessary reading demand without reducing the algebraic target.

## Separate the observation from the explanation

Record the learner's response and available work first. Then state any reproducible relationship between the prompt and answer.

A diagnostic explanation is a hypothesis. Two wrong responses may support a pattern while still allowing different causes. A correct similar probe shows that the earlier error was not reproduced; it does not prove carelessness or a slip.

Do not score an inability to use an unsupported input format as mathematical failure.

## Example diagnostic route: factorable quadratics

Suppose the current target is U08 and an answer to x^2+x-12=0 is incorrect.

Possible next checks depend on the actual response:

- If the factorization itself is suspect, F03 can distinguish the product/sum conditions before F04 is retaught.
- If factor pairs are available but expansion is wrong, P06 can expose the cross-term issue.
- If a signed sum fails in the factor-pair check, probe N03.
- If the factors are correct but only one zero is returned, probe F09 rather than assuming a multiplication gap.

These are conditional possibilities, not a diagnosis of a particular learner.

After the relevant repair, return to a fresh U08 task, such as x^2-7x+10=0, with roots 2 and 5. A later contextual transfer could ask for the positive width w of a rectangle satisfying w(w+3)=28; the feasible width is 4, while -7 is rejected as a length. Do not reveal these answers before collecting independent work.

## Immediate success, transfer and retention

The default immediate criterion is three consecutive correct independent accepted items covering appropriate variation. This is an engine policy, not a research-established threshold for durable mastery.

Hints, an already revealed solution, an invalid question or an unsupported grading route cannot count toward an independent success streak.

Transfer means a relevant change in structure, representation or context. Retention requires later independent evidence and a retained event time. Store all three dimensions separately.

## A practical rubric

Apply only dimensions that the item actually elicits. Do not mark an irrelevant dimension as zero.

| Dimension | 0 | 1 | 2 |
|---|---|---|---|
| Mathematical performance | Unsupported or incorrect result/method | Substantial correct work with a bounded error or omission | Correct required result and method |
| Justification | Missing or invalid when required | Partly valid but a necessary link is missing | Valid explanation of the required relationship |
| Representation and context | Inconsistent quantities or representation | Mostly consistent with a specific omission | Appropriate representation, interpretation and units |
| Conditions and completeness | Required restrictions or solutions missing | Some conditions handled | Domain, precision, endpoints and full answers handled |

A numerical answer alone cannot complete a task requiring a graph, model or explanation. A score total must not conceal a failure on a mandatory condition. Report the dimension evidence.

## Mathematical conventions

- Real numbers are the default core domain.
- A square-root symbol denotes the principal root; solving x^2=k may require two roots.
- Original denominator restrictions survive simplification.
- An equivalent expression can still fail a requested-form requirement.
- Interval endpoints, unions and intersections are mathematical content.
- Variables in context require defined quantities and units.
- Arithmetic and geometric sequence indexing is explicit.
- Population standard deviation is used in core examples.
- Quartiles use medians of halves, excluding the overall median for odd-sized data, unless an item declares another convention.
- Regression and graph tasks require declared technology or an appropriate reviewed representation.
- Correlation is neither causation nor a percent-correct score.

## Capability-aware deployment

| Task | Required implementation or review |
|---|---|
| Exact number or fraction | Exact arithmetic or a declared tolerance |
| Algebraic expression | Equivalence on the stated domain plus requested-form checking |
| Equation or solution set | Appropriate solution semantics, completeness and domain control |
| Interval or inequality | Endpoint, direction, union and intersection semantics |
| Graph | Actual accessible rendering/input and a graph rubric |
| Explanation or model | A bounded reasoning/model rubric and declared review status |

The API must not turn every nonnumeric response into a fuzzy string comparison. The mathematical obligations in validate_curriculum.py check examples in this authored package; they are not a general student-answer scoring service.

## Review before classroom claims

Check the source-to-task alignment, linguistic accessibility, graph requirements and local course expectations. Pilot diagnostic contrasts with learners before treating a response pattern as a reliable classifier.

Collect item difficulty, unexpected valid responses, common notation variants and evidence of retention. Revise the map through versioned changes; preserve old IDs and evidence provenance.

Local course coverage may differ. Full logarithmic solving, normal-curve inference, trigonometry and a complete Algebra 2 course are outside the declared core.
"""
(ROOT/"TEACHING_AND_ASSESSMENT_GUIDE.md").write_text(teacher)

guide=[
 "# Algebra 1 Curriculum and Prerequisite Map v1.1","",
 "Research-informed author draft · 20 October 2026","",
 "## What is built","",
 f"- {len(skills)} identified skills across {len(m['domains'])} strands.",
 f"- {counts['CORE']} core targets, {counts['FOUNDATION']} on-demand foundation skills and {counts['EXTENSION']} optional extension skills.",
 "- All 185 original IDs are retained. Twenty new IDs make missing performances or foundations explicit.",
 "- Each node contains scope, observable performance, prerequisite links, assessment variants, an original anchor question and answer expectation, diagnostic guidance and checker requirements.",
 "- The original summary's referenced prerequisite JSON/CSV files were not supplied. The edges here are newly authored and labeled accordingly.",
 "",
 "The package is a complete authored map for its declared reference profile. Its standards crosswalk and sequence are design judgments; they do not establish empirical diagnostic validity, local approval, Noon-specific alignment or NWEA calibration.",
 "",
 "## Reference scope","",
 "The reference course includes expression structure; equations and inequalities; functions and representations; linear and exponential models; systems; polynomial structure and factoring; quadratics; descriptive statistics; modeling and mathematical communication.",
 "",
 "Core function work includes basic root graphs, simple linear inverses, familiar-function transformations and function comparison. Broader composition, transformations, rational algebra, complex roots and logarithms are explicitly optional.",
 "",
 "## Research basis","",
 "The Common Core content pages define the referenced mathematical content. Illustrative Mathematics Algebra 1 is a published scope comparator, not the source of the unit order or anchor questions. The IES practice guide informs solved-work analysis, structural reasoning and deliberate method choice. Its first two recommendations carry minimal evidence ratings and the strategy-choice recommendation carries a moderate rating.",
 "",
 "The source pages do not establish this exact prerequisite graph or a three-correct mastery threshold. Those remain design choices requiring testing.",
 "",
 "## Additions to the original 185-node skeleton","",
 "| New ID | Explicit performance or foundation |",
 "|---|---|"]
for s in new:guide.append("| "+s["skill_id"]+" | "+s["statement"]+" |")
guide += [
 "", "Display-only normalization removes escaped math formatting from A05, L05, U07 and G02. It does not change their mathematical identities.",
 "",
 "The graph uses component, alternative-method and conditional links. Core tasks never require an unconditional optional-extension dependency. A hard or conditional edge does not license a causal diagnosis.",
 "",
 "## Planning sequence","",
 "The original planning route is 33 unit weeks plus 3 flexible review/assessment weeks. Pacing is adjustable.",
 "",
 "| Unit | Focus | Weeks |",
 "|---|---|---:|"]
for u in m["units"]:guide.append("| "+u["unit_id"]+" | "+u["title"]+" | "+str(u["planning_weeks"])+" |")
guide += ["| Flex | Review, assessment and local adjustments | 3 |","",
 "See SCOPE_AND_SEQUENCE.md for unit assessment blueprints and TEACHING_AND_ASSESSMENT_GUIDE.md for diagnostic use and rubric guidance.",
 "",
 "## Reading a skill record","",
 "The anchor is a teacher-facing illustration and answer expectation. It is not an independently calibrated test item. Required variants, conceptual evidence and transfer tasks must still be authored or generated and accepted before the node's full scope is assessed.",
 "",
 "Machine-readable records distinguish a declared checker requirement from an implemented checker. The graph and files can be integrated into the future PHP loader; the current package does not provide that API.",
 ""]
for d in m["domains"]:
 members=[s for s in skills if s["domain_id"]==d["domain_id"]]
 guide += ["## Strand "+d["domain_id"]+": "+d["name"],"",
           f"{len(members)} skills. Foundation and extension roles are explicit for each record.",""]
 for s in members:
  a=s["assessment"]["anchor_item"];p=s["prerequisites"]
  guide += ["### "+s["skill_id"]+" — "+s["statement"],"",
            "**Role:** "+s["curricular_role"]+". **First planned unit:** "+s["first_instructional_unit"]+".","",
            "**Performance:** "+s["observable_performance"],"",
            "**Bounds:** "+s["scope"]["included"],"",
            "**Direct components:** "+(", ".join(p["all_of"]) if p["all_of"] else "No earlier node required within this map's declared floor.") ]
  if p["alternative_paths"]:
   guide+=["","**Alternative methods:** "+"; ".join(x["path_id"]+": "+", ".join(x["all_of"]) for x in p["alternative_paths"])]
  if p["conditional"]:
   guide+=["","**Conditional requirements:** "+"; ".join(x["when"]+" → "+", ".join(x["requires"]) for x in p["conditional"])]
  if s["out_of_scope_redirects"]:
   guide+=["","**Redirect:** "+"; ".join(x["when"]+" → "+x["target_skill_id"] for x in s["out_of_scope_redirects"])]
  guide += ["","**Anchor:** "+prose(a["prompt"]),"",
            "**Answer expectation:** "+code(answer(a["expected_answer"])),"",
            "**Diagnostic contrast:** "+s["diagnostic_guidance"]["probe_focus"],"",
            "**Required variants:** "+"; ".join(s["assessment"]["required_variants"]),"",
            "**Reference contributions:** "+", ".join(x["standard"] for x in s["alignment"]),""]
guide += ["## Verification and limits","",
 "Run python validate_curriculum.py from this directory. The report checks identities, references, graph cycles, role boundaries, official code presence and explicit anchor mathematical obligations. It does not validate the empirical learning sequence or independently establish every item's semantics.",
 "",
 "The separate report lists which anchors have automated mathematical checks. Remaining reasoning, interpretation and graph expectations are author-reviewed and require appropriate independent review before production claims.",
 "",
 "## Sources",""]
for s in m["sources"]:guide += ["- **"+s["source_id"]+"** — ["+s["title"]+"]("+s["url"]+") · accessed "+s["accessed"]+". "+s["use"]]
(ROOT/"CURRICULUM_GUIDE.md").write_text("\n".join(guide)+"\n")

cross=json.loads((ROOT/"standards-crosswalk.json").read_text())
lines=["# Scoped Standards Crosswalk v1.1","",
 "Each row lists authored contributions, not one-node-to-one-standard equivalence or a claim that an anchor alone covers the standard.","",
 "| Standard | Core contributors | Foundation/extension contributors | Scope boundary |","|---|---|---|---|"]
for row in cross["standards"]:
 core=[x["skill_id"] for x in row["contributions"] if x["role"]=="CORE"]
 other=[x["skill_id"]+" ("+x["role"]+")" for x in row["contributions"] if x["role"]!="CORE"]
 lines.append("| "+row["standard"]+" | "+", ".join(core)+" | "+", ".join(other)+" | "+esc(row["scope_note"])+" |")
(ROOT/"STANDARDS_CROSSWALK.md").write_text("\n".join(lines)+"\n")

readme="""# Algebra 1 Curriculum Package v1.1

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
"""
(ROOT/"README.md").write_text(readme)
print(json.dumps({"schema":"written","guide_words":len("\n".join(guide).split()),"documents":"written"}))

