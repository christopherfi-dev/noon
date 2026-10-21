# Scripted sessions — metrics

Mode: LIVE — configuration thinking-high
Started (UTC): 2026-10-21T22:11:18Z
Settings: thinking enabled, reasoning_effort high, max_tokens 16384, provider_timeout 45 s, lease 90 s
Model list: GET https://api.deepseek.com/v1/models → HTTP 200; listed: deepseek-flash, deepseek-v4-pro
Configured model `deepseek-flash` listed: yes
Provider / model: openai_compatible / deepseek-flash

| Session | Path | Final mode | Report | Turns | Binding 1st try | Provider calls | Repairs by operation (accepted generations) | HOLDs | Guard rejections | Other rejections | Empty responses | finish=length | Call time median / slowest (s) | Turn latency median / slowest (s) | Tokens in | Tokens out (reasoning) | Cache hit / miss | Cost |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01-B1 | B1 | SESSION_CLOSED | COMPLETED | 18 | yes | 20 | PRESENT_ITEM 2 (9); GUIDED_STEP 1 (6) | 1 | 0 | 3 | 3 | 0 | 7.5 / 15.1 | 8.2 / 24.6 | 238098 | 33258 (23469) | 200832 / 37266 | not reported by provider |
| 02-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 17 | PRESENT_ITEM 1 (9); GUIDED_STEP 0 (6) | 0 | 0 | 1 | 1 | 0 | 8.9 / 21.6 | 9.9 / 21.6 | 212602 | 33119 (23197) | 176512 / 36090 | not reported by provider |
| 03-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 16 | PRESENT_ITEM 0 (9); GUIDED_STEP 0 (6) | 0 | 0 | 0 | 0 | 0 | 8.6 / 13.4 | 8.6 / 13.4 | 199531 | 27698 (18556) | 163712 / 35819 | not reported by provider |
| 04-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | no | 18 | PRESENT_ITEM 1 (9); GUIDED_STEP 0 (6) | 0 | 0 | 1 | 0 | 0 | 7.8 / 24.3 | 8.3 / 24.3 | 221813 | 34743 (24304) | 184704 / 37109 | not reported by provider |
| 05-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 19 | PRESENT_ITEM 2 (9); GUIDED_STEP 1 (6) | 0 | 0 | 3 | 3 | 0 | 7.6 / 13.8 | 8.0 / 26.7 | 238712 | 30842 (20891) | 201984 / 36728 | not reported by provider |
| 06-B3 | B3 | SESSION_CLOSED | COMPLETED | 19 | yes | 26 | PRESENT_ITEM 6 (13); GUIDED_STEP 2 (4) | 0 | 2 | 6 | 5 | 0 | 7.8 / 22.8 | 9.3 / 30.4 | 351931 | 47085 (34574) | 302592 / 49339 | not reported by provider |
| 07-B3 | B3 | SESSION_CLOSED | COMPLETED | 19 | yes | 19 | PRESENT_ITEM 1 (13); GUIDED_STEP 0 (4) | 0 | 0 | 1 | 1 | 0 | 7.6 / 21.0 | 7.6 / 21.1 | 251590 | 34490 (24352) | 210432 / 41158 | not reported by provider |
| 08-B3 | B3 | SESSION_CLOSED | COMPLETED | 19 | yes | 21 | PRESENT_ITEM 3 (13); GUIDED_STEP 0 (4) | 0 | 0 | 3 | 3 | 0 | 8.4 / 13.5 | 9.1 / 16.1 | 279762 | 35358 (24645) | 237952 / 41810 | not reported by provider |
| **Total** | 8 sessions | | | 143 | 7/8 | 156 | PRESENT_ITEM 16 (84); GUIDED_STEP 4 (42) | 1 | 2 | 18 | 16 | 0 | 7.9 / 24.3 | 8.5 / 30.4 | 1994039 | 276593 (193988) | 1678720 / 315319 | not reported by provider |

Repairs by operation: bounded repair requests per operation, with accepted generations in brackets. Call time: one provider call. Turn latency: the wall time of one API request (all provider calls in it included). Empty responses: HTTP 200 with empty content.

Model name returned in each response (count of calls): deepseek-flash (155), (none) (1). Each call's value is in the transcripts.

## Every host rejection of model output

- 01-B1 turn 6, GUIDED_STEP, attempt 0: empty content

  ```
  (empty content)
  ```
- 01-B1 turn 15, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 01-B1 turn 16, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 02-B1 turn 15, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 04-B1 turn 13, PRESENT_ITEM, attempt 0: student_message must speak to the learner directly and must not mention the host or keys

  ```
  {"architecture_version":"1.2","session_id":"ses_84919c0262feaf5cbfd2269c","state_revision":12,"reply_to_event_id":"evt_9ba8691e77ec1453c919e16a","message_type":"QUESTION","student_message":"You applied the outside factor to all three terms inside the group, unassisted, and the expanded form held. Back to where we started: take the grouped equation the host is showing you and solve it, working either through the grouped structure or through expansion, whichever you prefer.","item":{"item_id":"itm_57f8c7dbb4221e1d63fb96a6","item_version":1,"curriculum_id":"ACE-ALGEBRA1","curriculum_version":"1.1.0","skill_id":"Q07","role":"TARGET","component_id":"numeric_solution","variant_id":"constructed","prompt":"Solve 4(x + 5) = 48 for x.","response_type":"scalar","template_id":"GROUPED_LINEAR","parameters":{"a":4,"b":5,"c":48},"mathematical_objects":{"equation":"4(x + 5) = 48","variable":"x","grouped_sum":{"coefficient_of_x":1,"constant_inside":5},"outside_factor":4,"right_side":48},"domain":{"number_system":"RATIONAL","restrictions":[]},"answer_spec":{"kind":"numeric","expected":"7","requested_form":"solved_value","units":null,"tolerance":null,"rubric":[]},"checking":{"requested_route":"DETERMINISTIC_TEMPLATE","required_capabilities":[],"scope":"Set by the host."},"representation_requirements":[],"support_already_supplied":[],"independent_eligible_proposal":true},"proposed_transition":null,"evidence_proposals":[{"kind":"OBSERVATION","statement":"Retained event evt_9ba8691e77ec1453c919e16a: independent expansion of 6(x + y + 7) produced 6*x + 6*y + 42, judged CORRECT by deterministic polynomial identity checking with the expanded form confirmed separately; the outside factor reached every term in a three-term group, with no support supplied.","evidence_event_ids":["evt_9ba8691e77ec1453c919e16a"],"hypothesis_status":"SUPPORTED_FOR_OBSERVED_PATTERN"},{"kind":"HYPOTHESIS","statement":"Whether the expansion performance established on E06 carries over to solving a grouped linear equation without assistance is not yet established; the fresh Q07 target item now presented is the first evidence for that question.","evidence_event_ids":["evt_9ba8691e77ec1453c919e16a","evt_2d4fb22cf4bcf422b4736ac5"],"hypothesis_status":"PROVISIONAL"}],"resource_requests":[],"teacher_report":null,"issue":null}
  ```
- 05-B1 turn 7, GUIDED_STEP, attempt 0: empty content

  ```
  (empty content)
  ```
- 05-B1 turn 13, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 05-B1 turn 13, PRESENT_ITEM, attempt 1: empty content

  ```
  (empty content)
  ```
- 06-B3 turn 3, PRESENT_ITEM, attempt 0: error patterns must give distinct values

  ```
  {"architecture_version":"1.2","session_id":"ses_75047175f6c8b75d8be300f6","state_revision":2,"reply_to_event_id":"evt_b9064293cb6c2420a80dad4c","message_type":"QUESTION","student_message":"Here is a two-step equation to solve. Enter the value of x as an exact number, with no other text.","item":{"item_id":"itm_a396e3e4535bbad952c812fc","item_version":1,"curriculum_id":"ACE-ALGEBRA1","curriculum_version":"1.1.0","skill_id":"Q06","role":"PREREQUISITE","component_id":"numeric_solution","variant_id":"constructed","prompt":"Solve 6x + 3 = 27 for x.","response_type":"scalar","template_id":"TWO_STEP","parameters":{"a":6,"b":3,"c":27},"mathematical_objects":{"equation":"6x + 3 = 27","variable":"x","left_expression":"6x + 3","right_value":"27"},"domain":{"number_system":"RATIONAL","restrictions":[]},"answer_spec":{"kind":"exact_rational","expected":"4","requested_form":"value_of_x","units":null,"tolerance":null,"rubric":[]},"checking":{"requested_route":"DETERMINISTIC_TEMPLATE","required_capabilities":[],"scope":"Set by the host."},"representation_requirements":[],"support_already_supplied":[],"independent_eligible_proposal":true},"proposed_transition":{"to_mode":"PREREQUISITE_CHECK","target_skill_id":"Q06","reason":"The Q07 target item broke, and Q06 (two-step equations) is a declared all_of prerequisite of Q07; this item tests the additive-then-multiplicative structure in isolation from grouping, which is the structure that changed in the observed responses.","evidence_event_ids":["evt_b9064293cb6c2420a80dad4c"]},"evidence_proposals":[{"kind":"OBSERVATION","statement":"On 3(x + 5) = 27 the learner entered 14, which equals 27/3 + 5; on 4(x + 7) = 40 the learner entered 17, which equals 40/4 + 7. Both responses divide the right side by the coefficient and then add the constant instead of subtracting it.","evidence_event_ids":["evt_4312265333cdac397a9bc07a","evt_b9064293cb6c2420a80dad4c"],"hypothesis_status":"SUPPORTED_FOR_OBSERVED_PATTERN"},{"kind":"HYPOTHESIS","statement":"The learner may be undoing the grouped constant with the wrong inverse sign rather than misdistributing; a two-step item with the constant on the same side will test whether this recurs outside grouping.","evidence_event_ids":["evt_4312265333cdac397a9bc07a","evt_b9064293cb6c2420a80dad4c"],"hypothesis_status":"PROVISIONAL"}],"resource_requests":[],"teacher_report":null,"issue":null}
  ```
- 06-B3 turn 7, GUIDED_STEP, attempt 0 (disclosure guard): student_message contains the expected answer of the active or a remaining guided step

  ```
  {"architecture_version":"1.2","session_id":"ses_75047175f6c8b75d8be300f6","state_revision":6,"reply_to_event_id":"evt_eac46e6d3cd81707f977add3","message_type":"QUESTION","student_message":"Good — with the sizes of the numbers alone, 72 ÷ 9 gives 8. Now put the negative sign back on the divisor and pick the option that matches the sign you expect for the quotient.","item":{"item_id":"itm_8c9e73866be9c8d9feb0cbba","item_version":1,"curriculum_id":"ACE-ALGEBRA1","curriculum_version":"1.1.0","skill_id":"N06","role":"GUIDED","component_id":"guided_repair","variant_id":"guided_step","prompt":"Is 72 ÷ (−9) positive or negative?\nA) Positive\nB) Negative","response_type":"classification","template_id":"GUIDED_STEP","parameters":{"plan_id":"plan_cb821b94e12b1c182954e86e","step_id":"s2","phrasing":"main"},"mathematical_objects":{"dividend":72,"divisor":-9,"operation":"division","quotient_sign":"unresolved","option_codes":["POS","NEG"]},"domain":{"number_system":"RATIONAL","restrictions":[]},"answer_spec":{"kind":"classification","expected":["B"],"requested_form":"select_one","units":null,"tolerance":null,"rubric":[]},"checking":{"requested_route":"DETERMINISTIC_TEMPLATE","required_capabilities":[],"scope":"Set by the host."},"representation_requirements":[],"support_already_supplied":[],"independent_eligible_proposal":false},"proposed_transition":null,"evidence_proposals":[{"kind":"OBSERVATION","statement":"On the assisted guided step, the learner entered 8 for 72 ÷ 9, matching the host-derived key for the magnitudes-only quotient.","evidence_event_ids":["evt_eac46e6d3cd81707f977add3"],"hypothesis_status":"UNRESOLVED"},{"kind":"HYPOTHESIS","statement":"Magnitude computation for this division is currently producing the expected quotient; whether the sign of a quotient with one negative value is handled correctly is not yet discriminated by the retained evidence.","evidence_event_ids":["evt_eac46e6d3cd81707f977add3","evt_3b6bbef4af7f69d175443dfa"],"hypothesis_status":"PROVISIONAL"}],"resource_requests":[],"teacher_report":null,"issue":null}
  ```
- 06-B3 turn 7, GUIDED_STEP, attempt 1 (disclosure guard): student_message contains the expected answer of the active or a remaining guided step

  ```
  {"architecture_version":"1.2","session_id":"ses_75047175f6c8b75d8be300f6","state_revision":6,"reply_to_event_id":"evt_eac46e6d3cd81707f977add3","message_type":"QUESTION","student_message":"Your step with the sizes of the numbers alone matched the expected value. Now put the negative sign back on the divisor and choose the option that fits the sign the quotient will have.","item":{"item_id":"itm_8c9e73866be9c8d9feb0cbba","item_version":1,"curriculum_id":"ACE-ALGEBRA1","curriculum_version":"1.1.0","skill_id":"N06","role":"GUIDED","component_id":"guided_repair","variant_id":"guided_step","prompt":"Is 72 ÷ (−9) positive or negative?\nA) Positive\nB) Negative","response_type":"classification","template_id":"GUIDED_STEP","parameters":{"plan_id":"plan_cb821b94e12b1c182954e86e","step_id":"s2","phrasing":"main"},"mathematical_objects":{"dividend":72,"divisor":-9,"operation":"division","quotient_sign":"unresolved","option_codes":["POS","NEG"]},"domain":{"number_system":"RATIONAL","restrictions":[]},"answer_spec":{"kind":"classification","expected":["B"],"requested_form":"select_one","units":null,"tolerance":null,"rubric":[]},"checking":{"requested_route":"DETERMINISTIC_TEMPLATE","required_capabilities":[],"scope":"Set by the host."},"representation_requirements":[],"support_already_supplied":[],"independent_eligible_proposal":false},"proposed_transition":null,"evidence_proposals":[{"kind":"OBSERVATION","statement":"On the assisted guided step, the learner's magnitude-only quotient response matched the host-derived key.","evidence_event_ids":["evt_eac46e6d3cd81707f977add3"],"hypothesis_status":"UNRESOLVED"},{"kind":"HYPOTHESIS","statement":"Magnitude computation for this division is currently producing the expected quotient; whether the sign of a quotient with one negative value is handled correctly is not yet discriminated by the retained evidence.","evidence_event_ids":["evt_eac46e6d3cd81707f977add3","evt_3b6bbef4af7f69d175443dfa"],"hypothesis_status":"PROVISIONAL"}],"resource_requests":[],"teacher_report":null,"issue":null}
  ```
- 06-B3 turn 11, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 06-B3 turn 13, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 06-B3 turn 13, PRESENT_ITEM, attempt 1: empty content

  ```
  (empty content)
  ```
- 06-B3 turn 14, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 06-B3 turn 15, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 07-B3 turn 1, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 08-B3 turn 1, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 08-B3 turn 11, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```
- 08-B3 turn 13, PRESENT_ITEM, attempt 0: empty content

  ```
  (empty content)
  ```

This run confirms behaviour with the live provider for scripted learners only. It is not evidence of teaching quality or learning.
