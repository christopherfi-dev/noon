# Tutor sessions — round 9 arm A

LIVE openai_compatible / deepseek-flash, provider_thinking disabled

Generated 2026-10-24T01:44:16Z by `app/tools/tutor_sessions.php` from 6 session files.

Scripted learners against a live provider: not deployment validation and not evidence of learning.

## Per session

| Session | Turns | Calls | One-call turns | Wait median / slowest ms | Goals met (code) | Checks counted | Checks with help | Checks held | Help moves on open checks (with a practice item) | Readings is_answer true / false | equation_step verdicts | Slips eligible / not | RESOLVE_AFTER_CORRECT | VALUE_QUESTION_IN_TEXT | Repairs | Holds | Ungraded value answers | Empty replies | Binding accepted/calls |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| BREAKER-1 | 31 | 34 | 29/31 | 2335 / 5174 | Q07, E06, Q06 | 14 (14 correct) | 4 | 0 | 4 (0) | 0 / 1 | — | 0 / 0 | 0 | 0 | 1 | 0 | 0 | 0 | 1/1 |
| HELP-ON-CHECK-1 | 31 | 37 | 25/31 | 2297 / 4754 | — | 0 (0 correct) | 10 | 0 | 10 (9) | 0 / 0 | STEP_CORRECT 9 | 0 / 0 | 0 | 1 | 5 | 0 | 0 | 0 | 1/1 |
| HELP-SEEKER-1 | 31 | 32 | 30/31 | 2289 / 3666 | — | 0 (0 correct) | 0 | 0 | 2 (1) | 0 / 0 | STEP_CORRECT 2 | 0 / 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| SLIPS-1 | 31 | 35 | 27/31 | 2537 / 5006 | E06, Q07, Q06 | 17 (14 correct) | 0 | 0 | 0 (0) | 0 / 1 | — | 1 / 2 | 0 | 0 | 2 | 0 | 0 | 0 | 1/1 |
| STEP-1 | 31 | 36 | 26/31 | 2737 / 6385 | — | 0 (0 correct) | 5 | 0 | 5 (3) | 0 / 0 | STEP_CORRECT 5, EQUIVALENT_NOT_STEP 5, NOT_EQUIVALENT 3 | 0 / 0 | 0 | 0 | 4 | 0 | 0 | 0 | 1/1 |
| WORKING-1 | 31 | 49 | 14/31 | 3504 / 6267 | Q07 | 12 (12 correct) | 6 | 0 | 6 (0) | 8 / 8 | STEP_CORRECT 1 | 0 / 0 | 1 | 0 | 1 | 0 | 0 | 0 | 1/1 |

## Totals

- Sessions: 6; turns: 186; model calls: 223; answers recorded: 137
- Goals met (code): 7
- Check answers: 43 counted unassisted (40 correct), 25 with help recorded, 0 held
- Help moves on open checks: 27; with a practice item in the same envelope: 13
- Ungraded answers to value questions: 0 (graded by mistake: 0)
- Empty replies (empty content or empty student_message, each repaired): 0; calls cut off at the token limit: 0
- Binding: 6 accepted in 6 sessions, 6 binding calls

## Check answers with help recorded, by cause

| | Count |
|---|---|
| tutor move HELP | 15 |
| tutor move HINT | 10 |

## Check answers held, by cause

None.

## Help moves on open checks, by move

| | Count |
|---|---|
| HELP | 16 |
| HINT | 11 |

## equation_step verdicts

| | Count |
|---|---|
| STEP_CORRECT | 17 |
| EQUIVALENT_NOT_STEP | 5 |
| NOT_EQUIVALENT | 3 |

## Slip eligibility (answers code found not yet correct)

| | Count |
|---|---|
| not eligible | 2 |
| eligible | 1 |

## Detector signals by type (logged only; text shown unchanged; nothing held)

| | Count |
|---|---|
| CHECK_CARD_INVITATION | 66 |
| HELP_CLAIM | 38 |
| CHECK_CARD_SHARED_NUMBER | 37 |
| MASTERY_OR_GOAL_CLAIM | 6 |
| ANSWER_LEAK | 5 |
| INTERNAL_VOCABULARY | 2 |
| CHECK_CARD_ANSWER_SIGNAL | 2 |
| DURING_CHECK | 1 |
| CHECK_CARD_ANSWER | 1 |

## Detector signals, phrase by phrase (logged only)

| | Count |
|---|---|
| CHECK_CARD_INVITATION: try | 63 |
| HELP_CLAIM: on your own | 38 |
| CHECK_CARD_INVITATION: another one | 8 |
| CHECK_CARD_INVITATION: next | 6 |
| CHECK_CARD_INVITATION: one more | 3 |

## Counters (logged only)

| | Count |
|---|---|
| VALUE_QUESTION_IN_TEXT | 1 |
| RESOLVE_AFTER_CORRECT | 1 |

## Repairs by cause

| | Count |
|---|---|
| message_type and item disagree | 10 |
| move: slip check not allowed | 1 |
| item: original problem | 1 |
| item: other | 1 |

## Holds by cause

None.

## Readings

| | Count |
|---|---|
| sent_for_reading | 18 |
| is_answer_false | 10 |
| is_answer_true | 8 |
| accepted | 8 |
| rejected | 0 |
| readings_differ | 0 |
| clarifying_questions_shown | 0 |

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 151 |
| 2 | 33 |
| 3 | 2 |

Turns with exactly one call: 151 of 186 (81%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 2452 ms, slowest 6385 ms over 186 turns.
