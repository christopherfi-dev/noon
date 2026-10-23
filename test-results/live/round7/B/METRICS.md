# Tutor sessions — round 7 arm B (deepseek-flash, thinking enabled, reasoning_effort high)

LIVE openai_compatible / deepseek-flash, provider_thinking enabled, reasoning_effort high

Generated 2026-10-23T09:42:58Z by `app/tools/tutor_sessions.php` from 7 session files.

Scripted learners against a live provider: not deployment validation and not evidence of learning.

## Sessions

| Session | Turns | Calls | Answers recorded | Goals met (code) | Wait median / slowest ms | Tokens prompt (cached) / completion (reasoning) | Est. USD |
|---|---|---|---|---|---|---|---|
| B1-1 | 31 | 34 | 29 | Q07, E06, N06, N05 | 7778 / 26554 | 353677 (218624) / 46731 (34524) | 0.0326 |
| B1-2 | 21 | 24 | 20 | Q07, E06, Q06 | 9174 / 21280 | 247542 (160512) / 35397 (25833) | 0.0225 |
| B11-1 | 31 | 35 | 25 | Q06, Q07, Q04, Q05 | 8208 / 20826 | 351255 (230656) / 43594 (30930) | 0.0297 |
| BREAKER-1 | 31 | 35 | 24 | Q07, E06 | 6656 / 21438 | 353583 (226560) / 38954 (26484) | 0.0293 |
| HELP-SEEKER-1 | 31 | 33 | 11 |  | 8610 / 17960 | 328123 (214016) / 42457 (30208) | 0.0285 |
| N1-1 | 31 | 34 | 28 | E06, Q07, Q06, Q05, N06, N05 | 7562 / 20524 | 341754 (220288) / 42398 (30022) | 0.0295 |
| N1-2 | 15 | 17 | 12 | E06, N18 | 7875 / 15619 | 159142 (108032) / 21089 (15265) | 0.0134 |

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 171 |
| 2 | 19 |
| 3 | 1 |

Turns with exactly one call: 171 of 191 (90%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (round 6 §3.7 targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 7875 ms, slowest 26554 ms over 191 turns.

## Guard findings by type (logged only; text shown unchanged)

| | Count |
|---|---|
| CHECK_CARD_SHARED_NUMBER | 33 |
| CHECK_CARD_PHRASE | 26 |
| ANSWER_LEAK | 15 |
| CHECK_CARD_ANSWER | 7 |
| HELP_CLAIM | 6 |
| INTERNAL_VOCABULARY | 2 |

## Held attempts by cause

| | Count |
|---|---|
| HELP_BEFORE_CHECK | 30 |

## Repairs by cause

| | Count |
|---|---|
| item: shows its own answer | 8 |
| item: stated answer differs | 1 |
| item: check off pattern | 1 |
| move: slip check not allowed | 1 |

## Holds by cause

| | Count |
|---|---|
| repair limit: item: stated answer differs | 1 |
| repair limit: item: check off pattern | 1 |

## Readings

| | Count |
|---|---|
| sent_for_reading | 7 |
| not_an_answer | 3 |
| accepted | 2 |
| readings_differ | 2 |
| clarifying_questions_shown | 2 |
| rejected | 0 |

## Empty replies, truncation and binding

- Empty replies (empty content or empty student_message, each repaired): 0
- Calls cut off at the token limit (finish_reason length): 0
- Binding: 7 accepted in 7 sessions, 7 binding calls

## Tokens and cost

Prompt 2135076 (cached 1378688), completion 270620 (reasoning 193266). Estimated cost $0.1855 at $0.14 / $0.0028 / $0.28 per million tokens (prompt miss / prompt hit / completion).
