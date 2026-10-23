# Tutor sessions — mock

MOCK (simulated tutor; not a live result, not evidence of learning, latency not meaningful)

Generated 2026-10-23T13:09:12Z by `app/tools/tutor_sessions.php` from 14 session files.

**MOCK:** the tutor is the scripted mock. These numbers show how the loop behaves; they are not live results, not evidence of learning, and the waits are not a latency measurement.

## Per session

| Session | Turns | Calls | One-call turns | Wait median / slowest ms | Goals met (code) | Checks counted unassisted | Checks with help recorded | Checks held | Help moves on open checks | Backstop holds | RESOLVE_AFTER_CORRECT | VALUE_QUESTION_IN_TEXT | Repairs | Holds | Substitutions | Ungraded value answers | Empty replies | Binding accepted/calls |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| B1-1 | 12 | 13 | 11/12 | 8 / 25 | Q07, Q06 | 8 (7 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| B1-2 | 12 | 13 | 11/12 | 7 / 8 | Q07, Q06 | 8 (7 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| B1-3 | 12 | 13 | 11/12 | 8 / 10 | Q07, Q06 | 8 (7 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| B1-4 | 12 | 13 | 11/12 | 6 / 10 | Q07, Q06 | 8 (7 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| B1-5 | 12 | 13 | 11/12 | 7 / 10 | Q07, Q06 | 8 (7 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| B11-1 | 10 | 11 | 9/10 | 6 / 7 | Q06 | 3 (3 correct) | 1 | 0 | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| B11-2 | 10 | 11 | 9/10 | 6 / 11 | E06 | 3 (3 correct) | 1 | 0 | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| BREAKER-1 | 9 | 11 | 7/9 | 7 / 7 | Q07 | 3 (3 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| HELP-SEEKER-1 | 31 | 32 | 30/31 | 7 / 10 | — | 0 (0 correct) | 7 | 0 | 14 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| N1-1 | 10 | 13 | 7/10 | 9 / 15 | E06 | 4 (3 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| N1-2 | 13 | 16 | 10/13 | 7 / 11 | E06, N18 | 7 (6 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| descent-1 | 13 | 14 | 12/13 | 7 / 21 | E06, Q07 | 7 (7 correct) | 1 | 0 | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| descent-2 | 13 | 14 | 12/13 | 8 / 10 | E06, Q07 | 7 (7 correct) | 1 | 0 | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| descent-3 | 13 | 14 | 12/13 | 8 / 11 | E06, Q07 | 7 (7 correct) | 1 | 0 | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |

## Totals

- Sessions: 14; turns: 182; model calls: 201; answers recorded: 131
- Goals met (code): 22
- Check answers: 81 counted unassisted (74 correct), 12 with help recorded, 0 held
- Help moves on open checks: 19; backstop holds: 0
- Prepared-problem substitutions: 0
- Ungraded answers to value questions: 0 (graded by mistake: 0)
- Empty replies (empty content or empty student_message, each repaired): 0; calls cut off at the token limit: 0
- Binding: 14 accepted in 14 sessions, 14 binding calls

## Check answers with help recorded, by cause

| | Count |
|---|---|
| tutor move HELP | 9 |
| I'm stuck: HINT | 3 |

## Check answers held, by cause

None.

## Help moves on open checks, by move

| | Count |
|---|---|
| HELP | 16 |
| HINT | 3 |

## Guard findings by type (logged only; text shown unchanged)

None.

## Phrase guards, phrase by phrase

CHECK_CARD_PHRASE holds the check; CHECK_CARD_INVITATION and HELP_CLAIM are logged only.

None.

## Counters (logged only)

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

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 163 |
| 2 | 19 |

Turns with exactly one call: 163 of 182 (90%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 7 ms, slowest 25 ms over 182 turns. (Mock: not meaningful.)
