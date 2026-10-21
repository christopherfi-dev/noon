# Algebra 1 Curriculum and Prerequisite Map v1.1

Research-informed author draft · 20 October 2026

## What is built

- 205 identified skills across 17 strands.
- 148 core targets, 41 on-demand foundation skills and 16 optional extension skills.
- All 185 original IDs are retained. Twenty new IDs make missing performances or foundations explicit.
- Each node contains scope, observable performance, prerequisite links, assessment variants, an original anchor question and answer expectation, diagnostic guidance and checker requirements.
- The original summary's referenced prerequisite JSON/CSV files were not supplied. The edges here are newly authored and labeled accordingly.

The package is a complete authored map for its declared reference profile. Its standards crosswalk and sequence are design judgments; they do not establish empirical diagnostic validity, local approval, Noon-specific alignment or NWEA calibration.

## Reference scope

The reference course includes expression structure; equations and inequalities; functions and representations; linear and exponential models; systems; polynomial structure and factoring; quadratics; descriptive statistics; modeling and mathematical communication.

Core function work includes basic root graphs, simple linear inverses, familiar-function transformations and function comparison. Broader composition, transformations, rational algebra, complex roots and logarithms are explicitly optional.

## Research basis

The Common Core content pages define the referenced mathematical content. Illustrative Mathematics Algebra 1 is a published scope comparator, not the source of the unit order or anchor questions. The IES practice guide informs solved-work analysis, structural reasoning and deliberate method choice. Its first two recommendations carry minimal evidence ratings and the strategy-choice recommendation carries a moderate rating.

The source pages do not establish this exact prerequisite graph or a three-correct mastery threshold. Those remain design choices requiring testing.

## Additions to the original 185-node skeleton

| New ID | Explicit performance or foundation |
|---|---|
| N17 | Add and subtract whole numbers using place value |
| N18 | Multiply and divide whole numbers |
| N19 | Represent fractions as quantities and equal units |
| N20 | Interpret base-ten place value and decimal magnitude |
| Q14 | Solve linear equations with literal coefficients and parameter cases |
| C15 | Interpret basic transformations of familiar functions |
| C16 | Reverse simple functions and find linear inverses |
| C17 | Compare functions presented in different representations |
| C18 | Graph basic square-root and cube-root functions |
| C19 | Combine familiar function models using arithmetic |
| C20 | Interpret key features and intervals of functions |
| S09 | Solve and interpret linear-quadratic intersections |
| X11 | Evaluate and interpret real cube roots |
| P10 | Explain polynomial closure and its limits |
| U16 | Derive and justify the quadratic formula |
| G11 | Compare eventual exponential and polynomial growth |
| G12 | Rewrite exponential expressions across time intervals |
| G13 | Justify equal-difference and equal-factor growth laws |
| R08 | Justify rational and irrational number-operation claims |
| M09 | Choose and communicate appropriate measurement precision |

Display-only normalization removes escaped math formatting from A05, L05, U07 and G02. It does not change their mathematical identities.

The graph uses component, alternative-method and conditional links. Core tasks never require an unconditional optional-extension dependency. A hard or conditional edge does not license a causal diagnosis.

## Planning sequence

The original planning route is 33 unit weeks plus 3 flexible review/assessment weeks. Pacing is adjustable.

| Unit | Focus | Weeks |
|---|---|---:|
| 01 | Expressions, quantities and mathematical language | 2 |
| 02 | Linear equations and inequalities | 4 |
| 03 | Functions and representations | 3 |
| 04 | Linear relationships | 3 |
| 05 | Systems and constraints | 3 |
| 06 | Data and linear statistical models | 3 |
| 07 | Exponential relationships and sequences | 3 |
| 08 | Polynomials and factoring | 4 |
| 09 | Quadratic functions and equations | 5 |
| 10 | Integrated modeling and communication | 3 |
| Flex | Review, assessment and local adjustments | 3 |

See SCOPE_AND_SEQUENCE.md for unit assessment blueprints and TEACHING_AND_ASSESSMENT_GUIDE.md for diagnostic use and rubric guidance.

## Reading a skill record

The anchor is a teacher-facing illustration and answer expectation. It is not an independently calibrated test item. Required variants, conceptual evidence and transfer tasks must still be authored or generated and accepted before the node's full scope is assessed.

Machine-readable records distinguish a declared checker requirement from an implemented checker. The graph and files can be integrated into the future PHP loader; the current package does not provide that API.

## Strand N: Numerical prerequisites

20 skills. Foundation and extension roles are explicit for each record.

### N01 — Order signed integers on a number line

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Order signed values and justify position relative to zero.

**Bounds:** Integers from -100 to 100; number-line order distinct from absolute magnitude.

**Direct components:** N20

**Anchor:** Order -7, 2, -3, 0 from least to greatest.

**Answer expectation:** `[-7, -3, 0, 2]`

**Diagnostic contrast:** Separate signed order from ordering absolute values.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.NS.C.7.a

### N02 — Understand opposites and absolute value

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Identify an additive inverse and interpret distance from zero.

**Bounds:** Opposites, zero and absolute value; not absolute-value equations.

**Direct components:** N01

**Anchor:** Give the opposite of -9 and the value of abs(-9).

**Answer expectation:** `{"opposite": 9, "absolute_value": 9}`

**Diagnostic contrast:** Contrast direction with distance; check whether two correct outputs have the same meaning.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.NS.C.6.a, 6.NS.C.7.c

### N03 — Add positive and negative numbers

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Add signed quantities using a representation or valid number reasoning.

**Bounds:** Signed integers initially; signed rational variants use declared fraction prerequisites.

**Direct components:** N02, N17

**Conditional requirements:** item uses signed fractions → N09

**Anchor:** Calculate -8 + 5.

**Answer expectation:** `-3`

**Diagnostic contrast:** Contrast opposite-sign addition with same-sign addition.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.NS.A.1.b

### N04 — Subtract signed numbers

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Rewrite subtraction as addition of the opposite and compute.

**Bounds:** Subtraction of signed integers; rational-number variants conditional.

**Direct components:** N03, N02

**Conditional requirements:** item uses signed fractions → N09

**Anchor:** Calculate -3 - (-8).

**Answer expectation:** `5`

**Diagnostic contrast:** Contrast -3-8 with -3-(-8); observe the changed operation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.NS.A.1.c

### N05 — Multiply signed numbers

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Compute and explain the sign of a product.

**Bounds:** Products of signed integers; extension to signed fractions conditional.

**Direct components:** N02, N18

**Conditional requirements:** item uses signed fractions → N10

**Anchor:** Calculate (-6)(-4).

**Answer expectation:** `24`

**Diagnostic contrast:** Vary one sign while preserving magnitudes; separate a sign error from a fact error.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.NS.A.2.a

### N06 — Divide signed numbers

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Compute a signed quotient and check by multiplication.

**Bounds:** Division by nonzero signed values; zero-divisor restrictions explicit.

**Direct components:** N05, N18

**Conditional requirements:** item uses signed fractions → N10

**Anchor:** Calculate -24 / 6.

**Answer expectation:** `-4`

**Diagnostic contrast:** Distinguish division facts, quotient sign and division by zero.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.NS.A.2.b

### N07 — Apply order of operations and brackets

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Respect grouping and operation precedence.

**Bounds:** Numerical expressions with nested brackets and declared operations; powers require X01.

**Direct components:** N17, N18

**Conditional requirements:** item contains powers → X01; item contains signed operations → N03, N04, N05, N06; item contains fractions → N09, N10

**Anchor:** Calculate 18 - 2(3 + 4).

**Answer expectation:** `4`

**Diagnostic contrast:** Compare bracket evaluation with left-to-right evaluation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.1, 6.EE.A.2.c

### N08 — Recognize equivalent fractions

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Create an equivalent fraction by scaling numerator and denominator together.

**Bounds:** Equivalent rational values with nonzero denominators; visual and symbolic forms.

**Direct components:** N19, N18

**Anchor:** Write 3/4 with denominator 20.

**Answer expectation:** `15/20`

**Diagnostic contrast:** Contrast scaling both terms with adding the same number to both.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 4.NF.A.1

### N09 — Add and subtract fractions

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Use common units and preserve equivalent value.

**Bounds:** Addition and subtraction of fractions; signed variants require N03 and N04.

**Direct components:** N08, N17

**Anchor:** Calculate 3/4 - 1/6.

**Answer expectation:** `7/12`

**Diagnostic contrast:** Separate common-denominator reasoning from numerator arithmetic.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.NS.A.1, 6.NS.A.1

### N10 — Multiply and divide fractions

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Compute a fraction quotient and verify by inverse multiplication.

**Bounds:** Fraction multiplication and division with a nonzero divisor.

**Direct components:** N19, N18, N08

**Anchor:** Calculate (2/3) / (4/5).

**Answer expectation:** `5/6`

**Diagnostic contrast:** Distinguish the reciprocal of the divisor from the reciprocal of the dividend.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.NS.A.1, 7.NS.A.2

### N11 — Calculate with signed decimals

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Compute with signed decimals and estimate the magnitude.

**Bounds:** Signed decimal operations; decimal placement and sign are separate demands.

**Direct components:** N20, N03, N06

**Anchor:** Calculate -1.2 / 0.3.

**Answer expectation:** `-4`

**Diagnostic contrast:** Use a magnitude estimate to distinguish a decimal-place error from a sign error.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.NS.B.3, 7.NS.A.3

### N12 — Convert fractions, decimals, and percentages

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Convert without changing the represented amount.

**Bounds:** Fraction-decimal-percent representations of the same quantity.

**Direct components:** N08, N20, N18

**Anchor:** Write 3/8 as a decimal and a percent.

**Answer expectation:** `{"decimal": "0.375", "percent": "37.5%"}`

**Diagnostic contrast:** Distinguish multiplying by 100 from appending a percent sign.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.RP.A.3.c

### N13 — Compare and order rational numbers

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Compare unlike representations on a common scale.

**Bounds:** Signed fractions and decimals with exact comparison when possible.

**Direct components:** N01, N08, N20

**Anchor:** Order -0.6, -2/3, 1/4 from least to greatest.

**Answer expectation:** `["-2/3", "-0.6", "1/4"]`

**Diagnostic contrast:** Check whether changing notation changes the learner's ordering rule.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.NS.C.7.b, 7.NS.A.3

### N14 — Understand ratios and unit rates

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Find a per-one rate and explain what the quotient measures.

**Bounds:** Ratios, equivalent ratios and unit rates; units retained.

**Direct components:** N18, N19

**Anchor:** Six notebooks cost 15 dollars. Find the cost per notebook.

**Answer expectation:** `{"value": "2.5", "unit": "dollars per notebook"}`

**Diagnostic contrast:** Reverse the requested unit to test quotient orientation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.RP.A.1, 6.RP.A.2

### N15 — Evaluate powers and perfect-square roots

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Distinguish taking a principal square root from solving a square equation.

**Bounds:** Small whole-number powers and principal roots of perfect squares.

**Direct components:** X01, N18

**Anchor:** Evaluate 3^4 and sqrt(81).

**Answer expectation:** `{"power": 81, "principal_root": 9}`

**Diagnostic contrast:** Contrast sqrt(81) with the two solutions of x^2=81.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.2

### N16 — Solve simple proportions

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Solve a proportion through equivalent ratios or unit rate.

**Bounds:** Simple proportional equations; nonproportional situations excluded.

**Direct components:** N14, N10

**Anchor:** Solve x/12 = 3/4.

**Answer expectation:** `9`

**Diagnostic contrast:** Use a nonproportional counterexample to test indiscriminate cross multiplication.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.RP.A.2.c

### N17 — Add and subtract whole numbers using place value

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Calculate and verify an inverse operation using place value.

**Bounds:** Whole-number addition and subtraction; regrouping through thousands initially.

**Direct components:** N20

**Anchor:** Calculate 3004 - 896.

**Answer expectation:** `2108`

**Diagnostic contrast:** Separate a regrouping difficulty from a subtraction-fact difficulty.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 4.NBT.B.4

### N18 — Multiply and divide whole numbers

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Compute a quotient and verify with multiplication.

**Bounds:** Whole-number products and quotients; equal groups, area and standard algorithms.

**Direct components:** N17

**Anchor:** Calculate 156 / 12.

**Answer expectation:** `13`

**Diagnostic contrast:** Contrast multiplication-fact retrieval with multi-step division.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 4.NBT.B.5, 4.NBT.B.6, 6.NS.B.2

### N19 — Represent fractions as quantities and equal units

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Represent a fraction greater than one without changing the size of the unit.

**Bounds:** A fraction as a quantity, equal parts and a point on the number line.

**Direct components:** No earlier node required within this map's declared floor.

**Anchor:** Explain where 7/4 lies between consecutive whole numbers.

**Answer expectation:** `Between 1 and 2; seven quarter-units equal 1 and 3/4.`

**Diagnostic contrast:** Check whether the denominator describes piece size or the number of pieces counted.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 4.NF.B.3

### N20 — Interpret base-ten place value and decimal magnitude

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Identify a digit's value and compare decimal quantities.

**Bounds:** Base-ten place value, decimal magnitude and equivalent trailing zeros.

**Direct components:** No earlier node required within this map's declared floor.

**Anchor:** What is the value of 6 in 0.406? Which is greater: 0.406 or 0.46?

**Answer expectation:** `{"digit_value": "0.006", "greater": "0.46"}`

**Diagnostic contrast:** Compare digit-count reasoning with place-value reasoning.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 4.NBT.A.1, 6.NS.B.3

## Strand A: Algebraic language and notation

10 skills. Foundation and extension roles are explicit for each record.

### A01 — Understand variables as unknown or changing quantities

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Define an unknown and use it to describe a quantity.

**Bounds:** Variables as unknowns and changing quantities; meaning must be stated.

**Direct components:** No earlier node required within this map's declared floor.

**Anchor:** A box has an unknown number of pens. Define p and write the number in three identical boxes.

**Answer expectation:** `p is the number of pens in one box; total is 3p.`

**Diagnostic contrast:** Distinguish a variable's meaning from a numerical answer.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.6

### A02 — Identify terms, including their signs

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Identify the signed terms of an expression.

**Bounds:** Additive terms with attached signs; do not split factors into terms.

**Direct components:** A01, N02

**Anchor:** List the terms in 5x - 3y + 7.

**Answer expectation:** `["5x", "-3y", "7"]`

**Diagnostic contrast:** Contrast top-level addition with multiplication inside a term.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2.b, HSA.SSE.A.1.a

### A03 — Identify coefficients and constants, including implied coefficients

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Identify coefficients and constants including an implied -1.

**Bounds:** Explicit, negative, zero and implied coefficients; constants distinguished.

**Direct components:** A02

**Anchor:** Give the coefficient of x and the constant in -x + 8.

**Answer expectation:** `{"coefficient": -1, "constant": 8}`

**Diagnostic contrast:** Use x and -x to test implied coefficients.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2.b, HSA.SSE.A.1.a

### A04 — Distinguish terms from factors

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Identify structure at a specified level of grouping.

**Bounds:** Terms versus multiplicative factors, including grouped sums.

**Direct components:** A02, A05

**Anchor:** In 4(x+3), name the two top-level factors and the terms inside the brackets.

**Answer expectation:** `{"factors": ["4", "x+3"], "inner_terms": ["x", "3"]}`

**Diagnostic contrast:** Change the requested structural level while keeping the expression.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2.b, HSA.SSE.A.1.a, HSA.SSE.A.1.b

### A05 — Interpret algebraic notation such as 3x, x^2, and x/3

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Explain the different operations encoded by 3x and x^3.

**Bounds:** Implicit multiplication, powers and quotients; notation distinguished from evaluation.

**Direct components:** A01, X01

**Anchor:** Explain why 3x and x^3 are different expressions.

**Answer expectation:** `3x means 3 times x; x^3 means x times x times x.`

**Diagnostic contrast:** Substitute x=2 to expose a notation confusion without treating one match as equivalence.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2, HSA.SSE.A.1.a

### A06 — Distinguish expressions, equations, identities, and inequalities

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Classify a mathematical statement and justify the presence of a relation.

**Bounds:** Expressions, equations, identities and inequalities; identity meaning explicit.

**Direct components:** A01

**Anchor:** Classify 2x+3<11.

**Answer expectation:** `Inequality; it compares two expressions rather than asserting equality.`

**Diagnostic contrast:** Contrast the same expressions joined by =, < or no relation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.5, HSA.REI.A.1

### A07 — Understand grouping with parentheses

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Interpret parentheses as specifying an entire quantity.

**Bounds:** Grouping of sums and products; scope of a leading minus introduced later.

**Direct components:** A01, N07

**Anchor:** Explain what 3(x+4) means before calculating.

**Answer expectation:** `Three times the entire quantity x+4.`

**Diagnostic contrast:** Contrast 3(x+4) with 3x+4 using a concrete value.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2.b

### A08 — Translate verbal descriptions into expressions

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Translate structure while preserving operation order.

**Bounds:** Verbal expressions with order-sensitive subtraction and division.

**Direct components:** A01, A05

**Anchor:** Write an expression for five less than twice n.

**Answer expectation:** `2*n-5`

**Diagnostic contrast:** Reverse the verbal order without changing the intended quantity.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2.a, HSA.CED.A.1

### A09 — Substitute values into expressions

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Replace a variable by its entire assigned value and evaluate.

**Bounds:** Substitution with grouping; negative and fractional values introduced deliberately.

**Direct components:** A05, N07

**Conditional requirements:** substitution input is negative → N03, N05

**Anchor:** Evaluate 2x^2 - 3x for x=-2.

**Answer expectation:** `14`

**Diagnostic contrast:** Contrast x=-2 in x^2 and -x^2 to locate grouping errors.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2.c

### A10 — Define variables and units in context

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Define both quantities in a contextual formula.

**Bounds:** Variables as defined quantities with units; age-appropriate contexts.

**Direct components:** A01, N14

**Anchor:** In C=12+4h for a bike rental, define C and h with units.

**Answer expectation:** `C is total cost in dollars; h is rental time in hours.`

**Diagnostic contrast:** Distinguish the quantity being modeled from its numerical coefficient.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSN.Q.A.1, HSN.Q.A.2

## Strand E: Expression structure and equivalence

12 skills. Foundation and extension roles are explicit for each record.

### E01 — Recognize like and unlike terms

**Role:** CORE. **First planned unit:** 01.

**Performance:** Classify terms by their complete variable part.

**Bounds:** Like terms require matching variable factors and exponents.

**Direct components:** A02, X01

**Anchor:** Which terms can combine with 3x^2: 5x, -2x^2, 4y^2?

**Answer expectation:** `Only -2x^2.`

**Diagnostic contrast:** Hold the coefficient fixed and vary the exponent or variable.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.2.b, HSA.SSE.A.1.a

### E02 — Combine like terms with nonnegative coefficients

**Role:** CORE. **First planned unit:** 01.

**Performance:** Combine like terms and retain unlike terms.

**Bounds:** Nonnegative coefficients; no distribution in the entry item.

**Direct components:** E01, N17

**Anchor:** Simplify 3x+5x+2.

**Answer expectation:** `8*x+2`

**Diagnostic contrast:** Contrast adding coefficients with changing the exponent.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.3, 7.EE.A.1

### E03 — Combine like terms with signed coefficients

**Role:** CORE. **First planned unit:** 01.

**Performance:** Combine signed coefficients correctly.

**Bounds:** Signed coefficients with matching variable structure.

**Direct components:** E02, N03

**Anchor:** Simplify -4x+7x-2x.

**Answer expectation:** `x`

**Diagnostic contrast:** Isolate signed-number computation from identifying like terms.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.A.1

### E04 — Simplify expressions containing constants and variable terms

**Role:** CORE. **First planned unit:** 01.

**Performance:** Collect constants separately from variable terms.

**Bounds:** Variable and constant terms; initially no nested groups.

**Direct components:** E03, A03

**Anchor:** Simplify 5x+7-2x+4.

**Answer expectation:** `3*x+11`

**Diagnostic contrast:** Compare the grouping of constants with grouping of variable terms.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.A.1, HSA.SSE.A.2

### E05 — Use commutative and associative properties

**Role:** CORE. **First planned unit:** 01.

**Performance:** Justify a rearrangement and reject an invalid analogous move.

**Bounds:** Commutative and associative properties apply to addition and multiplication, not arbitrary subtraction.

**Direct components:** A05, N07

**Anchor:** Explain why a+(b+c)=(a+b)+c, but a-(b-c) need not equal (a-b)-c.

**Answer expectation:** `Addition is associative; subtraction is not. For a=8,b=3,c=1 the subtraction results are 6 and 4.`

**Diagnostic contrast:** Test transfer of a valid property to a different operation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.3, HSA.SSE.A.2

### E06 — Apply the distributive property with positive factors

**Role:** CORE. **First planned unit:** 01.

**Performance:** Expand a grouped sum with a positive factor.

**Bounds:** Positive integer distribution over every term.

**Direct components:** A07, N18

**Anchor:** Expand 4(x+3).

**Answer expectation:** `4*x+12`

**Diagnostic contrast:** Probe whether the outside factor reaches every term.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.3, 7.EE.A.1

### E07 — Distribute negative and fractional factors

**Role:** CORE. **First planned unit:** 01.

**Performance:** Distribute a signed factor across a whole expression.

**Bounds:** Negative factors; fractional variants require N09 and N10.

**Direct components:** E06, N05

**Conditional requirements:** item contains fractional factors → N09, N10

**Anchor:** Expand -3(2x-5).

**Answer expectation:** `-6*x+15`

**Diagnostic contrast:** Vary one inner sign to distinguish distribution from sign multiplication.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.A.1, HSA.SSE.A.2

### E08 — Handle a minus sign before parentheses

**Role:** CORE. **First planned unit:** 01.

**Performance:** Remove a subtraction grouping without changing its value.

**Bounds:** A leading minus is multiplication of the entire group by -1.

**Direct components:** E06, N02

**Conditional requirements:** result requires signed constant arithmetic → N03, N04

**Anchor:** Simplify 7-(2x-3).

**Answer expectation:** `10-2*x`

**Diagnostic contrast:** Contrast changing all bracketed signs with changing only the first.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.A.1

### E09 — Simplify multistep expressions

**Role:** CORE. **First planned unit:** 01.

**Performance:** Simplify a multi-step linear expression and justify grouping.

**Bounds:** Nested groups, signed distribution and collection; rational coefficients conditional.

**Direct components:** E04, E07, E08

**Anchor:** Simplify 2(3x-4)-(x+5).

**Answer expectation:** `5*x-13`

**Diagnostic contrast:** Remove one structural demand at a time to distinguish distribution from collection.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.A.1, HSA.SSE.A.2

### E10 — Factor out common factors

**Role:** CORE. **First planned unit:** 01.

**Performance:** Factor an expression as the reverse of distribution.

**Bounds:** Common numerical and variable factors; nonzero common factors.

**Direct components:** E06, A04, N18

**Anchor:** Factor 6x+9 using the greatest numerical common factor.

**Answer expectation:** `3*(2*x+3)`

**Diagnostic contrast:** Check whether proposed factors expand to the original expression.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.3, HSA.SSE.A.2

### E11 — Identify and create equivalent expressions

**Role:** CORE. **First planned unit:** 01.

**Performance:** Establish equivalence through valid transformations.

**Bounds:** Identity across the declared domain; isolated matching values are insufficient.

**Direct components:** E04, E06

**Anchor:** Are 2(x+3) and 2x+6 equivalent for all real x? Justify.

**Answer expectation:** `Yes; distributing 2 produces 2x+6 for every real x.`

**Diagnostic contrast:** Contrast identity with a coincidence at one chosen input.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.4, HSA.SSE.A.2

### E12 — Justify equivalence or find a counterexample

**Role:** CORE. **First planned unit:** 01.

**Performance:** Disprove a proposed identity with a specified counterexample.

**Bounds:** A valid proof or a genuine counterexample; no numerical sampling presented as universal proof.

**Direct components:** E11, A09

**Anchor:** Disprove (x+1)^2=x^2+1 with a counterexample.

**Answer expectation:** `At x=1 the two sides are 4 and 2.`

**Diagnostic contrast:** Distinguish a confirming example from a disconfirming counterexample.

**Required variants:** Provide a valid identity proof.; Produce a counterexample to a false identity.; Explain why a confirming example alone is insufficient.

**Reference contributions:** HSA.SSE.A.2, HSA.REI.A.1

## Strand Q: Equality and linear equations

14 skills. Foundation and extension roles are explicit for each record.

### Q01 — Understand equality and balance

**Role:** CORE. **First planned unit:** 02.

**Performance:** Maintain equality when an equal quantity changes on both sides.

**Bounds:** Equality as a relation, not an instruction to write an answer.

**Direct components:** A06, N17

**Anchor:** If 7+5=12, what must be added to both sides to keep equality after adding 3 on the left?

**Answer expectation:** `Add 3 on the right as well.`

**Diagnostic contrast:** Check relational equality against a left-to-right answer convention.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.5, HSA.REI.A.1

### Q02 — Check solutions by substitution

**Role:** CORE. **First planned unit:** 02.

**Performance:** Check a candidate solution without relying on the solving procedure.

**Bounds:** Substitution into the original equation; truth of both sides assessed.

**Direct components:** Q01, A09

**Anchor:** Is x=4 a solution of 3(x+4)=24? Show the substitution.

**Answer expectation:** `Yes; 3(4+4)=24.`

**Diagnostic contrast:** Compare checking the original equation with checking only a transformed line.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.5, HSA.REI.A.1

### Q03 — Apply inverse operations while maintaining equality

**Role:** CORE. **First planned unit:** 02.

**Performance:** Explain a valid inverse operation.

**Bounds:** Equivalent operations on both sides; nonzero divisors required.

**Direct components:** Q01, N04, N06

**Anchor:** Why may 5 be subtracted from both sides of x+5=12?

**Answer expectation:** `Subtracting the same quantity preserves equality, giving x=7.`

**Diagnostic contrast:** Contrast an inverse operation with moving a symbol without an equality argument.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.A.1

### Q04 — Solve one-step addition/subtraction equations

**Role:** CORE. **First planned unit:** 02.

**Performance:** Isolate an unknown using an inverse operation.

**Bounds:** One-step signed addition and subtraction equations.

**Direct components:** Q03, N03

**Anchor:** Solve x-7=-2.

**Answer expectation:** `5`

**Diagnostic contrast:** Probe the arithmetic separately when the chosen inverse operation is valid.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.7, 7.EE.B.4.a

### Q05 — Solve one-step multiplication/division equations

**Role:** CORE. **First planned unit:** 02.

**Performance:** Solve and verify a multiplicative equation.

**Bounds:** One-step multiplication and division with nonzero constants.

**Direct components:** Q03, N06

**Anchor:** Solve -4x=20.

**Answer expectation:** `-5`

**Diagnostic contrast:** Distinguish dividing by a negative coefficient from reversing an inequality.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.7, 7.EE.B.4.a

### Q06 — Solve two-step equations

**Role:** CORE. **First planned unit:** 02.

**Performance:** Undo the additive and multiplicative structure in a valid order.

**Bounds:** Two-step linear equations; rational variants separately tagged.

**Direct components:** Q04, Q05

**Anchor:** Solve 3x+5=20.

**Answer expectation:** `5`

**Diagnostic contrast:** Contrast order of inverse operations with a pure arithmetic slip.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.B.4.a, HSA.REI.B.3

### Q07 — Solve equations containing parentheses

**Role:** CORE. **First planned unit:** 02.

**Performance:** Use structure or expansion to solve a grouped equation.

**Bounds:** Linear equations with grouping on one or both sides.

**Direct components:** Q06, E06

**Anchor:** Solve 3(x+4)=24.

**Answer expectation:** `4`

**Diagnostic contrast:** Compare direct division by 3 with expansion to isolate distribution.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.B.4.a, HSA.REI.B.3

### Q08 — Solve equations with variables on both sides

**Role:** CORE. **First planned unit:** 02.

**Performance:** Solve while preserving the original solution set.

**Bounds:** Variables on both sides; equality-preserving collection.

**Direct components:** Q06, E03

**Anchor:** Solve 5x-7=2x+8.

**Answer expectation:** `5`

**Diagnostic contrast:** Remove one variable term to distinguish transposition from signed arithmetic.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.C.7, HSA.REI.B.3

### Q09 — Solve multistep equations requiring simplification

**Role:** CORE. **First planned unit:** 02.

**Performance:** Combine valid transformations and verify the original equation.

**Bounds:** Multi-step linear equations requiring simplification and collection.

**Direct components:** Q07, Q08, E09

**Anchor:** Solve 2(3x-4)-(x+5)=12.

**Answer expectation:** `5`

**Diagnostic contrast:** Use separate probes for E09 simplification and Q08 solving.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.C.7.b, HSA.REI.B.3

### Q10 — Solve equations with fractional or decimal coefficients

**Role:** CORE. **First planned unit:** 02.

**Performance:** Solve without treating fractions as independent addends.

**Bounds:** Fractional or decimal coefficients; variable denominators excluded.

**Direct components:** Q06

**Alternative methods:** fractions: N09, N10; decimals: N11

**Conditional requirements:** fractional-coefficient variant → N09, N10; decimal-coefficient variant → N11

**Anchor:** Solve (3/4)x-1/2=5/2.

**Answer expectation:** `4`

**Diagnostic contrast:** Compare a decimal-coefficient version with a fractional-coefficient version.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.C.7.b, HSA.REI.B.3

### Q11 — Clear denominators correctly

**Role:** CORE. **First planned unit:** 02.

**Performance:** Multiply the entire equation by a common multiple.

**Bounds:** Clearing constant denominators from every term; zero denominators excluded.

**Direct components:** Q06, N08, N10

**Anchor:** Solve x/3 + 1/2 = 5/6.

**Answer expectation:** `1`

**Diagnostic contrast:** Ask which terms are multiplied before asking for the solution.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.3

### Q12 — Recognize one, no, or infinitely many solutions

**Role:** CORE. **First planned unit:** 02.

**Performance:** Interpret the simplified relation as a solution-set statement.

**Bounds:** One solution, contradiction and identity for linear equations.

**Direct components:** Q08, E09

**Anchor:** Solve 3(x+2)=3x+6.

**Answer expectation:** `All real numbers.`

**Diagnostic contrast:** Contrast a true constant equality with x=0.

**Required variants:** Unique solution.; No solution.; All real numbers, with a true constant equality.

**Reference contributions:** 8.EE.C.7.a, HSA.REI.B.3

### Q13 — Rearrange simple formulas

**Role:** CORE. **First planned unit:** 02.

**Performance:** Isolate a requested quantity symbolically.

**Bounds:** Rearrangement of simple formulas; restrictions explicitly stated.

**Direct components:** Q03, A05

**Conditional requirements:** isolating the variable divides by a parameter → R04

**Anchor:** Rearrange A=(1/2)bh for h, assuming b is nonzero.

**Answer expectation:** `h=2*A/b`

**Diagnostic contrast:** Distinguish rearranging a formula from substituting sample values.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.CED.A.4

### Q14 — Solve linear equations with literal coefficients and parameter cases

**Role:** CORE. **First planned unit:** 02.

**Performance:** State when a symbolic linear solution exists and when it does not.

**Bounds:** Literal coefficients and parameter cases; division requires a nonzero coefficient.

**Direct components:** Q13, Q12, R04

**Anchor:** Solve ax=b, including the cases a=0.

**Answer expectation:** `If a is nonzero, x=b/a. If a=0 and b=0, every real x works. If a=0 and b is nonzero, no solution exists.`

**Diagnostic contrast:** Test the exceptional parameter before accepting division by a.

**Required variants:** Nonzero parameter.; Zero coefficient and zero constant.; Zero coefficient and nonzero constant.

**Reference contributions:** HSA.REI.B.3

## Strand I: Inequalities

9 skills. Foundation and extension roles are explicit for each record.

### I01 — Interpret inequality symbols

**Role:** CORE. **First planned unit:** 02.

**Performance:** Interpret an inclusive numerical boundary.

**Bounds:** Strict and inclusive inequality symbols; meaning in context.

**Direct components:** N01

**Anchor:** Does x<=5 include x=5?

**Answer expectation:** `Yes.`

**Diagnostic contrast:** Contrast < with <= while preserving the boundary value.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.8

### I02 — Represent inequalities on number lines

**Role:** CORE. **First planned unit:** 02.

**Performance:** Represent an inequality as an interval and number-line specification.

**Bounds:** Number-line direction and open or closed endpoints.

**Direct components:** I01

**Anchor:** Represent x>-2 as an interval and describe its endpoint.

**Answer expectation:** `(-2,infinity); open at -2 and shaded right.`

**Diagnostic contrast:** Separate endpoint inclusion from shading direction.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.B.8, 7.EE.B.4.b

### I03 — Solve inequalities using addition/subtraction

**Role:** CORE. **First planned unit:** 02.

**Performance:** Solve an additive inequality.

**Bounds:** Addition or subtraction preserves inequality direction.

**Direct components:** I01, Q04

**Anchor:** Solve x+4<9.

**Answer expectation:** `(-infinity,5)`

**Diagnostic contrast:** Check whether subtraction alone is incorrectly taken to reverse the sign.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.B.4.b, HSA.REI.B.3

### I04 — Solve inequalities using positive multiplication/division

**Role:** CORE. **First planned unit:** 02.

**Performance:** Solve a positive-coefficient inequality.

**Bounds:** Multiplication or division by positive constants.

**Direct components:** I03, Q05

**Anchor:** Solve 3x>=12.

**Answer expectation:** `[4,infinity)`

**Diagnostic contrast:** Probe the endpoint and a value on either side of it.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 7.EE.B.4.b, HSA.REI.B.3

### I05 — Reverse inequality direction when multiplying/dividing by negatives

**Role:** CORE. **First planned unit:** 02.

**Performance:** Solve and justify the direction change.

**Bounds:** Multiplication or division by negatives reverses the order.

**Direct components:** I04, N06

**Anchor:** Solve -2x<6.

**Answer expectation:** `(-3,infinity)`

**Diagnostic contrast:** Check x=0 and x=-4 in the original inequality.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.3

### I06 — Solve multistep inequalities

**Role:** CORE. **First planned unit:** 02.

**Performance:** Simplify and solve while tracking order reversals.

**Bounds:** Multi-step linear inequalities with groups and signed coefficients.

**Direct components:** I05, E09, Q09

**Anchor:** Solve 3-2(x+1)>=9.

**Answer expectation:** `(-infinity,-4]`

**Diagnostic contrast:** Separate bracket expansion from the final negative division.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.3

### I07 — Solve and graph compound inequalities

**Role:** CORE. **First planned unit:** 02.

**Performance:** Represent simultaneous bounds correctly.

**Bounds:** Compound inequalities as intersections or unions; notation distinguished.

**Direct components:** I06, I02

**Conditional requirements:** the relation is an OR rather than AND → I02

**Anchor:** Solve -1<2x+3<=7.

**Answer expectation:** `(-2,2]`

**Diagnostic contrast:** Contrast an AND intersection with an OR union.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.3

### I08 — Translate restrictions into inequalities

**Role:** CORE. **First planned unit:** 02.

**Performance:** Model a budget constraint and interpret allowable counts.

**Bounds:** Contextual restrictions and discrete feasible values.

**Direct components:** I01, A08, A10

**Anchor:** A ticket costs 4 dollars and a fixed fee is 3 dollars. With at most 19 dollars, write a condition on the whole-number ticket count n.

**Answer expectation:** `4n+3<=19; n is an integer from 0 to 4.`

**Diagnostic contrast:** Distinguish the algebraic real interval from the feasible count.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.CED.A.1, HSA.CED.A.3

### I09 — Graph two-variable linear inequalities

**Role:** CORE. **First planned unit:** 05.

**Performance:** Describe and construct the complete solution region.

**Bounds:** Linear inequalities in two variables; boundary and half-plane both required.

**Direct components:** L05, I01, C02

**Anchor:** Graph y>2x-1.

**Answer expectation:** `Dashed boundary y=2x-1; shade the region above it; boundary excluded.`

**Diagnostic contrast:** Use a test point to distinguish the boundary from the solution region.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.D.12

## Strand C: Coordinates and functions

20 skills. Foundation and extension roles are explicit for each record.

### C01 — Understand the coordinate plane

**Role:** CORE. **First planned unit:** 03.

**Performance:** Interpret coordinate signs and axis order.

**Bounds:** Coordinate axes, origin, quadrants and scale; no slope needed.

**Direct components:** N01

**Anchor:** Which quadrant contains (-3,4)?

**Answer expectation:** `Quadrant II.`

**Diagnostic contrast:** Swap the coordinates to test axis-order reasoning.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.NS.C.6.c

### C02 — Plot and read ordered pairs

**Role:** CORE. **First planned unit:** 03.

**Performance:** Represent an ordered pair with the correct horizontal and vertical displacement.

**Bounds:** Read and plot points on labeled, scaled coordinate planes.

**Direct components:** C01

**Anchor:** Plot (-2,3) on a coordinate plane.

**Answer expectation:** `(-2,3): two units left and three units up.`

**Diagnostic contrast:** Change axis scales to distinguish reading marks from reading values.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.NS.C.6.c

### C03 — Identify independent and dependent variables

**Role:** CORE. **First planned unit:** 03.

**Performance:** Identify which quantity is treated as the input.

**Bounds:** Input and output roles come from the modeling question.

**Direct components:** A01

**Anchor:** Distance depends on time at a fixed speed. Which quantity is the independent variable?

**Answer expectation:** `Time, under the stated model.`

**Diagnostic contrast:** Reverse the modeling question to show that the role is contextual.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.C.9

### C04 — Construct and interpret input-output tables

**Role:** CORE. **First planned unit:** 03.

**Performance:** Construct values from a rule without confusing inputs and outputs.

**Bounds:** Input-output tables with stated domains and units.

**Direct components:** A09, C03

**Anchor:** For y=2x+1, give y when x=0,1,2.

**Answer expectation:** `[1, 3, 5]`

**Diagnostic contrast:** Check one substitution separately from table organization.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.C.9, 8.F.A.1

### C05 — Determine whether a relation is a function

**Role:** CORE. **First planned unit:** 03.

**Performance:** Decide whether a relation is a function and name any offending input.

**Bounds:** Exactly one output for each input; repeated outputs permitted.

**Direct components:** C03, C04

**Conditional requirements:** relation is supplied as a graph → C02

**Anchor:** Is {(1,2),(1,3),(2,3)} a function from its first coordinate?

**Answer expectation:** `No; input 1 has outputs 2 and 3.`

**Diagnostic contrast:** Contrast repeated inputs with repeated outputs.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.F.A.1, HSF.IF.A.1

### C06 — Understand function notation

**Role:** CORE. **First planned unit:** 03.

**Performance:** Interpret function notation in context.

**Bounds:** Function notation means an output, not multiplication by f.

**Direct components:** C05, A05

**Anchor:** If f maps hours to distance in kilometers, what does f(3)=18 mean?

**Answer expectation:** `After 3 hours the modeled distance is 18 kilometers.`

**Diagnostic contrast:** Compare f(3) with 3f and with the input 18.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.A.1, HSF.IF.A.2

### C07 — Evaluate functions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Evaluate and interpret an output without solving for the input.

**Bounds:** Evaluate a defined function at an allowed input.

**Direct components:** C06, A09

**Anchor:** For f(x)=x^2-3x, find f(-2).

**Answer expectation:** `10`

**Diagnostic contrast:** Contrast evaluation with solving f(x)=-2.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.A.2

### C08 — Determine domain and range

**Role:** CORE. **First planned unit:** 03.

**Performance:** State input and output sets for the defined situation.

**Bounds:** Domains and ranges from rules, tables, graphs and contexts; restrictions explicit.

**Direct components:** C05, N13

**Conditional requirements:** domain uses a variable denominator → R04

**Anchor:** A trip lasts 3 hours at 5 km/h, with d=5t. Give the time domain and distance range.

**Answer expectation:** `{"domain": "[0,3] hours", "range": "[0,15] kilometers"}`

**Diagnostic contrast:** Compare a continuous time interval with a discrete counting domain.

**Required variants:** Finite table domain and range.; Continuous contextual domain.; Discrete contextual domain and a representation with a restriction.

**Reference contributions:** HSF.IF.B.5

### C09 — Identify intercepts and zeros

**Role:** CORE. **First planned unit:** 03.

**Performance:** Identify zeros and both intercept types.

**Bounds:** An intercept is a point; a zero is an input with output zero.

**Direct components:** C02, Q02

**Anchor:** Find the x- and y-intercepts of y=2x-6.

**Answer expectation:** `{"x_intercept": [3, 0], "y_intercept": [0, -6]}`

**Diagnostic contrast:** Contrast x=0 with y=0 and distinguish a point from its coordinate.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.B.4

### C10 — Connect tables, graphs, equations, and words

**Role:** CORE. **First planned unit:** 03.

**Performance:** Translate a verbal relationship into a rule and a table.

**Bounds:** Connect representations of the same relationship without assuming linearity.

**Direct components:** C04, C02, A08

**Anchor:** A tank starts with 10 liters and gains 3 liters each minute. Write a rule and the values at 0,1,2 minutes.

**Answer expectation:** `V=10+3t; values 10,13,16 liters.`

**Diagnostic contrast:** Distinguish initial value from change per interval.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.9, 8.F.A.2

### C11 — Evaluate piecewise-defined functions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Evaluate a piecewise function using its condition.

**Bounds:** Select the correct branch including endpoint inclusion.

**Direct components:** C07, I01

**Anchor:** Let f(x)=x+1 for x<2 and f(x)=2x for x>=2. Find f(2).

**Answer expectation:** `4`

**Diagnostic contrast:** Probe the boundary value separately from interior values.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.b

### C12 — Graph piecewise and step functions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Construct a graph that preserves branch conditions.

**Bounds:** Piecewise linear and step graphs; holes and closed endpoints explicit.

**Direct components:** C11, C02

**Anchor:** Graph f(x)=0 for x<1 and f(x)=2 for x>=1.

**Answer expectation:** `Horizontal y=0 left of x=1 with an open point at (1,0); horizontal y=2 for x>=1 with a closed point at (1,2).`

**Diagnostic contrast:** Separate branch evaluation from endpoint graphing.

**Required variants:** Piecewise linear branches.; A step function.; Included and excluded branch endpoints.

**Reference contributions:** HSF.IF.C.7.b

### C13 — Interpret basic absolute-value functions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Interpret absolute value as distance and locate a graph's minimum.

**Bounds:** Basic absolute-value graphs and translations; solving absolute-value inequalities belongs to H01.

**Direct components:** N02, C07, C02

**Anchor:** For f(x)=abs(x-2), give the vertex and minimum value.

**Answer expectation:** `{"vertex": [2, 0], "minimum": 0}`

**Diagnostic contrast:** Contrast an input shift with an output shift.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.b

### C14 — Calculate average rates of change

**Role:** CORE. **First planned unit:** 03.

**Performance:** Compute change in output divided by change in input.

**Bounds:** Average change over an interval; not instantaneous change or an assumption of linearity.

**Direct components:** C07, N04, N06

**Conditional requirements:** changes are fractional → N09, N10

**Anchor:** For f(x)=x^2, find the average rate of change from x=1 to x=3.

**Answer expectation:** `4`

**Diagnostic contrast:** Contrast the quotient of changes with the quotient of endpoint values.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.B.6

### C15 — Interpret basic transformations of familiar functions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Track the image of a point under a specified function transformation.

**Bounds:** Basic translations, reflection and simple vertical scaling of familiar functions; general family transformations remain H04.

**Direct components:** C07, C02

**Conditional requirements:** quadratic features must be derived rather than supplied → U05

**Anchor:** The graph of f contains (1,3). Where is the corresponding point on g(x)=f(x-2)+4?

**Answer expectation:** `(3,7)`

**Diagnostic contrast:** Distinguish an input translation from the sign appearing inside the function.

**Required variants:** Vertical shift of a known graph.; Horizontal translation of a known graph.; Simple reflection or vertical scale with input-output tracking.

**Reference contributions:** HSF.BF.B.3

### C16 — Reverse simple functions and find linear inverses

**Role:** CORE. **First planned unit:** 03.

**Performance:** Reverse a simple input-output rule and check the reversal.

**Bounds:** Inverse meaning and nonzero-slope linear inverses; composition and nonlinear restrictions remain H03.

**Direct components:** C07, Q06

**Redirect:** A nonlinear inverse, composition, or one-to-one restriction is required → H03

**Anchor:** Find the inverse of f(x)=3x+2 over the real numbers.

**Answer expectation:** `f_inverse(x)=(x-2)/3`

**Diagnostic contrast:** Contrast inverse function with reciprocal.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.BF.B.4.a

### C17 — Compare functions presented in different representations

**Role:** CORE. **First planned unit:** 03.

**Performance:** Compare relevant properties rather than matching surface notation.

**Bounds:** Compare familiar functions across tables, graphs, rules and descriptions; family-specific prerequisites conditional.

**Direct components:** C10

**Conditional requirements:** comparison requires slopes from point data → L02; comparison includes exponential behavior → G04; comparison includes quadratic extrema → U05

**Anchor:** A table for f has f(0)=3,f(1)=5,f(2)=7 and is known linear. Let g(x)=3x+1. Compare initial values and rates.

**Answer expectation:** `{"larger_initial": "f", "f_rate": 2, "g_rate": 3, "larger_rate": "g"}`

**Diagnostic contrast:** Separate comparing initial values from comparing rates.

**Required variants:** Compare intercepts or initial values.; Compare rates.; Compare domain, extrema or other relevant features across representations.

**Reference contributions:** HSF.IF.C.9

### C18 — Graph basic square-root and cube-root functions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Construct a root-function graph and state its real domain.

**Bounds:** Basic real square-root and cube-root graphs; distinguish their domains.

**Direct components:** X10, X11, C02, C08

**Conditional requirements:** the root graph is transformed → C15

**Anchor:** Graph y=sqrt(x), giving its domain and four exact points.

**Answer expectation:** `Domain x>=0; points (0,0),(1,1),(4,2),(9,3); increasing in the first quadrant.`

**Diagnostic contrast:** Contrast the square-root domain with the cube-root domain, which allows negative inputs.

**Required variants:** Square-root graph and nonnegative input domain.; Cube-root graph including negative inputs.; Domain and range of a simple translated root graph.

**Reference contributions:** HSF.IF.C.7.b

### C19 — Combine familiar function models using arithmetic

**Role:** CORE. **First planned unit:** 03.

**Performance:** Combine outputs pointwise and retain the common domain.

**Bounds:** Arithmetic combinations of familiar function rules on their common domain; composition remains H03.

**Direct components:** C07, E04

**Conditional requirements:** a combined model contains an exponential part → G02; the common domain excludes inputs → C08, R04

**Anchor:** Let f(x)=x+2 and g(x)=3x. Write h(x)=f(x)+g(x).

**Answer expectation:** `4*x+2`

**Diagnostic contrast:** Contrast adding outputs at the same input with composing one function into another.

**Required variants:** Add familiar linear functions.; Combine a familiar function with a constant.; Interpret a shifted exponential model after G02 is available.

**Reference contributions:** HSF.BF.A.1.b

### C20 — Interpret key features and intervals of functions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Describe key features with interval and endpoint precision.

**Bounds:** Increasing, decreasing, positive and negative intervals, extrema and end behavior for loaded nonperiodic families; actual representation required.

**Direct components:** C08, C09

**Conditional requirements:** a quadratic rule requires derived intercepts and vertex → U05, U09; features are inferred from an exponential rule → G04

**Anchor:** For f(x)=(x-1)^2-4, state where f is decreasing and where it is negative.

**Answer expectation:** `Decreasing on (-infinity,1); negative on (-1,3).`

**Diagnostic contrast:** Distinguish an interval of inputs from a range of outputs or a sign of slope.

**Required variants:** Increasing and decreasing intervals.; Positive and negative intervals and zeros.; Extrema and end behavior in a stated model family.

**Reference contributions:** HSF.IF.B.4

## Strand L: Linear relationships

14 skills. Foundation and extension roles are explicit for each record.

### L01 — Recognize constant additive change

**Role:** CORE. **First planned unit:** 04.

**Performance:** Recognize constant rate from a table.

**Bounds:** Equal additive output changes over equal input intervals.

**Direct components:** C04, N04

**Anchor:** For x=0,1,2,3 the outputs are 5,8,11,14. Is the relationship linear, and what is the rate?

**Answer expectation:** `Yes; rate 3 per input unit.`

**Diagnostic contrast:** Use unequal input intervals to test whether raw differences are sufficient.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.F.A.3, HSF.LE.A.1.b

### L02 — Calculate slope between two points

**Role:** CORE. **First planned unit:** 04.

**Performance:** Compute slope with the same coordinate subtraction order.

**Bounds:** Slope between distinct points; vertical-line case addressed in L03.

**Direct components:** C02, N04, N06

**Anchor:** Find the slope through (-1,2) and (3,10).

**Answer expectation:** `2`

**Diagnostic contrast:** Contrast rise/run with run/rise and inconsistent subtraction order.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.B.6, HSF.IF.B.6

### L03 — Interpret positive, negative, zero, and undefined slopes

**Role:** CORE. **First planned unit:** 04.

**Performance:** Interpret horizontal and vertical special cases.

**Bounds:** Positive, negative, zero and undefined slope.

**Direct components:** L02

**Anchor:** What is the slope of a line through (2,-1) and (2,5)?

**Answer expectation:** `Undefined; the change in x is zero.`

**Diagnostic contrast:** Contrast zero rise with zero run.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.B.6, HSF.IF.B.6

### L04 — Identify y-intercepts

**Role:** CORE. **First planned unit:** 04.

**Performance:** Read a y-intercept and distinguish it from slope.

**Bounds:** The y-intercept is the value or point at input zero.

**Direct components:** C09

**Anchor:** Give the y-intercept point of y=-3x+7.

**Answer expectation:** `(0,7)`

**Diagnostic contrast:** Probe the meaning of setting x equal to zero.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.B.4, 8.F.B.4

### L05 — Graph y=mx+b

**Role:** CORE. **First planned unit:** 04.

**Performance:** Graph a line using the intercept and a rate.

**Bounds:** Nonvertical lines in slope-intercept form; scaled axes supported.

**Direct components:** L02, L04, C02

**Anchor:** Graph y=-2x+3 with two exact points.

**Answer expectation:** `Line through (0,3) and (1,1).`

**Diagnostic contrast:** Check slope direction separately from intercept placement.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.a

### L06 — Write linear equations from tables

**Role:** CORE. **First planned unit:** 04.

**Performance:** Determine rate and initial value from a known linear table.

**Bounds:** Linear tables with nonzero or zero initial input; do not assume all tables are linear.

**Direct components:** L01, L02, Q06

**Anchor:** A linear table contains (1,5),(2,8),(3,11). Write y as a function of x.

**Answer expectation:** `y=3*x+2`

**Diagnostic contrast:** Contrast the first table output with the value at input zero.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.2, 8.F.B.4

### L07 — Write linear equations from graphs

**Role:** CORE. **First planned unit:** 04.

**Performance:** Write a line rule using graph-derived points.

**Bounds:** Read coordinates from an actual labeled graph; coordinates supplied in the anchor remove image ambiguity.

**Direct components:** L05, L06

**Anchor:** A line's graph passes through (0,-1) and (2,3). Write its equation.

**Answer expectation:** `y=2*x-1`

**Diagnostic contrast:** Distinguish a graph-reading error from equation construction.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.2

### L08 — Write linear equations from two points

**Role:** CORE. **First planned unit:** 04.

**Performance:** Write a line equation and verify both points.

**Bounds:** Two distinct points; vertical cases require x=c rather than y=mx+b.

**Direct components:** L02, Q06

**Conditional requirements:** the two points have equal x-coordinates → L11

**Anchor:** Write the equation of the line through (2,1) and (4,5).

**Answer expectation:** `y=2*x-3`

**Diagnostic contrast:** Use one point to find the intercept after computing slope.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.2

### L09 — Convert between forms of linear equations

**Role:** CORE. **First planned unit:** 04.

**Performance:** Rewrite a linear equation without changing its solution pairs.

**Bounds:** Slope-intercept, point-slope and standard forms represent the same line.

**Direct components:** L05, Q13

**Anchor:** Rewrite 2x+3y=12 in slope-intercept form.

**Answer expectation:** `y=(-2/3)*x+4`

**Diagnostic contrast:** Check division of every term when isolating y.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.CED.A.4, HSF.IF.C.7.a

### L10 — Distinguish proportional and nonproportional linear relationships

**Role:** CORE. **First planned unit:** 04.

**Performance:** Distinguish a fixed fee from a proportional rate.

**Bounds:** Proportional relationships have zero initial value and constant ratio.

**Direct components:** L05, N14

**Anchor:** Is C=5+2n proportional to n? Explain.

**Answer expectation:** `No; the fixed term 5 prevents a constant C/n ratio and the graph does not pass through the origin.`

**Diagnostic contrast:** Contrast constant difference with constant ratio.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.F.B.4, HSF.LE.A.1.b

### L11 — Recognize horizontal and vertical lines

**Role:** CORE. **First planned unit:** 04.

**Performance:** Identify equations of horizontal and vertical lines.

**Bounds:** Horizontal y=c and vertical x=c; only the former is a function of x.

**Direct components:** L03, C02

**Anchor:** Name the vertical line through (4,-2) and the horizontal line through it.

**Answer expectation:** `{"vertical": "x=4", "horizontal": "y=-2"}`

**Diagnostic contrast:** Contrast the coordinate that stays fixed on each line.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.a, HSA.REI.D.10

### L12 — Compare two linear models

**Role:** CORE. **First planned unit:** 04.

**Performance:** Interpret an intersection as a comparison threshold.

**Bounds:** Compare linear models across representations; contextual units required.

**Direct components:** L05, L13

**Conditional requirements:** an exact comparison threshold must be solved → Q08

**Anchor:** Two plans cost A=10+2n and B=4+3n. When do they have equal cost?

**Answer expectation:** `{"n": 6, "cost": 22, "unit": "dollars"}`

**Diagnostic contrast:** Compare equality at the threshold with which plan is cheaper on each side.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.9

### L13 — Interpret slope and intercept with units

**Role:** CORE. **First planned unit:** 04.

**Performance:** Connect coefficients to quantities in a model.

**Bounds:** Interpret slope and intercept with compatible units and realistic domains.

**Direct components:** L05, A10

**Anchor:** For C=12+4h, explain 12 and 4.

**Answer expectation:** `12 dollars is the fixed charge; 4 dollars per hour is the hourly rate.`

**Diagnostic contrast:** Ask for units to distinguish a rate from a total.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.B.5, HSS.ID.C.7

### L14 — Determine whether a relationship is linear

**Role:** CORE. **First planned unit:** 04.

**Performance:** Test a proposed linear relationship.

**Bounds:** Linearity requires constant change per equal input interval, not visual roughness.

**Direct components:** L01, L05

**Anchor:** For x=0,1,2, outputs are 1,2,5. Could these points lie on one line?

**Answer expectation:** `No; the successive output changes are 1 and 3 for equal input changes.`

**Diagnostic contrast:** Distinguish nonlinearity from an irregularly spaced input table.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.F.A.3, HSF.LE.A.1

## Strand S: Systems of equations and constraints

9 skills. Foundation and extension roles are explicit for each record.

### S01 — Understand simultaneous solutions

**Role:** CORE. **First planned unit:** 05.

**Performance:** Test an ordered pair in a system.

**Bounds:** A simultaneous solution satisfies every equation.

**Direct components:** Q02, C02

**Anchor:** Does (2,3) solve x+y=5 and 2x-y=1?

**Answer expectation:** `Yes; both equations are true.`

**Diagnostic contrast:** Compare satisfying one equation with satisfying the whole system.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.C.8.a, HSA.REI.C.6

### S02 — Solve systems graphically

**Role:** CORE. **First planned unit:** 05.

**Performance:** Identify and verify the intersection.

**Bounds:** Graphical intersections of two lines; exact versus approximate coordinates distinguished.

**Direct components:** S01, L05

**Anchor:** Find the intersection of y=x+1 and y=-x+5.

**Answer expectation:** `(2,3)`

**Diagnostic contrast:** Separate graph-reading precision from algebraic verification.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.C.6

### S03 — Solve systems using substitution

**Role:** CORE. **First planned unit:** 05.

**Performance:** Solve through substitution and verify both equations.

**Bounds:** Substitution in two-variable linear systems; replace the entire expression.

**Direct components:** S01, Q09

**Anchor:** Solve y=2x+1 and x+y=10.

**Answer expectation:** `(3,7)`

**Diagnostic contrast:** Probe grouping when the substituted expression has several terms.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.C.6

### S04 — Solve systems using elimination

**Role:** CORE. **First planned unit:** 05.

**Performance:** Solve and justify the elimination operation.

**Bounds:** Elimination as addition of equivalent equations; entire equations combined.

**Direct components:** S01, Q09

**Anchor:** Solve x+y=7 and x-y=1.

**Answer expectation:** `(4,3)`

**Diagnostic contrast:** Contrast adding equations with adding only selected terms.

**Required variants:** Explain the equation addition.; Retain the other equation.; Explain reversibility of the replacement and verify the original system.

**Reference contributions:** HSA.REI.C.5, HSA.REI.C.6

### S05 — Scale equations before elimination

**Role:** CORE. **First planned unit:** 05.

**Performance:** Choose and apply useful equation multipliers.

**Bounds:** Scaling every term before elimination.

**Direct components:** S04, E07

**Anchor:** Solve 2x+3y=13 and 3x-2y=0.

**Answer expectation:** `(2,3)`

**Diagnostic contrast:** Check scaled constants as well as coefficients.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.C.5, HSA.REI.C.6

### S06 — Identify one, no, or infinitely many solutions

**Role:** CORE. **First planned unit:** 05.

**Performance:** Interpret a contradictory or redundant system.

**Bounds:** Unique intersection, parallel distinct lines and coincident lines.

**Direct components:** S01, Q12

**Anchor:** Classify 2x+y=4 and 4x+2y=9.

**Answer expectation:** `No solution; the second left side is twice the first but the constants conflict.`

**Diagnostic contrast:** Contrast equal slopes with identical equations.

**Required variants:** One intersection.; Distinct parallel lines.; Coincident lines.

**Reference contributions:** HSA.REI.C.6

### S07 — Construct and interpret contextual systems

**Role:** CORE. **First planned unit:** 05.

**Performance:** Build a system and interpret the result in context.

**Bounds:** Contextual systems, defined variables and feasible interpretations.

**Direct components:** S01, A10, M02

**Conditional requirements:** symbolic solution by substitution is selected → S03; symbolic solution by elimination is selected → S04; graphical solution is selected → S02

**Anchor:** Ten tickets cost 68 dollars. Adult tickets cost 8 dollars and child tickets 5 dollars. Find the counts.

**Answer expectation:** `{"equations": ["a+c=10", "8*a+5*c=68"], "adults": 6, "children": 4}`

**Diagnostic contrast:** Separate model construction from the chosen solving method.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.CED.A.3, HSA.REI.C.6

### S08 — Solve systems of linear inequalities graphically

**Role:** CORE. **First planned unit:** 05.

**Performance:** Identify a complete feasible region.

**Bounds:** Intersection of half-planes with all boundary rules preserved.

**Direct components:** I09, S01

**Anchor:** Describe the region satisfying x>=0, y>=0 and x+y<=6.

**Answer expectation:** `Closed triangle including its boundary with vertices (0,0),(6,0),(0,6).`

**Diagnostic contrast:** Test a point against every constraint rather than one inequality.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.CED.A.3, HSA.REI.D.12

### S09 — Solve and interpret linear-quadratic intersections

**Role:** CORE. **First planned unit:** 09.

**Performance:** Find every real intersection and verify both relations.

**Bounds:** One line and one quadratic; graph or symbolic solution route; no general nonlinear-system claim.

**Direct components:** S01

**Alternative methods:** graph: S02, U04; substitution_then_factoring: S03, U08; substitution_then_formula: S03, U12

**Anchor:** Solve y=x+2 and y=x^2.

**Answer expectation:** `[[-1, 1], [2, 4]]`

**Diagnostic contrast:** Contrast solving the substituted quadratic with checking the resulting y-values.

**Required variants:** Two real intersections.; One tangent intersection.; No real intersection; graphical and symbolic evidence compared.

**Reference contributions:** HSA.REI.C.7, HSA.REI.D.11

## Strand X: Exponents and roots

11 skills. Foundation and extension roles are explicit for each record.

### X01 — Interpret exponent notation

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Interpret and evaluate a power.

**Bounds:** Whole-number exponents as repeated multiplication; not a product of base and exponent.

**Direct components:** N18

**Anchor:** Evaluate 2^5.

**Answer expectation:** `32`

**Diagnostic contrast:** Contrast exponent notation with multiplying 2 by 5.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 6.EE.A.1, 8.EE.A.1

### X02 — Distinguish negative bases and negative exponents

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Distinguish the base from a leading negative sign.

**Bounds:** Placement of a negative sign and parentheses; reciprocal calculation belongs to X08.

**Direct components:** X01, N05

**Conditional requirements:** a negative exponent must be evaluated → X08

**Anchor:** Evaluate -3^2 and (-3)^2.

**Answer expectation:** `{"negative_of_square": -9, "square_of_negative": 9}`

**Diagnostic contrast:** Change parentheses only to isolate the scope of the exponent.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.1, HSA.SSE.A.1.a

### X03 — Multiply powers with common bases

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Use repeated multiplication to justify addition of exponents.

**Bounds:** Products with a common nonzero base where required by later variants.

**Direct components:** X01

**Anchor:** Simplify x^3\*x^4.

**Answer expectation:** `x^7`

**Diagnostic contrast:** Contrast multiplying like bases with multiplying unlike bases.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.1

### X04 — Divide powers with common bases

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Subtract exponents and retain domain restrictions.

**Bounds:** Common-base quotients with nonzero base; positive exponent difference initially.

**Direct components:** X03, N06

**Anchor:** Simplify x^7/x^3 and state the original restriction.

**Answer expectation:** `x^4, with x!=0.`

**Diagnostic contrast:** Contrast a simplified formula with its original domain.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.1

### X05 — Apply the power-of-a-power rule

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Multiply exponents rather than add them.

**Bounds:** Power of a power; integer exponents.

**Direct components:** X03

**Anchor:** Simplify (x^3)^2.

**Answer expectation:** `x^6`

**Diagnostic contrast:** Compare a product of powers with a power of a power.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.1

### X06 — Apply powers to products and quotients

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Distribute a power over a product correctly.

**Bounds:** Powers applied to every factor or quotient component.

**Direct components:** X03, N10

**Anchor:** Expand (2x)^3.

**Answer expectation:** `8*x^3`

**Diagnostic contrast:** Distinguish cubing the coefficient from multiplying it by the exponent.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.1

### X07 — Understand zero exponents

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Explain the zero-exponent rule using a quotient.

**Bounds:** a^0=1 for a nonzero; 0^0 is outside the item family.

**Direct components:** X04

**Anchor:** Why is 5^0=1?

**Answer expectation:** `5^3/5^3 equals 1 and also equals 5^(3-3).`

**Diagnostic contrast:** Check the nonzero-base condition before extending the rule.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.1

### X08 — Interpret negative exponents

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Rewrite a negative exponent as a reciprocal.

**Bounds:** Negative integer exponents indicate reciprocals; zero base excluded.

**Direct components:** X07, N10

**Anchor:** Evaluate 2^(-3).

**Answer expectation:** `1/8`

**Diagnostic contrast:** Contrast a negative exponent with a negative value.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.1

### X09 — Work with scientific notation

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Normalize a decimal in scientific notation.

**Bounds:** Scientific notation uses 1<=abs(a)<10 and an integer exponent.

**Direct components:** X08, N20

**Anchor:** Write 0.00072 in scientific notation.

**Answer expectation:** `7.2*10^(-4)`

**Diagnostic contrast:** Separate moving the decimal from choosing the exponent sign.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.3, 8.EE.A.4

### X10 — Understand exact and approximate square roots

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Bound a nonperfect square root without calling it a two-valued function.

**Bounds:** Principal square roots; exact and approximate values distinguished.

**Direct components:** N15, N13

**Anchor:** Between which consecutive integers is sqrt(50)?

**Answer expectation:** `(7,8)`

**Diagnostic contrast:** Contrast the principal square root with solving x^2=50.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.2, HSN.RN.B.3

### X11 — Evaluate and interpret real cube roots

**Role:** FOUNDATION. **First planned unit:** ON_DEMAND.

**Performance:** Use the inverse of cubing with the correct sign.

**Bounds:** Real cube roots of positive and negative values; exact perfect cubes and estimates.

**Direct components:** X01, N05, N13

**Anchor:** Evaluate cbrt(-125).

**Answer expectation:** `-5`

**Diagnostic contrast:** Contrast an odd root of a negative number with an even root.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** 8.EE.A.2

## Strand P: Polynomial operations

10 skills. Foundation and extension roles are explicit for each record.

### P01 — Classify polynomial expressions

**Role:** CORE. **First planned unit:** 08.

**Performance:** Distinguish a polynomial from a variable-denominator expression.

**Bounds:** Polynomial expressions in one variable with nonnegative integer exponents.

**Direct components:** A02, X01

**Anchor:** Is 3x^2-2x+1 a polynomial? Is 1/x a polynomial in x?

**Answer expectation:** `The first is a polynomial; 1/x is not.`

**Diagnostic contrast:** Contrast numerical coefficients with a variable in a denominator.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1

### P02 — Determine degree and leading coefficient

**Role:** CORE. **First planned unit:** 08.

**Performance:** Identify degree and leading coefficient.

**Bounds:** Degree after simplification; leading coefficient includes its sign.

**Direct components:** P01

**Anchor:** Give the degree and leading coefficient of -4x^3+2x-7.

**Answer expectation:** `{"degree": 3, "leading_coefficient": -4}`

**Diagnostic contrast:** Present terms out of order to test reliance on visual position.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1, HSA.SSE.A.1.a

### P03 — Add polynomials

**Role:** CORE. **First planned unit:** 08.

**Performance:** Add polynomial expressions without combining unlike powers.

**Bounds:** Add polynomials by matching exponents; core tasks through quadratic degree.

**Direct components:** P01, E03

**Anchor:** Simplify (2x^2+3x-1)+(x^2-5x+4).

**Answer expectation:** `3*x^2-2*x+3`

**Diagnostic contrast:** Isolate matching powers from signed coefficient addition.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1

### P04 — Subtract polynomials

**Role:** CORE. **First planned unit:** 08.

**Performance:** Subtract polynomials correctly.

**Bounds:** Subtract an entire polynomial, including every sign.

**Direct components:** P03, E08

**Anchor:** Simplify (3x^2+x-2)-(x^2-4x+5).

**Answer expectation:** `2*x^2+5*x-7`

**Diagnostic contrast:** Compare correct collection after the subtraction group is removed.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1

### P05 — Multiply monomials by polynomials

**Role:** CORE. **First planned unit:** 08.

**Performance:** Combine distribution with exponent rules.

**Bounds:** A monomial times each polynomial term.

**Direct components:** E07, X03

**Anchor:** Expand -2x(3x^2-x+4).

**Answer expectation:** `-6*x^3+2*x^2-8*x`

**Diagnostic contrast:** Separate exponent combination from sign multiplication.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1

### P06 — Multiply binomials

**Role:** CORE. **First planned unit:** 08.

**Performance:** Expand a product using a justified area or distributive model.

**Bounds:** Binomial products including all cross terms.

**Direct components:** E06, P05

**Anchor:** Expand (x+3)(x-2).

**Answer expectation:** `x^2+x-6`

**Diagnostic contrast:** Check the middle term independently of the first and last terms.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1

### P07 — Multiply larger polynomial expressions

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Apply distribution beyond a memorized binomial routine.

**Bounds:** Optional extension to longer products and degree above two.

**Direct components:** P06

**Anchor:** Expand (x+1)(x^2-x+1).

**Answer expectation:** `x^3+1`

**Diagnostic contrast:** Test whether the method generalizes when there are more than four partial products.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1

### P08 — Expand special products

**Role:** CORE. **First planned unit:** 08.

**Performance:** Expand a special product and justify its middle term.

**Bounds:** Square-of-sum, square-of-difference and difference-of-squares identities.

**Direct components:** P06, X01

**Anchor:** Expand (x-4)^2.

**Answer expectation:** `x^2-8*x+16`

**Diagnostic contrast:** Contrast squaring a binomial with squaring its terms separately.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1, HSA.SSE.A.2

### P09 — Simplify polynomials without combining unlike powers

**Role:** CORE. **First planned unit:** 08.

**Performance:** Collect polynomial terms without merging different exponents.

**Bounds:** Simplification preserves distinct powers; identity restrictions unchanged.

**Direct components:** P03, P04

**Anchor:** Simplify 2x^2+3x-x^2+4x.

**Answer expectation:** `x^2+7*x`

**Diagnostic contrast:** Contrast like powers with visually similar variable terms.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.A.1

### P10 — Explain polynomial closure and its limits

**Role:** CORE. **First planned unit:** 08.

**Performance:** Justify closure using nonnegative integer exponents and give a division counterexample.

**Bounds:** Closure under addition, subtraction and multiplication; division is not generally closed.

**Direct components:** P03, P04, P06

**Anchor:** Are polynomials closed under division by nonzero polynomials? Explain.

**Answer expectation:** `No; dividing the polynomial 1 by the nonzero polynomial x gives 1/x, which is not a polynomial in x.`

**Diagnostic contrast:** Contrast valid polynomial division with a guarantee that every quotient is a polynomial.

**Required variants:** Addition/subtraction closure.; Multiplication closure by exponent structure.; Counterexample to division closure.

**Reference contributions:** HSA.APR.A.1

## Strand F: Factoring

9 skills. Foundation and extension roles are explicit for each record.

### F01 — Factor out the greatest common factor

**Role:** CORE. **First planned unit:** 08.

**Performance:** Identify and factor the greatest common monomial.

**Bounds:** Greatest common numerical and variable factors; complete extraction required.

**Direct components:** E10, P01

**Anchor:** Factor 12x^3+18x^2 completely using a common factor.

**Answer expectation:** `6*x^2*(2*x+3)`

**Diagnostic contrast:** Separate coefficient GCF from the minimum shared exponent.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.A.2

### F02 — Factor using negative common factors

**Role:** CORE. **First planned unit:** 08.

**Performance:** Choose a negative common factor and preserve the product.

**Bounds:** Negative common factors with equivalent remaining signs.

**Direct components:** F01, N02

**Anchor:** Factor -3x^2+6x with a negative greatest common monomial.

**Answer expectation:** `-3*x*(x-2)`

**Diagnostic contrast:** Re-expand to distinguish a sign convention from an incorrect identity.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.A.2

### F03 — Identify factor pairs

**Role:** CORE. **First planned unit:** 08.

**Performance:** Find factors satisfying two constraints.

**Bounds:** Signed integer factor pairs; product and sum both matter.

**Direct components:** N18, N05

**Anchor:** Find two integers whose product is -12 and whose sum is 1.

**Answer expectation:** `4 and -3.`

**Diagnostic contrast:** Check the product and sum separately before accepting a pair.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.B.3.a

### F04 — Factor monic trinomials

**Role:** CORE. **First planned unit:** 08.

**Performance:** Factor a monic trinomial and verify by expansion.

**Bounds:** Monic integer trinomials that factor over the integers.

**Direct components:** F03, P06

**Anchor:** Factor x^2+x-12.

**Answer expectation:** `(x+4)*(x-3)`

**Diagnostic contrast:** Use the signed factor-pair probe F03 to distinguish factoring structure from integer facts.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.B.3.a

### F05 — Factor nonmonic trinomials

**Role:** CORE. **First planned unit:** 08.

**Performance:** Factor and check both middle cross terms.

**Bounds:** Nonmonic quadratics with an integer factorization; irreducible cases acknowledged.

**Direct components:** F04, P05

**Anchor:** Factor 6x^2+11x+3.

**Answer expectation:** `(3*x+1)*(2*x+3)`

**Diagnostic contrast:** Check the combined cross-term coefficient rather than only the first and last products.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.B.3.a

### F06 — Factor differences of squares

**Role:** CORE. **First planned unit:** 08.

**Performance:** Recognize and factor a difference of squares.

**Bounds:** A difference of two squares; sums of squares do not use the same real identity.

**Direct components:** P08, N15

**Anchor:** Factor 9x^2-16.

**Answer expectation:** `(3*x-4)*(3*x+4)`

**Diagnostic contrast:** Contrast 9x^2-16 with 9x^2+16.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.A.2, HSA.SSE.B.3.a

### F07 — Factor perfect-square trinomials

**Role:** CORE. **First planned unit:** 08.

**Performance:** Recognize and factor a square trinomial.

**Bounds:** Perfect-square trinomials require the matching middle term.

**Direct components:** P08, F04

**Anchor:** Factor x^2-10x+25.

**Answer expectation:** `(x-5)^2`

**Diagnostic contrast:** Vary the middle coefficient while holding the two square terms fixed.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.A.2, HSA.SSE.B.3.a

### F08 — Verify factorization by expanding

**Role:** CORE. **First planned unit:** 08.

**Performance:** Reject or confirm a factorization by expansion.

**Bounds:** Verification must recover every term of the original expression.

**Direct components:** P06

**Anchor:** Is x^2+5x+6=(x+1)(x+6)? Justify.

**Answer expectation:** `No; the proposed product expands to x^2+7x+6.`

**Diagnostic contrast:** Distinguish matching constant terms from full equivalence.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.A.2

### F09 — Apply the zero-product property

**Role:** CORE. **First planned unit:** 08.

**Performance:** Set each factor equal to zero and report all solutions.

**Bounds:** Zero-product property applies only when the product equals zero.

**Direct components:** Q05, Q02, A04

**Anchor:** Solve (x-3)(x+2)=0.

**Answer expectation:** `[-2, 3]`

**Diagnostic contrast:** Contrast a zero product with a product equal to a nonzero number.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.b

## Strand U: Quadratic functions and equations

16 skills. Foundation and extension roles are explicit for each record.

### U01 — Recognize quadratic relationships

**Role:** CORE. **First planned unit:** 03.

**Performance:** Recognize a quadratic model among familiar families.

**Bounds:** Quadratic rules, area and constant second differences over equal intervals; finite data do not prove a unique future rule.

**Direct components:** X01, C04

**Anchor:** For the rule A(s)=s^2, explain why output changes over equal input steps are not constant.

**Answer expectation:** `The differences grow; for s=1,2,3 the areas are 1,4,9 and changes are 3 then 5.`

**Diagnostic contrast:** Contrast an explicit quadratic rule with merely seeing a curved picture.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.a

### U02 — Identify coefficients in standard quadratic form

**Role:** CORE. **First planned unit:** 09.

**Performance:** Identify all three coefficients including a missing linear term.

**Bounds:** Standard form ax^2+bx+c with a nonzero; missing terms have coefficient zero.

**Direct components:** P02

**Anchor:** Give a,b,c for -2x^2+5=0.

**Answer expectation:** `{"a": -2, "b": 0, "c": 5}`

**Diagnostic contrast:** Use a missing term to test coefficient-position assumptions.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.SSE.A.1.a, HSA.REI.B.4

### U03 — Recognize quadratic patterns through second differences

**Role:** CORE. **First planned unit:** 09.

**Performance:** Use first and second differences to support a quadratic model.

**Bounds:** Second differences only for equally spaced inputs; model assumption explicit.

**Direct components:** U01, N04

**Anchor:** At x=0,1,2,3 the outputs are 1,4,9,16. Find the second differences.

**Answer expectation:** `[2,2]; consistent with a quadratic model on these inputs.`

**Diagnostic contrast:** Change input spacing to test the equal-interval condition.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.B.4

### U04 — Interpret parabolas and their intercepts

**Role:** CORE. **First planned unit:** 09.

**Performance:** Interpret graph features from a factored quadratic.

**Bounds:** Parabola orientation and intercepts; real zeros may be absent.

**Direct components:** U01, C02, C09

**Anchor:** For y=(x-1)(x-3), give the x-intercepts and y-intercept.

**Answer expectation:** `{"x_intercepts": [[1, 0], [3, 0]], "y_intercept": [0, 3]}`

**Diagnostic contrast:** Distinguish roots from intercept points and check x=0 separately.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.a

### U05 — Identify vertex, symmetry, maxima, and minima

**Role:** CORE. **First planned unit:** 09.

**Performance:** Interpret vertex form and the sign of the leading factor.

**Bounds:** Vertex, symmetry and extrema in a stated real or contextual domain.

**Direct components:** U04

**Anchor:** For y=-(x-2)^2+9, state the vertex, axis and maximum.

**Answer expectation:** `{"vertex": [2, 9], "axis": "x=2", "maximum": 9}`

**Diagnostic contrast:** Contrast maximum output with the input at which it occurs.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.B.4, HSF.IF.C.7.a

### U06 — Interpret standard, factored, and vertex forms

**Role:** CORE. **First planned unit:** 09.

**Performance:** Choose the form that reveals a requested feature.

**Bounds:** Standard, factored and vertex forms; features extracted without unnecessary full expansion.

**Direct components:** U02, U04, P08

**Anchor:** For x^2-4x+3=(x-1)(x-3)=(x-2)^2-1, which form directly shows the minimum?

**Answer expectation:** `Vertex form shows minimum -1 at x=2.`

**Diagnostic contrast:** Ask which form is useful for zeros versus initial value versus vertex.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.8.a, HSA.SSE.B.3

### U07 — Solve equations of the form x^2=k

**Role:** CORE. **First planned unit:** 09.

**Performance:** Distinguish the square-root function from solving a squared equation.

**Bounds:** Real solutions of x^2=k; both signs when k>0, one zero root when k=0.

**Direct components:** X10, Q05

**Anchor:** Solve x^2=49 over the real numbers.

**Answer expectation:** `[-7, 7]`

**Diagnostic contrast:** Contrast the solution set with the principal value sqrt(49).

**Required variants:** Positive k with two real solutions.; k=0 with one real solution.; Negative k with no real solution.

**Reference contributions:** 8.EE.A.2, HSA.REI.B.4.b

### U08 — Solve quadratics by factoring

**Role:** CORE. **First planned unit:** 09.

**Performance:** Factor, solve every factor and verify the original equation.

**Bounds:** Integer-coefficient quadratics with accessible integer factorizations; solve over the reals and rearrange to zero first. Fractional coefficients require the declared clearing step.

**Direct components:** F09, Q02

**Alternative methods:** monic: F04; nonmonic: F05; difference_of_squares: F06; perfect_square: F07

**Conditional requirements:** fractional coefficients must first be cleared → Q11, N10

**Anchor:** Solve x^2-x-6=0.

**Answer expectation:** `[-2, 3]`

**Diagnostic contrast:** Separate a factoring probe from a zero-product probe.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.b

### U09 — Solve using square roots from vertex form

**Role:** CORE. **First planned unit:** 09.

**Performance:** Isolate the square and solve both branches.

**Bounds:** Quadratics in square form; sign and exact roots preserved.

**Direct components:** U07, Q06

**Anchor:** Solve (x-2)^2=9.

**Answer expectation:** `[-1, 5]`

**Diagnostic contrast:** Check whether the square-root step produces both signs before undoing the shift.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.b

### U10 — Complete the square for monic quadratics

**Role:** CORE. **First planned unit:** 09.

**Performance:** Add the required square while preserving equality.

**Bounds:** Monic completing the square, including odd linear coefficients.

**Direct components:** P08, Q06, N09

**Anchor:** Rewrite x^2+6x+2=0 as (x+h)^2=k.

**Answer expectation:** `(x+3)^2=7`

**Diagnostic contrast:** Probe half the linear coefficient before squaring it.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.a, HSA.SSE.B.3.b

### U11 — Complete the square for nonmonic quadratics

**Role:** CORE. **First planned unit:** 09.

**Performance:** Complete the square without losing constant scaling.

**Bounds:** Nonmonic quadratics; normalize the leading coefficient or preserve an equivalent outside factor.

**Direct components:** U10, Q10

**Anchor:** Rewrite 2x^2+8x+1=0 in square form.

**Answer expectation:** `(x+2)^2=7/2`

**Diagnostic contrast:** Distinguish dividing all terms by a from halving the original b.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.a

### U12 — Apply the quadratic formula

**Role:** CORE. **First planned unit:** 09.

**Performance:** Substitute signed coefficients with correct grouping and state both roots.

**Bounds:** Quadratic formula with a nonzero; exact real roots and declared approximations.

**Direct components:** U02, X10, Q10

**Anchor:** Solve x^2-2x-1=0 exactly.

**Answer expectation:** `["1-sqrt(2)", "1+sqrt(2)"]`

**Diagnostic contrast:** Probe coefficient extraction separately from the minus sign in -b.

**Required variants:** Two rational roots.; Irrational exact roots.; A repeated root and a no-real-root case, with number system explicit.

**Reference contributions:** HSA.REI.B.4.b

### U13 — Interpret the discriminant

**Role:** CORE. **First planned unit:** 09.

**Performance:** Interpret a negative discriminant over the real domain.

**Bounds:** Discriminant classifies the number of real roots; complex construction remains H07.

**Direct components:** U12

**Anchor:** How many real solutions does x^2+2x+5=0 have?

**Answer expectation:** `None; the discriminant is -16.`

**Diagnostic contrast:** Contrast no real roots with no roots in every possible number system.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.b

### U14 — Connect quadratic roots to x-intercepts

**Role:** CORE. **First planned unit:** 09.

**Performance:** Connect a solution set to x-intercepts.

**Bounds:** Real equation roots correspond to graph points with y=0.

**Direct components:** U08, U04

**Anchor:** For y=x^2-5x+6, connect the equation's roots to graph intercepts.

**Answer expectation:** `{"roots": [2, 3], "x_intercepts": [[2, 0], [3, 0]]}`

**Diagnostic contrast:** Distinguish x-coordinates from full ordered pairs.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.D.10, HSF.IF.C.8.a

### U15 — Choose an appropriate solving method and interpret results

**Role:** CORE. **First planned unit:** 09.

**Performance:** Choose and justify an efficient valid solving method.

**Bounds:** Strategic choice among known methods; contextual feasibility and exactness included.

**Direct components:** U08, U09, U12, M05

**Anchor:** Choose an efficient method for (x-4)^2=7 and solve exactly.

**Answer expectation:** `{"method": "square_roots", "solutions": ["4-sqrt(7)", "4+sqrt(7)"], "reason": "The square is already isolated."}`

**Diagnostic contrast:** Contrast choosing a valid method with choosing an unnecessarily long one.

**Required variants:** Compare at least two valid methods.; Select an efficient method from expression structure.; Interpret and check contextual feasibility.

**Reference contributions:** HSA.REI.B.4, HSF.IF.B.4

### U16 — Derive and justify the quadratic formula

**Role:** CORE. **First planned unit:** 09.

**Performance:** Derive the formula through equivalent transformations and completing the square.

**Bounds:** Derivation for ax^2+bx+c=0 with a nonzero; real-root condition stated.

**Direct components:** U11, E11, Q13

**Anchor:** Derive the quadratic formula from ax^2+bx+c=0 for a nonzero.

**Answer expectation:** `For a!=0, multiply by 4a and rearrange to (2ax+b)^2=b^2-4ac. Taking both real branches when the discriminant is nonnegative gives x=(-b +/- sqrt(b^2-4ac))/(2a).`

**Diagnostic contrast:** Check equality preservation and the denominator 2a rather than accepting a memorized formula.

**Required variants:** Show the completing-the-square identity.; Include a!=0.; Retain both branches and the real-discriminant condition.

**Reference contributions:** HSA.REI.B.4.a

## Strand G: Exponential relationships and sequences

13 skills. Foundation and extension roles are explicit for each record.

### G01 — Distinguish additive from multiplicative growth

**Role:** CORE. **First planned unit:** 07.

**Performance:** Distinguish additive and multiplicative patterns under the stated model family.

**Bounds:** Equal input intervals; fixed differences versus fixed positive ratios.

**Direct components:** L01, N14

**Anchor:** A quantity doubles each day. Is the change additive or multiplicative?

**Answer expectation:** `Multiplicative; the ratio for each day is 2.`

**Diagnostic contrast:** Contrast constant percentage with constant amount.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.1.a, HSF.LE.A.1.c

### G02 — Understand y=ab^x

**Role:** CORE. **First planned unit:** 07.

**Performance:** Interpret initial value and growth factor.

**Bounds:** y=a*b^x with b>0 and b!=1; nonnegative quantities used for entry contexts.

**Direct components:** G01, X01, A10

**Anchor:** For P(t)=50\*1.2^t, explain 50 and 1.2.

**Answer expectation:** `Initial quantity 50; each unit of t multiplies the quantity by 1.2.`

**Diagnostic contrast:** Distinguish a multiplier from the amount added.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.B.5, HSF.LE.A.2, HSA.SSE.A.1.b

### G03 — Evaluate exponential functions

**Role:** CORE. **First planned unit:** 07.

**Performance:** Compute the modeled value at a specified input.

**Bounds:** Evaluate familiar exponential functions; negative inputs conditional on X08.

**Direct components:** G02, A09

**Conditional requirements:** the exponent is a negative integer → X08

**Anchor:** For P(t)=50\*2^t, find P(3).

**Answer expectation:** `400`

**Diagnostic contrast:** Separate exponent evaluation from multiplication by the initial value.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.A.2

### G04 — Graph exponential growth and decay

**Role:** CORE. **First planned unit:** 07.

**Performance:** Graph growth or decay with meaningful scale and domain.

**Bounds:** Positive-base exponential graphs; intercept and end behavior; logarithmic and trigonometric families excluded from core scope.

**Direct components:** G03, C02

**Conditional requirements:** negative integer inputs must be plotted → X08

**Anchor:** Sketch y=2^x with three exact points and its horizontal limiting value.

**Answer expectation:** `Points (-1,1/2),(0,1),(1,2); approaches y=0 as x decreases without bound and never reaches 0.`

**Diagnostic contrast:** Contrast a limiting value with an attained intercept.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.e

### G05 — Interpret growth/decay multipliers and percentages

**Role:** CORE. **First planned unit:** 07.

**Performance:** Translate a repeated percentage change to a factor.

**Bounds:** Percent increase and decrease multipliers; valid nonnegative percentage contexts.

**Direct components:** G02, N12

**Anchor:** A value decreases by 15% each year. Write its annual multiplier.

**Answer expectation:** `0.85`

**Diagnostic contrast:** Contrast the percent lost with the fraction retained.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.1.c, HSF.LE.B.5

### G06 — Construct exponential models

**Role:** CORE. **First planned unit:** 07.

**Performance:** Build and interpret an exponential model.

**Bounds:** Construct a*b^t from context or suitable data; time origin explicit.

**Direct components:** G05, C04

**Conditional requirements:** two observations have a multi-unit time gap requiring roots → H06

**Anchor:** A culture starts at 80 units and triples each hour. Write P(t), with t in hours.

**Answer expectation:** `P(t)=80*3^t`

**Diagnostic contrast:** Ask for the value at t=0 to test initial-value placement.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.2, HSF.BF.A.1.a

### G07 — Compare linear and exponential models

**Role:** CORE. **First planned unit:** 07.

**Performance:** Distinguish first-step similarity from different later growth.

**Bounds:** Compare linear and exponential models over stated intervals and contexts.

**Direct components:** L05, G04

**Conditional requirements:** approximate intersections are requested → C20

**Anchor:** Compare A(n)=10+3n and B(n)=10\*1.3^n at n=1 and n=2.

**Answer expectation:** `{"n1": {"A": 13, "B": 13}, "n2": {"A": 16, "B": "169/10"}}`

**Diagnostic contrast:** Use a second interval to distinguish additive growth from percent growth.

**Required variants:** Compare linear and exponential change across equal intervals.; Compare models across representations.; Estimate an intersection graphically with a declared tolerance.

**Reference contributions:** HSF.LE.A.1, HSF.IF.C.9, HSA.REI.D.11

### G08 — Solve simple exponential equations with common bases

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Rewrite both sides to a common base.

**Bounds:** Optional common-base exponential equations solvable by exponent reasoning; logarithmic methods not required here.

**Direct components:** G03, X05

**Anchor:** Solve 2^(x+1)=16.

**Answer expectation:** `3`

**Diagnostic contrast:** Contrast an unknown exponent with a coefficient multiplying a power.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.4

### G09 — Write arithmetic sequence rules

**Role:** CORE. **First planned unit:** 07.

**Performance:** Write equivalent recursive and explicit rules.

**Bounds:** Arithmetic sequences as functions on positive integers; indexing origin explicit.

**Direct components:** L01, C06

**Anchor:** An arithmetic sequence has a\_1=7 and common difference 4. Give both rules.

**Answer expectation:** `a_n=7+4(n-1); a_1=7 and a_n=a_(n-1)+4 for n>=2.`

**Diagnostic contrast:** Probe n=1 to distinguish n from n-1 indexing.

**Required variants:** Explicit nth-term rule with stated indexing.; Recursive rule with initial condition.; Translate between the two and connect to a linear function.

**Reference contributions:** HSF.IF.A.3, HSF.BF.A.2

### G10 — Write geometric sequence rules

**Role:** CORE. **First planned unit:** 07.

**Performance:** Translate between repeated multiplication and an explicit exponent.

**Bounds:** Geometric sequences with stated first term and ratio; indexing explicit.

**Direct components:** G02, C06

**Anchor:** A geometric sequence has a\_1=3 and ratio 2. Give recursive and explicit rules.

**Answer expectation:** `a_n=3*2^(n-1); a_1=3 and a_n=2*a_(n-1) for n>=2.`

**Diagnostic contrast:** Compare a_n-a_(n-1) with a_n/a_(n-1).

**Required variants:** Explicit nth-term rule with stated indexing.; Recursive rule with initial condition.; Translate between the two and connect to an exponential function.

**Reference contributions:** HSF.IF.A.3, HSF.BF.A.2

### G11 — Compare eventual exponential and polynomial growth

**Role:** CORE. **First planned unit:** 07.

**Performance:** Use tables or graphs to compare growth while stating the inference boundary.

**Bounds:** Eventual growth of specified exponential and polynomial models; finite samples are not a universal proof.

**Direct components:** G07, U01

**Conditional requirements:** a higher-degree polynomial model is compared → P02

**Anchor:** At n=10 compare 2^n with n^2. Does that single comparison prove every later comparison?

**Answer expectation:** `{"exponential": 1024, "quadratic": 100, "single_comparison_proves_all_future": false}`

**Diagnostic contrast:** Distinguish observed dominance from a justified eventual-growth statement.

**Required variants:** Compare a specified exponential model with a linear model.; Compare with a specified quadratic model.; State the evidence or derivation supporting an eventual-growth claim.

**Reference contributions:** HSF.LE.A.3

### G12 — Rewrite exponential expressions across time intervals

**Role:** CORE. **First planned unit:** 07.

**Performance:** Rewrite an exponential expression to reveal a rate for another time unit.

**Bounds:** Core: integer changes of time interval; rational-exponent subperiod variants require H06 and are optional in this profile.

**Direct components:** G02, X05

**Conditional requirements:** rational-exponent subperiod conversion is requested → H06

**Anchor:** Rewrite 2^(3t) as b^t and interpret b.

**Answer expectation:** `8^t; the multiplier for each unit of t is 8.`

**Diagnostic contrast:** Contrast a change of exponent scale with adding to the base.

**Required variants:** Integer changes of the input time scale.; Interpret the new factor.; Optional root-based subperiod conversion, explicitly extension-scoped.

**Reference contributions:** HSA.SSE.B.3.c, HSF.IF.C.8.b

### G13 — Justify equal-difference and equal-factor growth laws

**Role:** CORE. **First planned unit:** 07.

**Performance:** Justify the difference and ratio laws symbolically.

**Bounds:** Equal input intervals; fixed additive or multiplicative change derived from a rule, not assumed from a finite table.

**Direct components:** G02, L05, X05

**Anchor:** For f(x)=mx+b and g(x)=a\*c^x with a nonzero and c positive, compare f(x+1)-f(x) and g(x+1)/g(x).

**Answer expectation:** `The difference is m; the ratio is c. Both are independent of x under the stated conditions.`

**Diagnostic contrast:** Distinguish an observed pattern from its general algebraic justification.

**Required variants:** Derive a constant difference for a linear rule.; Derive a constant ratio for an exponential rule.; State the nonzero and positive-base assumptions.

**Reference contributions:** HSF.LE.A.1.a

## Strand R: Real numbers, radicals, and rational algebra

8 skills. Foundation and extension roles are explicit for each record.

### R01 — Distinguish rational and irrational numbers

**Role:** CORE. **First planned unit:** 09.

**Performance:** Classify examples using structure rather than decimal length alone.

**Bounds:** Rational means a quotient of integers with nonzero denominator; irrational is a real number not of that form.

**Direct components:** X10, N08

**Anchor:** Classify sqrt(9), sqrt(2), and 0.333... with repeating 3s.

**Answer expectation:** `sqrt(9) and repeating 0.333... are rational; sqrt(2) is irrational.`

**Diagnostic contrast:** Contrast a terminating approximation with the exact irrational value.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSN.RN.B.3

### R02 — Simplify square roots

**Role:** CORE. **First planned unit:** 09.

**Performance:** Express an exact root in simplest radical form.

**Bounds:** Simplify real square roots by extracting square factors; principal root and nonnegative radicand preserved.

**Direct components:** X10, X03

**Anchor:** Simplify sqrt(72).

**Answer expectation:** `6*sqrt(2)`

**Diagnostic contrast:** Contrast sqrt(a+b) with valid factor-based simplification.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.b, 8.EE.A.2

### R03 — Perform basic operations with radicals

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Combine equivalent radical units correctly.

**Bounds:** Optional operations with real radicals; nonnegative radicands and like-radical collection.

**Direct components:** R02, E03

**Anchor:** Simplify sqrt(8)+sqrt(18).

**Answer expectation:** `5*sqrt(2)`

**Diagnostic contrast:** Probe simplification before testing collection of like radicals.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSN.RN.A.2

### R04 — Understand restrictions caused by division by zero

**Role:** CORE. **First planned unit:** 02.

**Performance:** Identify an excluded input before simplifying.

**Bounds:** Expressions with zero denominators are undefined; restrictions belong to the original expression.

**Direct components:** N06, A05

**Anchor:** For 1/(x-3), which real input is excluded and why?

**Answer expectation:** `{"excluded_inputs": [3], "reason": "zero_denominator"}`

**Diagnostic contrast:** Contrast a zero numerator with a zero denominator.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.A.2

### R05 — Simplify rational algebraic expressions

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Cancel common nonzero factors without canceling terms.

**Bounds:** Optional rational expressions; simplify factors while preserving original exclusions.

**Direct components:** R04, F01

**Conditional requirements:** a quadratic must be factored to expose common factors → F04

**Anchor:** Simplify (x^2-9)/(x-3), stating restrictions.

**Answer expectation:** `{"expression": "x+3", "excluded_inputs": [3]}`

**Diagnostic contrast:** Contrast canceling a common factor with canceling one term of a sum.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.D.7

### R06 — Solve rational equations and check restrictions

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Solve and reject excluded or extraneous candidates.

**Bounds:** Optional rational equations; original denominator restrictions and checking required.

**Direct components:** R05, Q11

**Conditional requirements:** a factorable quadratic is solved by factoring → U08; a quadratic is solved with the formula → U12

**Anchor:** Solve x/(x-1)=2 over the real numbers.

**Answer expectation:** `{"solutions": [2], "excluded_inputs": [1]}`

**Diagnostic contrast:** Use a second item whose only algebraic candidate is excluded.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.A.2

### R07 — Solve elementary radical equations and check extraneous solutions

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Check every candidate in the original radical equation.

**Bounds:** Optional radical equations; squaring may introduce extraneous candidates.

**Direct components:** X10, Q09, Q02

**Conditional requirements:** squaring produces a factorable quadratic → U08

**Anchor:** Solve sqrt(x+1)=x-1 over the real numbers.

**Answer expectation:** `[3]`

**Diagnostic contrast:** Compare the squared equation's candidates 0 and 3 in the original equation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.A.2

### R08 — Justify rational and irrational number-operation claims

**Role:** CORE. **First planned unit:** 09.

**Performance:** Explain valid rational-irrational conclusions without overgeneralizing.

**Bounds:** Closure arguments and counterexamples; a nonzero rational multiplier is required for the product claim.

**Direct components:** R01, N09, N10

**Anchor:** Classify 3+sqrt(2) and sqrt(2)+(-sqrt(2)), with reasons.

**Answer expectation:** `The first is irrational; the second is 0 and rational. Two irrational summands need not have an irrational sum.`

**Diagnostic contrast:** Vary the number types and test whether the proposed rule is universal.

**Required variants:** Rational plus rational and rational times rational.; Rational plus irrational and nonzero rational times irrational.; Counterexamples to invalid claims about two irrational numbers.

**Reference contributions:** HSN.RN.B.3

## Strand D: Data and statistical modeling

11 skills. Foundation and extension roles are explicit for each record.

### D01 — Distinguish data types and statistical questions

**Role:** CORE. **First planned unit:** 06.

**Performance:** Identify data type and a question requiring a distribution.

**Bounds:** Categorical versus quantitative data; statistical questions anticipate variation.

**Direct components:** No earlier node required within this map's declared floor.

**Anchor:** Is favorite sport categorical or quantitative? Does asking a class for their favorite sports anticipate variation?

**Answer expectation:** `Categorical; yes, different students may name different sports.`

**Diagnostic contrast:** Contrast numerical category labels with measured quantities.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSS.ID.B.5, HSS.ID.A.1

### D02 — Interpret and construct statistical displays

**Role:** CORE. **First planned unit:** 06.

**Performance:** Construct and interpret an appropriate one-variable display.

**Bounds:** Dot plots, histograms and box plots; scale, labels and bin conventions explicit.

**Direct components:** D01, N20

**Anchor:** For data 1,1,2,4, describe a dot plot with a unit scale.

**Answer expectation:** `Two dots above 1, one above 2, one above 4; labeled numerical axis.`

**Diagnostic contrast:** Separate frequency height from the numerical variable's values.

**Required variants:** Dot plot.; Histogram with declared bins.; Box plot with the declared quartile convention.

**Reference contributions:** HSS.ID.A.1

### D03 — Calculate and interpret mean and median

**Role:** CORE. **First planned unit:** 06.

**Performance:** Compute both centers and explain their meanings.

**Bounds:** Mean and median with correct ordering and sample size.

**Direct components:** N17, N18

**Anchor:** Find the mean and median of 2,2,3,9.

**Answer expectation:** `{"mean": 4, "median": "5/2"}`

**Diagnostic contrast:** Contrast ordering for the median with totaling for the mean.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSS.ID.A.2

### D04 — Interpret quartiles and interquartile range

**Role:** CORE. **First planned unit:** 06.

**Performance:** Compute quartiles using a stated convention.

**Bounds:** Quartiles and IQR; the declared median-of-halves convention excludes the median for odd n.

**Direct components:** D03, N13

**Anchor:** For 1,2,3,4,5,6,7, exclude the median from both halves. Find Q1,Q3 and IQR.

**Answer expectation:** `{"Q1": 2, "Q3": 6, "IQR": 4}`

**Diagnostic contrast:** Distinguish quartile convention differences from arithmetic errors.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSS.ID.A.2

### D05 — Understand standard deviation

**Role:** CORE. **First planned unit:** 06.

**Performance:** Compare variability around the mean and interpret an SD value.

**Bounds:** Population standard deviation interpreted with units; technology may compute it; normal-curve inference excluded.

**Direct components:** D03, N15

**Anchor:** Which set has larger population SD: A={4,4,4} or B={2,4,6}? Explain.

**Answer expectation:** `B; both means are 4, but A has no variation and B has nonzero deviations.`

**Diagnostic contrast:** Contrast spread with a different mean or a different number of observations.

**Required variants:** Compare spread about a common mean.; Interpret population SD with units.; Use supplied technology output or compute with declared technology.

**Reference contributions:** HSS.ID.A.2

### D06 — Describe distributions, skewness, and outliers

**Role:** CORE. **First planned unit:** 06.

**Performance:** Compare summaries before and after an extreme observation.

**Bounds:** Distribution shape, center, spread and contextual treatment of outliers.

**Direct components:** D02, D03, D04, D05

**Anchor:** For data 2,3,4, find mean and median before and after adding 30.

**Answer expectation:** `{"before": {"mean": 3, "median": 3}, "after": {"mean": "39/4", "median": "7/2"}}`

**Diagnostic contrast:** Check the observed effect instead of assuming that every unusual value should be deleted.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSS.ID.A.3

### D07 — Interpret scatter plots and associations

**Role:** CORE. **First planned unit:** 06.

**Performance:** Describe an observed association without a causal claim.

**Bounds:** Scatterplots, direction, form and unusual points; association alone is not causation.

**Direct components:** C02, D01

**Anchor:** Describe the association for points (1,2),(2,4),(3,6).

**Answer expectation:** `Perfect positive linear association in these three observations; no causal conclusion follows.`

**Diagnostic contrast:** Separate direction and form from a causal explanation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSS.ID.B.6

### D08 — Construct and interpret linear trend models

**Role:** CORE. **First planned unit:** 06.

**Performance:** Construct a fitted line and interpret its coefficients.

**Bounds:** Approximate linear fit with technology when appropriate; contextual slope and interpolation limits.

**Direct components:** D07, L13

**Anchor:** Fit a line to (0,1),(1,3),(2,5).

**Answer expectation:** `y_hat=2*x+1`

**Diagnostic contrast:** Compare fitting a trend with joining every observed point.

**Required variants:** Fit or interpret an approximate linear model.; Explain slope and intercept in context.; Check whether a linear fit is suitable and avoid unsupported extrapolation.

**Reference contributions:** HSS.ID.B.6.a, HSS.ID.B.6.c, HSS.ID.C.7

### D09 — Interpret residuals and correlation coefficients

**Role:** CORE. **First planned unit:** 06.

**Performance:** Compute a residual and interpret correlation without a causal or percent-correct claim.

**Bounds:** Residual=observed-predicted; correlation describes linear association and is not prediction accuracy.

**Direct components:** D08, D05

**Anchor:** A line predicts y=2x+1. At x=3 the observed y is 8. Find the residual; explain what a positive r describes.

**Answer expectation:** `{"residual": 1, "positive_r_means": "positive_linear_association", "not_percent_correct": true, "causation_not_established": true}`

**Diagnostic contrast:** Contrast residual sign with a point being above or below the fitted line.

**Required variants:** Residual sign and magnitude.; Correlation coefficient from technology.; Explain that r is not a percent-correct score and does not establish causality.

**Reference contributions:** HSS.ID.B.6.b, HSS.ID.C.8

### D10 — Distinguish correlation and causation

**Role:** CORE. **First planned unit:** 06.

**Performance:** Reject an unsupported causal conclusion and name a plausible alternative.

**Bounds:** Observational association, possible confounding and causal limitations.

**Direct components:** D07

**Anchor:** Ice-cream sales and swimming incidents rise together. Does that prove ice cream causes incidents?

**Answer expectation:** `No; a factor such as hotter weather may affect both.`

**Diagnostic contrast:** Ask what design or information would distinguish competing causal explanations.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSS.ID.C.9

### D11 — Interpret two-way tables and conditional proportions

**Role:** CORE. **First planned unit:** 06.

**Performance:** Compute and distinguish three reference groups.

**Bounds:** Joint, marginal and conditional relative frequencies; denominator follows the reference group.

**Direct components:** D01, N14

**Anchor:** Of 20 swimmers, 12 cycle. Of 30 non-swimmers, 8 cycle. Give the cycling fraction among swimmers, swimmer-and-cyclist fraction overall, and swimmer fraction overall.

**Answer expectation:** `{"conditional": "3/5", "joint": "6/25", "marginal": "2/5"}`

**Diagnostic contrast:** Hold the numerator fixed and change the stated reference group.

**Required variants:** Joint frequency.; Marginal frequency.; Conditional frequency with the correct reference group.

**Reference contributions:** HSS.ID.B.5

## Strand M: Integrated modeling and reasoning

9 skills. Foundation and extension roles are explicit for each record.

### M01 — Identify quantities, unknowns, and units

**Role:** CORE. **First planned unit:** 01.

**Performance:** Choose quantities that make a contextual relationship meaningful.

**Bounds:** Identify quantities, define symbols and maintain units across representations.

**Direct components:** A10, N20

**Anchor:** A taxi charges 5 dollars plus 2 dollars per km. Define variables and write the cost model.

**Answer expectation:** `d is distance in km, C is dollars; C=5+2d.`

**Diagnostic contrast:** Contrast dollars with dollars per kilometer.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSN.Q.A.1, HSN.Q.A.2

### M02 — Translate situations into algebraic models

**Role:** CORE. **First planned unit:** 02.

**Performance:** Construct a model with defined quantities rather than guessing an operation.

**Bounds:** Translate situations into equations or functions; representation choice justified.

**Direct components:** A08, A10, Q01

**Anchor:** A tank begins with 12 liters and drains 2 liters per minute. Model its volume before emptying.

**Answer expectation:** `V=12-2t, with t in minutes and V in liters; 0<=t<=6.`

**Diagnostic contrast:** Distinguish initial amount, direction of change and physical domain.

**Required variants:** Linear contextual model.; Quadratic contextual model after the relevant skills are available.; Simple exponential contextual model.

**Reference contributions:** HSA.CED.A.1, HSA.CED.A.2, HSF.BF.A.1.a

### M03 — Select appropriate linear, quadratic, or exponential models

**Role:** CORE. **First planned unit:** 09.

**Performance:** Use differences, ratios or contextual structure to justify a family choice.

**Bounds:** Choose among specified linear, quadratic and exponential families; state assumptions and finite-data limits.

**Direct components:** M02, L14, G01, U01

**Anchor:** Under a choice of linear, quadratic or exponential models, which fits outputs 2,6,18,54 at equal input steps?

**Answer expectation:** `An exponential model fits, with common factor 3; the finite data alone do not establish a unique unrestricted rule.`

**Diagnostic contrast:** Compare a model-family choice with a claim about every possible function.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.1, HSF.LE.A.2, HSF.IF.B.4

### M04 — Identify realistic domain restrictions

**Role:** CORE. **First planned unit:** 03.

**Performance:** Restrict a model to feasible inputs.

**Bounds:** Realistic continuous, discrete and nonnegative domains; restrictions from the situation retained.

**Direct components:** C08, A10, I01

**Anchor:** A model counts the cost of n tickets. Can n=-1 or n=2.5 be used as a ticket count?

**Answer expectation:** `Neither; n must be a nonnegative integer under this situation.`

**Diagnostic contrast:** Contrast algebraically evaluable inputs with physically meaningful inputs.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.B.5, HSA.CED.A.3

### M05 — Verify and evaluate solutions

**Role:** CORE. **First planned unit:** 02.

**Performance:** Reject a mathematical candidate that violates the original situation.

**Bounds:** Check mathematical validity and contextual feasibility separately.

**Direct components:** Q02, A10

**Anchor:** A model for a length yields candidates -3 cm and 5 cm. Which is feasible as the length?

**Answer expectation:** `5 cm; a negative length is infeasible in this model.`

**Diagnostic contrast:** Distinguish a negative quantity that is valid in another context from a negative physical length.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.CED.A.3, HSA.REI.A.1

### M06 — Connect multiple representations

**Role:** CORE. **First planned unit:** 03.

**Performance:** Translate and reconcile a rule, table, graph and context.

**Bounds:** Consistency of units, scales and the same relationship across representations.

**Direct components:** C10, M01

**Anchor:** For C=5+2d, give table values at d=0,1,2 and describe the graph.

**Answer expectation:** `{"values": [5, 7, 9], "slope": 2, "y_intercept": 5, "units": "dollars; dollars per kilometer for slope"}`

**Diagnostic contrast:** Probe whether a changed scale or unit is mistaken for a changed relationship.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.9, HSA.CED.A.2

### M07 — Explain valid algebraic transformations

**Role:** CORE. **First planned unit:** 02.

**Performance:** Explain why a solution method preserves the intended solution set.

**Bounds:** Justification of transformations; a sequence of written steps alone is not an explanation.

**Direct components:** Q03, E11

**Anchor:** Explain why adding x-y=1 to x+y=7 gives a valid consequence 2x=8.

**Answer expectation:** `Equal quantities may be added on each side; corresponding sides sum to the new equality.`

**Diagnostic contrast:** Contrast a valid consequence with claiming that one new equation alone replaces the entire system.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.A.1, HSA.REI.C.5

### M08 — Evaluate assumptions, limitations, and conclusions

**Role:** CORE. **First planned unit:** 06.

**Performance:** State a limitation that matters to a model's use.

**Bounds:** Assumptions, model limits, interpolation and extrapolation; no unsupported causal claim.

**Direct components:** M02, M05

**Anchor:** A linear plant-growth model is fitted from days 1-10. What is a limitation of predicting day 500?

**Answer expectation:** `The observed growth rate may not persist; day 500 is far outside the fitted range.`

**Diagnostic contrast:** Distinguish mathematical evaluation from evidence that the model remains appropriate.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSN.Q.A.2, HSF.IF.B.4

### M09 — Choose and communicate appropriate measurement precision

**Role:** CORE. **First planned unit:** 01.

**Performance:** Report a modeled quantity with defensible precision.

**Bounds:** Accuracy appropriate to stated measurement limits; no universal significant-figure rule invented.

**Direct components:** M01, N20

**Anchor:** A distance estimate is 12.347 km, but the source supports only tenths of a kilometer. Report it appropriately.

**Answer expectation:** `{"value": "12.3", "unit": "km"}`

**Diagnostic contrast:** Contrast exact algebraic value with a justified reported measurement.

**Required variants:** Stated decimal-place requirement.; Precision consistent with measurement limitations.; Distinguish exact algebra from rounded reported quantities.

**Reference contributions:** HSN.Q.A.3

## Strand H: Algebra 2 and course-dependent extensions

10 skills. Foundation and extension roles are explicit for each record.

### H01 — Solve absolute-value equations and inequalities

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Interpret distance and retain every valid branch.

**Bounds:** Optional absolute-value equations and inequalities; multiple branches and empty sets included.

**Direct components:** C13, I06

**Conditional requirements:** absolute-value inequality is assessed → I07

**Anchor:** Solve abs(2x-3)=5.

**Answer expectation:** `[-1, 4]`

**Diagnostic contrast:** Contrast a two-branch equation with taking only the positive internal expression.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.3

### H02 — Solve equations involving piecewise functions

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Solve each relevant branch and filter candidates.

**Bounds:** Optional piecewise equations; candidates must satisfy their branch conditions.

**Direct components:** C11, Q09

**Anchor:** Let f(x)=x+1 for x<2 and 2x for x>=2. Solve f(x)=4.

**Answer expectation:** `[2]`

**Diagnostic contrast:** Check the branch condition after solving the branch equation.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.b, HSA.REI.B.3

### H03 — Work with function composition and inverses

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Compose in the stated order and reverse a one-to-one rule.

**Bounds:** Optional composition and broader inverse functions; domain restrictions required.

**Direct components:** C16, C19, Q13

**Conditional requirements:** the inverse requires domain restriction → C08

**Anchor:** For f(x)=2x+1 and g(x)=x^2, find f(g(x)) and the inverse of f.

**Answer expectation:** `{"composition": "2*x^2+1", "inverse": "(x-1)/2"}`

**Diagnostic contrast:** Contrast f(g(x)), g(f(x)) and 1/f(x).

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.BF.A.1.c, HSF.BF.B.4

### H04 — Transform functions

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Explain combined input and output transformations.

**Bounds:** Optional broader transformations, input scaling and symmetry across loaded families; no trigonometry scope implied.

**Direct components:** C15, C17

**Conditional requirements:** transforming rational functions → H09

**Anchor:** For f(x)=x^2, describe g(x)=-2f(3(x-1))+4.

**Answer expectation:** `{"rule": "-18*(x-1)^2+4", "transformations": ["horizontal compression by 1/3", "right 1", "reflect in x-axis", "vertical stretch by 2", "up 4"]}`

**Diagnostic contrast:** Isolate one transformation to distinguish horizontal scaling from vertical scaling.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.BF.B.3

### H05 — Perform polynomial division

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Find quotient and remainder and state any original restriction.

**Bounds:** Optional polynomial division and remainder; rational-function domain kept separate from polynomial identity.

**Direct components:** P07, Q06

**Anchor:** Divide x^3-1 by x-1. If viewed as a quotient function, state its restriction.

**Answer expectation:** `{"quotient": "x^2+x+1", "remainder": 0, "excluded_inputs": [1]}`

**Diagnostic contrast:** Contrast a polynomial identity with the domain of the original rational function.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.APR.D.6, HSA.APR.B.2

### H06 — Understand rational exponents

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Connect fractional powers with roots and powers.

**Bounds:** Optional rational exponents; positive bases by default and explicit real-root conventions.

**Direct components:** X05, X10, X11

**Anchor:** Evaluate 27^(2/3) over the positive real domain.

**Answer expectation:** `9`

**Diagnostic contrast:** Contrast the denominator's root operation with multiplying by a fraction.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSN.RN.A.1, HSN.RN.A.2

### H07 — Solve quadratics with complex solutions

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Construct and verify nonreal quadratic roots.

**Bounds:** Optional complex roots with i^2=-1; distinguish no real solution from complex solutions.

**Direct components:** U12, R02

**Anchor:** Solve x^2+4=0 over the complex numbers.

**Answer expectation:** `["-2i", "2i"]`

**Diagnostic contrast:** Check the stated number system before deciding that there is no solution.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.B.4.b

### H08 — Interpret logarithms

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Translate between exponential and logarithmic form.

**Bounds:** Optional logarithms as inverse exponent questions; positive argument and valid base.

**Direct components:** G03, X08

**Anchor:** Evaluate log base 2 of 8.

**Answer expectation:** `3`

**Diagnostic contrast:** Contrast the logarithm with division by the base.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.LE.A.4, HSF.BF.B.5

### H09 — Analyze rational functions and asymptotes

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Analyze basic domain and asymptotes.

**Bounds:** Optional rational functions; holes distinguished from asymptotes and excluded inputs.

**Direct components:** R05, C08, L11

**Anchor:** For f(x)=(x+1)/(x-2), give the excluded input and vertical and horizontal asymptotes.

**Answer expectation:** `{"excluded": 2, "vertical": "x=2", "horizontal": "y=1"}`

**Diagnostic contrast:** Use a removable-factor example to distinguish a hole from a vertical asymptote.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSF.IF.C.7.d

### H10 — Solve elementary nonlinear systems

**Role:** EXTENSION. **First planned unit:** OPTIONAL.

**Performance:** Substitute or compare relations and verify every intersection.

**Bounds:** Optional broader elementary nonlinear systems, including two quadratics; no universal solving claim.

**Direct components:** S09, U12

**Conditional requirements:** a rational nonlinear system is introduced → H09, R06

**Anchor:** Solve y=x^2 and y=2x^2-1 over the real numbers.

**Answer expectation:** `[[-1, 1], [1, 1]]`

**Diagnostic contrast:** Check that both coordinates satisfy both original relations.

**Required variants:** Representative constructed response.; A diagnostic contrast addressing the stated probe focus.; A fresh transfer or interpretation task within this node's bounds.

**Reference contributions:** HSA.REI.C.7

## Verification and limits

Run python validate_curriculum.py from this directory. The report checks identities, references, graph cycles, role boundaries, official code presence and explicit anchor mathematical obligations. It does not validate the empirical learning sequence or independently establish every item's semantics.

The separate report lists which anchors have automated mathematical checks. Remaining reasoning, interpretation and graph expectations are author-reviewed and require appropriate independent review before production claims.

## Sources

- **CCSS-4-NBT** — [Grade 4 base-ten number](https://www.thecorestandards.org/Math/Content/4/NBT/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-4-NF** — [Grade 4 fractions](https://www.thecorestandards.org/Math/Content/4/NF/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-6-NS** — [Grade 6 number system](https://www.thecorestandards.org/Math/Content/6/NS/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-6-EE** — [Grade 6 expressions and equations](https://www.thecorestandards.org/Math/Content/6/EE/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-6-RP** — [Grade 6 ratios and rates](https://www.thecorestandards.org/Math/Content/6/RP/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-7-NS** — [Grade 7 rational-number operations](https://www.thecorestandards.org/Math/Content/7/NS/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-7-EE** — [Grade 7 expressions and equations](https://www.thecorestandards.org/Math/Content/7/EE/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-7-RP** — [Grade 7 proportional relationships](https://www.thecorestandards.org/Math/Content/7/RP/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-8-EE** — [Grade 8 expressions and equations](https://www.thecorestandards.org/Math/Content/8/EE/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-8-F** — [Grade 8 functions](https://www.thecorestandards.org/Math/Content/8/F/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSA-SSE** — [High-school expression structure](https://www.thecorestandards.org/Math/Content/HSA/SSE/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSA-APR** — [High-school polynomial and rational arithmetic](https://www.thecorestandards.org/Math/Content/HSA/APR/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSA-CED** — [High-school equation construction](https://www.thecorestandards.org/Math/Content/HSA/CED/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSA-REI** — [High-school equation and inequality reasoning](https://www.thecorestandards.org/Math/Content/HSA/REI/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSN-RN** — [High-school real-number system](https://www.thecorestandards.org/Math/Content/HSN/RN/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSN-Q** — [High-school quantities and units](https://www.thecorestandards.org/Math/Content/HSN/Q/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSF-IF** — [High-school function interpretation](https://www.thecorestandards.org/Math/Content/HSF/IF/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSF-BF** — [High-school function construction](https://www.thecorestandards.org/Math/Content/HSF/BF/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSF-LE** — [High-school linear and exponential modeling](https://www.thecorestandards.org/Math/Content/HSF/LE/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **CCSS-HSS-ID** — [High-school descriptive statistics](https://www.thecorestandards.org/Math/Content/HSS/ID/) · accessed 2026-10-20. Content reference; course allocation and prerequisite edges are author decisions.
- **IM-A1** — [Illustrative Mathematics Algebra 1 course narrative](https://im.kendallhunt.com/HS/teachers/1/narrative.html) · accessed 2026-10-20. Scope comparator covering data, equations, functions, exponential and quadratic work. This package uses an original sequence and original anchor items.
- **IES-ALGEBRA** — [Teaching Strategies for Improving Algebra Knowledge in Middle and High School Students](https://ies.ed.gov/ncee/wwc/PracticeGuide/20) · accessed 2026-10-20. Solved-problem analysis, structure and deliberate strategy choice. Guide evidence ratings: first two recommendations minimal; strategy choice moderate. These do not validate this product or its prerequisite graph.
