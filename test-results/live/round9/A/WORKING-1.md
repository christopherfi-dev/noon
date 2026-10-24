# WORKING-1 — LIVE openai_compatible / deepseek-flash, provider_thinking disabled

| # | Learner input | Calls (purpose: reasoning setting) | Outcomes | Wait ms |
|---|---|---|---|---|
| 0 | (start on Q06) | 2 — BINDING: disabled (binding call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 2877 |
| 1 | I took away 1 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4154 |
| 2 | My answer is 3x = -18. Why does that step work? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3853 |
| 3 | -6 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2287 |
| 4 | I took away 11 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4177 |
| 5 | My answer is -6. Why does that step work? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3815 |
| 6 | -3 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2683 |
| 7 | I took away 2 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3504 |
| 8 | My answer is -4. Why does that step work? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4484 |
| 9 | ok, what next? | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2685 |
| 10 | 4 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2500 |
| 11 | B,C | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2244 |
| 12 | I took away 7 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4269 |
| 13 | My answer is 6. Why does that step work? | 3 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call); REPAIR: disabled (configured: disabled on every call) | ACCEPTED, REJECTED, ACCEPTED_AFTER_REPAIR | 6267 |
| 14 | -8 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2106 |
| 15 | I took away 4 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3658 |
| 16 | My answer is 5. Why does that step work? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3588 |
| 17 | C | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2537 |
| 18 | 4 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2152 |
| 19 | I took away 5 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3671 |
| 20 | My answer is -8. Why does that step work? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4165 |
| 21 | 1 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2460 |
| 22 | A,C | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2337 |
| 23 | I took away 8 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4302 |
| 24 | My answer is 12. Why does that step work? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3755 |
| 25 | 3 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2589 |
| 26 | A,C | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2196 |
| 27 | I took away 2 from both sides first | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4038 |
| 28 | My answer is -1. Why does that step work? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 4054 |
| 29 | ok, what next? | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2432 |
| 30 | -5 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2210 |

## Conversation as the student saw it (the last 80 lines the session keeps)

- **student (message):** I took away 2 from both sides first
- **tutor (message):** You're describing a plan for −7x + 2 = 30, and thinking through the order before computing is worth doing. Look at the left side: the 2 is attached to the x-term by addition. What operation undoes adding 2? Apply it to both sides and tell me what the equation looks like after that one step.
- **student (message):** My answer is -4. Why does that step work?
- **app (result):** Correct. / On −7x + 2 = 30 you answered My answer is -4. Why does that step wor…. / Read as -4.
- **tutor (message):** Correct — x = −4 solves −7x + 2 = 30. You asked why that step works: subtracting the same number from both sides keeps the two sides equal, and dividing both sides by the same nonzero number keeps them equal too, so undoing the addition of 2 and then the multiplication by −7 leaves x alone. That check counted toward your goal for solving two-step equations, and the goal for that skill is now met for this session.
- **student (message):** ok, what next?
- **tutor (message):** Good — x = −4 solves −7x + 2 = 30, and your plan of undoing the addition first was the right order. The goal for solving two-step equations is met for this session. Next, a skill that builds on it: equations with parentheses. Here is one to try on your own.
- **app (check):** Solve 3(x + 2) = 18 for x.
- **student (message):** 4
- **app (result):** Correct. / On 3(x + 2) = 18 you answered 4.
- **tutor (message):** Correct — x = 4 solves 3(x + 2) = 18. Distributing a factor over a sum multiplies every term inside the parentheses, and dividing both sides by the same nonzero number keeps the sides equal. Your goal for equations with parentheses still needs a step choice and a new kind of equation, so here is a step-choice question to try on your own.
- **app (check):** Here is an equation: 7(x − 6) = −28. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 6 to undo the −6: 7x = −22 / B) Divide both sides by 7: x − 6 = −4 / C) Multiply both terms in the parentheses by 7: 7x − 42 = −28 / D) Multiply only the x by 7: 7x − 6 = −28
- **student (message):** B,C
- **app (result):** Correct. / On 7(x − 6) = −28 you chose B and C.
- **tutor (message):** Correct — B and C both give equations with exactly the same solution as 7(x − 6) = −28. Dividing both sides by the same nonzero number keeps the sides equal, and multiplying every term inside the parentheses by the factor keeps the expression equal. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 70 = 7(x + 4) for x.
- **student (message):** I took away 7 from both sides first
- **tutor (message):** You're describing a plan for the open problem, not an answer to it yet — and subtracting 7 from both sides won't undo the 7 that's multiplying the group (x + 4). Look at the structure: 7 times a quantity equals 70. What single operation would undo that multiplication and leave (x + 4) by itself on one side?
- **student (message):** My answer is 6. Why does that step work?
- **app (result):** Correct. / On 70 = 7(x + 4) you answered My answer is 6. Why does that step work?. / Read as 6.
- **tutor (message):** Correct — x = 6 solves 70 = 7(x + 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, so 70 ÷ 7 = 10 leaves x + 4 = 10, and subtracting 4 from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 3(x − 6) = −42 for x.
- **student (message):** -8
- **app (result):** Correct. / On 3(x − 6) = −42 you answered -8.
- **tutor (message):** Correct — x = -8 solves 3(x − 6) = −42. Dividing both sides by the same nonzero number keeps the sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 6 = 6(x − 4) for x.
- **student (message):** I took away 4 from both sides first
- **tutor (message):** You're describing a plan for the open problem, not an answer to it yet — and subtracting 4 from both sides won't undo the 4 that's inside the parentheses. Look at the structure: 6 times the quantity (x − 4) equals 6. What single operation would undo that multiplication and leave (x − 4) by itself on one side?
- **student (message):** My answer is 5. Why does that step work?
- **app (result):** Correct. / On 6 = 6(x − 4) you answered My answer is 5. Why does that step work?. / Read as 5.
- **tutor (message):** Correct — x = 5 solves 6 = 6(x − 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Here is an equation: 2(x + 1) = −14. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply only the x by 2: 2x + 1 = −14 / B) Subtract 1 to undo the 1: 2x = −15 / C) Multiply both terms in the parentheses by 2: 2x + 2 = −14 / D) Divide only the left side by 2: x + 1 = −14
- **student (message):** C
- **app (result):** Correct. / On 2(x + 1) = −14 you chose C.
- **tutor (message):** Correct — C is a valid first step: multiplying both terms inside the parentheses by 2 gives 2x + 2 = −14, which has the same solution as the original equation. The distributive property lets a factor multiply every term in a sum without changing the value. Your goal for equations with parentheses still needs a solve and a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 8(x − 7) = −24 for x.
- **student (message):** 4
- **app (result):** Correct. / On 8(x − 7) = −24 you answered 4.
- **tutor (message):** Correct — x = 4 solves 8(x − 7) = −24. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve −30 = 5(x + 2) for x.
- **student (message):** I took away 5 from both sides first
- **tutor (message):** You said you took away 5 from both sides first. Look at the left side, −30, and the 5 on the right: subtracting 5 from both sides would give −35 = 5(x + 2) − 5, which doesn't simplify the right side. What operation on both sides would undo the 5 that is multiplying (x + 2)?
- **student (message):** My answer is -8. Why does that step work?
- **app (result):** Correct. / On −30 = 5(x + 2) you answered My answer is -8. Why does that step wor…. / Read as -8.
- **tutor (message):** Correct — x = −8 solves −30 = 5(x + 2). Dividing both sides by the same nonzero number keeps the two sides equal, and subtracting the same number from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 2(x − 8) = −14 for x.
- **student (message):** 1
- **app (result):** Correct. / On 2(x − 8) = −14 you answered 1.
- **tutor (message):** Correct — x = 1 solves 2(x − 8) = −14. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own.
- **app (check):** Here is an equation: 2(x − 1) = 2. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply both terms in the parentheses by 2: 2x − 2 = 2 / B) Add 1 to undo the −1: 2x = 3 / C) Divide both sides by 2: x − 1 = 1 / D) Divide only the left side by 2: x − 1 = 2
- **student (message):** A,C
- **app (result):** Correct. / On 2(x − 1) = 2 you chose A and C.
- **tutor (message):** Correct — A and C both give equations with exactly the same solution as 2(x − 1) = 2. Multiplying every term inside the parentheses by the same factor, or dividing both sides by the same nonzero number, keeps the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 4(x + 8) − 6 = 74 for x.
- **student (message):** I took away 8 from both sides first
- **tutor (message):** You're working on 4(x + 8) − 6 = 74. Taking 8 away from both sides doesn't undo anything here, because the 8 is inside the parentheses and multiplied by 4 — it isn't a separate term you can remove. What would you do first to deal with the 4 multiplying the group (x + 8)?
- **student (message):** My answer is 12. Why does that step work?
- **app (result):** Correct. / On 4(x + 8) − 6 = 74 you answered My answer is 12. Why does that step wor…. / Read as 12.
- **tutor (message):** Correct — x = 12 solves 4(x + 8) − 6 = 74. You asked why that step works: multiplying every term inside the parentheses by the same factor, and adding or subtracting the same number on both sides, each keep the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 4(x − 6) = −12 for x.
- **student (message):** 3
- **app (result):** Correct. / On 4(x − 6) = −12 you answered 3.
- **tutor (message):** Correct — x = 3 solves 4(x − 6) = −12. Dividing both sides by the same nonzero number, then adding the same number to both sides, keeps the solution set unchanged. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own.
- **app (check):** Here is an equation: 6(x − 3) = −30. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide both sides by 6: x − 3 = −5 / B) Multiply only the x by 6: 6x − 3 = −30 / C) Multiply both terms in the parentheses by 6: 6x − 18 = −30 / D) Add 3 to undo the −3: 6x = −27
- **student (message):** A,C
- **app (result):** Correct. / On 6(x − 3) = −30 you chose A and C.
- **tutor (message):** Correct — A and C both give equations with exactly the same solution as 6(x − 3) = −30. Dividing both sides by the same nonzero number, or multiplying every term inside the parentheses by the same factor, keeps the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own.
- **app (check):** Solve 9 = 9(x + 2) for x.
- **student (message):** I took away 2 from both sides first
- **tutor (message):** You're describing a step on the open problem, and I can't take a step for you there — that one is yours to finish on your own. What you wrote is a plan, not an answer, so nothing is scored yet. Go ahead and carry it through and enter your value for x.
- **student (message):** My answer is -1. Why does that step work?
- **app (result):** Correct. / On 9 = 9(x + 2) you answered My answer is -1. Why does that step wor…. / Read as -1.
- **tutor (message):** Correct — x = -1 solves 9 = 9(x + 2). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the solution set unchanged, and so does subtracting the same number from both sides. Your goal for equations with parentheses is met for this session.
- **student (message):** ok, what next?
- **tutor (message):** Your goal for equations with parentheses is met for this session. The next skill in line is two-step equations, where you undo the additive and multiplicative structure in a valid order. Here is one to try on your own.
- **app (check):** Solve 3x + 2 = −13 for x.
- **student (message):** -5
- **app (result):** Correct. / On 3x + 2 = −13 you answered -5.
- **tutor (message):** Correct — x = -5 solves 3x + 2 = −13. Undoing the addition first and then the multiplication by the same nonzero number on both sides keeps the solution set unchanged. Here is another one to try on your own.
- **app (check):** Solve −8x − 6 = −38 for x.

## Every tutor message, with the answered and open problem it was written for

| Field | Text | Answered problem (attempt) | Open problem |
|---|---|---|---|
| student_message | Let's work on solving two-step equations. Here's one to start with — take it one step at a time, and tell me what you get for x. | — | — |
| student_message | You said you took away 1 from both sides. Look at the left side, 3x − 1: the number attached to the x by subtraction is 1, so subtracting 1 again would not undo it. What operation undoes subtracting 1? Try that same operation on both sides, and tell me what the equation looks like after that one step. | — | prb_42dc0520e590fa9e57e11c4b practice |
| student_message | Correct — adding 1 to both sides of 3x − 1 = −19 gives 3x = −18. That step works because adding the same number to both sides of an equation keeps the two sides equal, and adding 1 undoes the subtraction of 1 on the left. Now finish the job: solve 3x = −18 for x. | prb_fbacdc6bd9406c96d3fa3cd5 (evt_b04f3e921efd3257bb2a4cc7) | — |
| student_message | Correct — x = −6 solves 3x = −18. Dividing both sides of an equation by the same nonzero number keeps the two sides equal, and dividing by 3 undoes the multiplication by 3. You've now handled both steps of a two-step equation, so let's try a fresh one on your own. | prb_ba568f65caf0852201ecd0ae (evt_dc9752d8f550905ac097ca2b) | — |
| student_message | You're describing a plan for 7x − 11 = −53, and that's a good thing to think through before computing. Look at the left side: the 11 is attached to the x by subtraction. What operation undoes subtracting 11? Whatever you choose, apply it to both sides and tell me what the equation looks like after that one step. | prb_ba568f65caf0852201ecd0ae (evt_dc9752d8f550905ac097ca2b) | prb_c610be6a59702dd677e82243 check |
| student_message | Correct — x = −6 solves 7x − 11 = −53. That step works because adding the same number to both sides of an equation keeps the two sides equal, and adding 11 undoes the subtraction of 11 on the left. Here is another one to try on your own. | prb_c610be6a59702dd677e82243 (evt_568880e4491af7b5fde4bd16) | — |
| student_message | Correct — x = −3 solves −5x − 11 = 4. Adding the same number to both sides keeps the two sides equal, and dividing both sides by the same nonzero number keeps them equal too, which is how the additive and multiplicative structure gets undone. Here is another one to try on your own. | prb_5b7c47101e4e00933d971d94 (evt_2ed7da6a7a3a94af6e4b5cef) | — |
| student_message | You're describing a plan for −7x + 2 = 30, and thinking through the order before computing is worth doing. Look at the left side: the 2 is attached to the x-term by addition. What operation undoes adding 2? Apply it to both sides and tell me what the equation looks like after that one step. | prb_5b7c47101e4e00933d971d94 (evt_2ed7da6a7a3a94af6e4b5cef) | prb_e98281913302d7f766548fcc check |
| student_message | Correct — x = −4 solves −7x + 2 = 30. You asked why that step works: subtracting the same number from both sides keeps the two sides equal, and dividing both sides by the same nonzero number keeps them equal too, so undoing the addition of 2 and then the multiplication by −7 leaves x alone. That check counted toward your goal for solving two-step equations, and the goal for that skill is now met for this session. | prb_e98281913302d7f766548fcc (evt_c24dc1cd85dba3ff20fa36d8) | — |
| student_message | Good — x = −4 solves −7x + 2 = 30, and your plan of undoing the addition first was the right order. The goal for solving two-step equations is met for this session. Next, a skill that builds on it: equations with parentheses. Here is one to try on your own. | prb_e98281913302d7f766548fcc (evt_c24dc1cd85dba3ff20fa36d8) | — |
| student_message | Correct — x = 4 solves 3(x + 2) = 18. Distributing a factor over a sum multiplies every term inside the parentheses, and dividing both sides by the same nonzero number keeps the sides equal. Your goal for equations with parentheses still needs a step choice and a new kind of equation, so here is a step-choice question to try on your own. | prb_30c1956f79817a041443ba33 (evt_e0f34f92f81ef8dbffa76f5c) | — |
| student_message | Correct — B and C both give equations with exactly the same solution as 7(x − 6) = −28. Dividing both sides by the same nonzero number keeps the sides equal, and multiplying every term inside the parentheses by the factor keeps the expression equal. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_b64c41dbb41b9b217c41ea8d (evt_28690f31450230c0b8abd1f7) | — |
| student_message | You're describing a plan for the open problem, not an answer to it yet — and subtracting 7 from both sides won't undo the 7 that's multiplying the group (x + 4). Look at the structure: 7 times a quantity equals 70. What single operation would undo that multiplication and leave (x + 4) by itself on one side? | prb_b64c41dbb41b9b217c41ea8d (evt_28690f31450230c0b8abd1f7) | prb_195139d843d19fbf380fb22d check |
| student_message | Correct — x = 6 solves 70 = 7(x + 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, so 70 ÷ 7 = 10 leaves x + 4 = 10, and subtracting 4 from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_195139d843d19fbf380fb22d (evt_39a45e2a8d6d90935a3b272f) | — |
| student_message | Correct — x = -8 solves 3(x − 6) = −42. Dividing both sides by the same nonzero number keeps the sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_c111b82a4a39c13c8d812013 (evt_1cdbd73e584d11ea271523cb) | — |
| student_message | You're describing a plan for the open problem, not an answer to it yet — and subtracting 4 from both sides won't undo the 4 that's inside the parentheses. Look at the structure: 6 times the quantity (x − 4) equals 6. What single operation would undo that multiplication and leave (x − 4) by itself on one side? | prb_c111b82a4a39c13c8d812013 (evt_1cdbd73e584d11ea271523cb) | prb_dfeaf57fd5960a53372aa381 check |
| student_message | Correct — x = 5 solves 6 = 6(x − 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_dfeaf57fd5960a53372aa381 (evt_b172b4dd16678962d227ef38) | — |
| student_message | Correct — C is a valid first step: multiplying both terms inside the parentheses by 2 gives 2x + 2 = −14, which has the same solution as the original equation. The distributive property lets a factor multiply every term in a sum without changing the value. Your goal for equations with parentheses still needs a solve and a new kind of equation, so here is one to try on your own. | prb_f76a4e87cdc4f83de589eba2 (evt_977c42122c6bd82edcb8578e) | — |
| student_message | Correct — x = 4 solves 8(x − 7) = −24. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_7018cd03d8476e7bbaa239ca (evt_ffaade936faddb69bc1bfaa8) | — |
| student_message | You said you took away 5 from both sides first. Look at the left side, −30, and the 5 on the right: subtracting 5 from both sides would give −35 = 5(x + 2) − 5, which doesn't simplify the right side. What operation on both sides would undo the 5 that is multiplying (x + 2)? | prb_7018cd03d8476e7bbaa239ca (evt_ffaade936faddb69bc1bfaa8) | prb_ec887a0d63e3d9fdb8310093 check |
| student_message | Correct — x = −8 solves −30 = 5(x + 2). Dividing both sides by the same nonzero number keeps the two sides equal, and subtracting the same number from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_ec887a0d63e3d9fdb8310093 (evt_32edaa25f0e8b263cf3901d6) | — |
| student_message | Correct — x = 1 solves 2(x − 8) = −14. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own. | prb_6269e0d92d8173af497720e7 (evt_c4bcf45337a8fb1dcd2b3b23) | — |
| student_message | Correct — A and C both give equations with exactly the same solution as 2(x − 1) = 2. Multiplying every term inside the parentheses by the same factor, or dividing both sides by the same nonzero number, keeps the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_83e34040861bb7087713b453 (evt_6dd5e4a6d94c64f0e4e7ae89) | — |
| student_message | You're working on 4(x + 8) − 6 = 74. Taking 8 away from both sides doesn't undo anything here, because the 8 is inside the parentheses and multiplied by 4 — it isn't a separate term you can remove. What would you do first to deal with the 4 multiplying the group (x + 8)? | prb_83e34040861bb7087713b453 (evt_6dd5e4a6d94c64f0e4e7ae89) | prb_9bb91c3f03839a01491da18e check |
| student_message | Correct — x = 12 solves 4(x + 8) − 6 = 74. You asked why that step works: multiplying every term inside the parentheses by the same factor, and adding or subtracting the same number on both sides, each keep the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_9bb91c3f03839a01491da18e (evt_0d9cf85229595d27a5092300) | — |
| student_message | Correct — x = 3 solves 4(x − 6) = −12. Dividing both sides by the same nonzero number, then adding the same number to both sides, keeps the solution set unchanged. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own. | prb_7e077e10897cff1e2b43ed9c (evt_8f6f246ce07bc94453d187dd) | — |
| student_message | Correct — A and C both give equations with exactly the same solution as 6(x − 3) = −30. Dividing both sides by the same nonzero number, or multiplying every term inside the parentheses by the same factor, keeps the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. | prb_96c139d246162954483fab3c (evt_eede414fb3de5ce20f73196b) | — |
| student_message | You're describing a step on the open problem, and I can't take a step for you there — that one is yours to finish on your own. What you wrote is a plan, not an answer, so nothing is scored yet. Go ahead and carry it through and enter your value for x. | prb_96c139d246162954483fab3c (evt_eede414fb3de5ce20f73196b) | prb_90cb76643fdc7570bb207060 check |
| student_message | Correct — x = -1 solves 9 = 9(x + 2). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the solution set unchanged, and so does subtracting the same number from both sides. Your goal for equations with parentheses is met for this session. | prb_90cb76643fdc7570bb207060 (evt_bf210739b5210206a3909c13) | — |
| student_message | Your goal for equations with parentheses is met for this session. The next skill in line is two-step equations, where you undo the additive and multiplicative structure in a valid order. Here is one to try on your own. | prb_90cb76643fdc7570bb207060 (evt_bf210739b5210206a3909c13) | — |
| student_message | Correct — x = -5 solves 3x + 2 = −13. Undoing the addition first and then the multiplication by the same nonzero number on both sides keeps the solution set unchanged. Here is another one to try on your own. | prb_b1fe2a218ec4aa4dae081b1f (evt_645d31e0929f0aa26f450a4f) | — |

## Verdicts recorded by code

| Problem | Purpose | Entry | Read as | Verdict | Help | Slip | Held |
|---|---|---|---|---|---|---|---|
| Add 1 to both sides of 3x − 1 = −19. Write the equation you get. | PRACTICE | My answer is 3x = -18. Why does that step work? | 3*x = -18 | CORRECT |  |  |  |
| Solve 3x = −18 for x. | PRACTICE | -6 | -6 | CORRECT |  |  |  |
| Solve 7x − 11 = −53 for x. | CHECK | My answer is -6. Why does that step work? | -6 | CORRECT | HINT |  |  |
| Solve −5x − 11 = 4 for x. | CHECK | -3 | -3 | CORRECT |  |  |  |
| Solve −7x + 2 = 30 for x. | CHECK | My answer is -4. Why does that step work? | -4 | CORRECT | HINT |  |  |
| Solve 3(x + 2) = 18 for x. | CHECK | 4 | 4 | CORRECT |  |  |  |
| Here is an equation: 7(x − 6) = −28. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 6 to undo the −6: 7x = −22 / B) Divide both sides by 7: x − 6 = −4 / C) Multiply both terms in the parentheses by 7: 7x − 42 = −28 / D) Multiply only the x by 7: 7x − 6 = −28 | CHECK | B,C | B,C | CORRECT |  |  |  |
| Solve 70 = 7(x + 4) for x. | CHECK | My answer is 6. Why does that step work? | 6 | CORRECT | HINT |  |  |
| Solve 3(x − 6) = −42 for x. | CHECK | -8 | -8 | CORRECT |  |  |  |
| Solve 6 = 6(x − 4) for x. | CHECK | My answer is 5. Why does that step work? | 5 | CORRECT | HINT |  |  |
| Here is an equation: 2(x + 1) = −14. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply only the x by 2: 2x + 1 = −14 / B) Subtract 1 to undo the 1: 2x = −15 / C) Multiply both terms in the parentheses by 2: 2x + 2 = −14 / D) Divide only the left side by 2: x + 1 = −14 | CHECK | C | C | CORRECT |  |  |  |
| Solve 8(x − 7) = −24 for x. | CHECK | 4 | 4 | CORRECT |  |  |  |
| Solve −30 = 5(x + 2) for x. | CHECK | My answer is -8. Why does that step work? | -8 | CORRECT | HINT |  |  |
| Solve 2(x − 8) = −14 for x. | CHECK | 1 | 1 | CORRECT |  |  |  |
| Here is an equation: 2(x − 1) = 2. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply both terms in the parentheses by 2: 2x − 2 = 2 / B) Add 1 to undo the −1: 2x = 3 / C) Divide both sides by 2: x − 1 = 1 / D) Divide only the left side by 2: x − 1 = 2 | CHECK | A,C | A,C | CORRECT |  |  |  |
| Solve 4(x + 8) − 6 = 74 for x. | CHECK | My answer is 12. Why does that step work? | 12 | CORRECT | HINT |  |  |
| Solve 4(x − 6) = −12 for x. | CHECK | 3 | 3 | CORRECT |  |  |  |
| Here is an equation: 6(x − 3) = −30. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide both sides by 6: x − 3 = −5 / B) Multiply only the x by 6: 6x − 3 = −30 / C) Multiply both terms in the parentheses by 6: 6x − 18 = −30 / D) Add 3 to undo the −3: 6x = −27 | CHECK | A,C | A,C | CORRECT |  |  |  |
| Solve 9 = 9(x + 2) for x. | CHECK | My answer is -1. Why does that step work? | -1 | CORRECT |  |  |  |
| Solve 3x + 2 = −13 for x. | CHECK | -5 | -5 | CORRECT |  |  |  |

## Check answers

| Check problem | Entry | Verdict | Counted as |
|---|---|---|---|
| Solve 7x − 11 = −53 for x. | My answer is -6. Why does that step work? | CORRECT | with help: tutor move HINT |
| Solve −5x − 11 = 4 for x. | -3 | CORRECT | counted unassisted |
| Solve −7x + 2 = 30 for x. | My answer is -4. Why does that step work? | CORRECT | with help: tutor move HINT |
| Solve 3(x + 2) = 18 for x. | 4 | CORRECT | counted unassisted |
| Here is an equation: 7(x − 6) = −28. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 6 to undo the −6: 7x = −22 / B) Divide both sides by 7: x − 6 = −4 / C) Multiply both terms in the parentheses by 7: 7x − 42 = −28 / D) Multiply only the x by 7: 7x − 6 = −28 | B,C | CORRECT | counted unassisted |
| Solve 70 = 7(x + 4) for x. | My answer is 6. Why does that step work? | CORRECT | with help: tutor move HINT |
| Solve 3(x − 6) = −42 for x. | -8 | CORRECT | counted unassisted |
| Solve 6 = 6(x − 4) for x. | My answer is 5. Why does that step work? | CORRECT | with help: tutor move HINT |
| Here is an equation: 2(x + 1) = −14. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply only the x by 2: 2x + 1 = −14 / B) Subtract 1 to undo the 1: 2x = −15 / C) Multiply both terms in the parentheses by 2: 2x + 2 = −14 / D) Divide only the left side by 2: x + 1 = −14 | C | CORRECT | counted unassisted |
| Solve 8(x − 7) = −24 for x. | 4 | CORRECT | counted unassisted |
| Solve −30 = 5(x + 2) for x. | My answer is -8. Why does that step work? | CORRECT | with help: tutor move HINT |
| Solve 2(x − 8) = −14 for x. | 1 | CORRECT | counted unassisted |
| Here is an equation: 2(x − 1) = 2. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply both terms in the parentheses by 2: 2x − 2 = 2 / B) Add 1 to undo the −1: 2x = 3 / C) Divide both sides by 2: x − 1 = 1 / D) Divide only the left side by 2: x − 1 = 2 | A,C | CORRECT | counted unassisted |
| Solve 4(x + 8) − 6 = 74 for x. | My answer is 12. Why does that step work? | CORRECT | with help: tutor move HINT |
| Solve 4(x − 6) = −12 for x. | 3 | CORRECT | counted unassisted |
| Here is an equation: 6(x − 3) = −30. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide both sides by 6: x − 3 = −5 / B) Multiply only the x by 6: 6x − 3 = −30 / C) Multiply both terms in the parentheses by 6: 6x − 18 = −30 / D) Add 3 to undo the −3: 6x = −27 | A,C | CORRECT | counted unassisted |
| Solve 9 = 9(x + 2) for x. | My answer is -1. Why does that step work? | CORRECT | counted unassisted |
| Solve 3x + 2 = −13 for x. | -5 | CORRECT | counted unassisted |

## Help on open check problems

- Help moves on open checks: HINT 6; with a practice item: 0
- Help recorded on Solve 7x − 11 = −53 for x. (check): tutor move HINT
- Help recorded on Solve −7x + 2 = 30 for x. (check): tutor move HINT
- Help recorded on Solve 70 = 7(x + 4) for x. (check): tutor move HINT
- Help recorded on Solve 6 = 6(x − 4) for x. (check): tutor move HINT
- Help recorded on Solve −30 = 5(x + 2) for x. (check): tutor move HINT
- Help recorded on Solve 4(x + 8) − 6 = 74 for x. (check): tutor move HINT

## equation_step answers

| Problem | Entry | Verdict |
|---|---|---|
| Add 1 to both sides of 3x − 1 = −19. Write the equation you get. | My answer is 3x = -18. Why does that step work? | STEP_CORRECT |

## Slip eligibility (every answer code found not yet correct)

None.

## Detector signals (logged only; the text was shown unchanged, nothing held)

| Guard | Detail | Field | Text |
|---|---|---|---|
| ANSWER_LEAK | contains the answer to a problem the student has not answered yet | student_message | Correct — x = −6 solves 3x = −18. Dividing both sides of an equation by the same nonzero number keeps the two sides equal, and dividing by 3 undoes the multiplication by 3. You've now handled both steps of a two-step equation, so let's try a fresh one on your own. |
| CHECK_CARD_ANSWER_SIGNAL | contains a number equal to the check question's answer that comes from the previous problem's answer | student_message | Correct — x = −6 solves 3x = −18. Dividing both sides of an equation by the same nonzero number keeps the two sides equal, and dividing by 3 undoes the multiplication by 3. You've now handled both steps of a two-step equation, so let's try a fresh one on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = −6 solves 3x = −18. Dividing both sides of an equation by the same nonzero number keeps the two sides equal, and dividing by 3 undoes the multiplication by 3. You've now handled both steps of a two-step equation, so let's try a fresh one on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 11 | student_message | Correct — x = −6 solves 7x − 11 = −53. That step works because adding the same number to both sides of an equation keeps the two sides equal, and adding 11 undoes the subtraction of 11 on the left. Here is another one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try, another one | student_message | Correct — x = −6 solves 7x − 11 = −53. That step works because adding the same number to both sides of an equation keeps the two sides equal, and adding 11 undoes the subtraction of 11 on the left. Here is another one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 2 | student_message | Correct — x = −3 solves −5x − 11 = 4. Adding the same number to both sides keeps the two sides equal, and dividing both sides by the same nonzero number keeps them equal too, which is how the additive and multiplicative structure gets undone. Here is another one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try, another one | student_message | Correct — x = −3 solves −5x − 11 = 4. Adding the same number to both sides keeps the two sides equal, and dividing both sides by the same nonzero number keeps them equal too, which is how the additive and multiplicative structure gets undone. Here is another one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 2 | student_message | Good — x = −4 solves −7x + 2 = 30, and your plan of undoing the addition first was the right order. The goal for solving two-step equations is met for this session. Next, a skill that builds on it: equations with parentheses. Here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: next, try | student_message | Good — x = −4 solves −7x + 2 = 30, and your plan of undoing the addition first was the right order. The goal for solving two-step equations is met for this session. Next, a skill that builds on it: equations with parentheses. Here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 4 | student_message | Correct — x = 4 solves 3(x + 2) = 18. Distributing a factor over a sum multiplies every term inside the parentheses, and dividing both sides by the same nonzero number keeps the sides equal. Your goal for equations with parentheses still needs a step choice and a new kind of equation, so here is a step-choice question to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = 4 solves 3(x + 2) = 18. Distributing a factor over a sum multiplies every term inside the parentheses, and dividing both sides by the same nonzero number keeps the sides equal. Your goal for equations with parentheses still needs a step choice and a new kind of equation, so here is a step-choice question to try on your own. |
| ANSWER_LEAK | contains the answer to a problem the student has not answered yet | student_message | Correct — B and C both give equations with exactly the same solution as 7(x − 6) = −28. Dividing both sides by the same nonzero number keeps the sides equal, and multiplying every term inside the parentheses by the factor keeps the expression equal. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 7 | student_message | Correct — B and C both give equations with exactly the same solution as 7(x − 6) = −28. Dividing both sides by the same nonzero number keeps the sides equal, and multiplying every term inside the parentheses by the factor keeps the expression equal. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_ANSWER_SIGNAL | contains a number equal to the check question's answer that comes from the previous problem | student_message | Correct — B and C both give equations with exactly the same solution as 7(x − 6) = −28. Dividing both sides by the same nonzero number keeps the sides equal, and multiplying every term inside the parentheses by the factor keeps the expression equal. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — B and C both give equations with exactly the same solution as 7(x − 6) = −28. Dividing both sides by the same nonzero number keeps the sides equal, and multiplying every term inside the parentheses by the factor keeps the expression equal. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 6 | student_message | Correct — x = 6 solves 70 = 7(x + 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, so 70 ÷ 7 = 10 leaves x + 4 = 10, and subtracting 4 from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = 6 solves 70 = 7(x + 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, so 70 ÷ 7 = 10 leaves x + 4 = 10, and subtracting 4 from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 6 | student_message | Correct — x = -8 solves 3(x − 6) = −42. Dividing both sides by the same nonzero number keeps the sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = -8 solves 3(x − 6) = −42. Dividing both sides by the same nonzero number keeps the sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 2, 1 | student_message | Correct — x = 5 solves 6 = 6(x − 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = 5 solves 6 = 6(x − 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — C is a valid first step: multiplying both terms inside the parentheses by 2 gives 2x + 2 = −14, which has the same solution as the original equation. The distributive property lets a factor multiply every term in a sum without changing the value. Your goal for equations with parentheses still needs a solve and a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 2 | student_message | Correct — x = 4 solves 8(x − 7) = −24. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = 4 solves 8(x − 7) = −24. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| ANSWER_LEAK | contains the answer to a problem the student has not answered yet | student_message | Correct — x = −8 solves −30 = 5(x + 2). Dividing both sides by the same nonzero number keeps the two sides equal, and subtracting the same number from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 2, 8 | student_message | Correct — x = −8 solves −30 = 5(x + 2). Dividing both sides by the same nonzero number keeps the two sides equal, and subtracting the same number from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_ANSWER | contains the check question's answer | student_message | Correct — x = −8 solves −30 = 5(x + 2). Dividing both sides by the same nonzero number keeps the two sides equal, and subtracting the same number from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = −8 solves −30 = 5(x + 2). Dividing both sides by the same nonzero number keeps the two sides equal, and subtracting the same number from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 2, 1 | student_message | Correct — x = 1 solves 2(x − 8) = −14. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = 1 solves 2(x − 8) = −14. Dividing both sides by the same nonzero number keeps the two sides equal, and adding the same number to both sides keeps them equal too. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — A and C both give equations with exactly the same solution as 2(x − 1) = 2. Multiplying every term inside the parentheses by the same factor, or dividing both sides by the same nonzero number, keeps the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 4, 6, 12 | student_message | Correct — x = 12 solves 4(x + 8) − 6 = 74. You asked why that step works: multiplying every term inside the parentheses by the same factor, and adding or subtracting the same number on both sides, each keep the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = 12 solves 4(x + 8) − 6 = 74. You asked why that step works: multiplying every term inside the parentheses by the same factor, and adding or subtracting the same number on both sides, each keep the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 6, 3 | student_message | Correct — x = 3 solves 4(x − 6) = −12. Dividing both sides by the same nonzero number, then adding the same number to both sides, keeps the solution set unchanged. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = 3 solves 4(x − 6) = −12. Dividing both sides by the same nonzero number, then adding the same number to both sides, keeps the solution set unchanged. Your goal for equations with parentheses still needs a step choice, so here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — A and C both give equations with exactly the same solution as 6(x − 3) = −30. Dividing both sides by the same nonzero number, or multiplying every term inside the parentheses by the same factor, keeps the solution set unchanged. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |
| HELP_CLAIM | on your own | student_message | You're describing a step on the open problem, and I can't take a step for you there — that one is yours to finish on your own. What you wrote is a plan, not an answer, so nothing is scored yet. Go ahead and carry it through and enter your value for x. |
| CHECK_CARD_INVITATION | a neutral invitation: next, try | student_message | Your goal for equations with parentheses is met for this session. The next skill in line is two-step equations, where you undo the additive and multiplicative structure in a valid order. Here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try, another one | student_message | Correct — x = -5 solves 3x + 2 = −13. Undoing the addition first and then the multiplication by the same nonzero number on both sides keeps the solution set unchanged. Here is another one to try on your own. |

## Counters (logged only)

| Counter | Hits | Text |
|---|---|---|
| RESOLVE_AFTER_CORRECT | 70 ÷ 7 = 10 ; x + 4 = 10 ; value 10 | Correct — x = 6 solves 70 = 7(x + 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, so 70 ÷ 7 = 10 leaves x + 4 = 10, and subtracting 4 from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |

## Repairs (rejected output, never shown)

| Call | Attempt | Cause | Errors | Rejected student_message |
|---|---|---|---|---|
| REPLY | 0 | message_type and item disagree | message_type must be QUESTION when item is present and FEEDBACK otherwise | Correct — x = 6 solves 70 = 7(x + 4). You asked why that step works: dividing both sides of an equation by the same nonzero number keeps the two sides equal, so 70 ÷ 7 = 10 leaves x + 4 = 10, and subtracting 4 from both sides keeps them equal too. Your goal for equations with parentheses still needs a new kind of equation, so here is one to try on your own. |

## Holds

None.

## Readings

- READING_NOT_AN_ANSWER: entry “I took away 1 from both sides first”
- READING_ACCEPTED: entry “My answer is 3x = -18. Why does that step work?”, reading “3*x = -18”
- READING_NOT_AN_ANSWER: entry “I took away 11 from both sides first”
- READING_ACCEPTED: entry “My answer is -6. Why does that step work?”, reading “-6”
- READING_NOT_AN_ANSWER: entry “I took away 2 from both sides first”
- READING_ACCEPTED: entry “My answer is -4. Why does that step work?”, reading “-4”
- READING_NOT_AN_ANSWER: entry “I took away 7 from both sides first”
- READING_ACCEPTED: entry “My answer is 6. Why does that step work?”, reading “6”
- READING_NOT_AN_ANSWER: entry “I took away 4 from both sides first”
- READING_ACCEPTED: entry “My answer is 5. Why does that step work?”, reading “5”
- READING_NOT_AN_ANSWER: entry “I took away 5 from both sides first”
- READING_ACCEPTED: entry “My answer is -8. Why does that step work?”, reading “-8”
- READING_NOT_AN_ANSWER: entry “I took away 8 from both sides first”
- READING_ACCEPTED: entry “My answer is 12. Why does that step work?”, reading “12”
- READING_NOT_AN_ANSWER: entry “I took away 2 from both sides first”
- READING_ACCEPTED: entry “My answer is -1. Why does that step work?”, reading “-1”

## Tutor moves

- POSE_PROBLEM → Q06 (from Q06): Start the session with a two-step equation to see where the student's performance stands.
- HINT → Q06 (from Q06): Student proposed subtracting 1 to undo the subtraction; redirect to the inverse operation as a single step.
- POSE_PROBLEM → Q06 (from Q06): Student correctly performed the additive step; next step is the multiplicative inverse to finish solving.
- MOVE_ON → Q06 (from Q06): Student solved the one-step multiplicative equation correctly; ready for an independent two-step check problem.
- HINT (help on an open check) → Q06 (from Q06): Student described a first-step plan on the open check problem; help recorded, check becomes practice.
- POSE_PROBLEM → Q06 (from Q06): Student solved a two-step equation correctly; pose a fresh check problem to build independent evidence.
- POSE_PROBLEM → Q06 (from Q06): Second independent check correct; continue building the in-session run for two-step equations.
- HINT (help on an open check) → Q06 (from Q06): Student described a first step on the open check problem; helping with that check, so it becomes practice.
- MOVE_ON → Q06 (from Q06): Check answered correctly; Q06 goal met for this session, so consolidate with a fresh practice problem.
- MOVE_ON → Q07 (from Q06): Two-step equation goal met for this session; move to equations with parentheses, which builds on Q06 and E06.
- POSE_PROBLEM → Q07 (from Q07): Check correct; goal still needs a step choice and a new form, so pose the choice check.
- POSE_PROBLEM → Q07 (from Q07): Step-choice check correct; goal still needs a new-form equation, so pose the transfer check.
- HINT (help on an open check) → Q07 (from Q07): Student described a first step on the open check problem; reply helps with it, so it is marked as help and the check becomes practice.
- POSE_PROBLEM → Q07 (from Q07): Check answer correct; goal still needs the new-form variant, so pose a fresh solve check.
- POSE_PROBLEM → Q07 (from Q07): Correct check answer; goal still needs a new-form equation, so pose the transfer check.
- HINT (help on an open check) → Q07 (from Q07): Student described a first step that misreads the structure of the grouped equation; prompt toward undoing the multiplication.
- POSE_PROBLEM → Q07 (from Q07): Correct check answer; goal still needs a step-choice question, so pose a fresh choice check.
- POSE_PROBLEM → Q07 (from Q07): Choice check correct; goal still needs a solve and a new form, so pose a fresh solve check.
- POSE_PROBLEM → Q07 (from Q07): Correct check answer; goal still needs a new-form equation, so pose the transfer check.
- HINT (help on an open check) → Q07 (from Q07): Student described an invalid first step on the open check problem; prompt toward undoing the multiplication instead.
- POSE_PROBLEM → Q07 (from Q07): Correct check answer; goal still needs a new kind of equation, so pose a fresh solve check.
- POSE_PROBLEM → Q07 (from Q07): Correct check answer; goal still needs a step-choice check, so pose the prepared contrast choice problem.
- POSE_PROBLEM → Q07 (from Q07): Step-choice check answered correctly; goal still needs a new-form equation.
- HINT (help on an open check) → Q07 (from Q07): Student proposed an invalid first step on the open check problem; help with the open check, which makes it practice.
- POSE_PROBLEM → Q07 (from Q07): Correct check answer; goal still needs a new kind of equation, so pose a fresh solve check.
- POSE_PROBLEM → Q07 (from Q07): Correct check answer; goal still needs a step-choice question, so pose the prepared contrast choice check.
- POSE_PROBLEM → Q07 (from Q07): Step-choice check answered correctly; goal still needs a new-form equation, so pose a transfer check.
- CONTINUE → Q07 (from Q07): Student described a planned first step on the open check problem rather than offering an answer; no help requested, so no help is recorded and the check stays open.
- MOVE_ON → Q06 (from Q07): Q07 goal met for this session; shift to the prerequisite skill still open in the gradebook.
- MOVE_ON → Q06 (from Q06): Q07 goal met for this session; moving to the prerequisite skill two-step equations with a fresh check problem.
- POSE_PROBLEM → Q06 (from Q06): Continue building independent evidence on two-step equations with a fresh check problem.

## Model calls

| # | Purpose | Thinking | Effort | Outcome | Finish | ms | Prompt (cached) | Completion (reasoning) |
|---|---|---|---|---|---|---|---|---|
| 1 | BINDING | disabled |  | ACCEPTED | stop | 1257 | 5806 (5632) | 28 (0) |
| 2 | REPLY | disabled |  | ACCEPTED | stop | 1613 | 9157 (7808) | 161 (0) |
| 3 | READING | disabled |  | ACCEPTED | stop | 1228 | 7690 (6656) | 74 (0) |
| 4 | REPLY | disabled |  | ACCEPTED | stop | 2918 | 9322 (6656) | 323 (0) |
| 5 | READING | disabled |  | ACCEPTED | stop | 1382 | 7846 (6656) | 78 (0) |
| 6 | REPLY | disabled |  | ACCEPTED | stop | 2461 | 9863 (6656) | 322 (0) |
| 7 | REPLY | disabled |  | ACCEPTED | stop | 2280 | 10030 (6656) | 268 (0) |
| 8 | READING | disabled |  | ACCEPTED | stop | 1472 | 7769 (6656) | 74 (0) |
| 9 | REPLY | disabled |  | ACCEPTED | stop | 2695 | 10048 (6656) | 326 (0) |
| 10 | READING | disabled |  | ACCEPTED | stop | 1546 | 7850 (6656) | 75 (0) |
| 11 | REPLY | disabled |  | ACCEPTED | stop | 2258 | 10253 (6656) | 274 (0) |
| 12 | REPLY | disabled |  | ACCEPTED | stop | 2672 | 10291 (6656) | 283 (0) |
| 13 | READING | disabled |  | ACCEPTED | stop | 1262 | 7772 (6656) | 74 (0) |
| 14 | REPLY | disabled |  | ACCEPTED | stop | 2232 | 10249 (6656) | 273 (0) |
| 15 | READING | disabled |  | ACCEPTED | stop | 1495 | 7847 (6656) | 75 (0) |
| 16 | REPLY | disabled |  | ACCEPTED | stop | 2971 | 10438 (6656) | 357 (0) |
| 17 | REPLY | disabled |  | ACCEPTED | stop | 2676 | 10353 (6656) | 319 (0) |
| 18 | REPLY | disabled |  | ACCEPTED | stop | 2489 | 11039 (6656) | 290 (0) |
| 19 | REPLY | disabled |  | ACCEPTED | stop | 2233 | 11269 (6656) | 294 (0) |
| 20 | READING | disabled |  | ACCEPTED | stop | 1366 | 7889 (6656) | 74 (0) |
| 21 | REPLY | disabled |  | ACCEPTED | stop | 2890 | 11096 (6656) | 310 (0) |
| 22 | READING | disabled |  | ACCEPTED | stop | 1436 | 7868 (6656) | 74 (0) |
| 23 | REPLY | disabled |  | REJECTED | stop | 2620 | 11238 (6656) | 313 (0) |
| 24 | REPAIR | disabled |  | ACCEPTED_AFTER_REPAIR | stop | 2195 | 11635 (11392) | 311 (0) |
| 25 | REPLY | disabled |  | ACCEPTED | stop | 2094 | 11098 (6656) | 267 (0) |
| 26 | READING | disabled |  | ACCEPTED | stop | 1596 | 7778 (6656) | 96 (0) |
| 27 | REPLY | disabled |  | ACCEPTED | stop | 2047 | 11055 (6656) | 233 (0) |
| 28 | READING | disabled |  | ACCEPTED | stop | 1289 | 7858 (6656) | 74 (0) |
| 29 | REPLY | disabled |  | ACCEPTED | stop | 2283 | 11152 (6656) | 287 (0) |
| 30 | REPLY | disabled |  | ACCEPTED | stop | 2525 | 11366 (6656) | 303 (0) |
| 31 | REPLY | disabled |  | ACCEPTED | stop | 2139 | 11164 (6656) | 271 (0) |
| 32 | READING | disabled |  | ACCEPTED | stop | 1495 | 7783 (6656) | 95 (0) |
| 33 | REPLY | disabled |  | ACCEPTED | stop | 2161 | 11131 (6656) | 221 (0) |
| 34 | READING | disabled |  | ACCEPTED | stop | 1469 | 7866 (6656) | 75 (0) |
| 35 | REPLY | disabled |  | ACCEPTED | stop | 2679 | 11127 (6656) | 291 (0) |
| 36 | REPLY | disabled |  | ACCEPTED | stop | 2445 | 11058 (6656) | 283 (0) |
| 37 | REPLY | disabled |  | ACCEPTED | stop | 2318 | 11352 (6656) | 298 (0) |
| 38 | READING | disabled |  | ACCEPTED | stop | 1362 | 7895 (6656) | 74 (0) |
| 39 | REPLY | disabled |  | ACCEPTED | stop | 2919 | 11226 (6656) | 354 (0) |
| 40 | READING | disabled |  | ACCEPTED | stop | 1379 | 7875 (6656) | 74 (0) |
| 41 | REPLY | disabled |  | ACCEPTED | stop | 2359 | 11323 (6656) | 301 (0) |
| 42 | REPLY | disabled |  | ACCEPTED | stop | 2568 | 11099 (6656) | 274 (0) |
| 43 | REPLY | disabled |  | ACCEPTED | stop | 2174 | 11359 (6656) | 285 (0) |
| 44 | READING | disabled |  | ACCEPTED | stop | 1380 | 7888 (6656) | 97 (0) |
| 45 | REPLY | disabled |  | ACCEPTED | stop | 2639 | 11234 (6784) | 233 (0) |
| 46 | READING | disabled |  | ACCEPTED | stop | 1372 | 7849 (6656) | 75 (0) |
| 47 | REPLY | disabled |  | ACCEPTED | stop | 2664 | 11262 (6656) | 302 (0) |
| 48 | REPLY | disabled |  | ACCEPTED | stop | 2418 | 10812 (6656) | 298 (0) |
| 49 | REPLY | disabled |  | ACCEPTED | stop | 2195 | 10803 (6656) | 251 (0) |
