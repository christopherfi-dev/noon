# Tutor sessions — round 8 arm A

LIVE openai_compatible / deepseek-flash, provider_thinking disabled

Generated 2026-10-23T13:22:07Z by `app/tools/tutor_sessions.php` from 7 session files.

Scripted learners against a live provider: not deployment validation and not evidence of learning.

## Per session

| Session | Turns | Calls | One-call turns | Wait median / slowest ms | Goals met (code) | Checks counted unassisted | Checks with help recorded | Checks held | Help moves on open checks | Backstop holds | RESOLVE_AFTER_CORRECT | VALUE_QUESTION_IN_TEXT | Repairs | Holds | Substitutions | Ungraded value answers | Empty replies | Binding accepted/calls |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| B1-1 | 31 | 34 | 28/31 | 2729 / 5264 | Q07, E06, N18, Q06 | 23 (22 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 2 | 0 | 2 | 0 | 0 | 1/1 |
| B1-2 | 31 | 35 | 27/31 | 2633 / 5028 | Q07 | 10 (10 correct) | 0 | 11 | 0 | 0 | 0 | 0 | 3 | 0 | 0 | 0 | 0 | 1/1 |
| B11-1 | 31 | 35 | 27/31 | 2651 / 6787 | Q06, E06, Q07 | 12 (12 correct) | 0 | 2 | 0 | 0 | 4 | 1 | 3 | 0 | 0 | 0 | 0 | 1/1 |
| BREAKER-1 | 31 | 35 | 27/31 | 2625 / 5395 | Q07, E06, Q06 | 13 (13 correct) | 2 | 1 | 3 | 1 | 0 | 4 | 3 | 1 | 1 | 0 | 0 | 1/1 |
| HELP-SEEKER-1 | 31 | 33 | 29/31 | 2867 / 5830 | — | 0 (0 correct) | 3 | 3 | 20 | 0 | 0 | 8 | 1 | 0 | 0 | 0 | 0 | 1/1 |
| N1-1 | 31 | 35 | 28/31 | 2726 / 7445 | E06 | 9 (8 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 2 | 0 | 0 | 0 | 0 | 1/1 |
| N1-2 | 31 | 34 | 28/31 | 2678 / 5345 | E06 | 7 (6 correct) | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 0 | 0 | 0 | 0 | 1/1 |

## Totals

- Sessions: 7; turns: 217; model calls: 241; answers recorded: 146
- Goals met (code): 13
- Check answers: 74 counted unassisted (71 correct), 5 with help recorded, 17 held
- Help moves on open checks: 23; backstop holds: 1
- Prepared-problem substitutions: 3
- Ungraded answers to value questions: 0 (graded by mistake: 0)
- Empty replies (empty content or empty student_message, each repaired): 0; calls cut off at the token limit: 0
- Binding: 7 accepted in 7 sessions, 7 binding calls

## Check answers with help recorded, by cause

| | Count |
|---|---|
| tutor move HELP, tutor move HINT | 3 |
| tutor move HINT | 1 |
| tutor move ANSWER_QUESTION | 1 |

## Check answers held, by cause

| | Count |
|---|---|
| HELP_BEFORE_CHECK (phrase: same idea) | 10 |
| HELP_BEFORE_CHECK (answer) | 3 |
| HELP_BEFORE_CHECK (phrase: when you) | 2 |
| HELP_BEFORE_CHECK (answer) (phrase: same idea) | 1 |
| HELP_DURING_CHECK (expression, numbers) | 1 |

## Help moves on open checks, by move

| | Count |
|---|---|
| HINT | 11 |
| HELP | 11 |
| ANSWER_QUESTION | 1 |

## Guard findings by type (logged only; text shown unchanged)

| | Count |
|---|---|
| CHECK_CARD_INVITATION | 88 |
| CHECK_CARD_SHARED_NUMBER | 33 |
| ANSWER_LEAK | 15 |
| MASTERY_OR_GOAL_CLAIM | 15 |
| CHECK_CARD_PHRASE | 14 |
| HELP_CLAIM | 8 |
| CHECK_CARD_ANSWER_SIGNAL | 6 |
| CHECK_CARD_ANSWER | 4 |
| INTERNAL_VOCABULARY | 2 |

## Phrase guards, phrase by phrase

CHECK_CARD_PHRASE holds the check; CHECK_CARD_INVITATION and HELP_CLAIM are logged only.

| | Count |
|---|---|
| CHECK_CARD_INVITATION: try | 73 |
| CHECK_CARD_INVITATION: one more | 25 |
| CHECK_CARD_PHRASE: same idea | 12 |
| CHECK_CARD_INVITATION: next | 8 |
| HELP_CLAIM: without help | 6 |
| CHECK_CARD_INVITATION: another one | 4 |
| HELP_CLAIM: on your own | 2 |
| CHECK_CARD_PHRASE: when you | 2 |

## Counters (logged only)

| | Count |
|---|---|
| VALUE_QUESTION_IN_TEXT | 13 |
| RESOLVE_AFTER_CORRECT | 4 |

## Repairs by cause

| | Count |
|---|---|
| message_type and item disagree | 5 |
| item: check shows its own answer | 4 |
| identity fields | 2 |
| move: slip check not allowed | 2 |
| item: stated answer differs | 1 |
| item: other | 1 |

## Holds by cause

| | Count |
|---|---|
| repair limit: identity fields | 1 |

## Readings

| | Count |
|---|---|
| sent_for_reading | 5 |
| accepted | 2 |
| readings_differ | 2 |
| clarifying_questions_shown | 2 |
| not_an_answer | 1 |
| rejected | 0 |

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 194 |
| 2 | 22 |
| 3 | 1 |

Turns with exactly one call: 194 of 217 (89%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 2677 ms, slowest 7445 ms over 217 turns.
