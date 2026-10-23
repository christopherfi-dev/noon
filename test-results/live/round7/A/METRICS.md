# Tutor sessions — round 7 arm A (deepseek-flash, thinking disabled)

LIVE openai_compatible / deepseek-flash, provider_thinking disabled

Generated 2026-10-23T09:13:19Z by `app/tools/tutor_sessions.php` from 7 session files.

Scripted learners against a live provider: not deployment validation and not evidence of learning.

## Sessions

| Session | Turns | Calls | Answers recorded | Goals met (code) | Wait median / slowest ms | Tokens prompt (cached) / completion (reasoning) | Est. USD |
|---|---|---|---|---|---|---|---|
| B1-1 | 31 | 33 | 27 | Q07, E06, Q06 | 2889 / 5330 | 340379 (208256) / 10342 (0) | 0.0220 |
| B1-2 | 31 | 32 | 30 |  | 2680 / 4193 | 331285 (203904) / 10156 (0) | 0.0212 |
| B11-1 | 31 | 33 | 26 |  | 2524 / 4638 | 323615 (213760) / 9592 (0) | 0.0187 |
| BREAKER-1 | 31 | 35 | 23 |  | 2612 / 5367 | 357352 (231936) / 10564 (0) | 0.0212 |
| HELP-SEEKER-1 | 31 | 32 | 10 |  | 2591 / 3433 | 314232 (203904) / 9405 (0) | 0.0187 |
| N1-1 | 31 | 34 | 28 |  | 2658 / 6714 | 333926 (220544) / 10721 (0) | 0.0195 |
| N1-2 | 31 | 34 | 23 | E06 | 2641 / 5679 | 330423 (220672) / 10715 (0) | 0.0190 |

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 202 |
| 2 | 14 |
| 3 | 1 |

Turns with exactly one call: 202 of 217 (93%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (round 6 §3.7 targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 2658 ms, slowest 6714 ms over 217 turns.

## Guard findings by type (logged only; text shown unchanged)

| | Count |
|---|---|
| CHECK_CARD_PHRASE | 116 |
| CHECK_CARD_SHARED_NUMBER | 74 |
| HELP_CLAIM | 66 |
| ANSWER_LEAK | 16 |
| CHECK_CARD_ANSWER | 11 |
| INTERNAL_VOCABULARY | 1 |

## Held attempts by cause

| | Count |
|---|---|
| HELP_BEFORE_CHECK | 120 |
| HELP_DURING_CHECK | 1 |

## Repairs by cause

| | Count |
|---|---|
| item: shows its own answer | 6 |
| move: slip check not allowed | 2 |
| message_type and item disagree | 1 |

## Holds by cause

| | Count |
|---|---|
| repair limit: item: shows its own answer | 3 |

## Readings

| | Count |
|---|---|
| sent_for_reading | 5 |
| accepted | 2 |
| readings_differ | 2 |
| clarifying_questions_shown | 2 |
| not_an_answer | 1 |
| rejected | 0 |

## Empty replies, truncation and binding

- Empty replies (empty content or empty student_message, each repaired): 0
- Calls cut off at the token limit (finish_reason length): 0
- Binding: 7 accepted in 7 sessions, 7 binding calls

## Tokens and cost

Prompt 2331212 (cached 1502976), completion 71495 (reasoning 0). Estimated cost $0.1402 at $0.14 / $0.0028 / $0.28 per million tokens (prompt miss / prompt hit / completion).
