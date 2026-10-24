# Tutor sessions — round 10 §6 part 1, arm A

LIVE openai_compatible / deepseek-flash, provider_thinking disabled

Generated 2026-10-24T06:57:39Z by `app/tools/tutor_sessions.php` from 4 session files.

Scripted learners against a live provider: not deployment validation and not evidence of learning.

## Per session

| Session | Turns | Calls | One-call turns | Wait median / slowest ms | Goals met (code) | Checks counted | Checks with help | Checks held | Help moves on open checks (with a practice item) | Readings is_answer true / false | equation_step verdicts | Slips eligible / not | RESOLVE_AFTER_CORRECT | VALUE_QUESTION_IN_TEXT | Repairs | Holds | Ungraded value answers | Empty replies | Binding accepted/calls |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| BOUNDARY | 20 | 22 | 18/20 | 2726 / 5493 | Q07, E06 | 6 (6 correct) | 0 | 0 | 0 (0) | 0 / 0 | — | 0 / 4 | 0 | 0 | 1 | 0 | 0 | 0 | 1/1 |
| HELP-THEN-MOVE-ON | 31 | 32 | 30/31 | 2462 / 3251 | Q07, E06 | 24 (24 correct) | 1 | 0 | 1 (1) | 0 / 0 | STEP_CORRECT 1 | 0 / 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1/1 |
| RESET-LOOPHOLE | 31 | 33 | 29/31 | 2386 / 4236 | Q06, Q07, E06 | 23 (23 correct) | 1 | 0 | 1 (0) | 0 / 0 | — | 0 / 0 | 0 | 0 | 1 | 0 | 0 | 0 | 1/1 |
| STUCK-DEEP | 31 | 32 | 30/31 | 2557 / 3161 | Q07, E06 | 21 (21 correct) | 1 | 0 | 1 (0) | 0 / 0 | — | 0 / 3 | 0 | 1 | 0 | 0 | 0 | 0 | 1/1 |

## Route, prerequisites and catalogue errors (round 10 §7)

| Session | Start | Route entries | Detours (length, outcome) | Originals | Fresh evidence after the first return | Prerequisite answers not yet before the first success | Catalogue errors the learner gave |
|---|---|---|---|---|---|---|---|
| BOUNDARY | Q07 | DETOUR 4, RETURN_TO_SKILL 2 | E06: 1 problem(s), 0 correct / 1 not yet, returned to Q07 through practice (then 4/0); N18: 3 problem(s), 1 correct / 2 not yet, returned to Q07 through practice (then 4/0); E06: 3 problem(s), 3 correct / 0 not yet, returned to Q07 through practice (then 1/0); E06: 1 problem(s), 1 correct / 0 not yet, no return | — | Q07 3 counted, 3 correct | 3 | FIRST_TERM_ONLY 2, NO_RECIPE: the opposite sign 2 |
| HELP-THEN-MOVE-ON | Q07 | RETURN_ORIGINAL 1, DETOUR 1, RETURN_TO_SKILL 1 | E06: 3 problem(s), 3 correct / 0 not yet, returned to Q07 through fresh_check (then 18/0) | — | Q07 21 counted, 21 correct | 0 | — |
| RESET-LOOPHOLE | Q06 | — | — | — | — | 0 | — |
| STUCK-DEEP | Q07 | DETOUR 4, RETURN_TO_SKILL 2 | E06: 1 problem(s), 0 correct / 1 not yet, returned to Q07 through practice (then 5/0); N18: 2 problem(s), 1 correct / 1 not yet, returned to Q07 through practice (then 5/0); E06: 1 problem(s), 1 correct / 0 not yet, returned to Q07 through practice (then 5/0); E06: 3 problem(s), 3 correct / 0 not yet, returned to Q07 through fresh_check (then 15/0) | — | Q07 18 counted, 18 correct | 2 | FIRST_TERM_ONLY 2, NO_RECIPE: the opposite sign 1 |

## Boundary (the tutor's skills whose prerequisites all lie outside it: N18, N03)

| Session | Reached (a not-yet answer there) | Deepest skill posed | Problems posed there | Moves outside the tutor rejected | What the tutor said next |
|---|---|---|---|---|---|
| BOUNDARY | yes | N18 | 3 | 0 | student_message: Not yet correct. 6 × 8 is a product of two positive whole numbers, so the result is positive. What is 6 times 8? // student_message: Not yet correct. Your answer has the right size but the wrong sign: 6 × 8 is a product of two positive whole numbers, so the result is positive. What is 6 times 8? // student_message: Correct: 6 × 8 = 48. A product of two positive whole numbers is positive. Now let's return to the equation with parentheses. |
| HELP-THEN-MOVE-ON | no | E06 | 0 | 0 | — |
| RESET-LOOPHOLE | no | E06 | 0 | 0 | — |
| STUCK-DEEP | yes | N18 | 2 | 0 | student_message: Not yet — on 4 × 9 you answered -36. The product of two positive whole numbers is positive, so the sign here needs another look. Let's isolate the sign question first. // student_message: Correct — 4 × 9 = 36. The product of two positive whole numbers is positive. Now let's take that back to the distributive property: the factor outside the parentheses multiplies every term inside. // student_message: Correct — 4(x + 4) = 4x + 16. The distributive property says a factor outside parentheses multiplies every term inside. Now let's use that on the equation type you started with. |

## Totals

- Sessions: 4; turns: 113; model calls: 119; answers recorded: 95
- Goals met (code): 9
- Check answers: 74 counted unassisted (74 correct), 3 with help recorded, 0 held
- Help moves on open checks: 3; with a practice item in the same envelope: 1
- Ungraded answers to value questions: 0 (graded by mistake: 0)
- Empty replies (empty content or empty student_message, each repaired): 0; calls cut off at the token limit: 0
- Binding: 4 accepted in 4 sessions, 4 binding calls

## Check answers with help recorded, by cause

| | Count |
|---|---|
| tutor move HELP | 3 |

## Check answers held, by cause

None.

## Help moves on open checks, by move

| | Count |
|---|---|
| HELP | 3 |

## equation_step verdicts

| | Count |
|---|---|
| STEP_CORRECT | 1 |

## Slip eligibility (answers code found not yet correct)

| | Count |
|---|---|
| not eligible | 7 |
| eligible | 0 |

## Detector signals by type (logged only; text shown unchanged; nothing held)

| | Count |
|---|---|
| CHECK_CARD_INVITATION | 71 |
| MASTERY_OR_GOAL_CLAIM | 54 |
| CHECK_CARD_SHARED_NUMBER | 30 |
| ANSWER_LEAK | 6 |
| CHECK_CARD_ANSWER_SIGNAL | 4 |
| HELP_CLAIM | 1 |
| CHECK_CARD_PHRASE | 1 |

## Detector signals, phrase by phrase (logged only)

| | Count |
|---|---|
| CHECK_CARD_INVITATION: try | 70 |
| CHECK_CARD_INVITATION: next | 7 |
| CHECK_CARD_INVITATION: another one | 5 |
| CHECK_CARD_INVITATION: one more | 3 |
| HELP_CLAIM: on your own | 1 |
| CHECK_CARD_PHRASE: when you | 1 |

## Counters (logged only)

| | Count |
|---|---|
| VALUE_QUESTION_IN_TEXT | 1 |

## Repairs by cause

| | Count |
|---|---|
| move: slip check not allowed | 1 |
| item: prepared problem reference | 1 |

## Holds by cause

None.

## Readings

| | Count |
|---|---|
| sent_for_reading | 0 |
| is_answer_true | 0 |
| is_answer_false | 0 |
| accepted | 0 |
| rejected | 0 |
| readings_differ | 0 |
| clarifying_questions_shown | 0 |

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 1 | 107 |
| 2 | 6 |

Turns with exactly one call: 107 of 113 (95%). The first turn of each session also makes the binding call; an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Latency per turn (targets: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 2525 ms, slowest 5493 ms over 113 turns.
