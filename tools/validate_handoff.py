#!/usr/bin/env python3
"""Offline handoff preflight, not an application test or production validator.

Standard library only. Implements exactly the schema keywords used by the
handoff contracts; fails if an unimplemented assertion keyword is introduced.
The future PHP app must use a maintained production JSON Schema validator plus
the semantic gates described in the specification.
"""
from pathlib import Path
from fractions import Fraction
import ast
import copy
import hashlib
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
CONTRACTS = ROOT / 'contracts'
KEYWORDS = {'$schema','$ref','type','const','enum','anyOf','properties','required',
            'additionalProperties','items','maxItems','maxProperties','minLength',
            'maxLength','pattern','minimum','maximum'}

def load(path):
    return json.loads(path.read_text(encoding='utf-8'))

def validate(value, rule, location='$'):
    unknown=set(rule)-KEYWORDS
    assert not unknown,(location,'unsupported preflight schema keywords',unknown)
    if '$ref' in rule:
        ref=rule['$ref']
        assert '/' not in ref and '#' not in ref and ref.endswith('.schema.json'),ref
        validate(value,load(CONTRACTS/ref),location)
    if 'anyOf' in rule:
        errors=[]
        for sub in rule['anyOf']:
            try:
                validate(value,sub,location)
                break
            except AssertionError as exc:
                errors.append(str(exc))
        else:
            raise AssertionError((location,'no anyOf alternative',errors))
    if 'type' in rule:
        kind=rule['type']
        valid={'null':value is None,'string':isinstance(value,str),
               'integer':type(value) is int,'number':type(value) in (int,float),
               'boolean':type(value) is bool,'object':isinstance(value,dict),
               'array':isinstance(value,list)}
        assert valid[kind],(location,'type',kind)
    if 'const' in rule:assert type(value) is type(rule['const']) and value==rule['const'],(location,'const')
    if 'enum' in rule:assert value in rule['enum'],(location,'enum')
    if isinstance(value,dict):
        for key in rule.get('required',[]):assert key in value,(location,'missing',key)
        props=rule.get('properties',{})
        if rule.get('additionalProperties') is False:assert set(value)<=set(props),(location,'extra properties',set(value)-set(props))
        if 'maxProperties' in rule:assert len(value)<=rule['maxProperties'],location
        for key,val in value.items():
            if key in props:validate(val,props[key],location+'.'+key)
    if isinstance(value,list):
        if 'maxItems' in rule:assert len(value)<=rule['maxItems'],location
        if 'items' in rule:
            for i,val in enumerate(value):validate(val,rule['items'],f'{location}[{i}]')
    if isinstance(value,str):
        if 'minLength' in rule:assert len(value)>=rule['minLength'],location
        if 'maxLength' in rule:assert len(value)<=rule['maxLength'],location
        if 'pattern' in rule:assert re.search(rule['pattern'],value),location
    if type(value) in (int,float):
        if 'minimum' in rule:assert value>=rule['minimum'],location
        if 'maximum' in rule:assert value<=rule['maximum'],location

def event_semantics(event):
    spec=next(a for a in load(CONTRACTS/'public-actions.json')['actions'] if a['action']==event['action'])
    assert set(spec['required_payload'])<=set(event['payload']), 'required action payload'
    assert set(event['payload'])<=set(spec['allowed_payload']), 'disallowed action payload'
    if spec['requires_active_item']:
        assert event['item_id'] is not None and event['item_version'] is not None,'item reference required'
    else:
        assert event['item_id'] is None and event['item_version'] is None,'unexpected item reference'

def envelope_semantics(e):
    if e['message_type']=='QUESTION':
        assert e['item'] is not None,'QUESTION item required'
    else:assert e['item'] is None,'item outside QUESTION'
    if e['message_type']=='REPORT':assert e['teacher_report'] is not None,'REPORT needs report'
    else:assert e['teacher_report'] is None,'report outside REPORT'
    if any(e[k] is None for k in ['session_id','state_revision','reply_to_event_id']):
        assert e['message_type'] in ['CONTROL','HOLD'] and e['item'] is None and e['proposed_transition'] is None and not e['evidence_proposals'],'missing identity restriction'

def rational(text):
    # Narrow reference fixture oracle. Not a deployed learner input parser.
    assert isinstance(text,str) and len(text)<=80,'unsupported rational fixture'
    assert re.fullmatch(r'[+-]?(?:\d+(?:\.\d+)?|\d+/[+-]?\d+)',text),'unsupported rational syntax'
    return Fraction(text)

def polynomial(text):
    """Bounded exact polynomial fixture oracle; no division/TeX/multivariates."""
    assert len(text)<=200,'polynomial length'
    tree=ast.parse(text,mode='eval')
    assert len(list(ast.walk(tree)))<=80,'polynomial nodes'
    def add(a,b):
        c=a.copy()
        for k,v in b.items():c[k]=c.get(k,Fraction(0))+v
        return {k:v for k,v in c.items() if v}
    def mul(a,b):
        c={}
        for ka,va in a.items():
            for kb,vb in b.items():
                assert ka+kb<=8,'polynomial degree'
                c[ka+kb]=c.get(ka+kb,Fraction(0))+va*vb
        return {k:v for k,v in c.items() if v}
    def walk(n):
        if isinstance(n,ast.Constant) and type(n.value) is int:
            assert abs(n.value)<=10**6,'coefficient bound'
            return {0:Fraction(n.value)} if n.value else {}
        if isinstance(n,ast.Name) and n.id=='x':return {1:Fraction(1)}
        if isinstance(n,ast.UnaryOp) and isinstance(n.op,(ast.USub,ast.UAdd)):
            a=walk(n.operand)
            return {k:(-v if isinstance(n.op,ast.USub) else v) for k,v in a.items()}
        if isinstance(n,ast.BinOp):
            a=walk(n.left)
            if isinstance(n.op,ast.Pow):
                assert isinstance(n.right,ast.Constant) and type(n.right.value) is int and 0<=n.right.value<=8,'power'
                c={0:Fraction(1)}
                for _ in range(n.right.value):c=mul(c,a)
                return c
            b=walk(n.right)
            if isinstance(n.op,ast.Add):return add(a,b)
            if isinstance(n.op,ast.Sub):return add(a,{k:-v for k,v in b.items()})
            if isinstance(n.op,ast.Mult):return mul(a,b)
        raise AssertionError('unsupported polynomial syntax')
    return walk(tree.body)

def golden_verdict(case):
    if case['kind']=='rational':
        try:return 'CORRECT' if rational(case['response'])==rational(case['expected']) else 'INCORRECT'
        except (AssertionError,ValueError,ZeroDivisionError):return 'UNSCORABLE'
    if case['kind']=='polynomial':return 'CORRECT' if polynomial(case['response'])==polynomial(case['expected']) else 'INCORRECT'
    if case['kind']=='solution_set' and case['task']=='x^2=9':
        actual=set(case['response']);expected={-3,3}
        return 'CORRECT' if actual==expected else 'PARTIAL' if actual and actual<expected else 'INCORRECT'
    if case['id']=='ROOT-03':return 'CORRECT' if rational(case['response'])==3 else 'INCORRECT'
    return None  # Semantic/domain/form/graph cases remain future app requirements.

def main():
    checks=[]
    required=['START_HERE.md','CLAUDE.md','CLAUDE_CODE_START_PROMPT.md','handoff-manifest.json',
              'architecture/Algebra_Constraint_Architecture_v1.1.md',
              *[f'specifications/{name}' for name in ['01_PRODUCT_AND_FLOW.md','02_RUNTIME_AND_STATE.md','03_API_AND_MODEL.md','04_MATH_AND_CAPABILITIES.md','05_STORAGE_AND_DEPLOYMENT.md','06_REPORTS_AND_EVIDENCE.md','07_BUILD_AND_RELEASE.md']],
              'curriculum/algebra-map.json','curriculum/algebra-map.schema.json','curriculum/validate_curriculum.py','curriculum/test_validation_guards.py']
    for name in required:assert (ROOT/name).is_file(),name
    checks.append({'check':'required_files','count':len(required),'status':'PASS'})
    json_files=list(ROOT.rglob('*.json'))
    for p in json_files:load(p)
    checks.append({'check':'json_syntax','count':len(json_files),'status':'PASS'})
    mapping={'private-item':'item','model-question':'model-envelope','public-event':'public-event',
             'public-view':'public-view','private-session':'session','teacher-task':'teacher-task',
             'checker-result':'checker-result','session-report':'report'}
    for sample,kind in mapping.items():validate(load(ROOT/f'examples/{sample}.json'),load(CONTRACTS/f'{kind}.schema.json'))
    event_semantics(load(ROOT/'examples/public-event.json'))
    envelope_semantics(load(ROOT/'examples/model-question.json'))
    checks.append({'check':'schema_examples_and_semantic_invariants','count':len(mapping),'status':'PASS','scope':'Used-keyword subset and named cross-field checks; not general JSON Schema certification.'})

    m=load(ROOT/'curriculum/algebra-map.json');index={s['skill_id']:s for s in m['skills']}
    assert len(index)==205 and len(m['domains'])==17
    baseline=load(CONTRACTS/'runtime-capability-baseline.json')['skills']
    assert {s['skill_id'] for s in baseline}==set(index)
    assert all(s['runtime_status']=='UNAVAILABLE' and not s['test_references'] for s in baseline)
    for row in baseline:
        assert row['required_checker_capabilities']==index[row['skill_id']]['assessment']['required_checker_capabilities']
    catalog=load(ROOT/'examples/item-catalog.json')['templates'];templates={x['template_id']:x for x in catalog}
    assert len(templates)==len(catalog)==10
    for row in catalog:assert row['primary_skill_id'] in index
    entry=load(ROOT/'examples/entry-probes.json')
    for row in entry['seeds']:
        assert row['skill_id'] in index and row['template_id'] in templates
        assert all(s in index for s in row['on_correct']+row['on_incorrect'])
    assert 'E06' in index['Q07']['prerequisites']['all_of']
    checks.append({'check':'canonical_mapping_and_capability_baseline','skills':205,'templates':10,'status':'PASS'})

    template_checks=[]
    for t in catalog:
        p={k:Fraction(v) for k,v in t['example_parameters'].items()};tid=t['template_id']
        if tid=='POSITIVE_DISTRIBUTION':
            assert polynomial(t['example_expected'])=={1:p['a'],0:p['a']*p['b']}
        else:
            expected={
              'SIGNED_ADD':lambda:p['a']+p['b'],'SIGNED_SUBTRACT':lambda:p['a']-p['b'],
              'SIGNED_MULTIPLY':lambda:p['a']*p['b'],'SIGNED_DIVIDE':lambda:p['a']/p['b'],
              'ONE_STEP_ADD':lambda:p['b']-p['a'],'ONE_STEP_MULTIPLY':lambda:p['b']/p['a'],
              'TWO_STEP':lambda:(p['c']-p['b'])/p['a'],
              'GROUPED_LINEAR':lambda:p['c']/p['a']-p['b'],
              'BOTH_SIDES_LINEAR':lambda:(p['d']-p['b'])/(p['a']-p['c'])}[tid]()
            assert rational(t['example_expected'])==expected,(tid,expected)
        template_checks.append(tid)
    goldens=load(ROOT/'examples/checker-golden-cases.json')['cases'];math_checked=[];not_checked=[]
    for c in goldens:
        verdict=golden_verdict(c)
        if verdict is None:not_checked.append(c['id'])
        else:
            assert verdict==c['verdict'],c['id']
            math_checked.append(c['id'])
    checks.append({'check':'template_example_mathematics','count':len(template_checks),'status':'PASS'})
    checks.append({'check':'narrow_reference_fixture_mathematics','checked':math_checked,'not_automatically_checked':not_checked,'status':'PASS'})

    scenarios=load(CONTRACTS/'acceptance-scenarios.json')['scenarios'];ids=[x['id'] for x in scenarios]
    assert len(ids)==len(set(ids)) and len(ids)>=50
    for c in scenarios:assert c['setup_or_action'] and c['expected']
    checks.append({'check':'future_application_scenarios_well_formed','count':len(ids),'status':'PASS','application_scenarios_executed':False})
    arch=(ROOT/'architecture/Algebra_Constraint_Architecture_v1.1.md').read_text()
    handshake='ACE_BOUND: architecture=1.1 | first_principle=truth_over_approval | scope=algebra | state=AWAITING_SESSION_CONTEXT'
    assert arch.count(handshake)==1 and 'architecture=1.0 |' not in arch
    assert arch.index('## 18. BINDING HANDSHAKE')>arch.index('## 17. CORE TRUTH')
    assert all(x in arch for x in ['## 2. INITIAL OPERATIONAL REALITY PRINCIPLES','## 4. WHAT DOES NOT EXIST IN THIS REALITY','STARTING_POINT_ASSESSMENT','STARTING_POINT_RECOMMENDATION'])
    policy=load(CONTRACTS/'workflow-policy.json')
    assert policy['evidence_promotion']['durable_mastery_from_streak'] is False
    checks.append({'check':'architecture_entry_states_principles_exclusions_handshake','status':'PASS'})
    view=load(ROOT/'examples/public-view.json')
    forbidden={'answer_spec','expected','rubric','response_sha256','evidence_proposals','hypotheses','teacher_report','lease_token'}
    def no_private(v):
        if isinstance(v,dict):
            assert not set(v)&forbidden,('private public-view field',set(v)&forbidden)
            for s in v.values():no_private(s)
        elif isinstance(v,list):
            for s in v:no_private(s)
    no_private(view)
    checks.append({'check':'sample_public_projection_excludes_private_fields','status':'PASS','scope':'Sample only; future endpoint requires integration tests.'})

    guards=[]
    def rejected(name,fn):
        try:fn()
        except (AssertionError,ZeroDivisionError,ValueError,SyntaxError):guards.append(name)
        else:raise AssertionError('mutation not rejected: '+name)
    e=load(ROOT/'examples/model-question.json');e['item']=None
    rejected('question_without_item',lambda:envelope_semantics(e))
    e2=load(ROOT/'examples/model-question.json');e2['state_revision']=None
    rejected('scored_question_without_identity',lambda:envelope_semantics(e2))
    event=load(ROOT/'examples/public-event.json');event['payload']['success_count']=3
    rejected('client_score_injection',lambda:validate(event,load(CONTRACTS/'public-event.schema.json')))
    event2=load(ROOT/'examples/public-event.json');event2['payload']={}
    rejected('missing_action_answer',lambda:event_semantics(event2))
    event3=load(ROOT/'examples/public-event.json');event3['item_id']=None
    rejected('missing_active_item_reference',lambda:event_semantics(event3))
    task=load(ROOT/'examples/teacher-task.json');task['policy']={'passed':True}
    rejected('public_task_policy_injection',lambda:validate(task,load(CONTRACTS/'teacher-task.schema.json')))
    bad=copy.deepcopy(goldens[0]);bad['verdict']='INCORRECT'
    def wrong_golden():assert golden_verdict(bad)==bad['verdict']
    rejected('wrong_fixture_key',wrong_golden)
    rejected('polynomial_code_execution',lambda:polynomial('__import__("os").system("true")'))
    rejected('excessive_polynomial_exponent',lambda:polynomial('x**999999'))
    checks.append({'check':'preflight_corruption_rejection_guards','count':len(guards),'guards':guards,'status':'PASS','scope':'Handoff/preflight checks, not future app enforcement.'})
    report={'status':'PASS','delivery_kind':'IMPLEMENTATION_HANDOFF_NOT_COMPLETED_APPLICATION','checks':checks,
            'not_tested':['Future frontend/API/controller behavior','Production answer checkers','Live model call or provider credentials','Actual Bluehost deployment','Student usability, diagnostic validity or learning outcomes','General Draft 2020-12 schema conformance'],
            'curriculum_checks':'Run the two supplied curriculum validators separately; delivery run results are included in HANDOFF_VALIDATION_REPORT.json.'}
    (ROOT/'HANDOFF_PREFLIGHT_REPORT.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps({'status':'PASS','check_groups':len(checks),'schemas_exercised':len(mapping),'template_examples':len(template_checks),'goldens_mathematically_checked':len(math_checked),'goldens_semantic_requirements_only':len(not_checked),'preflight_mutation_guards':len(guards),'future_app_scenarios':len(scenarios)}))
    return report

if __name__=='__main__':
    try:main()
    except Exception as exc:
        print('HANDOFF PREFLIGHT FAILED:',exc,file=sys.stderr)
        raise
