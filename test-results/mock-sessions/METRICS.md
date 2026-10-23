# Tutor sessions — mock

MOCK (simulated tutor; not a live result, not evidence of learning, latency not meaningful)

Generated 2026-10-23T08:59:47Z by `app/tools/tutor_sessions.php` from 14 session files.

**MOCK:** the tutor is the scripted mock. These numbers show how the loop spends calls; they are not live results, not evidence of learning, and the waits are not a latency measurement.

## Sessions

| Session | Turns | Calls | Answers recorded | Goals met (code) | Wait median / slowest ms | Tokens prompt (cached) / completion (reasoning) | Est. USD |
|---|---|---|---|---|---|---|---|
| B1-1 | 12 | 13 | 11 | Q07, Q06 | 12 / 29 | 0 (0) / 0 (0) | 0.0000 |
| B1-2 | 12 | 13 | 11 | Q07, Q06 | 12 / 15 | 0 (0) / 0 (0) | 0.0000 |
| B1-3 | 12 | 13 | 11 | Q07, Q06 | 10 / 13 | 0 (0) / 0 (0) | 0.0000 |
| B1-4 | 12 | 13 | 11 | Q07, Q06 | 8 / 11 | 0 (0) / 0 (0) | 0.0000 |
| B1-5 | 12 | 13 | 11 | Q07, Q06 | 12 / 17 | 0 (0) / 0 (0) | 0.0000 |
| B11-1 | 9 | 10 | 4 | Q06 | 10 / 12 | 0 (0) / 0 (0) | 0.0000 |
| B11-2 | 9 | 10 | 4 | E06 | 8 / 16 | 0 (0) / 0 (0) | 0.0000 |
| BREAKER-1 | 9 | 11 | 5 | Q07 | 12 / 15 | 0 (0) / 0 (0) | 0.0000 |
| HELP-SEEKER-1 | 23 | 24 | 7 | Q06 | 9 / 17 | 0 (0) / 0 (0) | 0.0000 |
| N1-1 | 10 | 13 | 8 | E06 | 10 / 14 | 0 (0) / 0 (0) | 0.0000 |
| N1-2 | 10 | 13 | 8 | E06 | 9 / 11 | 0 (0) / 0 (0) | 0.0000 |
| descent-1 | 13 | 14 | 11 | E06, Q07 | 12 / 13 | 0 (0) / 0 (0) | 0.0000 |
| descent-2 | 13 | 14 | 11 | E06, Q07 | 12 / 23 | 0 (0) / 0 (0) | 0.0000 |
| descent-3 | 13 | 14 | 11 | E06, Q07 | 10 / 25 | 0 (0) / 0 (0) | 0.0000 |

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 150 |
| 2 | 19 |

Turns with exactly one call: 150 of 169 (89%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (round 6 §3.7 targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 11 ms, slowest 29 ms over 169 turns. (Mock: not meaningful.)

## Guard findings by type (logged only; text shown unchanged)

None.

## Held attempts by cause

None.

## Repairs by cause

None.

## Holds by cause

None.

## Readings

| | Count |
|---|---|
| sent_for_reading | 5 |
| accepted | 5 |
| rejected | 0 |
| readings_differ | 0 |
| clarifying_questions_shown | 0 |
| not_an_answer | 0 |

## Empty replies, truncation and binding

- Empty replies (empty content or empty student_message, each repaired): 0
- Calls cut off at the token limit (finish_reason length): 0
- Binding: 14 accepted in 14 sessions, 14 binding calls

## Tokens and cost

Prompt 0 (cached 0), completion 0 (reasoning 0). No price given.
