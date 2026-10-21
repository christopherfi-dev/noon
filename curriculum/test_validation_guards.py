#!/usr/bin/env python3
"""Mutation checks for consequential curriculum-integrity failures."""
from pathlib import Path
import contextlib, importlib.util, io, json, shutil, tempfile

ROOT=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location("curriculum_validator",ROOT/"validate_curriculum.py")
validator=importlib.util.module_from_spec(spec);spec.loader.exec_module(validator)

def main():
 cases=[]
 def unknown(m,g):
  next(s for s in m["skills"] if s["skill_id"]=="N03")["prerequisites"]["all_of"].append("Z99")
 def cycle(m,g):
  next(s for s in m["skills"] if s["skill_id"]=="N20")["prerequisites"]["all_of"].append("N17")
  g["edges"].append({"from":"N17","to":"N20","type":"COMPONENT"})
 def wrong_answer(m,g):
  next(s for s in m["skills"] if s["skill_id"]=="N07")["assessment"]["anchor_item"]["expected_answer"]=99
 def rename(m,g):
  next(s for s in m["skills"] if s["skill_id"]=="Q07")["statement"]+=" and calculus"
 def optional_gate(m,g):
  next(s for s in m["skills"] if s["skill_id"]=="Q06")["prerequisites"]["all_of"].append("H08")
  g["edges"].append({"from":"H08","to":"Q06","type":"COMPONENT"})
 def stale_graph(m,g):g["edges"].pop()
 def unknown_code(m,g):
  next(s for s in m["skills"] if s["skill_id"]=="N03")["alignment"][0]["standard"]="HSA.FAKE.A.99"
 def bad_unit_key(m,g):
  next(u for u in m["units"] if u["unit_id"]=="04")["illustrative_unit_checkpoint"]["expected"]["equal_cost"]=43
 tests=[
  ("unknown_prerequisite_rejected",unknown,"Z99"),
  ("dependency_cycle_rejected",cycle,"Cycle or downstream"),
  ("wrong_mathematical_answer_rejected",wrong_answer,"Anchor math failed N07"),
  ("silent_original_identity_change_rejected",rename,"silently changed identity"),
  ("optional_extension_core_gate_rejected",optional_gate,"Q06"),
  ("stale_graph_export_rejected",stale_graph,"Exported graph does not match map"),
  ("unobserved_standard_code_rejected",unknown_code,"HSA.FAKE.A.99"),
  ("wrong_unit_checkpoint_key_rejected",bad_unit_key,"Unit 04 key mathematics")]
 for name,mutate,expected in tests:
  with tempfile.TemporaryDirectory(prefix="ace-curriculum-guard-") as td:
   target=Path(td)
   for filename in ["algebra-map.json","algebra-map.schema.json","prerequisite-graph.json","change-log.json"]:
    shutil.copy2(ROOT/filename,target/filename)
   (target/"authoring").mkdir()
   for filename in ["original_skill_id_snapshot.json","standards-source-index.json"]:
    shutil.copy2(ROOT/"authoring"/filename,target/"authoring"/filename)
   m=json.loads((target/"algebra-map.json").read_text());g=json.loads((target/"prerequisite-graph.json").read_text())
   mutate(m,g)
   (target/"algebra-map.json").write_text(json.dumps(m))
   (target/"prerequisite-graph.json").write_text(json.dumps(g))
   validator.ROOT=target
   try:
    with contextlib.redirect_stdout(io.StringIO()):validator.main()
   except AssertionError as e:
    assert expected in str(e),(name,str(e),expected)
    cases.append({"guard":name,"status":"PASS","outcome":"Deliberately corrupted curriculum was rejected at the expected boundary."})
   else:raise AssertionError(name+" was not rejected")
 validator.ROOT=ROOT
 report={"status":"PASS","mutation_guard_count":len(cases),"cases":cases,
         "scope":"Tests data-integrity and mathematical-key rejection paths, not learner outcomes or general grading correctness."}
 (ROOT/"validation_guard_report.json").write_text(json.dumps(report,indent=2)+"\n")
 print(json.dumps({"status":"PASS","mutation_guards":len(cases)}))
 return report

if __name__=="__main__":main()

