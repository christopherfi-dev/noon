# PREREQ-THEN-EASIER-Q06 — LIVE openai_compatible / deepseek-flash, provider_thinking disabled

| # | Learner input | Calls (purpose: reasoning setting) | Outcomes | Wait ms |
|---|---|---|---|---|
| 0 | (start on Q06) | 2 — BINDING: disabled (binding call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 2970 |
| 1 | -20/9 *(catalogue: ADD_INSTEAD_OF_SUBTRACT)* — on Solve 9x − 1 = −19 for x. (Q06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2741 |
| 2 | 9x = -17 *(catalogue: NO_RECIPE: the step with its right side off by one)* — on Add 1 to both sides of 9x − 1 = −19. Write the equation you get. (Q06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2966 |
| 3 | 18 *(catalogue: SIGN_FLIP)* — on Calculate −19 + 1. (N03 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2654 |
| 4 | -8 — on Calculate −14 + 6. (N03 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2918 |
| 5 | -15 — on Calculate −18 + 3. (N03 practice) | 2 — REPLY: disabled (configured: disabled on every call); REPAIR: disabled (configured: disabled on every call) | REJECTED, ACCEPTED_AFTER_REPAIR | 5533 |
| 6 | 3 — on Solve −9x − 13 = −40 for x. (Q06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2366 |
| 7 | -2 — on Solve 9x − 3 = −21 for x. (Q06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2601 |
| 8 | 2 — on Solve 3x + 14 = 20 for x. (Q06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2243 |
| 9 | 1 — on Solve −4x − 8 = −12 for x. (Q06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2173 |
| 10 | 10 — on Solve −4x + 14 = −26 for x. (Q06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2122 |
| 11 | -8 — on Solve 9x − 14 = −86 for x. (Q06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2263 |
| 12 | 4 — on Solve −8x − 5 = −37 for x. (Q06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2515 |
| 13 | I am not sure how to do this kind. | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 3111 |
| 14 | 0 — on Solve 5(x + 5) = 25 for x. (Q07 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2413 |
| 15 | 11 — on Solve 8(x − 8) = 24 for x. (Q07 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2414 |
| 16 | C — on Here is an equation: 4(x + 3) = −8. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide only the left side by 4: x + 3 = −8 / B) Subtract 3 to undo the 3: 4x = −11 / C) Multiply both terms in the parentheses by 4: 4x + 12 = −8 / D) Multiply only the x by 4: 4x + 3 = −8 (Q07 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2428 |
| 17 | 10 — on Solve 7(x + 8) + 14 = 140 for x. (Q07 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2514 |
| 18 | I am not sure how to do this kind. | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 4212 |
| 19 | 2x+8 — on Expand 2(x + 4). Write your answer without parentheses. (E06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2353 |
| 20 | A,B — on Which of these expressions are equal to 9(x + 6) for every value of x? Select every one that is. / A) 9x + 54 / B) 9 · x + 9 · 6 / C) 9x + 6 / D) x + 15 (E06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2701 |
| 21 | 15x+30 — on Expand 5(3x + 6). Write your answer without parentheses. (E06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2648 |
| 22 | I am not sure how to do this kind. | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2825 |
| 23 | 7x+14 — on Expand 7(x + 2). Write your answer without parentheses. (E06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2978 |
| 24 | 3x+3 — on Expand 3(x + 1). Write your answer without parentheses. (E06 practice) | 2 — REPLY: disabled (configured: disabled on every call); REPAIR: disabled (configured: disabled on every call) | REJECTED, ACCEPTED_AFTER_REPAIR | 4981 |
| 25 | 1 — on Solve −4x + 14 = 10 for x. (Q06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2000 |
| 26 | -3 — on Solve −9x + 5 = 32 for x. (Q06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 1967 |
| 27 | I am not sure how to do this kind. | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 3100 |
| 28 | 4x+32 — on Expand 4(x + 8). Write your answer without parentheses. (E06 practice) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2366 |
| 29 | B — on Which of these expressions are equal to 4(x + 3) for every value of x? Select every one that is. / A) x + 12 / B) 4x + 12 / C) 4x + 3 / D) x + 7 (E06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2806 |
| 30 | 10x+15 — on Expand 5(2x + 3). Write your answer without parentheses. (E06 check) | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2799 |

## Conversation as the student saw it (the last 80 lines the session keeps)

- **tutor (problem):** Solve −4x − 8 = −12 for x.
- **student (message):** 1
- **app (result):** Correct. / On −4x − 8 = −12 you answered 1.
- **tutor (message):** Correct: x = 1. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. You've solved several two-step equations on your own now, so let's see how you do with a fresh one.
- **app (check):** Solve −4x + 14 = −26 for x.
- **student (message):** 10
- **app (result):** Correct. / On −4x + 14 = −26 you answered 10.
- **tutor (message):** Correct: x = 10. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That's one check problem solved on your own, so let's keep going.
- **app (check):** Solve 9x − 14 = −86 for x.
- **student (message):** -8
- **app (result):** Correct. / On 9x − 14 = −86 you answered -8.
- **tutor (message):** Correct: x = -8. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That's two check problems solved on your own, so here is one more to try on your own.
- **app (check):** Solve −8x − 5 = −37 for x.
- **student (message):** 4
- **app (result):** Correct. / On −8x − 5 = −37 you answered 4.
- **tutor (message):** Correct: x = 4. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That goal is met for this session, and you solved all three check problems on your own.
- **student (message):** I am not sure how to do this kind.
- **tutor (message):** You finished the two-step equations goal for this session, and the next kind adds parentheses. A good first question to ask yourself: what would remove the parentheses so the equation looks like the two-step ones you just solved? Let's start with one where the factor divides evenly.
- **tutor (problem):** Solve 5(x + 5) = 25 for x.
- **student (message):** 0
- **app (result):** Correct. / On 5(x + 5) = 25 you answered 0.
- **tutor (message):** Correct: x = 0. Dividing both sides by the factor outside the parentheses first, when it divides evenly, leaves a simpler equation to finish. You solved that one on your own, so here is a check problem for this kind of equation.
- **app (check):** Solve 8(x − 8) = 24 for x.
- **student (message):** 11
- **app (result):** Correct. / On 8(x − 8) = 24 you answered 11.
- **tutor (message):** Correct: x = 11. Dividing both sides by the factor outside the parentheses first, when it divides evenly, leaves a simpler equation to finish. That check counted toward this kind of equation, and the goal still needs a step choice and a new kind of equation. Here is a step-choice question for you.
- **app (check):** Here is an equation: 4(x + 3) = −8. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide only the left side by 4: x + 3 = −8 / B) Subtract 3 to undo the 3: 4x = −11 / C) Multiply both terms in the parentheses by 4: 4x + 12 = −8 / D) Multiply only the x by 4: 4x + 3 = −8
- **student (message):** C
- **app (result):** Correct. / On 4(x + 3) = −8 you chose C.
- **tutor (message):** Correct: C is a valid first step. Multiplying every term inside the parentheses by the factor outside is the distributive property, and it keeps the same solution. That check counted toward this kind of equation, and the goal still needs a new kind of equation. Here is one for you.
- **app (check):** Solve 7(x + 8) + 14 = 140 for x.
- **student (message):** 10
- **app (result):** Correct. / On 7(x + 8) + 14 = 140 you answered 10.
- **tutor (message):** Correct: x = 10. When a grouped equation has an extra term outside the parentheses, undoing that term first and then dividing by the factor outside leaves a simpler equation to finish. That check counted toward this kind of equation, and the goal for it is met for this session.
- **student (message):** I am not sure how to do this kind.
- **tutor (message):** You just solved 7(x + 8) + 14 = 140 correctly, so you can already handle that kind. The next kind of equation is one where the parentheses are multiplied by a factor and the expression inside has more than one term with a coefficient, like 3(2x + 5). Before solving those, it helps to be sure you can expand the parentheses. Try this one.
- **tutor (problem):** Expand 2(x + 4). Write your answer without parentheses.
- **student (message):** 2x+8
- **app (result):** Correct. / On 2(x + 4) you answered 2x+8.
- **tutor (message):** Correct: 2x + 8. Multiplying the factor outside the parentheses by each term inside gives the expanded form, and here both terms were multiplied. The goal for this skill still needs a choice question and a new kind of expression, so here is a choice one.
- **app (check):** Which of these expressions are equal to 9(x + 6) for every value of x? Select every one that is. / A) 9x + 54 / B) 9 · x + 9 · 6 / C) 9x + 6 / D) x + 15
- **student (message):** A,B
- **app (result):** Correct. / On 9(x + 6) you chose A and B.
- **tutor (message):** Correct: A and B both equal 9(x + 6) for every value of x. The distributive property says a factor multiplied by a sum equals that factor times each term, added. The goal for this skill still needs a new kind of expression, so try this one.
- **app (check):** Expand 5(3x + 6). Write your answer without parentheses.
- **student (message):** 15x+30
- **app (result):** Correct. / On 5(3x + 6) you answered 15x+30.
- **tutor (message):** Correct: 15x + 30. The distributive property says a factor multiplied by a sum equals that factor times each term, added — here 5 times 3x and 5 times 6. That completes the three kinds of question this skill's goal needs, so the goal for applying the distributive property with positive factors is met for this session.
- **student (message):** I am not sure how to do this kind.
- **tutor (message):** You just expanded 5(3x + 6) correctly, so the multiplying-out part is working. The next kind of equation puts that expansion inside a solve. Take this one step at a time: what do you get when you multiply the factor by each term inside the parentheses?
- **tutor (problem):** Expand 7(x + 2). Write your answer without parentheses.
- **student (message):** 7x+14
- **app (result):** Correct. / On 7(x + 2) you answered 7x+14.
- **tutor (message):** Correct: 7x + 14. The distributive property says a factor multiplied by a sum equals that factor times each term, added. You said the next kind — an expansion inside a solve — was unfamiliar, so let's build that up. First, just the multiplying-out part on a fresh expression.
- **tutor (problem):** Expand 3(x + 1). Write your answer without parentheses.
- **student (message):** 3x+3
- **app (result):** Correct. / On 3(x + 1) you answered 3x+3.
- **tutor (message):** Correct: 3x + 3. The distributive property says a factor multiplied by a sum equals that factor times each term, added. You said the expansion-inside-a-solve kind was unfamiliar, so let's take that next step with a fresh problem.
- **tutor (problem):** Solve −4x + 14 = 10 for x.
- **student (message):** 1
- **app (result):** Correct. / On −4x + 14 = 10 you answered 1.
- **tutor (message):** Correct: x = 1. When you undo operations to isolate x, undo the addition or subtraction first, then the multiplication or division. That goal is met for this session.
- **app (check):** Solve −9x + 5 = 32 for x.
- **student (message):** -3
- **app (result):** Correct. / On −9x + 5 = 32 you answered -3.
- **tutor (message):** Correct: x = -3. When you undo operations to isolate x, undo the addition or subtraction first, then the multiplication or division. That goal is met for this session.
- **student (message):** I am not sure how to do this kind.
- **tutor (message):** You just solved −9x + 5 = 32 correctly, so the two-step kind is working for you. The kind you said is unfamiliar is an equation with parentheses, like a(x + b) = c. The first move there is to deal with the parentheses before undoing anything else. Let's build that step on its own first.
- **tutor (problem):** Expand 4(x + 8). Write your answer without parentheses.
- **student (message):** 4x+32
- **app (result):** Correct. / On 4(x + 8) you answered 4x+32.
- **tutor (message):** Correct: 4x + 32. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. You have two correct in a row on this skill; the goal still needs a choice question and a new kind of expression.
- **app (check):** Which of these expressions are equal to 4(x + 3) for every value of x? Select every one that is. / A) x + 12 / B) 4x + 12 / C) 4x + 3 / D) x + 7
- **student (message):** B
- **app (result):** Correct. / On 4(x + 3) you chose B.
- **tutor (message):** Correct: B, 4x + 12. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. The goal for this skill still needs a new kind of expression, so here is one more to try on your own.
- **app (check):** Expand 5(2x + 3). Write your answer without parentheses.
- **student (message):** 10x+15
- **app (result):** Correct. / On 5(2x + 3) you answered 10x+15.
- **tutor (message):** Correct: 10x + 15. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. That completes the three kinds the goal for this skill needs, so the goal is met for this session.

## Every tutor message, with the answered and open problem it was written for

| Field | Text | Answered problem (attempt) | Open problem |
|---|---|---|---|
| student_message | Let's work on solving two-step equations. Here is one to start with. | — | — |
| student_message | Not yet. Your answer matches adding 1 on the right side, but the −1 needs to be undone. What operation undoes subtracting 1, and what does that do to both sides? | prb_d1d6e660cee3fc22ccdf972a (evt_be5e9f4a1708d5c5ceedbcfa) | — |
| student_message | Not yet. On 9x − 1 = −19, adding 1 to the left side gives 9x, and the right side must get the same treatment. What is −19 + 1? | prb_f0ee8977b0d74494ebf77f22 (evt_43ff679f351e267055cb2520) | — |
| student_message | Not yet. On −19 + 1 you wrote 18, which has the right size but the wrong sign. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. Which direction on the number line does adding 1 take you from −19? | prb_56705f98888d72344f34aa64 (evt_fdb4745e42416df13b3c5f8a) | — |
| student_message | Correct: −14 + 6 = −8. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. Now let's carry that back to the equation. | prb_de66f92520df2d904c5adca7 (evt_6efc8d2706bb0b670d824f4d) | — |
| student_message | Correct: −18 + 3 = −15. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. You've now handled two of these in a row, so let's carry that back to the equation. | prb_e0bb7deedfba743ead1d6b15 (evt_737b943df8b9fcce40871687) | — |
| student_message | Correct: x = 3. When you undo the additive step first and then the multiplicative step, the equation stays balanced at each stage. You've now solved one two-step equation on your own, so let's keep going. | prb_620eeddf7f8620def096b160 (evt_18bee5ac5a00751d35fdb404) | — |
| student_message | Correct: x = −2. When you undo the additive step first and then the multiplicative step, the equation stays balanced at each stage. That's two two-step equations solved on your own, so let's keep going. | prb_f471e3efaf195927f2bbf716 (evt_20ecc4d4cf2f0821a664d4f1) | — |
| student_message | Correct: x = 2. When you undo the additive step first and then the multiplicative step, the equation stays balanced at each stage. That's three two-step equations solved on your own, so let's keep going. | prb_2c07c1234501fd277bb4ea80 (evt_cea99775e7b59bba2b09820f) | — |
| student_message | Correct: x = 1. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. You've solved several two-step equations on your own now, so let's see how you do with a fresh one. | prb_971d238b172f423db243669f (evt_c043e554f8c47ea5917791b4) | — |
| student_message | Correct: x = 10. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That's one check problem solved on your own, so let's keep going. | prb_5366942fd7f59d4f2c247d23 (evt_4ecc7bc240e166a1b78bed89) | — |
| student_message | Correct: x = -8. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That's two check problems solved on your own, so here is one more to try on your own. | prb_997b041a200274be02b5bc28 (evt_b580b85e4e89d46e804a9254) | — |
| student_message | Correct: x = 4. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That goal is met for this session, and you solved all three check problems on your own. | prb_7301209078ab637a52d23fcb (evt_49cd7532988fc8b5833a3e2b) | — |
| student_message | You finished the two-step equations goal for this session, and the next kind adds parentheses. A good first question to ask yourself: what would remove the parentheses so the equation looks like the two-step ones you just solved? Let's start with one where the factor divides evenly. | prb_7301209078ab637a52d23fcb (evt_49cd7532988fc8b5833a3e2b) | — |
| student_message | Correct: x = 0. Dividing both sides by the factor outside the parentheses first, when it divides evenly, leaves a simpler equation to finish. You solved that one on your own, so here is a check problem for this kind of equation. | prb_c90a8d5c09825f47b3451e5a (evt_2720c2195585d49c137598b9) | — |
| student_message | Correct: x = 11. Dividing both sides by the factor outside the parentheses first, when it divides evenly, leaves a simpler equation to finish. That check counted toward this kind of equation, and the goal still needs a step choice and a new kind of equation. Here is a step-choice question for you. | prb_57703ad81aa2d80383d7c77f (evt_f56170fe9b4a2ca5ca19dbc5) | — |
| student_message | Correct: C is a valid first step. Multiplying every term inside the parentheses by the factor outside is the distributive property, and it keeps the same solution. That check counted toward this kind of equation, and the goal still needs a new kind of equation. Here is one for you. | prb_054e96e8d5d95203a0c7775d (evt_02400199d606a66495ad945a) | — |
| student_message | Correct: x = 10. When a grouped equation has an extra term outside the parentheses, undoing that term first and then dividing by the factor outside leaves a simpler equation to finish. That check counted toward this kind of equation, and the goal for it is met for this session. | prb_3cff3fb3dd19653ef5ba7551 (evt_817638f76ea4704e533b4626) | — |
| student_message | You just solved 7(x + 8) + 14 = 140 correctly, so you can already handle that kind. The next kind of equation is one where the parentheses are multiplied by a factor and the expression inside has more than one term with a coefficient, like 3(2x + 5). Before solving those, it helps to be sure you can expand the parentheses. Try this one. | prb_3cff3fb3dd19653ef5ba7551 (evt_817638f76ea4704e533b4626) | — |
| student_message | Correct: 2x + 8. Multiplying the factor outside the parentheses by each term inside gives the expanded form, and here both terms were multiplied. The goal for this skill still needs a choice question and a new kind of expression, so here is a choice one. | prb_81be025d23a6569a4447b3c7 (evt_5662d3998b4356d766e01bfc) | — |
| student_message | Correct: A and B both equal 9(x + 6) for every value of x. The distributive property says a factor multiplied by a sum equals that factor times each term, added. The goal for this skill still needs a new kind of expression, so try this one. | prb_8988adf2a3d2d7835edd8ce8 (evt_6e74ac0a2745f550a61810cb) | — |
| student_message | Correct: 15x + 30. The distributive property says a factor multiplied by a sum equals that factor times each term, added — here 5 times 3x and 5 times 6. That completes the three kinds of question this skill's goal needs, so the goal for applying the distributive property with positive factors is met for this session. | prb_9633587a9dc1c9122b16633e (evt_e3f728d6516693bb6eb05c75) | — |
| student_message | You just expanded 5(3x + 6) correctly, so the multiplying-out part is working. The next kind of equation puts that expansion inside a solve. Take this one step at a time: what do you get when you multiply the factor by each term inside the parentheses? | prb_9633587a9dc1c9122b16633e (evt_e3f728d6516693bb6eb05c75) | — |
| student_message | Correct: 7x + 14. The distributive property says a factor multiplied by a sum equals that factor times each term, added. You said the next kind — an expansion inside a solve — was unfamiliar, so let's build that up. First, just the multiplying-out part on a fresh expression. | prb_25f34d345077456072cfa36a (evt_947bdf21bb9100ab247a2e61) | — |
| student_message | Correct: 3x + 3. The distributive property says a factor multiplied by a sum equals that factor times each term, added. You said the expansion-inside-a-solve kind was unfamiliar, so let's take that next step with a fresh problem. | prb_0cfb87772128c92f51626090 (evt_be4b3866c2ceb7b65d19b460) | — |
| student_message | Correct: x = 1. When you undo operations to isolate x, undo the addition or subtraction first, then the multiplication or division. That goal is met for this session. | prb_e0675b5fbbcca70bbe2eedb6 (evt_d0d2151dc1a37c9123c734ea) | — |
| student_message | Correct: x = -3. When you undo operations to isolate x, undo the addition or subtraction first, then the multiplication or division. That goal is met for this session. | prb_282356aae4fff1204cb026e7 (evt_329331e98078b6cf092af41c) | — |
| student_message | You just solved −9x + 5 = 32 correctly, so the two-step kind is working for you. The kind you said is unfamiliar is an equation with parentheses, like a(x + b) = c. The first move there is to deal with the parentheses before undoing anything else. Let's build that step on its own first. | prb_282356aae4fff1204cb026e7 (evt_329331e98078b6cf092af41c) | — |
| student_message | Correct: 4x + 32. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. You have two correct in a row on this skill; the goal still needs a choice question and a new kind of expression. | prb_9239935fdb08ffa3460b2267 (evt_a03909099ae10c0f54cccdf9) | — |
| student_message | Correct: B, 4x + 12. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. The goal for this skill still needs a new kind of expression, so here is one more to try on your own. | prb_0f20bb72bf71d8bf9f9b43d1 (evt_2358e9d90b49331c156eeba9) | — |
| student_message | Correct: 10x + 15. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. That completes the three kinds the goal for this skill needs, so the goal is met for this session. | prb_84acf80075b78cf4c77244b2 (evt_7b23d88f863946791c4a9369) | — |

## Verdicts recorded by code

| Problem | Purpose | Entry | Read as | Verdict | Help | Slip | Held |
|---|---|---|---|---|---|---|---|
| Solve 9x − 1 = −19 for x. | PRACTICE | -20/9 | -20/9 | INCORRECT |  |  |  |
| Add 1 to both sides of 9x − 1 = −19. Write the equation you get. | PRACTICE | 9x = -17 | 9x = -17 | INCORRECT |  |  |  |
| Calculate −19 + 1. | PRACTICE | 18 | 18 | INCORRECT |  |  |  |
| Calculate −14 + 6. | PRACTICE | -8 | -8 | CORRECT |  |  |  |
| Calculate −18 + 3. | PRACTICE | -15 | -15 | CORRECT |  |  |  |
| Solve −9x − 13 = −40 for x. | PRACTICE | 3 | 3 | CORRECT |  |  |  |
| Solve 9x − 3 = −21 for x. | PRACTICE | -2 | -2 | CORRECT |  |  |  |
| Solve 3x + 14 = 20 for x. | PRACTICE | 2 | 2 | CORRECT |  |  |  |
| Solve −4x − 8 = −12 for x. | PRACTICE | 1 | 1 | CORRECT |  |  |  |
| Solve −4x + 14 = −26 for x. | CHECK | 10 | 10 | CORRECT |  |  |  |
| Solve 9x − 14 = −86 for x. | CHECK | -8 | -8 | CORRECT |  |  |  |
| Solve −8x − 5 = −37 for x. | CHECK | 4 | 4 | CORRECT |  |  |  |
| Solve 5(x + 5) = 25 for x. | PRACTICE | 0 | 0 | CORRECT |  |  |  |
| Solve 8(x − 8) = 24 for x. | CHECK | 11 | 11 | CORRECT |  |  |  |
| Here is an equation: 4(x + 3) = −8. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide only the left side by 4: x + 3 = −8 / B) Subtract 3 to undo the 3: 4x = −11 / C) Multiply both terms in the parentheses by 4: 4x + 12 = −8 / D) Multiply only the x by 4: 4x + 3 = −8 | CHECK | C | C | CORRECT |  |  |  |
| Solve 7(x + 8) + 14 = 140 for x. | CHECK | 10 | 10 | CORRECT |  |  |  |
| Expand 2(x + 4). Write your answer without parentheses. | PRACTICE | 2x+8 | 2x+8 | CORRECT |  |  |  |
| Which of these expressions are equal to 9(x + 6) for every value of x? Select every one that is. / A) 9x + 54 / B) 9 · x + 9 · 6 / C) 9x + 6 / D) x + 15 | CHECK | A,B | A,B | CORRECT |  |  |  |
| Expand 5(3x + 6). Write your answer without parentheses. | CHECK | 15x+30 | 15x+30 | CORRECT |  |  |  |
| Expand 7(x + 2). Write your answer without parentheses. | PRACTICE | 7x+14 | 7x+14 | CORRECT |  |  |  |
| Expand 3(x + 1). Write your answer without parentheses. | PRACTICE | 3x+3 | 3x+3 | CORRECT |  |  |  |
| Solve −4x + 14 = 10 for x. | PRACTICE | 1 | 1 | CORRECT |  |  |  |
| Solve −9x + 5 = 32 for x. | CHECK | -3 | -3 | CORRECT |  |  |  |
| Expand 4(x + 8). Write your answer without parentheses. | PRACTICE | 4x+32 | 4x+32 | CORRECT |  |  |  |
| Which of these expressions are equal to 4(x + 3) for every value of x? Select every one that is. / A) x + 12 / B) 4x + 12 / C) 4x + 3 / D) x + 7 | CHECK | B | B | CORRECT |  |  |  |
| Expand 5(2x + 3). Write your answer without parentheses. | CHECK | 10x+15 | 10x+15 | CORRECT |  |  |  |

## Check answers

| Check problem | Entry | Verdict | Counted as |
|---|---|---|---|
| Solve −4x + 14 = −26 for x. | 10 | CORRECT | counted unassisted |
| Solve 9x − 14 = −86 for x. | -8 | CORRECT | counted unassisted |
| Solve −8x − 5 = −37 for x. | 4 | CORRECT | counted unassisted |
| Solve 8(x − 8) = 24 for x. | 11 | CORRECT | counted unassisted |
| Here is an equation: 4(x + 3) = −8. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide only the left side by 4: x + 3 = −8 / B) Subtract 3 to undo the 3: 4x = −11 / C) Multiply both terms in the parentheses by 4: 4x + 12 = −8 / D) Multiply only the x by 4: 4x + 3 = −8 | C | CORRECT | counted unassisted |
| Solve 7(x + 8) + 14 = 140 for x. | 10 | CORRECT | counted unassisted |
| Which of these expressions are equal to 9(x + 6) for every value of x? Select every one that is. / A) 9x + 54 / B) 9 · x + 9 · 6 / C) 9x + 6 / D) x + 15 | A,B | CORRECT | counted unassisted |
| Expand 5(3x + 6). Write your answer without parentheses. | 15x+30 | CORRECT | counted unassisted |
| Solve −9x + 5 = 32 for x. | -3 | CORRECT | counted unassisted |
| Which of these expressions are equal to 4(x + 3) for every value of x? Select every one that is. / A) x + 12 / B) 4x + 12 / C) 4x + 3 / D) x + 7 | B | CORRECT | counted unassisted |
| Expand 5(2x + 3). Write your answer without parentheses. | 10x+15 | CORRECT | counted unassisted |

## Help on open check problems

- Help moves on open checks: none; with a practice item: 0

## Route (round 10 §4.3; code's record)

- DETOUR N03: Calculate −19 + 1.
- RETURN_TO_SKILL Q06: Solve −9x − 13 = −40 for x.
- Detour to N03: 3 problem(s), 2 correct, 1 not yet; returned to Q06 through practice, then 9 correct and 0 not yet
- Fresh evidence on Q06 after the first return: 4 check answer(s) counted, 4 correct
- Prerequisite answers: 15; not yet before the first success: 1
- Boundary (N18, N03): reached at Calculate −19 + 1. — entry 18; deepest skill posed: N03; moves outside the tutor rejected: 0
  - tutor there: student_message: Not yet. On −19 + 1 you wrote 18, which has the right size but the wrong sign. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. Which direction on the number line does adding 1 take you from −19?
  - tutor there: student_message: Correct: −14 + 6 = −8. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. Now let's carry that back to the equation.
  - tutor there: student_message: Correct: −18 + 3 = −15. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. You've now handled two of these in a row, so let's carry that back to the equation.

## equation_step answers

| Problem | Entry | Verdict |
|---|---|---|
| Add 1 to both sides of 9x − 1 = −19. Write the equation you get. | 9x = -17 | NOT_EQUIVALENT |

## Slip eligibility (every answer code found not yet correct)

| Problem | Purpose | Entry | Eligible for the pause | Signatures | Detail sent to the tutor |
|---|---|---|---|---|---|
| Solve 9x − 1 = −19 for x. | PRACTICE | -20/9 | no | ADD_INSTEAD_OF_SUBTRACT |  |
| Calculate −19 + 1. | PRACTICE | 18 | no | SIGN_FLIP |  |

## Detector signals (logged only; the text was shown unchanged, nothing held)

| Guard | Detail | Field | Text |
|---|---|---|---|
| ANSWER_LEAK | contains the answer to a problem the student has not answered yet | student_message | Correct: −18 + 3 = −15. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. You've now handled two of these in a row, so let's carry that back to the equation. |
| HELP_CLAIM | on your own | student_message | Correct: x = 3. When you undo the additive step first and then the multiplicative step, the equation stays balanced at each stage. You've now solved one two-step equation on your own, so let's keep going. |
| ANSWER_LEAK | contains the answer to a problem the student has not answered yet | student_message | Correct: x = −2. When you undo the additive step first and then the multiplicative step, the equation stays balanced at each stage. That's two two-step equations solved on your own, so let's keep going. |
| HELP_CLAIM | on your own | student_message | Correct: x = −2. When you undo the additive step first and then the multiplicative step, the equation stays balanced at each stage. That's two two-step equations solved on your own, so let's keep going. |
| HELP_CLAIM | on your own | student_message | Correct: x = 2. When you undo the additive step first and then the multiplicative step, the equation stays balanced at each stage. That's three two-step equations solved on your own, so let's keep going. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 8 | student_message | Correct: x = -8. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That's two check problems solved on your own, so here is one more to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try, one more | student_message | Correct: x = -8. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That's two check problems solved on your own, so here is one more to try on your own. |
| MASTERY_OR_GOAL_CLAIM | goal is met | student_message | Correct: x = 4. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That goal is met for this session, and you solved all three check problems on your own. |
| HELP_CLAIM | on your own | student_message | Correct: x = 4. Undoing the additive step first and then the multiplicative step keeps the equation balanced at each stage. That goal is met for this session, and you solved all three check problems on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 11 | student_message | Correct: x = 11. Dividing both sides by the factor outside the parentheses first, when it divides evenly, leaves a simpler equation to finish. That check counted toward this kind of equation, and the goal still needs a step choice and a new kind of equation. Here is a step-choice question for you. |
| MASTERY_OR_GOAL_CLAIM | goal for it is met | student_message | Correct: x = 10. When a grouped equation has an extra term outside the parentheses, undoing that term first and then dividing by the factor outside leaves a simpler equation to finish. That check counted toward this kind of equation, and the goal for it is met for this session. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 6 | student_message | Correct: A and B both equal 9(x + 6) for every value of x. The distributive property says a factor multiplied by a sum equals that factor times each term, added. The goal for this skill still needs a new kind of expression, so try this one. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct: A and B both equal 9(x + 6) for every value of x. The distributive property says a factor multiplied by a sum equals that factor times each term, added. The goal for this skill still needs a new kind of expression, so try this one. |
| MASTERY_OR_GOAL_CLAIM | goal for applying the distributive property with positive factors is met | student_message | Correct: 15x + 30. The distributive property says a factor multiplied by a sum equals that factor times each term, added — here 5 times 3x and 5 times 6. That completes the three kinds of question this skill's goal needs, so the goal for applying the distributive property with positive factors is met for this session. |
| MASTERY_OR_GOAL_CLAIM | goal is met | student_message | Correct: x = 1. When you undo operations to isolate x, undo the addition or subtraction first, then the multiplication or division. That goal is met for this session. |
| CHECK_CARD_PHRASE | directed at the new problem: when you | student_message | Correct: x = 1. When you undo operations to isolate x, undo the addition or subtraction first, then the multiplication or division. That goal is met for this session. |
| MASTERY_OR_GOAL_CLAIM | goal is met | student_message | Correct: x = -3. When you undo operations to isolate x, undo the addition or subtraction first, then the multiplication or division. That goal is met for this session. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 4 | student_message | Correct: 4x + 32. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. You have two correct in a row on this skill; the goal still needs a choice question and a new kind of expression. |
| CHECK_CARD_INVITATION | a neutral invitation: try, one more | student_message | Correct: B, 4x + 12. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. The goal for this skill still needs a new kind of expression, so here is one more to try on your own. |
| MASTERY_OR_GOAL_CLAIM | goal is met, goal for this skill needs, so the goal is met | student_message | Correct: 10x + 15. The distributive property says a(b + c) = ab + ac, so the factor multiplies every term inside the parentheses. That completes the three kinds the goal for this skill needs, so the goal is met for this session. |

## Counters (logged only)

None.

## Repairs (rejected output, never shown)

| Call | Attempt | Cause | Errors | Rejected student_message |
|---|---|---|---|---|
| REPLY | 0 | item: original problem | item: source original is valid only when ORIGINAL_PROBLEM is not null, with purpose practice | Correct: −18 + 3 = −15. When you add a positive number to a negative number, the result moves toward zero, so it stays negative here. You've now handled two of these in a row, so let's carry that back to the equation. |
| REPLY | 0 | item: prepared problem reference | item: recipe R3 is a Q06 problem, not Q07 | Correct: 3x + 3. The distributive property says a factor multiplied by a sum equals that factor times each term, added. You said the expansion-inside-a-solve kind was unfamiliar, so let's take that next step with a fresh problem. |

## Holds

None.

## Readings


## Tutor moves

- POSE_PROBLEM → Q06 (from Q06): Start the session with a two-step equation to see the student's current approach.
- STEP_BACK → Q06 (from Q06): Isolate the additive step of the two-step equation before dividing.
- HINT → Q06 (from Q06): Isolate the additive step: student added 1 to the left but not correctly to the right; practice the right-side sum.
- STEP_BACK → N03 (from Q06): Signed addition error blocks the additive step of the two-step equation; practise signed addition before returning to Q06.
- CONTINUE → N03 (from N03): Student answered a signed-addition practice problem correctly; continue building signed-addition accuracy before returning to the two-step equation.
- CONTINUE → Q06 (from N03): Signed-addition detour has produced two correct results; return to the two-step equation skill with a fresh problem.
- POSE_PROBLEM → Q06 (from Q06): Correct independent solve on the return to two-step equations; continue practice at the target skill.
- POSE_PROBLEM → Q06 (from Q06): Continue practice on two-step equations after a correct independent solve.
- POSE_PROBLEM → Q06 (from Q06): Third consecutive correct two-step equation; continue practice on the same skill.
- MOVE_ON → Q06 (from Q06): Student solved the practice two-step equation correctly; ready for an independent check problem.
- POSE_PROBLEM → Q06 (from Q06): Check problem answered correctly without help; continue the goal run with a fresh two-step equation check.
- POSE_PROBLEM → Q06 (from Q06): Two check problems correct without help; posing a third fresh check to complete the goal run.
- MOVE_ON → Q07 (from Q06): Q06 goal met for this session with three unassisted check problems; ready to move to equations with parentheses.
- POSE_PROBLEM → Q07 (from Q07): Student says they don't know how to do equations with parentheses; begin Q07 with a constructed solve where division by the factor is clean.
- POSE_PROBLEM → Q07 (from Q07): Student solved a grouped equation correctly; pose a fresh check problem on the same skill.
- POSE_PROBLEM → Q07 (from Q07): Check problem correct; goal needs a step choice and a new form, so pose the prepared choice check.
- POSE_PROBLEM → Q07 (from Q07): Step-choice check correct; goal still needs the new-form variant, so pose the transfer check problem.
- MOVE_ON → E06 (from Q07): Q07 goal met for this session; the distributive property is a prerequisite of Q07 and its goal is still open.
- STEP_BACK → E06 (from E06): Student says they are unsure how to solve the new kind of grouped equation; step back to expanding parentheses, a prerequisite of Q07.
- POSE_PROBLEM → E06 (from E06): Expansion correct; goal still needs a choice question and a new form, so pose the choice check.
- POSE_PROBLEM → E06 (from E06): Choice check correct; goal still needs a new-form expression, so pose the transfer check.
- MOVE_ON → Q07 (from E06): E06 goal met this session; return to the original target of solving equations containing parentheses.
- STEP_BACK → E06 (from Q07): Student says they are unsure how to do this kind; step back to a fresh expansion to confirm the distributive step before returning to solving equations with parentheses.
- POSE_PROBLEM → E06 (from E06): Student flagged the grouped-solve kind as unfamiliar; practice the expansion step before returning to Q07.
- POSE_PROBLEM → Q06 (from E06): Expansion is solid; move to solving a two-step equation, the kind the student flagged as unfamiliar.
- MOVE_ON → Q06 (from Q06): Q06 goal met this session; offering a fresh check problem to confirm independent performance.
- MOVE_ON → E06 (from Q06): Q06 goal met this session; E06 still needs an expansion check to complete its goal.
- STEP_BACK → E06 (from E06): Student reports the parentheses kind is unfamiliar; isolate the expansion step that grouped equations require before returning to Q07.
- POSE_PROBLEM → E06 (from E06): Expansion correct; goal still needs a choice question, so pose the prepared choice check.
- POSE_PROBLEM → E06 (from E06): Choice check correct; goal still needs a new-form expansion, so pose a fresh check of that kind.
- MOVE_ON → Q07 (from E06): E06 goal met this session; return to the original target of solving equations containing parentheses, which builds on this expansion step.

## Model calls

| # | Purpose | Thinking | Effort | Outcome | Finish | ms | Prompt (cached) | Completion (reasoning) |
|---|---|---|---|---|---|---|---|---|
| 1 | BINDING | disabled |  | ACCEPTED | stop | 1209 | 6012 (5760) | 28 (0) |
| 2 | REPLY | disabled |  | ACCEPTED | stop | 1749 | 9343 (8064) | 138 (0) |
| 3 | REPLY | disabled |  | ACCEPTED | stop | 2730 | 9857 (6912) | 343 (0) |
| 4 | REPLY | disabled |  | ACCEPTED | stop | 2957 | 10064 (6912) | 372 (0) |
| 5 | REPLY | disabled |  | ACCEPTED | stop | 2646 | 10379 (6912) | 356 (0) |
| 6 | REPLY | disabled |  | ACCEPTED | stop | 2907 | 10563 (6912) | 348 (0) |
| 7 | REPLY | disabled |  | REJECTED | stop | 3020 | 10611 (6912) | 413 (0) |
| 8 | REPAIR | disabled |  | ACCEPTED_AFTER_REPAIR | stop | 2500 | 11122 (10880) | 410 (0) |
| 9 | REPLY | disabled |  | ACCEPTED | stop | 2352 | 10694 (6912) | 242 (0) |
| 10 | REPLY | disabled |  | ACCEPTED | stop | 2591 | 10690 (6912) | 326 (0) |
| 11 | REPLY | disabled |  | ACCEPTED | stop | 2231 | 10676 (6912) | 247 (0) |
| 12 | REPLY | disabled |  | ACCEPTED | stop | 2161 | 10659 (6912) | 247 (0) |
| 13 | REPLY | disabled |  | ACCEPTED | stop | 2109 | 10703 (6912) | 245 (0) |
| 14 | REPLY | disabled |  | ACCEPTED | stop | 2250 | 10706 (6912) | 254 (0) |
| 15 | REPLY | disabled |  | ACCEPTED | stop | 2502 | 10719 (6912) | 260 (0) |
| 16 | REPLY | disabled |  | ACCEPTED | stop | 3097 | 10968 (6912) | 377 (0) |
| 17 | REPLY | disabled |  | ACCEPTED | stop | 2397 | 11135 (6912) | 253 (0) |
| 18 | REPLY | disabled |  | ACCEPTED | stop | 2399 | 11166 (6912) | 272 (0) |
| 19 | REPLY | disabled |  | ACCEPTED | stop | 2412 | 11485 (6912) | 268 (0) |
| 20 | REPLY | disabled |  | ACCEPTED | stop | 2499 | 11407 (6912) | 291 (0) |
| 21 | REPLY | disabled |  | ACCEPTED | stop | 4198 | 11232 (6912) | 367 (0) |
| 22 | REPLY | disabled |  | ACCEPTED | stop | 2335 | 11322 (6912) | 266 (0) |
| 23 | REPLY | disabled |  | ACCEPTED | stop | 2684 | 11487 (6912) | 257 (0) |
| 24 | REPLY | disabled |  | ACCEPTED | stop | 2632 | 11446 (6912) | 344 (0) |
| 25 | REPLY | disabled |  | ACCEPTED | stop | 2810 | 11347 (6912) | 326 (0) |
| 26 | REPLY | disabled |  | ACCEPTED | stop | 2960 | 11375 (6912) | 329 (0) |
| 27 | REPLY | disabled |  | REJECTED | stop | 2724 | 11280 (6912) | 369 (0) |
| 28 | REPAIR | disabled |  | ACCEPTED_AFTER_REPAIR | stop | 2238 | 11740 (11520) | 368 (0) |
| 29 | REPLY | disabled |  | ACCEPTED | stop | 1982 | 11103 (6912) | 237 (0) |
| 30 | REPLY | disabled |  | ACCEPTED | stop | 1949 | 11069 (6912) | 217 (0) |
| 31 | REPLY | disabled |  | ACCEPTED | stop | 3081 | 11066 (6912) | 347 (0) |
| 32 | REPLY | disabled |  | ACCEPTED | stop | 2333 | 11153 (6912) | 257 (0) |
| 33 | REPLY | disabled |  | ACCEPTED | stop | 2787 | 11355 (6912) | 298 (0) |
| 34 | REPLY | disabled |  | ACCEPTED | stop | 2782 | 11321 (6912) | 331 (0) |
