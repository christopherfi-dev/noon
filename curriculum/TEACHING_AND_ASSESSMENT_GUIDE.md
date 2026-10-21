# Teaching and Assessment Guide v1.1

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
