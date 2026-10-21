#!/usr/bin/env python3
"""Build the researched ACE curriculum from explicit authoring records. Standard library only."""
from pathlib import Path
import csv, hashlib, json, re, shutil, sys
from collections import Counter, defaultdict, deque

ROOT = Path(__file__).resolve().parents[1]
UPLOADED_SKELETON = ROOT.parent / "upload" / "Pasted text (2)(20261020-122352).txt"
VERSION = "1.1.0"
RESEARCH_DATE = "2026-10-20"
DOMAIN_NAMES = {
 "N":"Numerical prerequisites","A":"Algebraic language and notation",
 "E":"Expression structure and equivalence","Q":"Equality and linear equations",
 "I":"Inequalities","C":"Coordinates and functions","L":"Linear relationships",
 "S":"Systems of equations and constraints","X":"Exponents and roots",
 "P":"Polynomial operations","F":"Factoring","U":"Quadratic functions and equations",
 "G":"Exponential relationships and sequences","R":"Real numbers, radicals, and rational algebra",
 "D":"Data and statistical modeling","M":"Integrated modeling and reasoning",
 "H":"Algebra 2 and course-dependent extensions"}
DOMAIN_ORDER = list(DOMAIN_NAMES)
NEW_STATEMENTS = {
 "N17":"Add and subtract whole numbers using place value",
 "N18":"Multiply and divide whole numbers",
 "N19":"Represent fractions as quantities and equal units",
 "N20":"Interpret base-ten place value and decimal magnitude",
 "Q14":"Solve linear equations with literal coefficients and parameter cases",
 "C15":"Interpret basic transformations of familiar functions",
 "C16":"Reverse simple functions and find linear inverses",
 "C17":"Compare functions presented in different representations",
 "C18":"Graph basic square-root and cube-root functions",
 "C19":"Combine familiar function models using arithmetic",
 "C20":"Interpret key features and intervals of functions",
 "S09":"Solve and interpret linear-quadratic intersections",
 "X11":"Evaluate and interpret real cube roots",
 "P10":"Explain polynomial closure and its limits",
 "U16":"Derive and justify the quadratic formula",
 "G11":"Compare eventual exponential and polynomial growth",
 "G12":"Rewrite exponential expressions across time intervals",
 "G13":"Justify equal-difference and equal-factor growth laws",
 "R08":"Justify rational and irrational number-operation claims",
 "M09":"Choose and communicate appropriate measurement precision"}
FORMATTING_NORMALIZATIONS = {
 "A05":"Interpret algebraic notation such as 3x, x^2, and x/3",
 "L05":"Graph y=mx+b","U07":"Solve equations of the form x^2=k",
 "G02":"Understand y=ab^x"}
ANSWER_OVERRIDES = {
 "L12":{"n":6,"cost":22,"unit":"dollars"},
 "S07":{"equations":["a+c=10","8*a+5*c=68"],"adults":6,"children":4},
 "U14":{"roots":[2,3],"x_intercepts":[[2,0],[3,0]]},
 "U15":{"method":"square_roots","solutions":["4-sqrt(7)","4+sqrt(7)"],"reason":"The square is already isolated."},
 "G07":{"n1":{"A":13,"B":13},"n2":{"A":16,"B":"169/10"}},
 "G11":{"exponential":1024,"quadratic":100,"single_comparison_proves_all_future":False},
 "R04":{"excluded_inputs":[3],"reason":"zero_denominator"},
 "R05":{"expression":"x+3","excluded_inputs":[3]},
 "R06":{"solutions":[2],"excluded_inputs":[1]},
 "D06":{"before":{"mean":3,"median":3},"after":{"mean":"39/4","median":"7/2"}},
 "D09":{"residual":1,"positive_r_means":"positive_linear_association","not_percent_correct":True,"causation_not_established":True},
 "M06":{"values":[5,7,9],"slope":2,"y_intercept":5,"units":"dollars; dollars per kilometer for slope"},
 "H03":{"composition":"2*x^2+1","inverse":"(x-1)/2"},
 "H04":{"rule":"-18*(x-1)^2+4","transformations":["horizontal compression by 1/3","right 1","reflect in x-axis","vertical stretch by 2","up 4"]},
 "H05":{"quotient":"x^2+x+1","remainder":0,"excluded_inputs":[1]}}
EXTENSION_IDS = {"P07","G08","R03","R05","R06","R07"} | {f"H{i:02}" for i in range(1,11)}
FOUNDATION_DOMAINS = {"N","A","X"}

SOURCE_DOMAINS = [
 ("4/NBT","Grade 4 base-ten number","4.NBT","turn14view0"),
 ("4/NF","Grade 4 fractions","4.NF","turn14view1"),
 ("6/NS","Grade 6 number system","6.NS","turn13view1"),
 ("6/EE","Grade 6 expressions and equations","6.EE","turn12view2"),
 ("6/RP","Grade 6 ratios and rates","6.RP","turn14view2"),
 ("7/NS","Grade 7 rational-number operations","7.NS","turn12view1"),
 ("7/EE","Grade 7 expressions and equations","7.EE","turn13view2"),
 ("7/RP","Grade 7 proportional relationships","7.RP","turn14view3"),
 ("8/EE","Grade 8 expressions and equations","8.EE","turn12view3"),
 ("8/F","Grade 8 functions","8.F","turn13view3"),
 ("HSA/SSE","High-school expression structure","HSA.SSE","turn10view0"),
 ("HSA/APR","High-school polynomial and rational arithmetic","HSA.APR","turn10view1"),
 ("HSA/CED","High-school equation construction","HSA.CED","turn8view1"),
 ("HSA/REI","High-school equation and inequality reasoning","HSA.REI","turn8view0"),
 ("HSN/RN","High-school real-number system","HSN.RN","turn7view3"),
 ("HSN/Q","High-school quantities and units","HSN.Q","turn10view3"),
 ("HSF/IF","High-school function interpretation","HSF.IF","turn7view1"),
 ("HSF/BF","High-school function construction","HSF.BF","turn7view2"),
 ("HSF/LE","High-school linear and exponential modeling","HSF.LE","turn10view2"),
 ("HSS/ID","High-school descriptive statistics","HSS.ID","turn8view2")]
SOURCES = [
 {"source_id":"CCSS-"+p.replace("/","-"),"title":title,
  "url":"https://www.thecorestandards.org/Math/Content/"+p+"/",
  "publisher":"Common Core State Standards Initiative","accessed":RESEARCH_DATE,
  "source_type":"PRIMARY_CONTENT_STANDARD","code_prefix":prefix,
  "use":"Content reference; course allocation and prerequisite edges are author decisions."}
 for p,title,prefix,ref in SOURCE_DOMAINS]
SOURCES += [
 {"source_id":"IM-A1","title":"Illustrative Mathematics Algebra 1 course narrative",
  "url":"https://im.kendallhunt.com/HS/teachers/1/narrative.html",
  "publisher":"Illustrative Mathematics / Kendall Hunt","accessed":RESEARCH_DATE,
  "source_type":"PRIMARY_CURRICULUM_REFERENCE",
  "use":"Scope comparator covering data, equations, functions, exponential and quadratic work. This package uses an original sequence and original anchor items."},
 {"source_id":"IES-ALGEBRA","title":"Teaching Strategies for Improving Algebra Knowledge in Middle and High School Students",
  "url":"https://ies.ed.gov/ncee/wwc/PracticeGuide/20",
  "publisher":"Institute of Education Sciences, What Works Clearinghouse","accessed":RESEARCH_DATE,
  "source_type":"PRIMARY_PRACTICE_GUIDE",
  "use":"Solved-problem analysis, structure and deliberate strategy choice. Guide evidence ratings: first two recommendations minimal; strategy choice moderate. These do not validate this product or its prerequisite graph."}]

UNIT_ROWS = [
 ("01","Expressions, quantities and mathematical language",2,"E","Construct meaning, preserve equivalence, define quantities and explain operations."),
 ("02","Linear equations and inequalities",4,"Q I","Solve, justify transformations and interpret complete feasible solution sets."),
 ("03","Functions and representations",3,"C","Use notation, domains, tables and graphs; compare and interpret familiar functions."),
 ("04","Linear relationships",3,"L","Construct, graph and interpret linear models across representations."),
 ("05","Systems and constraints",3,"S","Represent simultaneous constraints, choose a solving method and interpret feasibility."),
 ("06","Data and linear statistical models",3,"D","Compare distributions, analyze associations and interpret linear fit."),
 ("07","Exponential relationships and sequences",3,"G","Distinguish growth structures, write sequence rules and interpret exponential models."),
 ("08","Polynomials and factoring",4,"P F","Use algebraic structure, justify identities and choose useful forms."),
 ("09","Quadratic functions and equations",5,"U R","Connect representations, solve over the stated number system and justify method choice."),
 ("10","Integrated modeling and communication",3,"M","Build, test, interpret and critique models with explicit limitations.")]
FIRST_UNIT = {letter:unit for unit,title,weeks,letters,goal in UNIT_ROWS for letter in letters.split()}
UNIT_EXAMPLES = {
 "01":{"task":"Simplify 3(2x+5)-4x. Explain an equality-preserving method. Disprove the proposed answer 2x+5 with a counterexample.","expected":{"simplified":"2*x+15","counterexample":{"x":0,"original":15,"proposed":5}},"mandatory_evidence":["Every distributed term is retained","Like terms collected","A valid counterexample, not a confirming example"]},
 "02":{"task":"Solve 3(2x-1)=4x+11. Then solve 3(2n-1)<=4n+11 and interpret n as a nonnegative whole-number count.","expected":{"equation_solution":7,"inequality":"n<=7","feasible_counts":"integers 0 through 7"},"mandatory_evidence":["Equivalent equation steps","Correct inclusive boundary","Discrete contextual restriction"]},
 "03":{"task":"Let f(x)=2x+1 for 0<=x<3 and f(x)=7 for 3<=x<=5. Find f(2), f(3), domain and range, and construct the graph with correct endpoints.","expected":{"f2":5,"f3":7,"domain":"[0,5]","range":"[1,7]"},"mandatory_evidence":["Correct branch at 3","Accessible actual graph evidence","Input and output intervals distinguished"]},
 "04":{"task":"Plans A and B cost A=18+4h and B=6+6h dollars for h nonnegative hours. Compare intercepts and rates, find equal cost, and decide the cheaper plan at 2 and 8 hours.","expected":{"equal_hours":6,"equal_cost":42,"at2":{"A":26,"B":18,"cheaper":"B"},"at8":{"A":50,"B":54,"cheaper":"A"}},"mandatory_evidence":["Rates carry dollars/hour","Equality checked in both plans","Comparison interpreted on either side"]},
 "05":{"task":"Notebooks cost 3 dollars and folders 2 dollars. Find counts p,q for exactly 10 items costing 24 dollars. Then graph the real relaxation of p>=0,q>=0,p+q<=10,3p+2q<=24, and distinguish it from feasible integer counts.","expected":{"notebooks":4,"folders":6,"real_region_vertices":[[0,0],[8,0],[4,6],[0,10]],"integer_policy":"Only nonnegative integer points in that region are feasible counts."},"mandatory_evidence":["Both equalities checked","All inequality constraints respected","Continuous region distinguished from count feasibility"]},
 "06":{"task":"For data 4,5,5,6,10 find mean, median and IQR using the declared quartile convention. For pairs (1,3),(2,4),(3,7),(4,8), find a least-squares line and the residual at x=3. State why association alone does not establish causation.","expected":{"mean":6,"median":5,"IQR":"7/2","line":"y_hat=(9/5)*x+1","residual_at3":"3/5"},"mandatory_evidence":["Quartile convention retained","Observed minus predicted residual","Causal limitation stated"]},
 "07":{"task":"For integer t>=0 compare A(t)=100+20t and B(t)=100*(1.2)^t at t=0,1,2. Give B's recursive rule and its multiplier over a two-unit interval. Explain why one matching step does not make both models linear.","expected":{"A":[100,120,140],"B":[100,120,144],"B_recursive":"B(0)=100; B(t)=1.2*B(t-1) for t>=1","two_unit_factor":"36/25"},"mandatory_evidence":["Difference versus factor distinguished","Initial condition and indexing stated","Time-interval change justified"]},
 "08":{"task":"Expand (x-4)(x+1), factor 2x^2-6x-8 completely, and disprove (x-4)^2=x^2+16. Explain why polynomial closure under multiplication does not imply closure under division.","expected":{"expanded":"x^2-3*x-4","factored":"2*(x-4)*(x+1)","counterexample":{"x":1,"left":9,"right":17},"division_counterexample":"1/x"},"mandatory_evidence":["All cross terms present","Factorization re-expanded","Identity and counterexample distinguished","Division closure rejected correctly"]},
 "09":{"task":"A simulation uses h(t)=-t^2+6t+7 until it first reaches zero for t>=0. Find vertex form, maximum, stopping time, physical time domain, and both times when h=10. Compare methods.","expected":{"vertex_form":"-(t-3)^2+16","maximum":16,"maximum_time":3,"stopping_time":7,"time_domain":"[0,7]","h10_times":["3-sqrt(6)","3+sqrt(6)"]},"mandatory_evidence":["Both algebraic roots considered","Negative stopping-time candidate rejected by context","Exact answers and contextual domain retained","Method choice justified"]},
 "10":{"task":"A rectangular garden has perimeter 28 m and area 48 square meters. Define variables, construct and solve a model for its positive dimensions, compare with the maximum area possible at that perimeter, and check a fence cost of 3 dollars/m plus a fixed 20 dollars against a 110-dollar budget. State assumptions.","expected":{"unordered_dimensions":[6,8],"area_model":"A(w)=w*(14-w)","positive_width_domain":"(0,14)","maximum_area":49,"maximizing_width":7,"fence_cost":104,"budget_margin":6},"mandatory_evidence":["Defined quantities and compatible units","Both dimension candidates interpreted as the same unordered rectangle","Quadratic optimization or equivalent argument","Cost and budget check","Model limitations and assumptions stated"]}}
UNIT_OVERRIDES = {"S09":"09","I09":"05","U01":"03","R04":"02","R01":"09","R02":"09","R08":"09",
 "M01":"01","M02":"02","M03":"09","M04":"03","M05":"02","M06":"03","M07":"02","M08":"06","M09":"01"}
BRIDGES = {
 "N":"Use quantity, place value, equal units or an inverse operation before relying on a remembered rule.",
 "A":"Connect the symbol or grouping to the quantity or operation it represents.",
 "E":"Treat a grouped expression as a quantity; preserve its value while changing its form.",
 "Q":"Connect each operation to an equivalent equation and check the original relation.",
 "I":"Connect the inequality to ordered values and test boundary inclusion.",
 "C":"Connect input, output, domain and the same relationship across representations.",
 "L":"Connect equal input intervals to a constant output change with units.",
 "S":"Treat every equation or inequality as a constraint that must hold simultaneously.",
 "X":"Use repeated factors and inverse operations to derive the relevant exponent or root relationship.",
 "P":"Use distribution and matching powers; keep every partial product visible when diagnosing.",
 "F":"Treat factoring as an equivalence that must survive expansion; product and sum conditions are separate.",
 "U":"Connect equivalent quadratic forms to roots, vertex, symmetry and the stated solving goal.",
 "G":"Connect equal time intervals to a repeated multiplier and distinguish that from a repeated amount.",
 "R":"Keep exact value, number system, principal-root conventions and original domain restrictions explicit.",
 "D":"Connect each statistic or display to the actual observations and the question being asked.",
 "M":"Define the quantities, model the relation, check mathematics and then check contextual feasibility.",
 "H":"State the added mathematical structure and its domain before transferring a familiar method."}
REQUIRED_CAPABILITIES = {
 "scalar":["exact_numeric_or_declared_tolerance"],"fraction":["exact_rational_equivalence"],
 "quantity":["numeric_equivalence","units_and_precision_review"],
 "expression":["symbolic_equivalence_with_domain","requested_form_review"],
 "equation":["equation_semantics_with_domain"],"solution_set":["complete_solution_set_with_domain"],
 "interval":["interval_endpoint_and_union_semantics"],
 "ordered_pair":["ordered_coordinate_semantics"],"point_set":["complete_intersection_set"],
 "ordered_list":["ordered_exact_values"],"table":["structured_table_semantics"],
 "graph_spec":["graph_renderer_or_reviewable_graph_input","graph_rubric_review"],
 "reasoning":["reasoning_rubric_review"],"classification":["classification_with_required_justification"],
 "model":["model_structure_units_domain_review"],"structured_response":["structured_response_rubric_review"]}
ROLE_LIMITS = {
 "N":"The foundation floor is explicit. Do not infer deficits below the loaded map or require a blanket foundation pretest.",
 "A":"Notation knowledge does not by itself demonstrate later algebraic reasoning.",
 "E":"Equivalence and the requested form are separate judgments. A few sampled values do not prove an identity.",
 "Q":"Exclude variable-denominator and nonlinear cases unless the item is explicitly bound to their extension skills.",
 "I":"Do not replace a solution set by one example value. Preserve strictness and domain feasibility.",
 "C":"Graph-based performance requires actual accessible graph evidence; no text-only proxy may silently count as graph construction.",
 "L":"Do not infer a line from finite data unless a linear-model assumption or a model-fitting task is explicit.",
 "S":"A valid pair must satisfy every constraint; methods and representations are alternatives rather than a compulsory common sequence.",
 "X":"State nonzero-base restrictions and distinguish a principal root from all solutions of a power equation.",
 "P":"Core work emphasizes structural polynomial operations and low-degree applications; longer products remain an optional course variant.",
 "F":"A finite failure to find integer factors does not prove that no factorization exists in every number system.",
 "U":"Core solution evidence is over the real numbers. Complex construction is explicitly optional H07.",
 "G":"Positive-base exponential models only in core; logarithms and rational-exponent subperiod methods remain optional extensions.",
 "R":"Simplification does not enlarge the original domain. Rational and radical equation solving is optional here.",
 "D":"Use the declared quartile and population-SD conventions. Association or a fitted line does not establish causation.",
 "M":"Model validity is conditional on assumptions; a mathematically evaluable input need not be feasible.",
 "H":"Optional extensions do not block the declared Algebra 1 profile. No claim of complete Algebra 2 coverage is made."}

def conditional(when,ids,reason):
 return {"when":when,"requires":ids.split(),"rationale":reason,"status":"AUTHOR_DESIGNED_NOT_EMPIRICALLY_CALIBRATED"}
CONDITIONAL = {
 "N03":[conditional("item uses signed fractions","N09","Fraction units must be available.")],
 "N04":[conditional("item uses signed fractions","N09","Signed addition and common units are distinct demands.")],
 "N05":[conditional("item uses signed fractions","N10","Separate sign reasoning from fraction multiplication.")],
 "N06":[conditional("item uses signed fractions","N10","Separate the reciprocal operation from quotient sign.")],
 "N07":[conditional("item contains powers","X01","The exponent is a mathematical operation, not an extra coefficient."),
        conditional("item contains signed operations","N03 N04 N05 N06","Probe only the operations present in the item."),
        conditional("item contains fractions","N09 N10","Fraction arithmetic is an item-specific demand.")],
 "A09":[conditional("substitution input is negative","N03 N05","Substitute the whole signed quantity.")],
 "E07":[conditional("item contains fractional factors","N09 N10","Fraction operations support the declared variant.")],
 "E08":[conditional("result requires signed constant arithmetic","N03 N04","Do not diagnose sign distribution from a pure arithmetic error.")],
 "Q10":[conditional("fractional-coefficient variant","N09 N10","Use the fraction route."),
         conditional("decimal-coefficient variant","N11","Use the decimal route.")],
 "Q13":[conditional("isolating the variable divides by a parameter","R04","State the nonzero-parameter restriction.")],
 "C05":[conditional("relation is supplied as a graph","C02","Graph reading must be available.")],
 "C08":[conditional("domain uses a variable denominator","R04","Exclude undefined inputs.")],
 "C14":[conditional("changes are fractional","N09 N10","Exact rational differences and quotients are required.")],
 "C15":[conditional("quadratic features must be derived rather than supplied","U05","Keep a transformation task separate from a vertex-computation task.")],
 "C17":[conditional("comparison requires slopes from point data","L02","Rates must be derived."),
         conditional("comparison includes exponential behavior","G04","The unfamiliar family cannot be inferred secure."),
         conditional("comparison includes quadratic extrema","U05","The feature must be understood.")],
 "C18":[conditional("the root graph is transformed","C15","Apply a transformation to the correct base domain.")],
 "C19":[conditional("a combined model contains an exponential part","G02","Interpret the component model."),
         conditional("the common domain excludes inputs","C08 R04","Intersection of component domains matters.")],
 "C20":[conditional("a quadratic rule requires derived intercepts and vertex","U05 U09","An algebraic feature task requires those specific operations."),
         conditional("features are inferred from an exponential rule","G04","Use the correct exponential family.")],
 "I07":[conditional("the relation is an OR rather than AND","I02","The item requires a union rather than an intersection.")],
 "L08":[conditional("the two points have equal x-coordinates","L11","Represent the vertical line correctly.")],
 "L12":[conditional("an exact comparison threshold must be solved","Q08","Solve the equality before interpreting the alternatives.")],
 "S07":[conditional("symbolic solution by substitution is selected","S03","Use a legitimate solving route."),
         conditional("symbolic solution by elimination is selected","S04","Use a legitimate solving route."),
         conditional("graphical solution is selected","S02","Use a legitimate solving route.")],
 "X02":[conditional("a negative exponent must be evaluated","X08","Distinguishing notation is not the same as evaluating its reciprocal.")],
 "U08":[conditional("fractional coefficients must first be cleared","Q11 N10","Keep the equation equivalent before using the declared factoring route.")],
 "G03":[conditional("the exponent is a negative integer","X08","Use reciprocal powers.")],
 "G04":[conditional("negative integer inputs must be plotted","X08","Evaluate reciprocal values correctly.")],
 "G06":[conditional("two observations have a multi-unit time gap requiring roots","H06","This is an optional harder fitting variant; it cannot block the core integer-step profile.")],
 "G07":[conditional("approximate intersections are requested","C20","Use graph features and explicit technology tolerance.")],
 "G11":[conditional("a higher-degree polynomial model is compared","P02","Identify its growth structure without assuming a linear model.")],
 "G12":[conditional("rational-exponent subperiod conversion is requested","H06","Optional variant; core time-scale tasks use integer changes.")],
 "R05":[conditional("a quadratic must be factored to expose common factors","F04","Do not cancel terms.")],
 "R06":[conditional("a factorable quadratic is solved by factoring","U08","Use the selected quadratic route."),
         conditional("a quadratic is solved with the formula","U12","Use the selected quadratic route.")],
 "R07":[conditional("squaring produces a factorable quadratic","U08","Solve the generated equation before checking original-domain candidates.")],
 "H01":[conditional("absolute-value inequality is assessed","I07","Use the correct union or intersection.")],
 "H03":[conditional("the inverse requires domain restriction","C08","A relation is not automatically a function inverse.")],
 "H04":[conditional("transforming rational functions","H09","Preserve holes, asymptotes and the restricted domain.")],
 "H10":[conditional("a rational nonlinear system is introduced","H09 R06","The broader extension requires rational-domain control.")]}
ALTERNATIVES = {
 "Q10":[{"path_id":"fractions","all_of":["N09","N10"]},{"path_id":"decimals","all_of":["N11"]}],
 "S09":[{"path_id":"graph","all_of":["S02","U04"]},
        {"path_id":"substitution_then_factoring","all_of":["S03","U08"]},
        {"path_id":"substitution_then_formula","all_of":["S03","U12"]}],
 "U08":[{"path_id":"monic","all_of":["F04"]},{"path_id":"nonmonic","all_of":["F05"]},
        {"path_id":"difference_of_squares","all_of":["F06"]},{"path_id":"perfect_square","all_of":["F07"]}]}
ANCHOR_EXTRA = {
 "A09":["N03","N05"],"E08":["N03","N04"],"L12":["Q08"],
 "S07":["S03"],"S09":["S03","U08"],"G04":["X08"],
 "C17":["L02"],"C20":["U05","U09"],"U12":["R02"],
 "R05":["F06"],"R07":["U08"]}
VARIANTS = {
 "E12":["Provide a valid identity proof.","Produce a counterexample to a false identity.","Explain why a confirming example alone is insufficient."],
 "Q12":["Unique solution.","No solution.","All real numbers, with a true constant equality."],
 "Q14":["Nonzero parameter.","Zero coefficient and zero constant.","Zero coefficient and nonzero constant."],
 "C08":["Finite table domain and range.","Continuous contextual domain.","Discrete contextual domain and a representation with a restriction."],
 "C12":["Piecewise linear branches.","A step function.","Included and excluded branch endpoints."],
 "C15":["Vertical shift of a known graph.","Horizontal translation of a known graph.","Simple reflection or vertical scale with input-output tracking."],
 "C17":["Compare intercepts or initial values.","Compare rates.","Compare domain, extrema or other relevant features across representations."],
 "C18":["Square-root graph and nonnegative input domain.","Cube-root graph including negative inputs.","Domain and range of a simple translated root graph."],
 "C19":["Add familiar linear functions.","Combine a familiar function with a constant.","Interpret a shifted exponential model after G02 is available."],
 "C20":["Increasing and decreasing intervals.","Positive and negative intervals and zeros.","Extrema and end behavior in a stated model family."],
 "S04":["Explain the equation addition.","Retain the other equation.","Explain reversibility of the replacement and verify the original system."],
 "S06":["One intersection.","Distinct parallel lines.","Coincident lines."],
 "S09":["Two real intersections.","One tangent intersection.","No real intersection; graphical and symbolic evidence compared."],
 "P10":["Addition/subtraction closure.","Multiplication closure by exponent structure.","Counterexample to division closure."],
 "U07":["Positive k with two real solutions.","k=0 with one real solution.","Negative k with no real solution."],
 "U12":["Two rational roots.","Irrational exact roots.","A repeated root and a no-real-root case, with number system explicit."],
 "U15":["Compare at least two valid methods.","Select an efficient method from expression structure.","Interpret and check contextual feasibility."],
 "U16":["Show the completing-the-square identity.","Include a!=0.","Retain both branches and the real-discriminant condition."],
 "G07":["Compare linear and exponential change across equal intervals.","Compare models across representations.","Estimate an intersection graphically with a declared tolerance."],
 "G09":["Explicit nth-term rule with stated indexing.","Recursive rule with initial condition.","Translate between the two and connect to a linear function."],
 "G10":["Explicit nth-term rule with stated indexing.","Recursive rule with initial condition.","Translate between the two and connect to an exponential function."],
 "G11":["Compare a specified exponential model with a linear model.","Compare with a specified quadratic model.","State the evidence or derivation supporting an eventual-growth claim."],
 "G12":["Integer changes of the input time scale.","Interpret the new factor.","Optional root-based subperiod conversion, explicitly extension-scoped."],
 "G13":["Derive a constant difference for a linear rule.","Derive a constant ratio for an exponential rule.","State the nonzero and positive-base assumptions."],
 "R08":["Rational plus rational and rational times rational.","Rational plus irrational and nonzero rational times irrational.","Counterexamples to invalid claims about two irrational numbers."],
 "D02":["Dot plot.","Histogram with declared bins.","Box plot with the declared quartile convention."],
 "D05":["Compare spread about a common mean.","Interpret population SD with units.","Use supplied technology output or compute with declared technology."],
 "D08":["Fit or interpret an approximate linear model.","Explain slope and intercept in context.","Check whether a linear fit is suitable and avoid unsupported extrapolation."],
 "D09":["Residual sign and magnitude.","Correlation coefficient from technology.","Explain that r is not a percent-correct score and does not establish causality."],
 "D11":["Joint frequency.","Marginal frequency.","Conditional frequency with the correct reference group."],
 "M02":["Linear contextual model.","Quadratic contextual model after the relevant skills are available.","Simple exponential contextual model."],
 "M09":["Stated decimal-place requirement.","Precision consistent with measurement limitations.","Distinguish exact algebra from rounded reported quantities."]}
ALIGNMENT_OVERRIDES = {
 "A04":["HSA.SSE.A.1.b"],"G02":["HSA.SSE.A.1.b"],
 "G07":["HSA.REI.D.11"],"C20":["HSF.IF.B.4"]}
SCOPE_NOTES = {
 "HSA.REI.B.4.b":"Core profile is real-root work. Complex-root construction is optional H07.",
 "HSA.REI.C.7":"Core S09 is one line and one quadratic. H10 is a related broader extension, not an alias.",
 "HSA.REI.D.11":"Core scope uses linear, quadratic and exponential comparisons/intersections; rational, logarithmic and other advanced families are not all required.",
 "HSF.IF.C.7.e":"Exponential portion only in the core. Logarithmic and trigonometric graphing are not claimed.",
 "HSF.IF.C.8.b":"Core integer time-scale cases; rational-exponent variants are optional H06/G12 work.",
 "HSA.SSE.B.3.c":"Core integer time-scale cases; subperiod/root variants require the optional rational-exponent bridge.",
 "HSF.BF.B.3":"Core basic familiar-function transformations; broader input scaling and symmetry are extended in H04.",
 "HSF.BF.B.4.a":"Core simple linear inverses and inverse meaning; broader composition and nonlinear inverses remain H03.",
 "HSA.APR.A.1":"Core operations and closure reasoning; longer products are optional P07.",
 "HSS.ID.B.6.a":"D08 supplies linear-fit work; G06 and M03 contribute model-family choice and exponential construction.",
 "HSS.ID.A.2":"Population-SD interpretation/technology and the declared quartile convention; no normal-curve inference."}
STANDARD_PREFIX_SOURCE = {prefix:"CCSS-"+p.replace("/","-") for p,title,prefix,ref in SOURCE_DOMAINS}

def source_for(code):
 for prefix,source in STANDARD_PREFIX_SOURCE.items():
  if code.startswith(prefix+"."): return source
 raise ValueError("Unresolved standard source: "+code)

def load_originals():
 if UPLOADED_SKELETON.exists():
  raw=UPLOADED_SKELETON.read_text()
  pairs=re.findall(r"^\|\s*([A-Z]\d{2})\s*\|\s*(.*?)\s*\|\s*$",raw,re.M)
  original=dict(pairs)
  if len(original)!=185: raise ValueError("Expected 185 original skill IDs.")
  (ROOT/"authoring"/"original_skill_id_snapshot.json").write_text(json.dumps(original,ensure_ascii=False,indent=2)+"\n")
  return original
 return json.loads((ROOT/"authoring"/"original_skill_id_snapshot.json").read_text())

def build():
 original=load_originals()
 rows=[]
 for p in sorted((ROOT/"authoring").glob("skill_specs*.psv")):
  for line in p.read_text().splitlines():
   if line and not line.startswith("id|"):
    fields=line.split("|")
    if len(fields)!=9: raise ValueError("Malformed record "+repr(fields[:2]))
    rows.append(fields)
 ids=[r[0] for r in rows]
 if len(ids)!=205 or len(set(ids))!=205: raise ValueError("Unexpected skill count or duplicate IDs.")
 skills=[]
 for sid,deps,codes,rtype,scope,performance,prompt,answer,probe in rows:
  statement=FORMATTING_NORMALIZATIONS.get(sid,original.get(sid,NEW_STATEMENTS.get(sid)))
  if not statement: raise ValueError("Missing stable statement "+sid)
  role="EXTENSION" if sid in EXTENSION_IDS else ("FOUNDATION" if sid[0] in FOUNDATION_DOMAINS else "CORE")
  dep_ids=[d for d in deps.split(",") if d]
  if sid=="Q10": dep_ids=["Q06"]
  all_codes=list(dict.fromkeys(codes.split(",")+ALIGNMENT_OVERRIDES.get(sid,[])))
  try: expected=json.loads(answer)
  except json.JSONDecodeError: expected=answer
  if sid in ANSWER_OVERRIDES: expected=ANSWER_OVERRIDES[sid]
  if sid=="N19": all_codes=["4.NF.B.3"]
  if sid=="U16":
   expected="For a!=0, multiply by 4a and rearrange to (2ax+b)^2=b^2-4ac. Taking both real branches when the discriminant is nonnegative gives x=(-b +/- sqrt(b^2-4ac))/(2a)."
  first=UNIT_OVERRIDES.get(sid,FIRST_UNIT.get(sid[0],"ON_DEMAND"))
  if role=="FOUNDATION": first="ON_DEMAND"
  if role=="EXTENSION": first="OPTIONAL"
  align=[]
  for code in all_codes:
   relation="RELATED_EXTENSION" if role=="EXTENSION" else ("PREREQUISITE_COMPONENT" if role=="FOUNDATION" else "CONTRIBUTION")
   align.append({"standard":code,"relation":relation,"source_id":source_for(code),
                 "scope_note":SCOPE_NOTES.get(code,"This node contributes part of the standard; no single-item or single-node full-standard equivalence is asserted.")})
  conceptual="Explain the relevant relation or reject a plausible invalid method; explanation depth follows the skill."
  transfer="Use a fresh instance with a meaningful structural, representational or contextual variation; do not count a revealed solution as independent."
  variants=VARIANTS.get(sid,["Representative constructed response.","A diagnostic contrast addressing the stated probe focus.","A fresh transfer or interpretation task within this node's bounds."])
  record={
   "skill_id":sid,"statement":statement,"domain_id":sid[0],"domain_name":DOMAIN_NAMES[sid[0]],
   "curricular_role":role,"required_in_reference_profile":role=="CORE",
   "first_instructional_unit":first,"added_in_version":VERSION if sid in NEW_STATEMENTS else "1.0-skeleton",
   "identity_note":"Original stable ID preserved; statement display normalized only where recorded." if sid in original else "New ID; no original ID renumbered.",
   "observable_performance":performance,
   "scope":{"included":scope,"guardrail":ROLE_LIMITS[sid[0]],"language_level":"Adapt reading demand independently of algebraic demand."},
   "prerequisites":{"all_of":dep_ids,"alternative_paths":ALTERNATIVES.get(sid,[]),
                    "conditional":CONDITIONAL.get(sid,[]),
                    "edge_status":"AUTHOR_DESIGNED_NOT_EMPIRICALLY_CALIBRATED",
                    "routing_semantics":"Probe relevant components when evidence warrants. These links do not require a blanket prerequisite pretest or establish the cause of an error."},
   "out_of_scope_redirects":[{"when":"A nonlinear inverse, composition, or one-to-one restriction is required","target_skill_id":"H03","reason":"This is an extension target rather than a prerequisite for simple linear inverses."}] if sid=="C16" else [],
   "alignment":align,
   "assessment":{"required_variants":variants,
                 "constructed_performance":performance,"conceptual_evidence":conceptual,
                 "transfer_requirement":transfer,"accepted_response_types":[rtype],
                 "required_checker_capabilities":REQUIRED_CAPABILITIES[rtype],
                 "progress_policy_ref":"POLICY-IMMEDIATE-3",
                 "anchor_item":{"item_id":"ANCHOR-"+sid+"-v1.1","primary_skill_id":sid,
                   "role":"ILLUSTRATIVE_ANCHOR_NOT_CALIBRATED_TEST_ITEM",
                   "prompt":prompt,"response_type":rtype,"expected_answer":expected,
                   "additional_prerequisites_for_this_anchor":ANCHOR_EXTRA.get(sid,[]),
                   "answer_review_status":"AUTHOR_REVIEWED; see validation_report.json for separately automated mathematical checks.",
                   "coverage_limit":"One anchor does not cover this node's full scope or establish mastery."}},
   "diagnostic_guidance":{"status":"TESTABLE_DESIGN_GUIDANCE_NOT_LEARNER_FACT",
                          "probe_focus":probe,
                          "interpretation_rule":"State the observed response relationship first. A proposed cause remains a hypothesis until discriminating evidence supports the bounded claim."},
   "instructional_guidance":{"bridge":BRIDGES[sid[0]],
                            "method_freedom":"The LLM may choose or derive a valid explanation, representation or example inside the node's scope.",
                            "repair_return":"Retain the original target; after immediate repair, verify the parent with fresh independent evidence.",
                            "research_basis":["IES-ALGEBRA"],
                            "status":"AUTHOR_DESIGNED_APPLICATION_OF_GENERAL_GUIDANCE"},
   "implementation_requirements":{"graph_evidence_required":rtype=="graph_spec",
                                  "reasoning_review_required":rtype in {"reasoning","model","structured_response","classification","graph_spec"},
                                  "production_scoring_implemented":False}}
  if sid=="U16":
   record["assessment"]["required_reasoning_conditions"]=["a!=0","Equivalent completing-the-square identity","Both square-root branches","Real-domain discriminant condition or an explicitly declared complex extension","Do not use sqrt(a^2)=a for unrestricted real a."]
  skills.append(record)
 skills.sort(key=lambda s:(DOMAIN_ORDER.index(s["domain_id"]),int(s["skill_id"][1:])))
 counts=Counter(s["curricular_role"] for s in skills)
 units=[]
 for uid,title,weeks,letters,goal in UNIT_ROWS:
  assigned=[s["skill_id"] for s in skills if s["first_instructional_unit"]==uid]
  units.append({"unit_id":uid,"title":title,"planning_weeks":weeks,
                "planning_status":"AUTHOR_PROPOSED_ADAPTABLE_NOT_RESEARCH_DOSAGE",
                "learning_goal":goal,"primary_skill_ids":assigned,
                "cross_cutting_skills":[s for s in ["M01","M02","M03","M04","M05","M06","M07","M08","M09"]],
                "revisit_skill_ids":(["M01","M02","M03","M04","M05","M06","M07","M08","M09","C17","G11","U15","D08"] if uid=="10" else (["U01","C15","C17","C20"] if uid=="09" else [])),
                "illustrative_unit_checkpoint":UNIT_EXAMPLES[uid],
                "assessment_blueprint":{"entry":"A target-level task and selective prerequisite probes.",
                  "formative":"Worked-example critique, representation checks and a short constructed exit task.",
                  "independent":"Fresh items covering the unit's required variants; hints remain assisted.",
                  "transfer":"One integrated unfamiliar application plus explanation of method, domain and limits."}})
 metadata={
  "curriculum_id":"ACE-ALGEBRA1","curriculum_version":VERSION,
  "schema_version":"ace-curriculum-1.0","architecture_compatibility":"ACE architecture 1.0",
  "title":"Algebra 1 Curriculum and Prerequisite Map",
  "research_date":RESEARCH_DATE,"language":"English","scope":"Declared US-style Algebra 1 reference profile with on-demand numerical foundations and optional Algebra 2/course-dependent extensions.",
  "review_status":"RESEARCH_INFORMED_AUTHOR_DRAFT",
  "original_skill_ids_preserved":185,"new_skill_ids":sorted(NEW_STATEMENTS),
  "skill_count":len(skills),"domain_count":len(DOMAIN_NAMES),"role_counts":dict(counts),
  "coverage_claim":"A complete authored map for the explicitly declared reference profile. Standards crosswalks are scoped author judgments, not certification, local-school approval or empirically calibrated placement.",
  "not_claimed":["Noon-specific curriculum alignment","NWEA item/RIT calibration","Validated diagnostic accuracy","Durable mastery from a short streak","Complete Algebra 2 coverage","A production scoring API or working graph interface"],
  "prerequisite_graph_status":"Author-designed instructional hypotheses; referential integrity and cycles are checked separately.",
  "runtime_warning":"The curriculum describes required checker capabilities. It does not implement those checkers."}
 profile={
  "profile_id":"US_STYLE_A1_REFERENCE-v1.1","name":"Algebra 1 reference profile",
  "basis":["CCSS high-school content references with explicit course-scope restrictions","Illustrative Mathematics Algebra 1 as a published scope comparator"],
  "course_allocation_status":"AUTHOR_DESIGNED; school and jurisdiction allocations vary",
  "entry_targets":[{"skill_id":sid,"label":label} for sid,label in [("Q06","Two-step equations"),("Q09","Multi-step equations"),("Q14","Literal-coefficient equations"),("I06","Linear inequalities"),("C08","Domain and range"),("C20","Interpreting function features"),("L06","Constructing linear models"),("S03","Systems of equations"),("G06","Exponential models"),("F04","Factoring quadratics"),("U08","Solving by factoring"),("U12","Quadratic formula"),("D06","Comparing distributions"),("D09","Residuals and correlation"),("M02","Building a model")]],
  "required_core_skill_ids":[s["skill_id"] for s in skills if s["curricular_role"]=="CORE"],
  "foundation_policy":"Assess on demand from target evidence. Foundation nodes are not a compulsory opening course.",
  "extension_policy":"Optional nodes and optional conditional variants do not block core completion.",
  "completion_policy":{"kind":"OPERATIONAL_COURSE_EVIDENCE_NOT_PSYCHOLOGICAL_CERTIFICATION",
    "requirements":["Evidence covering the declared core outcomes and required variants.",
                    "Constructed performance and applicable explanation/representation criteria.",
                    "Unit-level transfer evidence and recorded unresolved boundaries.",
                    "An explicit later-review policy before using a durable-mastery label."],
    "no_shortcut":"A standard tag, a correct anchor or a single numerical answer cannot independently complete a skill or course."},
  "course_scope_exclusions":["General logarithmic solving and graphing","Trigonometric functions","Normal-distribution inference","General rational-function analysis","Complete complex-number curriculum","All possible local Algebra 1 additions"],
  "mathematical_practices":{"policy":"Integrate all eight CCSS mathematical practices; do not use practice labels as substitute evidence.",
                           "observable_emphases":["Interpret the situation","Define symbols and units","Justify transformations","Choose and compare methods","Use representations","Attend to domains and precision","Test reasonableness","State model limitations"]}}
 policies={
 "POLICY-IMMEDIATE-3":{"independent_correct_required":3,"consecutive":True,
   "item_requirements":["Accepted item and applicable checking result","Fresh problem","No item-specific hint or revealed solution","Required structural/representational variation appropriate to the node"],
   "result_label":"IMMEDIATE_CRITERION_MET","research_status":"ENGINE_DESIGN_CHOICE_NOT_A_VALIDATED_MASTERY_THRESHOLD",
   "transfer":"Record separately","retention":"Record separately from a later event","invalid_item_effect":"Remove from success/failure counts and re-evaluate dependent claims"},
 "diagnostic":{"classification":"An answer is observed evidence; a cause is a bounded hypothesis.","single_probe_limit":"A correct probe means the earlier error was not reproduced, not that a slip is proven.","repeated_error_limit":"Repeated errors support a pattern but do not uniquely establish an internal mechanism."},
 "representation":{"policy":"Graph or drawing tasks require actual supported representations or teacher review; text-only numeric substitutes do not count as graphical performance."},
 "statistics":{"quartiles":"Median of lower/upper halves; exclude the overall median when n is odd unless the item declares another convention.",
               "standard_deviation":"Population SD for core examples unless explicitly labeled otherwise.",
               "technology":"Regression, correlation, SD and graph construction may require declared tools.",
               "correlation":"Association is not causation and r is not a percent-correct score."},
 "overlap":{"policy":"One item has one primary skill. Secondary tags may describe components, but do not automatically promote related core/extension nodes.",
            "pairs":[["C15","H04"],["C16","H03"],["S09","H10"],["G12","H06"]]}}
 curriculum={"metadata":metadata,"reference_profile":profile,"policies":policies,
             "sources":SOURCES,"domains":[{"domain_id":k,"name":v} for k,v in DOMAIN_NAMES.items()],
             "units":units,"flex_review_weeks":3,"skills":skills}
 (ROOT/"algebra-map.json").write_text(json.dumps(curriculum,ensure_ascii=False,indent=2)+"\n")
 (ROOT/"unit-assessment-blueprints.json").write_text(json.dumps({"status":"AUTHOR_DESIGNED_ILLUSTRATIONS_NOT_CALIBRATED_UNIT_TESTS","units":[{"unit_id":u["unit_id"],"title":u["title"],"checkpoint":u["illustrative_unit_checkpoint"]} for u in units]},ensure_ascii=False,indent=2)+"\n")
 (ROOT/"curriculum-sources.json").write_text(json.dumps(SOURCES,ensure_ascii=False,indent=2)+"\n")
 graph={"curriculum_id":metadata["curriculum_id"],"curriculum_version":VERSION,
        "semantics":"Hard component links, alternative method paths and item-conditional links are distinct; no forced whole-graph pretest.",
        "edge_status":metadata["prerequisite_graph_status"],"nodes":[s["skill_id"] for s in skills],"edges":[]}
 for s in skills:
  for pre in s["prerequisites"]["all_of"]:
   graph["edges"].append({"from":pre,"to":s["skill_id"],"type":"COMPONENT"})
  for path in s["prerequisites"]["alternative_paths"]:
   for pre in path["all_of"]:
    graph["edges"].append({"from":pre,"to":s["skill_id"],"type":"ALTERNATIVE_PATH","path_id":path["path_id"]})
  for rule in s["prerequisites"]["conditional"]:
   for pre in rule["requires"]:
    graph["edges"].append({"from":pre,"to":s["skill_id"],"type":"ITEM_CONDITIONAL","when":rule["when"]})
 (ROOT/"prerequisite-graph.json").write_text(json.dumps(graph,ensure_ascii=False,indent=2)+"\n")
 csv_fields=["skill_id","statement","domain","role","required_core","first_unit","all_of","alternative_paths","conditional","standards","observable_performance","scope","response_type","anchor_prompt","anchor_expected_answer","diagnostic_focus"]
 with (ROOT/"skill-inventory.csv").open("w",newline="",encoding="utf-8-sig") as f:
  w=csv.DictWriter(f,fieldnames=csv_fields);w.writeheader()
  for s in skills:
   a=s["assessment"]["anchor_item"];p=s["prerequisites"]
   w.writerow(dict(zip(csv_fields,[s["skill_id"],s["statement"],s["domain_name"],s["curricular_role"],s["required_in_reference_profile"],s["first_instructional_unit"],",".join(p["all_of"]),json.dumps(p["alternative_paths"],ensure_ascii=False),json.dumps(p["conditional"],ensure_ascii=False),",".join(a["standard"] for a in s["alignment"]),s["observable_performance"],s["scope"]["included"],a["response_type"],a["prompt"],json.dumps(a["expected_answer"],ensure_ascii=False),s["diagnostic_guidance"]["probe_focus"]])))
 by_standard=defaultdict(list)
 for s in skills:
  for a in s["alignment"]:by_standard[a["standard"]].append({"skill_id":s["skill_id"],"role":s["curricular_role"],"relation":a["relation"]})
 crosswalk={"mapping_status":"AUTHOR_MAPPED_SCOPED_CONTRIBUTIONS_NOT_EXTERNAL_CERTIFICATION",
             "standards":[{"standard":code,"source_id":source_for(code),"scope_note":SCOPE_NOTES.get(code,"Use each linked node's stated scope; a node contributes evidence and does not automatically establish full-standard proficiency."),"contributions":by_standard[code]} for code in sorted(by_standard)]}
 (ROOT/"standards-crosswalk.json").write_text(json.dumps(crosswalk,ensure_ascii=False,indent=2)+"\n")
 additions=[{"skill_id":s["skill_id"],"statement":s["statement"],"reason":"A missing explicit performance or foundation floor identified while building the scope and coverage crosswalk."} for s in skills if s["skill_id"] in NEW_STATEMENTS]
 (ROOT/"change-log.json").write_text(json.dumps({"from":"185-skill v1.0 summary","to":VERSION,"preserved_ids":sorted(original),"new_nodes":additions,
  "formatting_only_normalizations":FORMATTING_NORMALIZATIONS,
  "classification_decisions":{"P07":"Optional longer-product practice; polynomial closure reasoning is core P10.",
                              "G08":"Optional common-base exponential solving.",
                              "H03":"Broader composition/inverse extension; simple inverses separated as C16.",
                              "H04":"Broader transformation extension; basic transformations separated as C15.",
                              "H10":"Broader nonlinear systems extension; line-quadratic core separated as S09."},
  "important_boundary":"The earlier summary's referenced dependency files were not supplied; all edges in this package are newly authored and explicitly labeled."},ensure_ascii=False,indent=2)+"\n")
 print(json.dumps({"skills":len(skills),"roles":dict(counts),"sources":len(SOURCES),"edges":len(graph["edges"]),"standards":len(by_standard)},ensure_ascii=False))
 return curriculum

if __name__=="__main__":build()

