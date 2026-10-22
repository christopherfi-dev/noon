# Scripted sessions — metrics

Mode: LIVE — configuration thinking-disabled
Started (UTC): 2026-10-22T09:37:57Z
Settings: thinking disabled, reasoning_effort (not sent), max_tokens 4096, provider_timeout 45 s, lease 90 s
Model list: GET https://api.deepseek.com/v1/models → HTTP 200; listed: deepseek-flash, deepseek-v4-pro
Configured model `deepseek-flash` listed: yes
Provider / model: openai_compatible / deepseek-flash

| Session | Path | Final mode | Report | Turns | Binding 1st try | Provider calls | Repairs by operation (accepted generations) | HOLDs | Guard rejections (on sign steps) | Other rejections (internal vocabulary) | Independent items shown / with model text | Model text discarded | Internal-vocabulary hits in learner text | Empty responses | finish=length | Call time median / slowest (s) | Learner wait median / slowest (s) | Tokens in | Tokens out (reasoning) | Cache hit / miss | Cost |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 15 | PRESENT_ITEM 0 (9); GUIDED_STEP 0 (5) | 0 | 0 (0) | 0 (0) | 9 / 0 | 0 | 0 | 0 | 0 | 2.7 / 3.0 | 2.6 / 4.5 | 186379 | 6442 (0) | 150400 / 35979 | not reported by provider |
| 02-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 15 | PRESENT_ITEM 0 (9); GUIDED_STEP 0 (5) | 0 | 0 (0) | 0 (0) | 9 / 0 | 0 | 0 | 0 | 0 | 2.7 / 3.1 | 2.7 / 4.3 | 186451 | 6449 (0) | 153344 / 33107 | not reported by provider |
| 03-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 15 | PRESENT_ITEM 0 (9); GUIDED_STEP 0 (5) | 0 | 0 (0) | 0 (0) | 9 / 0 | 0 | 0 | 0 | 0 | 2.6 / 3.4 | 2.6 / 3.8 | 186379 | 6688 (0) | 153344 / 33035 | not reported by provider |
| 04-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 15 | PRESENT_ITEM 0 (9); GUIDED_STEP 0 (5) | 0 | 0 (0) | 0 (0) | 9 / 0 | 0 | 0 | 0 | 0 | 2.8 / 3.6 | 2.8 / 4.3 | 186423 | 6467 (0) | 153344 / 33079 | not reported by provider |
| 05-B1 | B1 | SESSION_CLOSED | COMPLETED | 17 | yes | 15 | PRESENT_ITEM 0 (9); GUIDED_STEP 0 (5) | 0 | 0 (0) | 0 (0) | 9 / 0 | 0 | 0 | 0 | 0 | 2.7 / 3.1 | 2.7 / 4.2 | 186309 | 6429 (0) | 153344 / 32965 | not reported by provider |
| 06-B3 | B3 | SESSION_CLOSED | COMPLETED | 19 | yes | 19 | PRESENT_ITEM 1 (13); GUIDED_STEP 0 (4) | 0 | 0 (0) | 1 (0) | 13 / 0 | 0 | 0 | 0 | 0 | 2.7 / 3.8 | 2.8 / 5.8 | 252239 | 8145 (0) | 206592 / 45647 | not reported by provider |
| 07-B3 | B3 | SESSION_CLOSED | COMPLETED | 19 | yes | 19 | PRESENT_ITEM 1 (13); GUIDED_STEP 0 (4) | 0 | 0 (0) | 1 (0) | 13 / 0 | 0 | 0 | 0 | 0 | 2.7 / 3.1 | 2.7 / 5.0 | 252112 | 7939 (0) | 211712 / 40400 | not reported by provider |
| 08-B3 | B3 | SESSION_CLOSED | COMPLETED | 19 | yes | 18 | PRESENT_ITEM 0 (13); GUIDED_STEP 0 (4) | 0 | 0 (0) | 0 (0) | 13 / 0 | 0 | 0 | 0 | 0 | 2.5 / 3.5 | 2.5 / 3.9 | 239415 | 7603 (0) | 198784 / 40631 | not reported by provider |
| **Total** | 8 sessions | | | 142 | 8/8 | 131 (16.4 per session) | PRESENT_ITEM 2 (84); GUIDED_STEP 0 (37) | 0 | 0 (0) | 2 (0) | 84 / 0 | 0 | 0 | 0 | 0 | 2.6 / 3.8 | 2.7 / 5.8 | 1675707 | 56162 (0) | 1380864 / 294843 | not reported by provider |

Repairs by operation: bounded repair requests per operation, with accepted generations in brackets. Call time: one provider call. Learner wait: the wall time of one API request (all provider calls in it included). Empty responses: HTTP 200 with empty content. Guard rejections on sign steps: disclosure-guard rejections while the step asked "positive or negative". Other rejections (internal vocabulary): rejections naming an internal term (R5-B). Independent items shown / with model text: turns that showed an independent item / of those, turns that also showed model-written text (B14; must be 0). Model text discarded: independent items whose model text was dropped unread (R5-A). Internal-vocabulary hits: listed terms found in any learner-visible message, progress line or item (must be 0).

Model name returned in each response (count of calls): deepseek-flash (131). Each call's value is in the transcripts.

## Every host rejection of model output

- 06-B3 turn 15, PRESENT_ITEM, attempt 0: parameters reuse an item instance already presented in this session; choose fresh parameters

  ```
  {"architecture_version":"1.2","session_id":"ses_38df31cf79ae2806ff8496e9","state_revision":14,"reply_to_event_id":"evt_672b191ef09f864fd3748b80","message_type":"QUESTION","student_message":"","item":{"item_id":"itm_3568a5567f78fd9270ed0ed1","item_version":1,"curriculum_id":"ACE-ALGEBRA1","curriculum_version":"1.1.0","skill_id":"Q07","role":"TARGET","component_id":"numeric_solution","variant_id":"constructed","prompt":"Solve 3(x + 4) = 21 for x.","response_type":"scalar","template_id":"GROUPED_LINEAR","parameters":{"a":3,"b":4,"c":21},"mathematical_objects":{"equation":"3(x + 4) = 21","solution":1},"domain":{"number_system":"RATIONAL","restrictions":[]},"answer_spec":{"kind":"exact_rational","expected":null,"requested_form":"value_of_x","units":null,"tolerance":null,"rubric":[]},"checking":{"requested_route":"DETERMINISTIC_TEMPLATE","required_capabilities":[],"scope":"Set by the host."},"representation_requirements":[],"support_already_supplied":[],"independent_eligible_proposal":true},"proposed_transition":{"to_mode":"TARGET_ASSESSMENT","target_skill_id":"Q07","reason":"Prerequisite check on Q06 was answered correctly and independently; returning to the original target with a fresh grouped-equation item.","evidence_event_ids":["evt_672b191ef09f864fd3748b80"]},"evidence_proposals":[{"kind":"OBSERVATION","statement":"Learner solved the two-step equation -8x - 12 = 20 correctly and independently, entering -4.","evidence_event_ids":["evt_672b191ef09f864fd3748b80"],"hypothesis_status":"UNRESOLVED"}],"resource_requests":[],"teacher_report":null,"issue":null}
  ```
- 07-B3 turn 15, PRESENT_ITEM, attempt 0: parameters reuse an item instance already presented in this session; choose fresh parameters

  ```
  {"architecture_version":"1.2","session_id":"ses_c1140041967f4114e902bd59","state_revision":14,"reply_to_event_id":"evt_21788e72ab6838206e2b587f","message_type":"QUESTION","student_message":"","item":{"item_id":"itm_72353affc33f81c5c14dce81","item_version":1,"curriculum_id":"ACE-ALGEBRA1","curriculum_version":"1.1.0","skill_id":"Q07","role":"TARGET","component_id":"numeric_solution","variant_id":"constructed","prompt":"Solve 3(x + 4) = 27 for x.","response_type":"scalar","template_id":"GROUPED_LINEAR","parameters":{"a":3,"b":4,"c":27},"mathematical_objects":{"equation":"3(x + 4) = 27","variable":"x"},"domain":{"number_system":"RATIONAL","restrictions":[]},"answer_spec":{"kind":"exact_rational","expected":null,"requested_form":"value_of_x","units":null,"tolerance":null,"rubric":[]},"checking":{"requested_route":"DETERMINISTIC_TEMPLATE","required_capabilities":[],"scope":"Set by the host."},"representation_requirements":[],"support_already_supplied":[],"independent_eligible_proposal":true},"proposed_transition":null,"evidence_proposals":[{"kind":"OBSERVATION","statement":"On the prerequisite item 4x − 8 = 24, the learner entered 8, which the deterministic checker accepted as correct; reasoning was not assessed.","evidence_event_ids":["evt_21788e72ab6838206e2b587f"],"hypothesis_status":"UNRESOLVED"}],"resource_requests":[],"teacher_report":null,"issue":null}
  ```

This run confirms behaviour with the live provider for scripted learners only. It is not evidence of teaching quality or learning.
