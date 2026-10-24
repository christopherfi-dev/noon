# Capability matrix — Algebra Engine AI tutor (round 10)

Generated from the tested runtime registry (`app/private/src/Capability/CapabilityRegistry.php`) by `app/tools/gen_capability_matrix.php`. Curriculum ACE-ALGEBRA1 1.1.0 (205 skills, all browsable); architecture 1.8.

Runtime status counts: AVAILABLE_SCORED 2, PARTIAL_COMPONENTS 8, UNAVAILABLE 195. No all-skills operational claim is made.

**Live provider smoke test:** Scripted learners ran against DeepSeek in rounds 7, 8 and 9 (test-results/live/round7/, round8/, round9/); they are runs of scripted learners: not deployment validation, not teaching quality and not evidence of learning. Earlier live runs (rounds 3–5) used the retired state machine and say nothing about this tutor. Every test result in this matrix is from mock mode (simulated tutor).

## Beta skills

| Skill | Title | Status | Check patterns (recipe, response type) | Checkers | Criterion | Remaining | Tests |
|---|---|---|---|---|---|---|---|
| N03 | Add positive and negative numbers | PARTIAL_COMPONENTS | constructed (SIGNED_ADD, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore` |
| N04 | Subtract signed numbers | PARTIAL_COMPONENTS | constructed (SIGNED_SUBTRACT, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore` |
| N05 | Multiply signed numbers | PARTIAL_COMPONENTS | constructed (SIGNED_MULTIPLY, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Unit/Tutor/LearnerInstrumentsTest.php`<br>`tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore` |
| N06 | Divide signed numbers | PARTIAL_COMPONENTS | constructed (SIGNED_DIVIDE, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore`<br>`tests/Unit/Tutor/LearnerInstrumentsTest.php` |
| N18 | Multiply and divide whole numbers | PARTIAL_COMPONENTS | constructed (WHOLE_MULTIPLY_DIVIDE, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Integration/OwnerCasesTest.php`<br>`tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore` |
| E06 | Apply the distributive property with positive factors | AVAILABLE_SCORED | constructed (POSITIVE_DISTRIBUTION, expression)<br>contrast (DISTRIBUTION_CONTRAST, classification)<br>transfer (DISTRIBUTION_TRANSFER, expression) | polynomial identity + expanded form, exact option set | 3 check questions correct in a row without help, covering a solve or expansion, a choice question and a new form (one slip may pause the count; help resets it) | Written explanations are not assessed; choice check questions come only from the contrast recipes, so rejecting an invalid method is checked by selection only. | `tests/Integration/TutorLoopTest.php::testJourneyStepsBackAndReturnsWithGoalsComputedByCode`<br>`tests/Integration/OwnerCasesTest.php`<br>`tests/Unit/Items/TemplatesTest.php` |
| Q04 | Solve one-step addition/subtraction equations | PARTIAL_COMPONENTS | constructed (ONE_STEP_ADD, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore` |
| Q05 | Solve one-step multiplication/division equations | PARTIAL_COMPONENTS | constructed (ONE_STEP_MULTIPLY, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore`<br>`tests/Unit/Tutor/ProblemInstrumentsTest.php` |
| Q06 | Solve two-step equations | PARTIAL_COMPONENTS | constructed (TWO_STEP, scalar) | exact rational | 3 check questions correct in a row without help, numeric part only (one slip may pause the count; help resets it) | Conceptual evidence (explain or reject an invalid method) not implemented. No new-form check pattern. The goal covers the numeric part only. | `tests/Integration/CheatingTest.php`<br>`tests/Integration/TutorLoopTest.php::testEveryBetaSkillCanStartAndScore` |
| Q07 | Solve equations containing parentheses | AVAILABLE_SCORED | constructed (GROUPED_LINEAR, scalar)<br>contrast (GROUPED_FIRST_STEP_CONTRAST, classification)<br>transfer (GROUPED_LINEAR_TRANSFER, scalar) | exact rational, exact option set | 3 check questions correct in a row without help, covering a solve or expansion, a choice question and a new form (one slip may pause the count; help resets it) | Written explanations are not assessed; choice check questions come only from the contrast recipes, so rejecting an invalid method is checked by selection only. | `tests/Integration/TutorLoopTest.php::testJourneyStepsBackAndReturnsWithGoalsComputedByCode`<br>`tests/Unit/Tutor/ProblemInstrumentsTest.php`<br>`tests/Unit/Items/TemplatesTest.php` |

## Heuristics and their limits

| Item | Note |
|---|---|
| Answers (round 6 §2.1) | Code computes every answer: from a recipe, from the AI's spec (linear_equation, expand, signed_arithmetic, numeric_step, and equation_step for practice: the equation after one step, round 9 §4.5) or from the slipped check problem for a retry. A proposal whose stated answer differs from code's, that has no unique solution, whose numbers are out of range (|n| ≤ spec_number_limit, answers ≤ spec_answer_limit, denominators ≤ spec_denominator_limit), or (equation_step) whose operand is zero for multiply or divide, is rejected. There is no rule against an answer that appears in the problem (round 9 §3.1). An equation_step entry is STEP_CORRECT (each side equal to the expected side as a polynomial, sides in either order), EQUIVALENT_NOT_STEP (same solution set, recorded not yet correct with that observation) or NOT_EQUIVALENT; an entry that is not an equation is a request to rewrite it. The AI never states a verdict; text that contradicts code's verdict is logged (round 7 §4), never changes the verdict, and is shown unchanged after code's own result line. |
| Check problems (round 6 §2.3, §4.2, §5; round 7 §5, §6; round 8 §3; round 9 §3, §4.3; round 10 §4.2, §4.3) | A check problem must match a recipe of its skill (the recipes used in reverse: exact structure, then the recipe's own validate()), use a number set not used in this session, and is worded by code. A problem or envelope that still fails after one repair holds the turn; nothing is substituted (round 9 §3.1). Prepared problems prefer parameters whose answer is not among the problem's numbers and accept the overlap when none fit. Choice check problems come only from the contrast recipes. The card reads "Check question: on your own". HEURISTIC audit before the card, logged as signals only (round 9 §3.2): hint, method, strategy or reminder phrases, neutral invitations, numbers shared with the check, and the check's answer with where it may come from. Help during an open check (round 9 §3.3, §4.3; round 10 §4.2–§4.4): only a reply whose move is HELP or HINT records help, before the reply is shown; the check becomes practice, the run starts again when the help is recorded, and the check's later answer counts neither for nor against. Such a reply may carry a practice problem, shown after it; a helped problem still open when a new problem is posed waits as the original (ORIGINAL_PROBLEM). Only an unanswered check without help blocks a new check. The original closes when it is answered, completed through connected equation steps (ORIGINAL_COMPLETED_WITH_HELP, no goal credit), set aside by an accepted fresh check on the target skill (RETURN_FRESH, ORIGINAL_SET_ASIDE) or by a newer helped problem, or when the session ends; practice never sets it aside, and skipping, "I haven't learned this yet" and a topic change leave it waiting. The context carries TARGET and ROUTE (DETOUR, RETURN_ORIGINAL, RETURN_TO_SKILL, RETURN_FRESH, ORIGINAL_SET_ASIDE, ORIGINAL_COMPLETED_WITH_HELP); the teacher view reports the route as evidence, not a score. A reply with no help move whose text contains the check's expression, a number from it or its answer, and a reply whose move is ANSWER_QUESTION or EXPLAIN, are logged as signals; nothing is held. Only a verdict conflict raised in the envelope holds an attempt. |
| Reading an entry (round 6 §4.1; round 7 §7) | HEURISTIC. Code reads standard notation itself, a number written in words (up to 999), and "3 = x" as x = 3. Every other entry (words, "×", "times", a letter standing alone between spaces, or anything code cannot read) is read by the AI. Code checks the reading: only numbers the student wrote (digits or number words), only the problem's variables the student wrote, at most 80 characters, and the drop check: the reading must re-express one stretch of the entry token for token, and nothing joined to it by an operation sign or word (plus, minus, times, divided by, over, negative), implicit multiplication or grouping may be left out; a change of grouping or operand order is rejected. Content joined only by "=" or a relation word may be left out (declared scope). When the reading fails, or plausible readings would be scored differently, nothing is graded and the student sees the AI's clarifying question. The shown result says "Read as …". |
| Slips (round 6 §4.4, §5.5; round 9 §4.4) | Code decides SLIP.eligible_for_pause. Expansions: every term except the constant matches, and the constant is a times-table neighbour of the product a × b (a × (b ± 1) or (a ± 1) × b) or differs from it in exactly one digit by exactly 1, and is none of a + b, b, a or −(a × b): for 7(x + 5), 7x + 25 and 7x + 30 are eligible, 7x + 95, 7x + 12 and 7x − 35 are not; a product with a factor of 1 never is. Signed multiplication and division: the right sign and a times-table neighbour of the right size. A bare value of x is never eligible; the off-by-one signatures stay observations. The detail sent to the tutor states what the answer did ("The other terms match; the constant 25 is a near miss of 7 × 5 = 35."). After an eligible slip the tutor may ask one micro-question (code checks it) and offer a retry; the first slip in a run pauses the count, a second resets it; the slipped answer and the retry never count. |
| Text guards (round 6 §2.6; round 7 §4; round 9 §3.2, §4.6; round 10 §4.8) | HEURISTIC DETECTORS; every one logs a signal and none blocks, holds or changes a count. Every learner-visible AI text is checked for the answer to an open or newly posed problem (as a number, a standalone number word, an equivalent polynomial span, a standalone option letter or the key option's text; "one" as a determiner (including "one to try", "one more" and "one of"), "two-step", digits inside codes such as Q07 and letters inside a formula such as a(b + c) are not read as answers), internal vocabulary (expected answer, answer key, host, verdict, criterion and the like), skill codes (Q07, E06), a mastery or goal claim (including "the goal for … is met" and "the … goal is met"), a claim that work was done with or without help (not flagged in the last sentence before a check card), and a contradiction of code's verdict. Two counters log only: RESOLVE_AFTER_CORRECT (correct-answer feedback with an equation in the text on the way from the problem to its answer, or for linear equations a digit value of one of its sub-expressions at the solution, other than the problem, the student's answer and the student's numbers; substitution checks such as "4·10 + 6 = 46 checks out" are left out) and VALUE_QUESTION_IN_TEXT (a question sentence with a value cue and some mathematics, or a request for an expression such as "what is … written as", while no problem is posed; questions that ask for an operation are left out). Each finding is recorded with the exact text, the guard and the problem and response it relates to; the student sees the text unchanged. Paraphrased answers are not detected. Only envelope and problem-acceptance failures are repaired (once); if the repair fails too, the turn is held with a retry. |
| "I'm stuck" | Two choices: a hint, or "I haven't learned this yet" (round 7 §6.4). Help is recorded before any AI call and restarts the run then; a later answer to that check counts neither for nor against (round 10 §4.4). Typed text is never classified as stuck: "idk" or "???" is a message to the tutor (round 7 §6.3); help is then recorded only if the tutor's reply carries a HELP or HINT move (round 9 §3.3). |
| Error signatures | Deterministic matching of each recipe's known error patterns (e.g. the factor multiplied only the x). A match is recorded as an observation, never a confirmed cause; the AI sees it and chooses what to do. |
| Calls per turn (round 6 §3) | One AI call per turn with the full context, built anew; two when an entry must be read first; one repair at most. The first turn of a session also makes the architecture-binding call. Reasoning per call (per_call): enabled at low effort for a reading, a wrong answer with no signature, or a free-text message about maths; disabled otherwise. Each call is logged with its purpose and reasoning setting. |
| Progress codes (round UI v3, U10) | A code is created with the first recorded answer of a session and its record is updated in the same database transaction as every later commit. The record holds, per skill, a status (goal met, needed help, unresolved, not in the beta), a date and observed mistake-pattern codes, plus the route target, the tutor's stated next focus and the next skill; no answers, names or addresses. The server stores only the sha256 of a code. 12 characters from 31 symbols give about 59.4 bits. One live session per code; entering it on another device takes the session over. Lookups: one generic message, at most 10 failures per hour per browser session. Records are deleted progress_retention_days (default 90) after their last use. Results from earlier sessions never count toward this session's goals. Codes do not carry between the beta and this install (separate databases). |
| Downloaded report (round UI v3, U9) | One HTML file (algebra-report-YYYY-MM-DD.html) with inline CSS, no scripts and no external requests; the four learner headings, the teacher details expanded, the audit list, the date and the progress code. Every sentence is written by code from the gradebook. |

## Owner rules

| Rule | Note |
|---|---|
| Help restarts the run when it is recorded (round 5.1, P2; round 9 §3.3; round 10 §4.4) | Help recorded on an unanswered check ("I'm stuck", or a tutor reply with a HELP or HINT move during the check) restarts that skill's count of correct answers in a row at the moment it is recorded; the check's later answer counts neither for nor against, so there is no second reset. Help known only from a correction restarts the run at the answer. Round 6 records of an explanation or a typed stuck phrase count the same way. |
| One slip may pause the count (round 6 §5.5) | The first code-confirmed slip in a run pauses the count instead of resetting it; goal wording "3 correct without help (one slip may pause the count)". |

## Deferred — NOT IMPLEMENTED

| Item | Behaviour in this install |
|---|---|
| Teacher task links (create_task / task endpoints, signed public settings) | Endpoints return 422 FEATURE_NOT_IN_BETA. |
| "Help me find a starting point" orientation | start with entry_route UNSURE returns 422 FEATURE_NOT_IN_BETA. |
| Free-text topic mapping | The learner picks a topic from the beta list (CHANGE_TOPIC takes a listed skill only); the tutor cannot add a topic. |
| Resume of a closed session | A closed session cannot be reopened. A progress code starts a new session that continues from its record; RESUME is used only to retry a held turn inside a live session. |
| Review and retention checks | Never posed; reports state that no retention evidence exists. |
| New-form check questions outside Q07 and E06 | Only Q07 and E06 have a new-form check pattern; the other eight goals cover the numeric part only. |
| Graph, table and reasoning-rubric inputs and checkers | Not implemented; no beta skill uses them. |
| All 195 skills outside the beta list | Browsable only; runtime_status UNAVAILABLE. The tutor may not pose problems there; a step back to one is listed in the report as "Practise with your teacher". |
| Localisation | English only. |
| Accounts | Account-free; browser-session ownership only. |

## Other gaps against the package — NOT IMPLEMENTED

| Item | Note |
|---|---|
| Bundled math renderer (spec 01) | Not bundled. Math is shown as plain text with Unicode signs; no TeX is interpreted anywhere, so unsafe TeX cannot execute. |
| AI-worded reports | Not used. Code writes the report from the gradebook; no AI text appears in it (round 6 §2.5). |
| Solution-set, interval, rational-domain checkers (golden ROOT-*, DOMAIN-01, INTERVAL-01, GRAPH-01) | Not implemented; no beta skill needs them. |
| MySQL storage adapter | Not implemented; PDO SQLite only (preflight fails clearly without it). |
| Live provider validation | Rounds 7, 8 and 9 ran scripted sessions against DeepSeek (test-results/live/round7/, test-results/live/round8/, test-results/live/round9/); those are runs of scripted learners, not deployment validation and not evidence of learning. Unit and mock results are mock results. |
| Review and correction tool | Minimum path only (round 9 §4.7): the owner runs app/tools/correct_attempt.php to append a CORRECTION event (help or verdict) to one attempt; the gradebook applies the latest correction per field and goals, the progress record and the stored report are recomputed, and the next request's ANSWERED_PROBLEM, GRADEBOOK, GOALS and slip state show the corrected values (round 10 §4.5); the teacher view lists each correction. There is no review screen; held attempts and verdict conflicts are found from the report and transcripts. |

## All 205 curriculum skills

| Skill | Title | Role | Runtime status |
|---|---|---|---|
| N01 | Order signed integers on a number line | FOUNDATION | UNAVAILABLE |
| N02 | Understand opposites and absolute value | FOUNDATION | UNAVAILABLE |
| N03 | Add positive and negative numbers | FOUNDATION | PARTIAL_COMPONENTS |
| N04 | Subtract signed numbers | FOUNDATION | PARTIAL_COMPONENTS |
| N05 | Multiply signed numbers | FOUNDATION | PARTIAL_COMPONENTS |
| N06 | Divide signed numbers | FOUNDATION | PARTIAL_COMPONENTS |
| N07 | Apply order of operations and brackets | FOUNDATION | UNAVAILABLE |
| N08 | Recognize equivalent fractions | FOUNDATION | UNAVAILABLE |
| N09 | Add and subtract fractions | FOUNDATION | UNAVAILABLE |
| N10 | Multiply and divide fractions | FOUNDATION | UNAVAILABLE |
| N11 | Calculate with signed decimals | FOUNDATION | UNAVAILABLE |
| N12 | Convert fractions, decimals, and percentages | FOUNDATION | UNAVAILABLE |
| N13 | Compare and order rational numbers | FOUNDATION | UNAVAILABLE |
| N14 | Understand ratios and unit rates | FOUNDATION | UNAVAILABLE |
| N15 | Evaluate powers and perfect-square roots | FOUNDATION | UNAVAILABLE |
| N16 | Solve simple proportions | FOUNDATION | UNAVAILABLE |
| N17 | Add and subtract whole numbers using place value | FOUNDATION | UNAVAILABLE |
| N18 | Multiply and divide whole numbers | FOUNDATION | PARTIAL_COMPONENTS |
| N19 | Represent fractions as quantities and equal units | FOUNDATION | UNAVAILABLE |
| N20 | Interpret base-ten place value and decimal magnitude | FOUNDATION | UNAVAILABLE |
| A01 | Understand variables as unknown or changing quantities | FOUNDATION | UNAVAILABLE |
| A02 | Identify terms, including their signs | FOUNDATION | UNAVAILABLE |
| A03 | Identify coefficients and constants, including implied coefficients | FOUNDATION | UNAVAILABLE |
| A04 | Distinguish terms from factors | FOUNDATION | UNAVAILABLE |
| A05 | Interpret algebraic notation such as 3x, x^2, and x/3 | FOUNDATION | UNAVAILABLE |
| A06 | Distinguish expressions, equations, identities, and inequalities | FOUNDATION | UNAVAILABLE |
| A07 | Understand grouping with parentheses | FOUNDATION | UNAVAILABLE |
| A08 | Translate verbal descriptions into expressions | FOUNDATION | UNAVAILABLE |
| A09 | Substitute values into expressions | FOUNDATION | UNAVAILABLE |
| A10 | Define variables and units in context | FOUNDATION | UNAVAILABLE |
| E01 | Recognize like and unlike terms | CORE | UNAVAILABLE |
| E02 | Combine like terms with nonnegative coefficients | CORE | UNAVAILABLE |
| E03 | Combine like terms with signed coefficients | CORE | UNAVAILABLE |
| E04 | Simplify expressions containing constants and variable terms | CORE | UNAVAILABLE |
| E05 | Use commutative and associative properties | CORE | UNAVAILABLE |
| E06 | Apply the distributive property with positive factors | CORE | AVAILABLE_SCORED |
| E07 | Distribute negative and fractional factors | CORE | UNAVAILABLE |
| E08 | Handle a minus sign before parentheses | CORE | UNAVAILABLE |
| E09 | Simplify multistep expressions | CORE | UNAVAILABLE |
| E10 | Factor out common factors | CORE | UNAVAILABLE |
| E11 | Identify and create equivalent expressions | CORE | UNAVAILABLE |
| E12 | Justify equivalence or find a counterexample | CORE | UNAVAILABLE |
| Q01 | Understand equality and balance | CORE | UNAVAILABLE |
| Q02 | Check solutions by substitution | CORE | UNAVAILABLE |
| Q03 | Apply inverse operations while maintaining equality | CORE | UNAVAILABLE |
| Q04 | Solve one-step addition/subtraction equations | CORE | PARTIAL_COMPONENTS |
| Q05 | Solve one-step multiplication/division equations | CORE | PARTIAL_COMPONENTS |
| Q06 | Solve two-step equations | CORE | PARTIAL_COMPONENTS |
| Q07 | Solve equations containing parentheses | CORE | AVAILABLE_SCORED |
| Q08 | Solve equations with variables on both sides | CORE | UNAVAILABLE |
| Q09 | Solve multistep equations requiring simplification | CORE | UNAVAILABLE |
| Q10 | Solve equations with fractional or decimal coefficients | CORE | UNAVAILABLE |
| Q11 | Clear denominators correctly | CORE | UNAVAILABLE |
| Q12 | Recognize one, no, or infinitely many solutions | CORE | UNAVAILABLE |
| Q13 | Rearrange simple formulas | CORE | UNAVAILABLE |
| Q14 | Solve linear equations with literal coefficients and parameter cases | CORE | UNAVAILABLE |
| I01 | Interpret inequality symbols | CORE | UNAVAILABLE |
| I02 | Represent inequalities on number lines | CORE | UNAVAILABLE |
| I03 | Solve inequalities using addition/subtraction | CORE | UNAVAILABLE |
| I04 | Solve inequalities using positive multiplication/division | CORE | UNAVAILABLE |
| I05 | Reverse inequality direction when multiplying/dividing by negatives | CORE | UNAVAILABLE |
| I06 | Solve multistep inequalities | CORE | UNAVAILABLE |
| I07 | Solve and graph compound inequalities | CORE | UNAVAILABLE |
| I08 | Translate restrictions into inequalities | CORE | UNAVAILABLE |
| I09 | Graph two-variable linear inequalities | CORE | UNAVAILABLE |
| C01 | Understand the coordinate plane | CORE | UNAVAILABLE |
| C02 | Plot and read ordered pairs | CORE | UNAVAILABLE |
| C03 | Identify independent and dependent variables | CORE | UNAVAILABLE |
| C04 | Construct and interpret input-output tables | CORE | UNAVAILABLE |
| C05 | Determine whether a relation is a function | CORE | UNAVAILABLE |
| C06 | Understand function notation | CORE | UNAVAILABLE |
| C07 | Evaluate functions | CORE | UNAVAILABLE |
| C08 | Determine domain and range | CORE | UNAVAILABLE |
| C09 | Identify intercepts and zeros | CORE | UNAVAILABLE |
| C10 | Connect tables, graphs, equations, and words | CORE | UNAVAILABLE |
| C11 | Evaluate piecewise-defined functions | CORE | UNAVAILABLE |
| C12 | Graph piecewise and step functions | CORE | UNAVAILABLE |
| C13 | Interpret basic absolute-value functions | CORE | UNAVAILABLE |
| C14 | Calculate average rates of change | CORE | UNAVAILABLE |
| C15 | Interpret basic transformations of familiar functions | CORE | UNAVAILABLE |
| C16 | Reverse simple functions and find linear inverses | CORE | UNAVAILABLE |
| C17 | Compare functions presented in different representations | CORE | UNAVAILABLE |
| C18 | Graph basic square-root and cube-root functions | CORE | UNAVAILABLE |
| C19 | Combine familiar function models using arithmetic | CORE | UNAVAILABLE |
| C20 | Interpret key features and intervals of functions | CORE | UNAVAILABLE |
| L01 | Recognize constant additive change | CORE | UNAVAILABLE |
| L02 | Calculate slope between two points | CORE | UNAVAILABLE |
| L03 | Interpret positive, negative, zero, and undefined slopes | CORE | UNAVAILABLE |
| L04 | Identify y-intercepts | CORE | UNAVAILABLE |
| L05 | Graph y=mx+b | CORE | UNAVAILABLE |
| L06 | Write linear equations from tables | CORE | UNAVAILABLE |
| L07 | Write linear equations from graphs | CORE | UNAVAILABLE |
| L08 | Write linear equations from two points | CORE | UNAVAILABLE |
| L09 | Convert between forms of linear equations | CORE | UNAVAILABLE |
| L10 | Distinguish proportional and nonproportional linear relationships | CORE | UNAVAILABLE |
| L11 | Recognize horizontal and vertical lines | CORE | UNAVAILABLE |
| L12 | Compare two linear models | CORE | UNAVAILABLE |
| L13 | Interpret slope and intercept with units | CORE | UNAVAILABLE |
| L14 | Determine whether a relationship is linear | CORE | UNAVAILABLE |
| S01 | Understand simultaneous solutions | CORE | UNAVAILABLE |
| S02 | Solve systems graphically | CORE | UNAVAILABLE |
| S03 | Solve systems using substitution | CORE | UNAVAILABLE |
| S04 | Solve systems using elimination | CORE | UNAVAILABLE |
| S05 | Scale equations before elimination | CORE | UNAVAILABLE |
| S06 | Identify one, no, or infinitely many solutions | CORE | UNAVAILABLE |
| S07 | Construct and interpret contextual systems | CORE | UNAVAILABLE |
| S08 | Solve systems of linear inequalities graphically | CORE | UNAVAILABLE |
| S09 | Solve and interpret linear-quadratic intersections | CORE | UNAVAILABLE |
| X01 | Interpret exponent notation | FOUNDATION | UNAVAILABLE |
| X02 | Distinguish negative bases and negative exponents | FOUNDATION | UNAVAILABLE |
| X03 | Multiply powers with common bases | FOUNDATION | UNAVAILABLE |
| X04 | Divide powers with common bases | FOUNDATION | UNAVAILABLE |
| X05 | Apply the power-of-a-power rule | FOUNDATION | UNAVAILABLE |
| X06 | Apply powers to products and quotients | FOUNDATION | UNAVAILABLE |
| X07 | Understand zero exponents | FOUNDATION | UNAVAILABLE |
| X08 | Interpret negative exponents | FOUNDATION | UNAVAILABLE |
| X09 | Work with scientific notation | FOUNDATION | UNAVAILABLE |
| X10 | Understand exact and approximate square roots | FOUNDATION | UNAVAILABLE |
| X11 | Evaluate and interpret real cube roots | FOUNDATION | UNAVAILABLE |
| P01 | Classify polynomial expressions | CORE | UNAVAILABLE |
| P02 | Determine degree and leading coefficient | CORE | UNAVAILABLE |
| P03 | Add polynomials | CORE | UNAVAILABLE |
| P04 | Subtract polynomials | CORE | UNAVAILABLE |
| P05 | Multiply monomials by polynomials | CORE | UNAVAILABLE |
| P06 | Multiply binomials | CORE | UNAVAILABLE |
| P07 | Multiply larger polynomial expressions | EXTENSION | UNAVAILABLE |
| P08 | Expand special products | CORE | UNAVAILABLE |
| P09 | Simplify polynomials without combining unlike powers | CORE | UNAVAILABLE |
| P10 | Explain polynomial closure and its limits | CORE | UNAVAILABLE |
| F01 | Factor out the greatest common factor | CORE | UNAVAILABLE |
| F02 | Factor using negative common factors | CORE | UNAVAILABLE |
| F03 | Identify factor pairs | CORE | UNAVAILABLE |
| F04 | Factor monic trinomials | CORE | UNAVAILABLE |
| F05 | Factor nonmonic trinomials | CORE | UNAVAILABLE |
| F06 | Factor differences of squares | CORE | UNAVAILABLE |
| F07 | Factor perfect-square trinomials | CORE | UNAVAILABLE |
| F08 | Verify factorization by expanding | CORE | UNAVAILABLE |
| F09 | Apply the zero-product property | CORE | UNAVAILABLE |
| U01 | Recognize quadratic relationships | CORE | UNAVAILABLE |
| U02 | Identify coefficients in standard quadratic form | CORE | UNAVAILABLE |
| U03 | Recognize quadratic patterns through second differences | CORE | UNAVAILABLE |
| U04 | Interpret parabolas and their intercepts | CORE | UNAVAILABLE |
| U05 | Identify vertex, symmetry, maxima, and minima | CORE | UNAVAILABLE |
| U06 | Interpret standard, factored, and vertex forms | CORE | UNAVAILABLE |
| U07 | Solve equations of the form x^2=k | CORE | UNAVAILABLE |
| U08 | Solve quadratics by factoring | CORE | UNAVAILABLE |
| U09 | Solve using square roots from vertex form | CORE | UNAVAILABLE |
| U10 | Complete the square for monic quadratics | CORE | UNAVAILABLE |
| U11 | Complete the square for nonmonic quadratics | CORE | UNAVAILABLE |
| U12 | Apply the quadratic formula | CORE | UNAVAILABLE |
| U13 | Interpret the discriminant | CORE | UNAVAILABLE |
| U14 | Connect quadratic roots to x-intercepts | CORE | UNAVAILABLE |
| U15 | Choose an appropriate solving method and interpret results | CORE | UNAVAILABLE |
| U16 | Derive and justify the quadratic formula | CORE | UNAVAILABLE |
| G01 | Distinguish additive from multiplicative growth | CORE | UNAVAILABLE |
| G02 | Understand y=ab^x | CORE | UNAVAILABLE |
| G03 | Evaluate exponential functions | CORE | UNAVAILABLE |
| G04 | Graph exponential growth and decay | CORE | UNAVAILABLE |
| G05 | Interpret growth/decay multipliers and percentages | CORE | UNAVAILABLE |
| G06 | Construct exponential models | CORE | UNAVAILABLE |
| G07 | Compare linear and exponential models | CORE | UNAVAILABLE |
| G08 | Solve simple exponential equations with common bases | EXTENSION | UNAVAILABLE |
| G09 | Write arithmetic sequence rules | CORE | UNAVAILABLE |
| G10 | Write geometric sequence rules | CORE | UNAVAILABLE |
| G11 | Compare eventual exponential and polynomial growth | CORE | UNAVAILABLE |
| G12 | Rewrite exponential expressions across time intervals | CORE | UNAVAILABLE |
| G13 | Justify equal-difference and equal-factor growth laws | CORE | UNAVAILABLE |
| R01 | Distinguish rational and irrational numbers | CORE | UNAVAILABLE |
| R02 | Simplify square roots | CORE | UNAVAILABLE |
| R03 | Perform basic operations with radicals | EXTENSION | UNAVAILABLE |
| R04 | Understand restrictions caused by division by zero | CORE | UNAVAILABLE |
| R05 | Simplify rational algebraic expressions | EXTENSION | UNAVAILABLE |
| R06 | Solve rational equations and check restrictions | EXTENSION | UNAVAILABLE |
| R07 | Solve elementary radical equations and check extraneous solutions | EXTENSION | UNAVAILABLE |
| R08 | Justify rational and irrational number-operation claims | CORE | UNAVAILABLE |
| D01 | Distinguish data types and statistical questions | CORE | UNAVAILABLE |
| D02 | Interpret and construct statistical displays | CORE | UNAVAILABLE |
| D03 | Calculate and interpret mean and median | CORE | UNAVAILABLE |
| D04 | Interpret quartiles and interquartile range | CORE | UNAVAILABLE |
| D05 | Understand standard deviation | CORE | UNAVAILABLE |
| D06 | Describe distributions, skewness, and outliers | CORE | UNAVAILABLE |
| D07 | Interpret scatter plots and associations | CORE | UNAVAILABLE |
| D08 | Construct and interpret linear trend models | CORE | UNAVAILABLE |
| D09 | Interpret residuals and correlation coefficients | CORE | UNAVAILABLE |
| D10 | Distinguish correlation and causation | CORE | UNAVAILABLE |
| D11 | Interpret two-way tables and conditional proportions | CORE | UNAVAILABLE |
| M01 | Identify quantities, unknowns, and units | CORE | UNAVAILABLE |
| M02 | Translate situations into algebraic models | CORE | UNAVAILABLE |
| M03 | Select appropriate linear, quadratic, or exponential models | CORE | UNAVAILABLE |
| M04 | Identify realistic domain restrictions | CORE | UNAVAILABLE |
| M05 | Verify and evaluate solutions | CORE | UNAVAILABLE |
| M06 | Connect multiple representations | CORE | UNAVAILABLE |
| M07 | Explain valid algebraic transformations | CORE | UNAVAILABLE |
| M08 | Evaluate assumptions, limitations, and conclusions | CORE | UNAVAILABLE |
| M09 | Choose and communicate appropriate measurement precision | CORE | UNAVAILABLE |
| H01 | Solve absolute-value equations and inequalities | EXTENSION | UNAVAILABLE |
| H02 | Solve equations involving piecewise functions | EXTENSION | UNAVAILABLE |
| H03 | Work with function composition and inverses | EXTENSION | UNAVAILABLE |
| H04 | Transform functions | EXTENSION | UNAVAILABLE |
| H05 | Perform polynomial division | EXTENSION | UNAVAILABLE |
| H06 | Understand rational exponents | EXTENSION | UNAVAILABLE |
| H07 | Solve quadratics with complex solutions | EXTENSION | UNAVAILABLE |
| H08 | Interpret logarithms | EXTENSION | UNAVAILABLE |
| H09 | Analyze rational functions and asymptotes | EXTENSION | UNAVAILABLE |
| H10 | Solve elementary nonlinear systems | EXTENSION | UNAVAILABLE |
