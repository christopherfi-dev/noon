# Capability matrix — Algebra Engine beta

Generated from the tested runtime registry (`app/private/src/Capability/CapabilityRegistry.php`) by `app/tools/gen_capability_matrix.php`. Curriculum ACE-ALGEBRA1 1.1.0 (205 skills, all browsable); architecture 1.2.

Runtime status counts: AVAILABLE_SCORED 2, PARTIAL_COMPONENTS 8, UNAVAILABLE 195. No all-skills operational claim is made.

**Live provider smoke test:** Round 4 (2026-10-22): live confirmation with thinking disabled (the chosen setting) against DeepSeek deepseek-flash: the 8 round 3 sessions (5 B1, 3 B3) and the B11 messy-learner session twice; all 10 sessions completed. Results: test-results/live/round4/ and ROUND_4_REPORT.md. Earlier runs: test-results/live/round3/, test-results/live/00-smoke-and-model-check.md, test-results/08-live-smoke.txt. These runs use scripted learners: they confirm provider compatibility and host gating, not teaching quality, learning or deployment. Every other result in this matrix is from mock mode (simulated model).

## Beta skills

| Skill | Title | Status | Implemented variants (template, response type) | Checkers | Criterion | Remaining | Tests |
|---|---|---|---|---|---|---|---|
| N03 | Add positive and negative numbers | PARTIAL_COMPONENTS | constructed (SIGNED_ADD, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore` |
| N04 | Subtract signed numbers | PARTIAL_COMPONENTS | constructed (SIGNED_SUBTRACT, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore` |
| N05 | Multiply signed numbers | PARTIAL_COMPONENTS | constructed (SIGNED_MULTIPLY, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore` |
| N06 | Divide signed numbers | PARTIAL_COMPONENTS | constructed (SIGNED_DIVIDE, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Integration/BetaJourneyTest.php::testB3DeepDescentAndReturn` |
| N18 | Multiply and divide whole numbers | PARTIAL_COMPONENTS | constructed (WHOLE_MULTIPLY_DIVIDE, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore` |
| E06 | Apply the distributive property with positive factors | AVAILABLE_SCORED | constructed (POSITIVE_DISTRIBUTION, expression)<br>contrast (DISTRIBUTION_CONTRAST, classification)<br>transfer (DISTRIBUTION_TRANSFER, expression) | polynomial identity + expanded form, exact option set | 3 consecutive independent correct covering constructed + contrast + transfer → IMMEDIATE_CRITERION_MET | Written explanations are not assessed; the contrast item checks rejection of invalid methods by selection only. | `tests/Integration/BetaJourneyTest.php::testB1DistributionJourney`<br>`tests/Unit/Items/TemplatesTest.php` |
| Q04 | Solve one-step addition/subtraction equations | PARTIAL_COMPONENTS | constructed (ONE_STEP_ADD, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Unit/Items/TemplatesTest.php`<br>`tests/Integration/BetaJourneyTest.php::testEveryBetaSkillCanStartAndScore` |
| Q05 | Solve one-step multiplication/division equations | PARTIAL_COMPONENTS | constructed (ONE_STEP_MULTIPLY, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Integration/BetaJourneyTest.php::testB3DeepDescentAndReturn` |
| Q06 | Solve two-step equations | PARTIAL_COMPONENTS | constructed (TWO_STEP, scalar) | exact rational | 3 consecutive independent correct (numeric component) → COMPONENT_CRITERION_MET only | Conceptual evidence (explain or reject an invalid method) not implemented. Transfer variant not implemented. Criterion reported as COMPONENT_CRITERION_MET only. | `tests/Integration/BetaJourneyTest.php::testB3DeepDescentAndReturn`<br>`tests/Integration/EvidenceTest.php::testEvid03NumericOnlySkill` |
| Q07 | Solve equations containing parentheses | AVAILABLE_SCORED | constructed (GROUPED_LINEAR, scalar)<br>contrast (GROUPED_FIRST_STEP_CONTRAST, classification)<br>transfer (GROUPED_LINEAR_TRANSFER, scalar) | exact rational, exact option set | 3 consecutive independent correct covering constructed + contrast + transfer → IMMEDIATE_CRITERION_MET | Written explanations are not assessed; the contrast item checks rejection of invalid methods by selection only. | `tests/Integration/BetaJourneyTest.php::testB1DistributionJourney`<br>`tests/Integration/BetaJourneyTest.php::testB2ProbeCorrectNoDescent`<br>`tests/Unit/Items/TemplatesTest.php` |

## Heuristics and their limits

| Item | Note |
|---|---|
| Answer-disclosure guard (D1) | HEURISTIC. Rejects model text containing the active or a remaining guided step's answer as a standalone number, number word, equivalent polynomial span or the key option's text. The word "one" used as a determiner or pronoun ("one way", "on one side") is not read as the value 1 unless a value cue precedes it ("x is one"). Paraphrases (e.g. "dividing by 3" for the option "Divide by 3") are not detected. Not read as a choice: options named together as alternatives ("positive or negative"); the learner's own answer to a completed step (never the active step's); positive, negative, plus or minus (or a plural) directly followed by an operand noun ("the negative sign", "a negative divisor"; round 5 ruling 2). |
| Internal vocabulary (R5-B) | Learner-facing model text containing expected value, expected answer, answer key, matched the key, guided step, host, assisted, verdict or criterion (whole words or phrases, any case, plurals included) is rejected and repaired; host lines pass the same check. Other internal wording is not detected. The session report keeps its defined evidence terms (criterion, assisted) and is outside this check. |
| Independent items (R5-A) | Every independent item (target, probe, prerequisite check, independent check, return) is shown with the host line "Here is one to try on your own." only. The model still proposes the item's parameters through the same gate; any learner text it writes with them is discarded unread. Model-written learner text remains in guided steps, hints and explanations. |
| Stuck and off-task entries (R5-C) | Fixed lists, matched after normalising (lowercase, trimmed, spaces collapsed, apostrophes removed, trailing . ! ? stripped): stuck phrases and "?" give a smaller question in guided repair or the hint path on an independent item; asking for the answer adds "I won't give the answer, but here is a smaller step."; other words ending in "?" and number words zero to twenty get a host line; a readable answer with a trailing "?" and "X = 3" on an item in x are scored normally. Other phrasings keep the clarification for unreadable input. None is scored as incorrect. |
| Guided-step skill tags | Each guided step names the skill it exercises (the repair skill or a declared prerequisite); being stuck at the smallest step checks that prerequisite directly (Round 2 ruling 8). The tags are authored per template, not inferred. |
| One action per message | HEURISTIC. Model text may not contain "?"; the only question shown is the host-rendered item prompt. |
| Error signatures | Deterministic value matching of known error patterns. A match is recorded as an observation, never a confirmed cause, and only selects the probe/descent branch. |

## Deferred (addendum §5) — NOT IMPLEMENTED

| Item | Behaviour in the beta |
|---|---|
| Teacher task links (create_task / task endpoints, signed public settings) | Endpoints return 422 FEATURE_NOT_IN_BETA. |
| "Help me find a starting point" orientation (START_ORIENTATION, recommendation) | start with entry_route UNSURE returns 422 FEATURE_NOT_IN_BETA. |
| Free-text topic mapping (SUGGEST_TARGET / CONFIRM_TARGET) | Actions are not offered; the learner picks from the beta list. |
| Resume across sessions (RESUME_SESSION of a closed session) | Returns 422 FEATURE_NOT_IN_BETA. RESUME_SESSION is used only to retry a held generation inside a live session. |
| Review and retention checks (REVIEW_CHECK) | Never entered; reports state that no retention evidence exists. |
| Transfer checks outside Q07 and E06 (TRANSFER_CHECK mode) | Q07/E06 transfer variants are part of their criterion; no separate transfer mode. |
| Graph, table and reasoning-rubric inputs and checkers | Not implemented; no beta skill uses them. |
| All 195 skills outside the beta list | Browsable only; runtime_status UNAVAILABLE; descent to them records "not available in beta". |
| Localisation | English only. |
| Accounts | Account-free; browser-session ownership only. |

## Other gaps against the package — NOT IMPLEMENTED

| Item | Note |
|---|---|
| Bundled math renderer (spec 01) | Not bundled. Math is shown as plain text with Unicode signs; no TeX is interpreted anywhere, so unsafe TeX cannot execute. |
| Model-worded REPORT envelopes | Not used. The report is built by the host from ledger events only. |
| Solution-set, interval, rational-domain checkers (golden ROOT-*, DOMAIN-01, INTERVAL-01, GRAPH-01) | Not implemented; no beta skill needs them. |
| MySQL storage adapter | Not implemented; PDO SQLite only (preflight fails clearly without it). |

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
