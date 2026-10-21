# 04 — Mathematics, item acceptance and honest coverage

## Two independent gates

**Item gate:** establish the task, mathematical objects, answer/rubric, domain,
requested form, skill fit, representation and supported checking route before
presenting a scored task. **Response gate:** assess the exact submitted work
against that accepted task. Correctness of an answer key alone does not prove
that a word problem asks for that answer or that the skill was assessed.

Items must have a semantic fingerprint (template, normalized parameters,
primary target, role and variant); exclude an item whose solution/instance has
already been revealed from independent evidence. Changing punctuation or ID is
not freshness. Scope policy and variant coverage are host data.

## Initial production scoring route

Use a server-owned constrained ItemFactory for the first vertical journey.
The LLM may propose a supported template and bounded parameters or a new
candidate. Host checks parameters, renders the mathematical question, derives
the key independently and verifies that the item fits the canonical node.
LLM-composed free mathematical items remain drafts until an actual declared
review route accepts them. Never simply trust its expected_answer field.

The supplied examples/item-catalog.json defines initial deterministic template
families and examples; they are implementation requirements, not a working PHP
factory or calibrated bank. Build/test the factory and checker. Do not use one
published anchor repeatedly as three independent successes.

## Input and checking

| Curriculum response type | Required runtime behavior |
|---|---|
| scalar / fraction | Exact rational arithmetic where feasible; declared tolerance only for approximation |
| expression | Parse a safe mathematical AST; prove supported polynomial/rational equivalence on declared domain; check requested form separately |
| equation | Distinguish an equation from a value; compare solution sets/conditions, not string similarity |
| solution_set | Order-independent complete real sets; reject missing roots, extraneous solutions and excluded denominator values |
| interval | Endpoint inclusion, unions/intersections, direction and infinity semantics |
| ordered_list | Ordered exact values, no accidental order-insensitive comparison |
| ordered_pair / point_set | Coordinate semantics; set/order policy explicit |
| quantity | Numeric value, unit conversion, dimension and requested precision |
| table | Structured row/column labels, values and independent/dependent variables |
| graph_spec | Actual plotted object: axes, scale, points/curves, domain, endpoints; assess graph input, not a typed scalar |
| classification | Correct category plus required justification when target demands it |
| reasoning / model / structured_response | Explicit rubric and provenance; no deterministic-understanding claim from numeric matching |

Bounds: ASCII/unicode normalization may standardize a minus sign or multiplication
symbol, but may not remove minus signs, change exponents or convert an equation
to a scalar. Reject zero denominators, excessive nesting/digits/exponents,
ambiguous mixed-number syntax and unsupported variable names gracefully.
Never use PHP eval or Python eval. No fuzzy edit distance for mathematical truth.
If syntax is unclear, ask clarification; do not mark wrong.

Exact polynomial equality must be algebraic within a stated bounded class,
not random substitution. Rational equivalence must preserve original excluded
values; e.g. (x^2-1)/(x-1) and x+1 are not domain-identical without x != 1.
Solving x^2=9 requires {-3,3}; sqrt(9) denotes principal root 3. Check requested
factorization separately from equivalent expanded form. See checker fixtures.

## Reasoning and graph boundary

Full curriculum includes explanation, interpretation and graphical performance.
Numeric correctness does not satisfy those components. Use a coverage matrix
per skill: AVAILABLE_SCORED, PARTIAL_COMPONENTS, REVIEW_REQUIRED or UNAVAILABLE,
with implemented response types and remaining criteria. Runtime registry starts
unimplemented; it is promoted only with actual tests, never copied from the
curriculum's required capabilities list.

An independent provider review is a separate controlled call with a bounded
rubric and no accepted authority to self-promote state. Label its result
MODEL_REVIEWED, including disagreements/uncertainty. It is not deterministic
proof or independent human validation. It may guide feedback. In v1, learning
criterion promotion for reasoning/modeling/graph skills requires either an
implemented task-specific checker covering the required performance or an
authenticated human review feature that is outside account-free v1. An LLM
rubric-only activity can be REVIEW_REQUIRED without being called failed.
Do not quietly redefine these skills as scalar drills to increase coverage.

For graph construction implement actual structured graph input and task-specific
validation (e.g. line points/endpoints), or honestly route that component.
An image of the correct graph is not evidence the student can construct it.

## First vertical journey and expansion

First implement N03/N04/N05/N06 signed arithmetic, E06 positive distribution,
Q04/Q05/Q06/Q07/Q08 equation-solving components and their scoped template
families. The first journey centers on Q07 → E06 → Q07. A node's conceptual,
representation and transfer requirements remain visible; passing the numeric
component alone is PARTIAL_COMPONENTS, not a whole-node immediate criterion.
Build additional required variants/rubrics before whole-node promotion.

The entire 205-node reference remains canonical and browsable. Stage expansion
through linear relationships/inequalities/systems, functions/exponents, then
polynomials/factoring/quadratics/data/modeling, and optional extensions.
Unsupported prerequisites produce a resource/capability boundary, not an
invented repair or inferred learner gap. Continue implementing available
components while retaining an exact remaining-capability list.

## Correction and conflict

A checker result binds item_id, item_version, event_id and response hash. The
hash is private; keep original response with declared retention policy. On a
demonstrated contradiction suspend the affected result, append correction,
recompute streaks/claims, and surface a concise correction. Never label an
invalid item as a student error or reuse its verification on a revised task.
