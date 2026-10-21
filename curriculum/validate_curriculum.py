#!/usr/bin/env python3
"""Audit this curriculum, its source codes, dependency graph and explicit anchor mathematics.
Standard library only. This is not a production student-answer checker."""
from pathlib import Path
from fractions import Fraction as F
from collections import Counter, defaultdict, deque
from decimal import Decimal, ROUND_HALF_UP
import ast, json, math, re, sys

ROOT=Path(__file__).resolve().parent

def clean(p):
 return {k:F(v) for k,v in p.items() if v}
def pa(p,q):
 d=dict(p)
 for k,v in q.items():d[k]=d.get(k,F(0))+v
 return clean(d)
def pn(p):return {k:-v for k,v in p.items()}
def pm(p,q):
 d=defaultdict(F)
 for a,av in p.items():
  for b,bv in q.items():
   powers=defaultdict(int)
   for var,n in a+b:powers[var]+=n
   key=tuple(sorted((var,n) for var,n in powers.items() if n))
   d[key]+=av*bv
 return clean(d)
def pp(p,n):
 r={():F(1)}
 for _ in range(n):r=pm(r,p)
 return r

class RF:
 def __init__(self,n=0,d=None):
  self.n=clean(n if isinstance(n,dict) else {():F(n)})
  self.d=clean(d if isinstance(d,dict) else {():F(1) if d is None else F(d)})
  if not self.d:raise ZeroDivisionError()
 @staticmethod
 def var(name):return RF({((name,1),):F(1)})
 def __add__(self,o):
  o=asrf(o);return RF(pa(pm(self.n,o.d),pm(o.n,self.d)),pm(self.d,o.d))
 __radd__=__add__
 def __neg__(self):return RF(pn(self.n),self.d)
 def __sub__(self,o):return self+-asrf(o)
 def __rsub__(self,o):return asrf(o)+-self
 def __mul__(self,o):
  o=asrf(o);return RF(pm(self.n,o.n),pm(self.d,o.d))
 __rmul__=__mul__
 def __truediv__(self,o):
  o=asrf(o);return RF(pm(self.n,o.d),pm(self.d,o.n))
 def __rtruediv__(self,o):return asrf(o)/self
 def __pow__(self,n):
  if isinstance(n,RF):n=n.constant()
  n=F(n)
  if n.denominator!=1 or abs(n)>32:raise ValueError("Unsupported polynomial exponent")
  n=int(n)
  return RF(pp(self.n,n),pp(self.d,n)) if n>=0 else RF(pp(self.d,-n),pp(self.n,-n))
 def __eq__(self,o):
  o=asrf(o);return clean(pm(self.n,o.d))==clean(pm(o.n,self.d))
 def constant(self):
  if any(k for k in self.n) or any(k for k in self.d):raise ValueError("Not constant")
  return self.n.get((),F(0))/self.d.get((),F(0))
 def coeffs(self,var="x"):
  if any(k for k in self.d):raise ValueError("Nonconstant denominator")
  den=self.d.get((),F(0));out=defaultdict(F)
  for mon,coef in self.n.items():
   if any(v!=var for v,n in mon):raise ValueError("Unexpected variable")
   out[sum(n for v,n in mon)]+=coef/den
  return dict(out)
def asrf(x):return x if isinstance(x,RF) else RF(x)

def rf(text,env=None):
 env=env or {}
 def visit(n):
  if isinstance(n,ast.Expression):return visit(n.body)
  if isinstance(n,ast.Constant) and type(n.value) in (int,float):return RF(F(str(n.value)))
  if isinstance(n,ast.Name):return asrf(env[n.id]) if n.id in env else RF.var(n.id)
  if isinstance(n,ast.UnaryOp):
   v=visit(n.operand)
   if isinstance(n.op,ast.USub):return -v
   if isinstance(n.op,ast.UAdd):return v
  if isinstance(n,ast.BinOp):
   a,b=visit(n.left),visit(n.right)
   if isinstance(n.op,ast.Add):return a+b
   if isinstance(n.op,ast.Sub):return a-b
   if isinstance(n.op,ast.Mult):return a*b
   if isinstance(n.op,ast.Div):return a/b
   if isinstance(n.op,ast.Pow):return a**b
  raise ValueError("Unsupported RF expression "+ast.dump(n))
 return visit(ast.parse(str(text).replace("^","**"),mode="eval"))
def rat(v):return rf(str(v)).constant()

def median(values):
 values=sorted(F(v) for v in values);n=len(values)
 return values[n//2] if n%2 else (values[n//2-1]+values[n//2])/2
def ols(points):
 n=F(len(points));mx=sum(F(x) for x,y in points)/n;my=sum(F(y) for x,y in points)/n
 slope=sum((F(x)-mx)*(F(y)-my) for x,y in points)/sum((F(x)-mx)**2 for x,y in points)
 return slope,my-slope*mx
def linear_interval(lhs,rhs,op):
 co=(rf(lhs)-rf(rhs)).coeffs();assert max(co)<=1
 aa,bb=co.get(1,F(0)),co.get(0,F(0));assert aa
 bound=-bb/aa
 if aa<0:op={"<":">",">":"<","<=":">=",">=":"<="}[op]
 return (bound,op==">=",None,False) if op in (">",">=") else (None,False,bound,op=="<=")
def interval_text(iv):
 lo,lc,hi,hc=iv
 return ("[" if lc else "(")+(str(lo) if lo is not None else "-infinity")+","+(str(hi) if hi is not None else "infinity")+("]" if hc else ")")

def square_factor(n):
 if n<0:raise ValueError()
 c=1;d=2
 while d*d<=n:
  while n%(d*d)==0:n//=d*d;c*=d
  d+=1
 return c,n
def iroot(n,k):
 if n<0:
  if k%2==0:raise ValueError("No real even root")
  return -iroot(-n,k)
 lo,hi=0,max(1,n)
 while lo<=hi:
  mid=(lo+hi)//2;v=mid**k
  if v==n:return mid
  if v<n:lo=mid+1
  else:hi=mid-1
 raise ValueError("Not a perfect rational root")
class Surd:
 def __init__(self,data=0):
  self.v={d:F(c) for d,c in data.items() if c} if isinstance(data,dict) else ({1:F(data)} if data else {})
 @staticmethod
 def sqrt(q):
  q=F(q)
  if q<0:raise ValueError("Real surds only")
  if q==0:return Surd(0)
  c,d=square_factor(q.numerator*q.denominator)
  return Surd({d:F(c,q.denominator)})
 def __add__(self,o):
  o=assurd(o);d=dict(self.v)
  for k,v in o.v.items():d[k]=d.get(k,F(0))+v
  return Surd(d)
 __radd__=__add__
 def __neg__(self):return Surd({d:-v for d,v in self.v.items()})
 def __sub__(self,o):return self+-assurd(o)
 def __rsub__(self,o):return assurd(o)+-self
 def __mul__(self,o):
  o=assurd(o);d=defaultdict(F)
  for a,av in self.v.items():
   for b,bv in o.v.items():
    c,r=square_factor(a*b);d[r]+=av*bv*c
  return Surd(d)
 __rmul__=__mul__
 def constant(self):
  if any(d!=1 for d in self.v):raise ValueError("Not rational")
  return self.v.get(1,F(0))
 def __truediv__(self,o):
  q=assurd(o).constant()
  if not q:raise ZeroDivisionError()
  return Surd({d:v/q for d,v in self.v.items()})
 def __pow__(self,n):
  q=assurd(n).constant()
  if q.denominator!=1:
   base=self.constant();root=F(iroot(base.numerator,q.denominator),iroot(base.denominator,q.denominator))
   return Surd(root)**Surd(q.numerator)
  n=int(q)
  if n<0:return Surd(1)/(self**Surd(-n))
  r=Surd(1)
  for _ in range(n):r=r*self
  return r
 def __eq__(self,o):return self.v==assurd(o).v
def assurd(x):return x if isinstance(x,Surd) else Surd(x)
def se(text,env=None):
 env=env or {}
 def visit(n):
  if isinstance(n,ast.Expression):return visit(n.body)
  if isinstance(n,ast.Constant) and type(n.value) in (int,float):return Surd(F(str(n.value)))
  if isinstance(n,ast.Name) and n.id in env:return assurd(env[n.id])
  if isinstance(n,ast.UnaryOp):
   a=visit(n.operand)
   if isinstance(n.op,ast.USub):return -a
   if isinstance(n.op,ast.UAdd):return a
  if isinstance(n,ast.BinOp):
   a,b=visit(n.left),visit(n.right)
   if isinstance(n.op,ast.Add):return a+b
   if isinstance(n.op,ast.Sub):return a-b
   if isinstance(n.op,ast.Mult):return a*b
   if isinstance(n.op,ast.Div):return a/b
   if isinstance(n.op,ast.Pow):return a**b
  if isinstance(n,ast.Call) and isinstance(n.func,ast.Name) and len(n.args)==1:
   q=visit(n.args[0]).constant()
   if n.func.id=="sqrt":return Surd.sqrt(q)
   if n.func.id=="cbrt":return Surd(F(iroot(q.numerator,3),iroot(q.denominator,3)))
  raise ValueError("Unsupported surd expression "+ast.dump(n))
 return visit(ast.parse(str(text).replace("^","**"),mode="eval"))

def schema_check(value,schema,root,path="$"):
 """Evaluate exactly the keyword subset used by the bundled schema."""
 if "$ref" in schema:
  node=root
  for part in schema["$ref"].removeprefix("#/").split("/"):node=node[part]
  return schema_check(value,node,root,path)
 if "anyOf" in schema:
  for branch in schema["anyOf"]:
   try:schema_check(value,branch,root,path);return
   except (AssertionError,TypeError):pass
  raise AssertionError(path+" does not satisfy anyOf")
 typ=schema.get("type")
 types={"object":lambda v:isinstance(v,dict),"array":lambda v:isinstance(v,list),
        "string":lambda v:isinstance(v,str),"boolean":lambda v:type(v) is bool,
        "integer":lambda v:type(v) is int,"number":lambda v:type(v) in (int,float),
        "null":lambda v:v is None}
 if typ:assert types[typ](value),(path,typ,type(value))
 if "enum" in schema:assert value in schema["enum"],(path,value)
 if "pattern" in schema:assert re.search(schema["pattern"],value),(path,value)
 if "minLength" in schema:assert len(value)>=schema["minLength"],path
 if isinstance(value,dict):
  for key in schema.get("required",[]):assert key in value,path+" missing "+key
  props=schema.get("properties",{})
  if schema.get("additionalProperties") is False:assert not set(value)-set(props),path+" extra keys"
  for k,v in value.items():
   if k in props:schema_check(v,props[k],root,path+"."+k)
 if isinstance(value,list):
  if "minItems" in schema:assert len(value)>=schema["minItems"],path
  if schema.get("uniqueItems"):assert len({json.dumps(v,sort_keys=True) for v in value})==len(value),path
  if "items" in schema:
   for i,v in enumerate(value):schema_check(v,schema["items"],root,path+"["+str(i)+"]")

def audit_math(skills):
 answers={s["skill_id"]:s["assessment"]["anchor_item"]["expected_answer"] for s in skills}
 records=[]
 def a(sid,path=None):
  v=answers[sid]
  if path:
   for k in path.split("."):v=v[int(k)] if isinstance(v,list) else v[k]
  return v
 def checked(sid,kind,fn,scope="Mathematical answer component only; placement, wording, requested form and reasoning remain author-reviewed."):
  try:fn()
  except Exception as e:raise AssertionError("Anchor math failed "+sid+" "+kind+": "+str(e)) from e
  records.append({"skill_id":sid,"check":kind,"scope":scope})
 def eq(x,y):assert x==y,(x,y)
 def calc(sid,expr,path=None):
  checked(sid,"EXACT_CALCULATION",lambda:eq(se(expr),se(a(sid,path))))
 def poly(sid,expr,path=None,answer_rhs=False):
  def run():
   expected=a(sid,path)
   if answer_rhs:expected=expected.split("=",1)[1]
   eq(rf(expr),rf(expected))
  checked(sid,"EXACT_RATIONAL_POLYNOMIAL_IDENTITY",run,
          "Formal identity over the common nonzero-denominator domain; domain equivalence and requested form remain separately reviewed.")
 def linear(sid,left,right):
  def run():
   co=(rf(left)-rf(right)).coeffs()
   assert max(co,default=0)<=1 and co.get(1,0)!=0
   eq(rat(a(sid)),-co.get(0,F(0))/co[1])
  checked(sid,"COMPLETE_LINEAR_SOLUTION",run)
 def roots(sid,expr,path=None):
  def run():
   co=rf(expr).coeffs();assert max(co)==2
   aa,bb,cc=co[2],co.get(1,F(0)),co.get(0,F(0))
   vals=[se(v) for v in a(sid,path)]
   disc=bb*bb-4*aa*cc
   count=2 if disc>0 else 1 if disc==0 else 0
   assert len(vals)==count
   assert len({str(v.v) for v in vals})==len(vals)
   for x in vals:eq(aa*x*x+bb*x+cc,Surd(0))
   if count==2:eq(vals[0]+vals[1],Surd(-bb/aa));eq(vals[0]*vals[1],Surd(cc/aa))
  checked(sid,"COMPLETE_REAL_QUADRATIC_ROOTS",run)
 def point(sid,eqs):
  def run():
   v=a(sid)
   if isinstance(v,str):v=ast.literal_eval(v)
   env={"x":F(v[0]),"y":F(v[1])}
   for lhs,rhs in eqs:eq(rf(lhs,env),rf(rhs,env))
   assert len(eqs)==2
   l1=(rf(eqs[0][0])-rf(eqs[0][1]));l2=(rf(eqs[1][0])-rf(eqs[1][1]))
   def coef(p,var):return p.n.get(((var,1),),F(0))/p.d.get((),F(1))
   assert coef(l1,"x")*coef(l2,"y")-coef(l1,"y")*coef(l2,"x")!=0
  checked(sid,"UNIQUE_LINEAR_SYSTEM_PAIR",run)

 checked("N01","EXACT_ORDER",lambda:eq(a("N01"),sorted([-7,2,-3,0])))
 calc("N02","-(-9)","opposite");calc("N02","sqrt((-9)^2)","absolute_value")
 for sid,expr in {
  "N03":"-8+5","N04":"-3-(-8)","N05":"(-6)*(-4)","N06":"-24/6","N07":"18-2*(3+4)",
  "N08":"3/4","N09":"3/4-1/6","N10":"(2/3)/(4/5)","N11":"-1.2/0.3",
  "N16":"12*3/4","N17":"3004-896","N18":"156/12","A09":"2*(-2)^2-3*(-2)",
  "C07":"(-2)^2-3*(-2)","C11":"2*2","C14":"(3^2-1^2)/(3-1)",
  "L02":"(10-2)/(3-(-1))","X01":"2^5","X08":"2^(-3)","X09":"0.00072",
  "X11":"cbrt(-125)","G03":"50*2^3","G05":"1-15/100",
  "R02":"sqrt(72)","R03":"sqrt(8)+sqrt(18)","H06":"27^(2/3)"}.items():calc(sid,expr)
 calc("N12","3/8","decimal")
 checked("N12","PERCENT_EQUIVALENCE",lambda:eq(rat(a("N12","percent").rstrip("%"))/100,F(3,8)))
 checked("N13","EXACT_RATIONAL_ORDER",lambda:eq([rat(v) for v in a("N13")],sorted([F("-0.6"),F(-2,3),F(1,4)])))
 calc("N14","15/6","value");calc("N15","3^4","power");calc("N15","sqrt(81)","principal_root")
 calc("N20","6/1000","digit_value")
 checked("N20","DECIMAL_COMPARISON",lambda:eq(rat(a("N20","greater")),max(F("0.406"),F("0.46"))))
 checked("C04","FUNCTION_TABLE",lambda:eq([rat(v) for v in a("C04")],[F(2*x+1) for x in [0,1,2]]))
 for sid,expr in {
  "E02":"3*x+5*x+2","E03":"-4*x+7*x-2*x","E04":"5*x+7-2*x+4",
  "E06":"4*(x+3)","E07":"-3*(2*x-5)","E08":"7-(2*x-3)",
  "E09":"2*(3*x-4)-(x+5)","E10":"6*x+9",
  "C19":"(x+2)+3*x","X03":"x^3*x^4","X04":"x^4","X05":"(x^3)^2","X06":"(2*x)^3",
  "P03":"(2*x^2+3*x-1)+(x^2-5*x+4)","P04":"(3*x^2+x-2)-(x^2-4*x+5)",
  "P05":"-2*x*(3*x^2-x+4)","P06":"(x+3)*(x-2)","P07":"(x+1)*(x^2-x+1)",
  "P08":"(x-4)^2","P09":"2*x^2+3*x-x^2+4*x",
  "F01":"12*x^3+18*x^2","F02":"-3*x^2+6*x","F04":"x^2+x-12",
  "F05":"6*x^2+11*x+3","F06":"9*x^2-16","F07":"x^2-10*x+25"}.items():
  if sid=="X04":
   checked(sid,"QUOTIENT_IDENTITY_AND_STATED_RESTRICTION",lambda:(eq(rf("x^7/x^3"),rf(a("X04").split(",")[0])),eq("x!=0" in a("X04").replace(" ",""),True)))
  else:poly(sid,expr)
 for sid,expr in {"L06":"3*x+2","L07":"2*x-1","L08":"2*x-3","L09":"(-2/3)*x+4","C16":"(x-2)/3"}.items():
  poly(sid,expr,answer_rhs=True)
 for sid,lhs,rhs in [
  ("Q04","x-7","-2"),("Q05","-4*x","20"),("Q06","3*x+5","20"),
  ("Q07","3*(x+4)","24"),("Q08","5*x-7","2*x+8"),
  ("Q09","2*(3*x-4)-(x+5)","12"),("Q10","(3/4)*x-1/2","5/2"),("Q11","x/3+1/2","5/6")]:linear(sid,lhs,rhs)
 checked("Q12","IDENTITY_SOLUTION_CLASSIFICATION",lambda:(eq(rf("3*(x+2)"),rf("3*x+6")),eq(a("Q12").lower().startswith("all real"),True)))
 checked("Q13","FORMULA_REARRANGEMENT_IDENTITY",lambda:eq(rf("(1/2)*b*h",{"h":rf(a("Q13").split("=",1)[1])}),rf("A")))
 calc("L12","(10-4)/(3-2)","n");calc("L12","10+2*"+str(a("L12","n")),"cost")
 checked("C16","INVERSE_BOTH_COMPOSITIONS",lambda:(eq(rf("3*x+2",{"x":rf(a("C16").split("=",1)[1])}),rf("x")),eq(rf(a("C16").split("=",1)[1],{"x":rf("3*x+2")}),rf("x"))))
 for sid,lhs,rhs,op in [("I02","x","-2",">"),("I03","x+4","9","<"),("I04","3*x","12",">="),("I05","-2*x","6","<"),("I06","3-2*(x+1)","9",">=")]:
  checked(sid,"EXACT_LINEAR_INEQUALITY",lambda sid=sid,lhs=lhs,rhs=rhs,op=op:eq(a(sid).split(";")[0].strip(),interval_text(linear_interval(lhs,rhs,op))))
 checked("I07","COMPOUND_INEQUALITY_INTERSECTION",lambda:eq(a("I07"),interval_text((linear_interval("-1","2*x+3","<")[0],False,linear_interval("2*x+3","7","<=")[2],True))))
 def features():
  co=rf("(x-1)^2-4").coeffs();aa,bb,cc=co[2],co[1],co[0]
  vertex=-bb/(2*aa);disc=bb*bb-4*aa*cc;root=F(math.isqrt(disc.numerator),math.isqrt(disc.denominator));assert root*root==disc
  lo,hi=sorted([(-bb-root)/(2*aa),(-bb+root)/(2*aa)]);assert aa>0
  eq(re.findall(r"\([^()]+\)",a("C20")),["(-infinity,"+str(vertex)+")","("+str(lo)+","+str(hi)+")"])
 checked("C20","QUADRATIC_FEATURE_INTERVALS",features,"Specified feature intervals derived from this quadratic; actual graph performance is not assessed by this script.")
 checked("F03","SIGNED_FACTOR_PAIR_CONSTRAINTS",lambda:(eq(sum(int(v) for v in re.findall(r"-?\d+",a("F03"))),1),eq(math.prod(int(v) for v in re.findall(r"-?\d+",a("F03"))),-12)))
 checked("L04","Y_INTERCEPT",lambda:eq(ast.literal_eval(a("L04")),(0,7)))
 point("S02",[("y","x+1"),("y","-x+5")])
 point("S03",[("y","2*x+1"),("x+y","10")])
 point("S04",[("x+y","7"),("x-y","1")])
 point("S05",[("2*x+3*y","13"),("3*x-2*y","0")])
 checked("S07","CONTEXTUAL_SYSTEM_COUNTS",lambda:(eq(a("S07","adults")+a("S07","children"),10),eq(8*a("S07","adults")+5*a("S07","children"),68)))
 for sid,expr,path in [
  ("F09","(x-3)*(x+2)",None),("U07","x^2-49",None),("U08","x^2-x-6",None),
  ("U09","(x-2)^2-9",None),("U12","x^2-2*x-1",None),("U14","x^2-5*x+6","roots"),
  ("U15","(x-4)^2-7","solutions")]:roots(sid,expr,path)
 checked("U02","SIGNED_COEFFICIENTS",lambda:eq(a("U02"),{"a":rf("-2*x^2+5").coeffs().get(2,0),"b":rf("-2*x^2+5").coeffs().get(1,0),"c":rf("-2*x^2+5").coeffs().get(0,0)}))
 checked("U03","SECOND_DIFFERENCES",lambda:eq([int(v) for v in a("U03").split("]")[0].strip("[ ").split(",")],[2,2]))
 checked("U04","INTERCEPTS",lambda:(eq(a("U04","x_intercepts"),[[1,0],[3,0]]),eq(a("U04","y_intercept"),[0,3])))
 checked("U05","VERTEX_AND_MAXIMUM",lambda:(eq(a("U05","vertex"),[2,9]),eq(a("U05","maximum"),9),eq(a("U05","axis"),"x=2")))
 for sid,base in [("U10","x^2+6*x+2"),("U11","2*x^2+8*x+1")]:
  def run(sid=sid,base=base):
   l,r=a(sid).split("=");p=(rf(l)-rf(r)).coeffs();q=rf(base).coeffs()
   assert set(p)==set(q)
   k=next(p[d]/q[d] for d in q if q[d]);assert k!=0
   assert all(p[d]==k*q[d] for d in q)
  checked(sid,"EQUIVALENT_QUADRATIC_EQUATION",run)
 checked("U13","DISCRIMINANT",lambda:(eq(F(2)**2-4*F(1)*F(5),-16),eq(a("U13").lower().startswith("none"),True)))
 checked("U14","ROOT_INTERCEPT_LINK",lambda:eq(a("U14","x_intercepts"),[[r,0] for r in a("U14","roots")]))
 checked("U16","COMPLETING_SQUARE_DERIVATION_IDENTITY",lambda:eq(rf("(2*a*x+b)^2-(b^2-4*a*c)"),rf("4*a*(a*x^2+b*x+c)")),
         "Exact identity used in the derivation; the full explanatory argument and domain conditions remain author-reviewed.")
 for sid,expr,field in [
  ("G07","10+3","n1.A"),("G07","10*(13/10)","n1.B"),
  ("G07","10+3*2","n2.A"),("G07","10*(13/10)^2","n2.B"),
  ("G11","2^10","exponential"),("G11","10^2","quadratic")]:calc(sid,expr,field)
 checked("G12","INTEGER_TIME_SCALE_BASE",lambda:eq(rat(a("G12").split("^")[0]),F(2)**3))
 checked("G08","COMMON_BASE_EXPONENT_SOLUTION",lambda:eq(F(2)**(int(rat(a("G08")))+1),F(16)))
 checked("H08","INVERSE_EXPONENT_VALUE",lambda:eq(F(2)**int(rat(a("H08"))),F(8)))
 checked("G13","LINEAR_DIFFERENCE_IDENTITY",lambda:eq(rf("m*(x+1)+b-(m*x+b)"),rf("m")),
         "Linear component of the reasoning anchor only; the exponential argument remains author-reviewed.")
 checked("R04","EXCLUDED_DENOMINATOR",lambda:eq(a("R04","excluded_inputs"),[3]))
 poly("R05","(x^2-9)/(x-3)","expression")
 checked("R05","ORIGINAL_DOMAIN_METADATA",lambda:eq(a("R05","excluded_inputs"),[3]))
 checked("R06","RATIONAL_SOLUTION_AND_EXCLUSION",lambda:(eq(a("R06","solutions"),[2]),eq(rf("x/(x-1)",{"x":F(2)}),RF(2)),eq(a("R06","excluded_inputs"),[1])))
 checked("R07","EXTRANEOUS_ROOT_FILTER",lambda:eq([x for x in [0,3] if se("sqrt(x+1)",{"x":F(x)})==se("x-1",{"x":F(x)})],a("R07")))
 calc("D03","(2+2+3+9)/4","mean");calc("D03","(2+3)/2","median")
 checked("D04","DECLARED_QUARTILE_CONVENTION",lambda:(eq(rat(a("D04","Q1")),median([1,2,3])),eq(rat(a("D04","Q3")),median([5,6,7])),eq(rat(a("D04","IQR")),median([5,6,7])-median([1,2,3]))))
 for field,expr in {"before.mean":"(2+3+4)/3","before.median":"3","after.mean":"(2+3+4+30)/4","after.median":"(3+4)/2"}.items():calc("D06",expr,field)
 checked("D08","EXACT_LEAST_SQUARES_LINE",lambda:eq(rf(a("D08").split("=",1)[1]),ols([(0,1),(1,3),(2,5)])[0]*RF.var("x")+ols([(0,1),(1,3),(2,5)])[1]))
 calc("D09","8-(2*3+1)","residual")
 for field,expr in {"conditional":"12/20","joint":"12/50","marginal":"20/50"}.items():calc("D11",expr,field)
 checked("M06","MODEL_TABLE_VALUES",lambda:eq(a("M06","values"),[5+2*d for d in [0,1,2]]))
 checked("M09","DECLARED_PRECISION",lambda:eq(Decimal(str(a("M09","value"))),Decimal("12.347").quantize(Decimal("0.1"),rounding=ROUND_HALF_UP)))
 checked("H01","ABSOLUTE_VALUE_ROOTS",lambda:(eq(a("H01"),[-1,4]),eq([abs(2*x-3) for x in a("H01")],[5,5])))
 checked("H02","PIECEWISE_DOMAIN_FILTER",lambda:eq([x for x in [2,3] if (x+1 if x<2 else 2*x)==4],a("H02")))
 poly("H03","2*(x^2)+1","composition");poly("H03","(x-1)/2","inverse")
 poly("H04","-2*(3*(x-1))^2+4","rule")
 checked("H05","DIVISION_IDENTITY_AND_RESTRICTION",lambda:(eq(rf(a("H05","quotient"))*rf("x-1")+rf(a("H05","remainder")),rf("x^3-1")),eq(a("H05","excluded_inputs"),[1])))
 checked("H07","COMPLETE_COMPLEX_INTEGER_ROOTS",lambda:(eq(len(set(a("H07"))),2),eq(sum(complex(v.replace("i","j")) for v in a("H07")),0j),eq(math.prod(complex(v.replace("i","j")) for v in a("H07")),4+0j),eq([complex(v.replace("i","j"))**2+4 for v in a("H07")],[0j,0j])))
 checked("H09","RATIONAL_ASYMPTOTE_METADATA",lambda:eq(a("H09"),{"excluded":2,"vertical":"x=2","horizontal":"y=1"}))
 def intersections(sid,polynomial,relations):
  def run():
   pts=a(sid);xs=[F(v[0]) for v in pts]
   co=rf(polynomial).coeffs();aa,bb,cc=co[2],co.get(1,F(0)),co.get(0,F(0))
   assert bb*bb-4*aa*cc>0 and len(set(xs))==2
   eq(sum(xs),-bb/aa);eq(xs[0]*xs[1],cc/aa)
   for x,y in pts:
    for lhs,rhs in relations:eq(rf(lhs,{"x":F(x),"y":F(y)}),rf(rhs,{"x":F(x),"y":F(y)}))
  checked(sid,"COMPLETE_REAL_INTERSECTION_SET",run)
 intersections("S09","x^2-x-2",[("y","x+2"),("y","x^2")])
 intersections("H10","x^2-1",[("y","x^2"),("y","2*x^2-1")])
 return records

def audit_units(units):
 keys={u["unit_id"]:u["illustrative_unit_checkpoint"]["expected"] for u in units}
 done=[]
 def eq(x,y):assert x==y,(x,y)
 def check(uid,fn):
  try:fn(keys[uid])
  except Exception as e:raise AssertionError("Unit "+uid+" key mathematics: "+str(e)) from e
  done.append({"unit_id":uid,"status":"PASS","scope":"Explicit mathematical key components; wording, model assumptions and learner reasoning are not independently certified."})
 check("01",lambda k:(eq(rf(k["simplified"]),rf("3*(2*x+5)-4*x")),eq(rf("3*(2*x+5)-4*x",{"x":F(k["counterexample"]["x"])}).constant(),F(k["counterexample"]["original"])),eq(rf("2*x+5",{"x":F(k["counterexample"]["x"])}).constant(),F(k["counterexample"]["proposed"]))))
 check("02",lambda k:(eq(rf("3*(2*x-1)",{"x":F(k["equation_solution"])}),rf("4*x+11",{"x":F(k["equation_solution"])})),eq(interval_text(linear_interval("3*(2*x-1)","4*x+11","<=")),"(-infinity,"+str(k["equation_solution"])+"]")))
 check("03",lambda k:(eq(k["f2"],2*2+1),eq(k["f3"],7),eq(k["domain"],"[0,5]"),eq(k["range"],"[1,7]")))
 def plans(k):
  h=F(k["equal_hours"]);eq(18+4*h,6+6*h);eq(F(k["equal_cost"]),18+4*h)
  for t,label in [(2,"at2"),(8,"at8")]:
   eq(k[label]["A"],18+4*t);eq(k[label]["B"],6+6*t)
   eq(k[label]["cheaper"],"A" if 18+4*t<6+6*t else "B")
 check("04",plans)
 def constraints(k):
  p,q=k["notebooks"],k["folders"];eq(p+q,10);eq(3*p+2*q,24)
  boundaries=[(F(1),F(0),F(0)),(F(0),F(1),F(0)),(F(1),F(1),F(10)),(F(3),F(2),F(24))];verts=set()
  for i,(a,b,c) in enumerate(boundaries):
   for d,e,f in boundaries[i+1:]:
    det=a*e-b*d
    if not det:continue
    x=(c*e-b*f)/det;y=(a*f-c*d)/det
    if x>=0 and y>=0 and x+y<=10 and 3*x+2*y<=24:verts.add((x,y))
  eq({tuple(F(v) for v in pt) for pt in k["real_region_vertices"]},verts)
 check("05",constraints)
 def stats(k):
  data=[4,5,5,6,10];eq(rat(k["mean"]),F(sum(data),len(data)));eq(rat(k["median"]),median(data));eq(rat(k["IQR"]),median([6,10])-median([4,5]))
  mm,bb=ols([(1,3),(2,4),(3,7),(4,8)]);eq(rf(k["line"].split("=",1)[1]),mm*RF.var("x")+bb);eq(rat(k["residual_at3"]),F(7)-(mm*3+bb))
 check("06",stats)
 check("07",lambda k:(eq(k["A"],[100+20*t for t in range(3)]),eq([F(v) for v in k["B"]],[100*F(6,5)**t for t in range(3)]),eq(rat(k["two_unit_factor"]),F(6,5)**2)))
 check("08",lambda k:(eq(rf(k["expanded"]),rf("(x-4)*(x+1)")),eq(rf(k["factored"]),rf("2*x^2-6*x-8")),eq(k["counterexample"]["left"],(k["counterexample"]["x"]-4)**2),eq(k["counterexample"]["right"],k["counterexample"]["x"]**2+16)))
 def quadratic(k):
  eq(rf(k["vertex_form"]),rf("-t^2+6*t+7"));eq(rf("-t^2+6*t+7",{"t":F(k["maximum_time"])}).constant(),F(k["maximum"]))
  eq(rf("-t^2+6*t+7",{"t":F(k["stopping_time"])}).constant(),F(0));eq(k["time_domain"],"[0,7]")
  roots=[se(x) for x in k["h10_times"]];assert len(roots)==2;eq(roots[0]+roots[1],Surd(6));eq(roots[0]*roots[1],Surd(3))
  for r in roots:eq(-r*r+6*r+7,Surd(10))
 check("09",quadratic)
 def garden(k):
  a,b=k["unordered_dimensions"];assert a>0 and b>0;eq(2*(a+b),28);eq(a*b,48)
  eq(rf(k["area_model"].split("=",1)[1]),rf("w*(14-w)"));eq(k["positive_width_domain"],"(0,14)")
  eq(k["maximum_area"],49);eq(k["maximizing_width"],7);eq(k["fence_cost"],3*2*(a+b)+20);eq(k["budget_margin"],110-k["fence_cost"])
 check("10",garden)
 return done

def main():
 m=json.loads((ROOT/"algebra-map.json").read_text())
 schema=json.loads((ROOT/"algebra-map.schema.json").read_text())
 schema_check(m,schema,schema)
 skills=m["skills"];index={s["skill_id"]:s for s in skills};ids=set(index)
 assert len(skills)==len(ids)==m["metadata"]["skill_count"]==205
 original=json.loads((ROOT/"authoring/original_skill_id_snapshot.json").read_text())
 assert set(original)<=ids and len(original)==185
 additions=set(m["metadata"]["new_skill_ids"])
 assert ids-set(original)==additions and len(additions)==20
 normalizations=json.loads((ROOT/"change-log.json").read_text())["formatting_only_normalizations"]
 for original_id,statement in original.items():assert index[original_id]["statement"]==normalizations.get(original_id,statement),original_id+" silently changed identity"
 observed_codes=set(json.loads((ROOT/"authoring/standards-source-index.json").read_text())["codes"])
 used_codes={a["standard"] for s in skills for a in s["alignment"]}
 assert used_codes<=observed_codes,sorted(used_codes-observed_codes)
 sources={s["source_id"] for s in m["sources"]}
 edges=[];hard=[];anchor_edges=[]
 for s in skills:
  sid=s["skill_id"];p=s["prerequisites"]
  for pre in p["all_of"]:edges.append((pre,sid,"COMPONENT"));hard.append((pre,sid))
  for path in p["alternative_paths"]:
   for pre in path["all_of"]:edges.append((pre,sid,"ALTERNATIVE"))
  for c in p["conditional"]:
   for pre in c["requires"]:edges.append((pre,sid,"CONDITIONAL"))
  for pre in s["assessment"]["anchor_item"]["additional_prerequisites_for_this_anchor"]:anchor_edges.append((pre,sid,"ANCHOR_ONLY"))
  assert all(a["source_id"] in sources for a in s["alignment"])
  for r in s["out_of_scope_redirects"]:assert r["target_skill_id"] in ids
  assert s["assessment"]["anchor_item"]["primary_skill_id"]==sid
  if s["curricular_role"]=="EXTENSION":assert not s["required_in_reference_profile"]
 for pre,target,kind in edges+anchor_edges:assert pre in ids and target in ids and pre!=target,(pre,target,kind)
 exported_graph=json.loads((ROOT/"prerequisite-graph.json").read_text())
 graph_type={"COMPONENT":"COMPONENT","ALTERNATIVE_PATH":"ALTERNATIVE","ITEM_CONDITIONAL":"CONDITIONAL"}
 assert set(exported_graph["nodes"])==ids
 assert Counter((e["from"],e["to"],graph_type[e["type"]]) for e in exported_graph["edges"])==Counter(edges),"Exported graph does not match map"
 def topo(edges):
  adj={i:set() for i in ids};deg={i:0 for i in ids}
  for pre,target,*_ in edges:
   if target not in adj[pre]:adj[pre].add(target);deg[target]+=1
  q=deque(sorted(i for i in ids if deg[i]==0));order=[]
  while q:
   i=q.popleft();order.append(i)
   for j in sorted(adj[i]):
    deg[j]-=1
    if deg[j]==0:q.append(j)
  assert len(order)==len(ids),"Cycle or downstream nodes: "+str(sorted(ids-set(order)))
  return order
 order=topo(edges)
 topo(edges+anchor_edges)
 # No required core has an unconditional dependency on an optional extension.
 hard_pre=defaultdict(set)
 for pre,target in hard:hard_pre[target].add(pre)
 for s in skills:
  if s["curricular_role"]!="CORE":continue
  seen=set();todo=list(hard_pre[s["skill_id"]])
  while todo:
   pre=todo.pop()
   if pre in seen:continue
   seen.add(pre);todo+=list(hard_pre[pre])
  assert not [i for i in seen if index[i]["curricular_role"]=="EXTENSION"],s["skill_id"]
  for pre in hard_pre[s["skill_id"]]:
   if index[pre]["curricular_role"]=="CORE":assert int(index[pre]["first_instructional_unit"])<=int(s["first_instructional_unit"]),("Core component scheduled too late",pre,s["skill_id"])
 assert sum(u["planning_weeks"] for u in m["units"])+m["flex_review_weeks"]==36
 assigned=[sid for u in m["units"] for sid in u["primary_skill_ids"]]
 assert len(assigned)==len(set(assigned))
 assert set(assigned)=={s["skill_id"] for s in skills if s["curricular_role"]=="CORE"}
 assert set(m["reference_profile"]["required_core_skill_ids"])==set(assigned)
 math_records=audit_math(skills)
 unit_checks=audit_units(m["units"])
 roles=Counter(s["curricular_role"] for s in skills)
 report={
  "curriculum_version":m["metadata"]["curriculum_version"],"status":"PASS",
  "structural_checks":{"skills":len(skills),"original_ids_preserved":len(original),"new_ids":len(additions),
    "role_counts":dict(roles),"all_references_resolve":True,"all_dependency_types_acyclic":True,
    "anchor_additional_dependencies_acyclic":True,"core_unconditional_extension_dependencies":0,
    "core_unit_assignments_complete":True,"planning_weeks_including_flex":36,
    "unconditional_core_components_scheduled_no_later_than_targets":True,
    "standard_codes_observed_in_official_sources":len(used_codes),"source_count":len(sources),
    "project_schema_keyword_subset":"PASS; not a general-purpose Draft 2020-12 conformance certification"},
  "mathematical_checks":{"check_count":len(math_records),"anchor_items_with_at_least_one_automated_math_check":len({r["skill_id"] for r in math_records}),
    "anchors_without_automated_math_check":[s["skill_id"] for s in skills if s["skill_id"] not in {r["skill_id"] for r in math_records}],
    "scope":"Exact explicit mathematical obligations for the authored anchors. Passing does not independently establish wording alignment, diagnostic validity, a graph drawing, reasoning quality or classroom effectiveness.",
    "records":math_records},
  "unit_checkpoint_mathematical_checks":unit_checks,
  "not_validated":["Empirical prerequisite ordering","Diagnostic sensitivity/specificity","Item difficulty or discrimination","Production answer-checker behavior","Independent semantic review of all anchor wording","Learning gains or durable mastery"],
  "topological_order_for_integrity_only":order}
 (ROOT/"validation_report.json").write_text(json.dumps(report,ensure_ascii=False,indent=2)+"\n")
 print(json.dumps({"status":"PASS","skills":len(skills),"edges":len(edges),"automated_math_checks":len(math_records),"anchors_with_automated_math_checks":len({r["skill_id"] for r in math_records}),"unit_math_keys_checked":len(unit_checks),"standard_codes":len(used_codes)}))
 return report

if __name__=="__main__":
 try:main()
 except Exception as e:
  print("FAIL:",e,file=sys.stderr);raise

