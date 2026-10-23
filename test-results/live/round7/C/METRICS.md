# Tutor sessions — round 7 arm C (deepseek-v4-pro, thinking disabled)

LIVE openai_compatible / deepseek-v4-pro, provider_thinking disabled

Generated 2026-10-23T10:05:36Z by `app/tools/tutor_sessions.php` from 7 session files.

Scripted learners against a live provider: not deployment validation and not evidence of learning.

## Sessions

| Session | Turns | Calls | Answers recorded | Goals met (code) | Wait median / slowest ms | Tokens prompt (cached) / completion (reasoning) | Est. USD |
|---|---|---|---|---|---|---|---|
| B1-1 | 31 | 58 | 3 |  | 8621 / 9659 | 584879 (468736) / 21975 (0) | 0.2854 |
| B1-2 | 31 | 60 | 2 |  | 8070 / 9082 | 594398 (491136) / 22458 (0) | 0.2650 |
| B11-1 | 31 | 34 | 13 | Q06 | 4347 / 7581 | 338009 (227072) / 11579 (0) | 0.2366 |
| BREAKER-1 | 31 | 33 | 13 | Q07 | 4774 / 6833 | 328070 (214528) / 11826 (0) | 0.2418 |
| HELP-SEEKER-1 | 31 | 32 | 6 |  | 4196 / 11099 | 306726 (208000) / 9127 (0) | 0.2066 |
| N1-1 | 31 | 40 | 5 |  | 4403 / 13535 | 387534 (281984) / 12128 (0) | 0.2300 |
| N1-2 | 31 | 48 | 10 |  | 7039 / 13652 | 470470 (363648) / 17354 (0) | 0.2515 |

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 131 |
| 2 | 84 |
| 3 | 2 |

Turns with exactly one call: 131 of 217 (60%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (round 6 §3.7 targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 4992 ms, slowest 13652 ms over 217 turns.

## Guard findings by type (logged only; text shown unchanged)

| | Count |
|---|---|
| HELP_CLAIM | 26 |
| MASTERY_OR_GOAL_CLAIM | 4 |
| ANSWER_LEAK | 4 |
| CHECK_CARD_PHRASE | 3 |
| CHECK_CARD_SHARED_NUMBER | 2 |
| INTERNAL_VOCABULARY | 1 |
| CHECK_CARD_ANSWER | 1 |

## Held attempts by cause

| | Count |
|---|---|
| HELP_BEFORE_CHECK | 4 |

## Repairs by cause

| | Count |
|---|---|
| message_type and item disagree | 68 |
| item: shows its own answer | 7 |
| item: other | 1 |

## Holds by cause

None.

## Readings

| | Count |
|---|---|
| sent_for_reading | 5 |
| accepted | 4 |
| not_an_answer | 1 |
| rejected | 0 |
| readings_differ | 0 |
| clarifying_questions_shown | 0 |

## Empty replies, truncation and binding

- Empty replies (empty content or empty student_message, each repaired): 0
- Calls cut off at the token limit (finish_reason length): 0
- Binding: 7 accepted in 7 sessions, 7 binding calls

## Tokens and cost

Prompt 3010086 (cached 2255104), completion 106447 (reasoning 0). Estimated cost $1.7168 at $1.74 / $0.0145 / $3.48 per million tokens (prompt miss / prompt hit / completion).
