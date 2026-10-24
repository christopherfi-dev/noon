# SLIPS-1 — LIVE openai_compatible / deepseek-flash, provider_thinking disabled

| # | Learner input | Calls (purpose: reasoning setting) | Outcomes | Wait ms |
|---|---|---|---|---|
| 0 | (start on Q07) | 2 — BINDING: disabled (binding call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3304 |
| 1 | Can we practise expanding brackets first, like 3(x + 4)? | 2 — READING: disabled (configured: disabled on every call); REPLY: disabled (configured: disabled on every call) | ACCEPTED, ACCEPTED | 3697 |
| 2 | 8x+24 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2551 |
| 3 | 6x+54 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2681 |
| 4 | A | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2306 |
| 5 | 4x + 4y + 16 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2560 |
| 6 | 20 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2889 |
| 7 | 4x+4y+20 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 3024 |
| 8 | 16x + 10 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2836 |
| 9 | 24 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2117 |
| 10 | 16x+24 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2972 |
| 11 | A,C | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2373 |
| 12 | 25x+55 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2423 |
| 13 | B,D | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2537 |
| 14 | 4x+12 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2254 |
| 15 | 5 | 2 — REPLY: disabled (configured: disabled on every call); REPAIR: disabled (configured: disabled on every call) | REJECTED, ACCEPTED_AFTER_REPAIR | 5006 |
| 16 | 9 | 2 — REPLY: disabled (configured: disabled on every call); REPAIR: disabled (configured: disabled on every call) | REJECTED, ACCEPTED_AFTER_REPAIR | 3683 |
| 17 | 7 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2882 |
| 18 | 8 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2418 |
| 19 | B,C | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2488 |
| 20 | 9 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2037 |
| 21 | ok, what next? | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2461 |
| 22 | 9 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2529 |
| 23 | -6 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2148 |
| 24 | -7 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2331 |
| 25 | -2 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2934 |
| 26 | -9 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2395 |
| 27 | 8 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2257 |
| 28 | 1 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2559 |
| 29 | B,C | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2707 |
| 30 | 3 | 1 — REPLY: disabled (configured: disabled on every call) | ACCEPTED | 2029 |

## Conversation as the student saw it (the last 80 lines the session keeps)

- **app (result):** Correct. / On 4(4x + 6) you answered 16x+24.
- **tutor (message):** Correct — 16x + 24 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That expansion is done; here is a fresh question to try on your own.
- **app (check):** Which of these expressions are equal to 5(x + 10) for every value of x? Select every one that is. / A) 5x + 50 / B) x + 50 / C) 5 · x + 5 · 10 / D) 5x + 10
- **student (message):** A,C
- **app (result):** Correct. / On 5(x + 10) you chose A and C.
- **tutor (message):** Correct — A and C are both equal to 5(x + 10), since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. Here is a fresh one to try on your own.
- **app (check):** Expand 5(5x + 11). Write your answer without parentheses.
- **student (message):** 25x+55
- **app (result):** Correct. / On 5(5x + 11) you answered 25x+55.
- **tutor (message):** Correct — 25x + 55 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That goal is met for this session on the expansion part. Here is a fresh one to try on your own.
- **app (check):** Which of these expressions are equal to 5(x + 6) for every value of x? Select every one that is. / A) x + 30 / B) 5x + 30 / C) 5x + 6 / D) 5 · x + 5 · 6
- **student (message):** B,D
- **app (result):** Correct. / On 5(x + 6) you chose B and D.
- **tutor (message):** Correct — B and D are both equal to 5(x + 6), since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. Here is a fresh one to try on your own.
- **app (check):** Expand 4(x + 3). Write your answer without parentheses.
- **student (message):** 4x+12
- **app (result):** Correct. / On 4(x + 3) you answered 4x+12.
- **tutor (message):** Correct — 4x + 12 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. The distributive-property goal is met for this session. Your next target is solving equations that contain parentheses; here is one to try on your own.
- **app (check):** Solve 2(x + 5) = 18 for x.
- **student (message):** 5
- **app (result):** Not yet. / On 2(x + 5) = 18 you answered 5.
- **tutor (message):** Not yet — 5 doesn't satisfy 2(x + 5) = 18. The answer differs from the solution by 1, so let's check the arithmetic at the end. What is 18 ÷ 2?
- **tutor (problem):** Calculate 18 ÷ 2.
- **student (message):** 9
- **app (result):** Correct. / On 18 ÷ 2 you answered 9.
- **tutor (message):** Correct — 18 ÷ 2 = 9, since dividing undoes multiplying by the same whole number. Now let's return to the equation with parentheses and try it again on your own.
- **tutor (problem):** Solve 3(x + 4) = 33 for x.
- **student (message):** 7
- **app (result):** Correct. / On 3(x + 4) = 33 you answered 7.
- **tutor (message):** Correct — x = 7 is right, since dividing both sides by the factor outside the parentheses undoes that multiplication and leaves the grouped sum equal to the quotient. Now let's see what you can do with a fresh one on your own.
- **app (check):** Solve 3(x − 1) = 21 for x.
- **student (message):** 8
- **app (result):** Correct. / On 3(x − 1) = 21 you answered 8.
- **tutor (message):** Correct — x = 8 is right, since dividing both sides by the factor outside the parentheses undoes that multiplication and leaves the grouped difference equal to the quotient. The goal for this skill still needs a step-choice question and a new kind of equation, so here is a step-choice one.
- **app (check):** Here is an equation: 2(x − 4) = −2. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 4 to undo the −4: 2x = 2 / B) Divide both sides by 2: x − 4 = −1 / C) Multiply both terms in the parentheses by 2: 2x − 8 = −2 / D) Divide only the left side by 2: x − 4 = −2
- **student (message):** B,C
- **app (result):** Correct. / On 2(x − 4) = −2 you chose B and C.
- **tutor (message):** Correct — B and C are right, since dividing both sides by the same nonzero number and distributing the factor over both terms each keep the solution unchanged. The goal for this skill still needs a new kind of equation, so here is one on your own.
- **app (check):** Solve 56 = 7(x − 1) for x.
- **student (message):** 9
- **app (result):** Correct. / On 56 = 7(x − 1) you answered 9.
- **tutor (message):** Correct — x = 9 is right, since dividing both sides by the factor outside the parentheses undoes that multiplication and leaves the grouped difference equal to the quotient. That completes the goal for this skill in this session, covering the solve, the step choice and the new form.
- **student (message):** ok, what next?
- **tutor (message):** The goal for solving equations with parentheses is met for this session — that covers the solve, the step choice and the new form. Next, let's look at two-step equations, which come before grouped ones. Here is one to try.
- **tutor (problem):** Solve −8x + 2 = −70 for x.
- **student (message):** 9
- **app (result):** Correct. / On −8x + 2 = −70 you answered 9.
- **tutor (message):** Correct — x = 9 is right, since undoing the addition and then the multiplication by the same nonzero value on both sides keeps the solution unchanged. That was your first correct two-step equation this session, so let's keep going with another one.
- **tutor (problem):** Solve −3x + 5 = 23 for x.
- **student (message):** -6
- **app (result):** Correct. / On −3x + 5 = 23 you answered -6.
- **tutor (message):** Correct — x = -6 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your second correct two-step equation this session; here is another to try.
- **tutor (problem):** Solve −6x + 4 = 46 for x.
- **student (message):** -7
- **app (result):** Correct. / On −6x + 4 = 46 you answered -7.
- **tutor (message):** Correct — x = -7 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct two-step equation this session, so let's see how you do with one on your own.
- **app (check):** Solve −9x − 14 = 4 for x.
- **student (message):** -2
- **app (result):** Correct. / On −9x − 14 = 4 you answered -2.
- **tutor (message):** Correct — x = -2 is right, since undoing the subtraction and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your first correct two-step equation on your own this session; here is another to try.
- **app (check):** Solve −5x + 2 = 47 for x.
- **student (message):** -9
- **app (result):** Correct. / On −5x + 2 = 47 you answered -9.
- **tutor (message):** Correct — x = -9 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your second correct two-step equation on your own this session; here is another to try.
- **app (check):** Solve −4x + 11 = −21 for x.
- **student (message):** 8
- **app (result):** Correct. / On −4x + 11 = −21 you answered 8.
- **tutor (message):** Correct — x = 8 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct two-step equation on your own this session. Now let's move to equations with parentheses.
- **app (check):** Solve 9(x + 7) = 72 for x.
- **student (message):** 1
- **app (result):** Correct. / On 9(x + 7) = 72 you answered 1.
- **tutor (message):** Correct — x = 1 is right, since dividing both sides by the same nonzero value keeps the solution unchanged. That is your first correct parentheses equation on your own this session, and the goal for this skill is met for this session. Here is a question about which first steps keep the same solution.
- **app (check):** Here is an equation: 5(x − 6) = −10. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 6 to undo the −6: 5x = −4 / B) Multiply both terms in the parentheses by 5: 5x − 30 = −10 / C) Divide both sides by 5: x − 6 = −2 / D) Multiply only the x by 5: 5x − 6 = −10
- **student (message):** B,C
- **app (result):** Correct. / On 5(x − 6) = −10 you chose B and C.
- **tutor (message):** Correct — B and C are right, since multiplying every term inside the parentheses by the factor, or dividing both sides by the same nonzero value, keeps the solution unchanged. The goal for this skill is met for this session. Here is a new kind of parentheses equation to try.
- **app (check):** Solve 3(x + 4) + 5 = 26 for x.
- **student (message):** 3
- **app (result):** Correct. / On 3(x + 4) + 5 = 26 you answered 3.
- **tutor (message):** Correct — x = 3 is right, since dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct parentheses equation on your own this session, and the goal for this skill is met for this session.

## Every tutor message, with the answered and open problem it was written for

| Field | Text | Answered problem (attempt) | Open problem |
|---|---|---|---|
| student_message | Let's start with an equation that has parentheses. Take your time and show your thinking if you like. | — | — |
| student_message | Good idea — expanding the brackets is exactly the skill that makes equations like the one on your screen easier. Let's practise that first. Expand 8(x + 3), writing your answer without parentheses. | — | prb_96fa154fcc24d678fdf838a1 practice |
| student_message | Correct — 8(x + 3) expands to 8x + 24. In general, a(b + c) = ab + ac, distributing the factor to every term inside the parentheses. Let's try another expansion. | prb_74e048851781d171909a4fe1 (evt_116edc19dd62b117e48a199f) | — |
| student_message | Correct — 6(x + 9) expands to 6x + 54. In general, a(b + c) = ab + ac, distributing the factor to every term inside the parentheses. You've expanded two expressions accurately; here is a question about which expressions are equivalent. | prb_eb0037da4dfce2dceffaa027 (evt_519c9fdfcb9f9a1e37410c82) | — |
| student_message | Correct — 9(x + 4) is equal to 9 · x + 9 · 4, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That choice question is done; here is a fresh expansion to try on your own. | prb_3a7539291c1be606160a7f30 (evt_827cd4ce86fd0e969c6b080b) | — |
| student_message | Not yet — on 4(x + y + 5) you wrote 4x + 4y + 16. The x and y terms match what distributing gives, but the constant needs a check. Let's isolate that one multiplication first. | prb_4d48a06b2b622fd2145fd0df (evt_f1bee916591a723c3171256f) | — |
| student_message | Correct — 4 × 5 = 20. In general, a product ab gives the total of a groups of b. Now let's put that back into the expansion you were working on. | prb_bd7332485ddbb09b28b7fc5d (evt_145b12288cdf77151c31fc87) | — |
| student_message | Correct — 4x + 4y + 20 is right, since a(b + c + d) = ab + ac + ad distributes the factor to every term inside the parentheses. That retry is done; here is a fresh expansion to try on your own. | prb_b866ee55b4e8501b0ee2d364 (evt_4abf39032f0f14a6c81e5f77) | — |
| student_message | Not yet — on 4(4x + 6) you wrote 16x + 10. The x-term matches what distributing gives, but the constant needs a check. Let's isolate that one multiplication first. | prb_ac2eec9907cc2a9f4b6f1945 (evt_c59f4414eb9694fce5e83b89) | — |
| student_message | Correct — 4 × 6 = 24. In general, a product ab gives the total of a groups of b. Now let's put that back into the expansion you were working on. | prb_c4b4f890a86e9bc7ac708146 (evt_639ab489f7b0dbfa31ab3417) | — |
| student_message | Correct — 16x + 24 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That expansion is done; here is a fresh question to try on your own. | prb_a32a26be82b06432fb3d9f99 (evt_1ef805f957c5f89a2f39368c) | — |
| student_message | Correct — A and C are both equal to 5(x + 10), since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. Here is a fresh one to try on your own. | prb_71c3dc9e55cf5b726517008d (evt_34fd5fab9ac695132631e900) | — |
| student_message | Correct — 25x + 55 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That goal is met for this session on the expansion part. Here is a fresh one to try on your own. | prb_518f06d0a96526824a1c6912 (evt_a8a6365a105ced19b752794e) | — |
| student_message | Correct — B and D are both equal to 5(x + 6), since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. Here is a fresh one to try on your own. | prb_536903e75e2ad1be14681258 (evt_3959956d4de38dc83051c90f) | — |
| student_message | Correct — 4x + 12 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. The distributive-property goal is met for this session. Your next target is solving equations that contain parentheses; here is one to try on your own. | prb_8e65ee2f13713a1b386bc16d (evt_7a385d5364efa874019cf776) | — |
| student_message | Not yet — 5 doesn't satisfy 2(x + 5) = 18. The answer differs from the solution by 1, so let's check the arithmetic at the end. What is 18 ÷ 2? | prb_20deb219a0cf2685d4da64ba (evt_52b96e486a25446f0d5a7eb8) | — |
| student_message | Correct — 18 ÷ 2 = 9, since dividing undoes multiplying by the same whole number. Now let's return to the equation with parentheses and try it again on your own. | prb_cdcc610ee7597d2a25dd5913 (evt_de3a057b39cade764b44c4f2) | — |
| student_message | Correct — x = 7 is right, since dividing both sides by the factor outside the parentheses undoes that multiplication and leaves the grouped sum equal to the quotient. Now let's see what you can do with a fresh one on your own. | prb_779be54b2c9a6bacd6aaacee (evt_a4b51b3e27d664a7b00b27f8) | — |
| student_message | Correct — x = 8 is right, since dividing both sides by the factor outside the parentheses undoes that multiplication and leaves the grouped difference equal to the quotient. The goal for this skill still needs a step-choice question and a new kind of equation, so here is a step-choice one. | prb_50a9346971a8c78d65077a37 (evt_f40742fa8b95eeb14039fcb9) | — |
| student_message | Correct — B and C are right, since dividing both sides by the same nonzero number and distributing the factor over both terms each keep the solution unchanged. The goal for this skill still needs a new kind of equation, so here is one on your own. | prb_2014812dfb930874577abcce (evt_db9b8fbaa6b598ae4aab338d) | — |
| student_message | Correct — x = 9 is right, since dividing both sides by the factor outside the parentheses undoes that multiplication and leaves the grouped difference equal to the quotient. That completes the goal for this skill in this session, covering the solve, the step choice and the new form. | prb_5892ecc95048c9d244a41771 (evt_1af948398d8a572a6a578552) | — |
| student_message | The goal for solving equations with parentheses is met for this session — that covers the solve, the step choice and the new form. Next, let's look at two-step equations, which come before grouped ones. Here is one to try. | prb_5892ecc95048c9d244a41771 (evt_1af948398d8a572a6a578552) | — |
| student_message | Correct — x = 9 is right, since undoing the addition and then the multiplication by the same nonzero value on both sides keeps the solution unchanged. That was your first correct two-step equation this session, so let's keep going with another one. | prb_950498ecfc774ac780f94ea9 (evt_7083c43ce0480e1c8c19d03d) | — |
| student_message | Correct — x = -6 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your second correct two-step equation this session; here is another to try. | prb_3b38ebaea7f1725558ad0f86 (evt_1fd9ec26fe02b6e0fbdb340e) | — |
| student_message | Correct — x = -7 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct two-step equation this session, so let's see how you do with one on your own. | prb_844d4c6cab6a5bea5acc3f36 (evt_cbd6d7ec873e0d5e3fbc9d91) | — |
| student_message | Correct — x = -2 is right, since undoing the subtraction and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your first correct two-step equation on your own this session; here is another to try. | prb_a3efea35903dd1fbfc5c5a01 (evt_04616fbe3665d8c9dd7ba3c7) | — |
| student_message | Correct — x = -9 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your second correct two-step equation on your own this session; here is another to try. | prb_03c53280c742be60d3d52357 (evt_028fb9abc31248a970d426e6) | — |
| student_message | Correct — x = 8 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct two-step equation on your own this session. Now let's move to equations with parentheses. | prb_2042a569f3bf654649d94b02 (evt_bc2d9205e2dce6144ecaeecc) | — |
| student_message | Correct — x = 1 is right, since dividing both sides by the same nonzero value keeps the solution unchanged. That is your first correct parentheses equation on your own this session, and the goal for this skill is met for this session. Here is a question about which first steps keep the same solution. | prb_524e45675f3b3f601b97a11a (evt_5bf6cefbb3281eb60e8c8c03) | — |
| student_message | Correct — B and C are right, since multiplying every term inside the parentheses by the factor, or dividing both sides by the same nonzero value, keeps the solution unchanged. The goal for this skill is met for this session. Here is a new kind of parentheses equation to try. | prb_5bd3f0f2429b7cb8ab0abe89 (evt_712a8a034b39bd679247f45b) | — |
| student_message | Correct — x = 3 is right, since dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct parentheses equation on your own this session, and the goal for this skill is met for this session. | prb_b2a806c02d1ea874f5e09336 (evt_6a91d769b5e5a6fe7deb1f75) | — |

## Verdicts recorded by code

| Problem | Purpose | Entry | Read as | Verdict | Help | Slip | Held |
|---|---|---|---|---|---|---|---|
| Expand 8(x + 3). Write your answer without parentheses. | PRACTICE | 8x+24 | 8x+24 | CORRECT |  |  |  |
| Expand 6(x + 9). Write your answer without parentheses. | PRACTICE | 6x+54 | 6x+54 | CORRECT |  |  |  |
| Which of these expressions are equal to 9(x + 4) for every value of x? Select every one that is. / A) 9 · x + 9 · 4 / B) x + 36 / C) x + 13 / D) 9x + 4 | CHECK | A | A | CORRECT |  |  |  |
| Expand 4(x + y + 5). Write your answer without parentheses. | CHECK | 4x + 4y + 16 | 4x + 4y + 16 | INCORRECT |  | PRODUCT_FACT |  |
| Calculate 4 × 5. | PRACTICE | 20 | 20 | CORRECT |  |  |  |
| Expand 4(x + y + 5). Write your answer without parentheses. | RETRY | 4x+4y+20 | 4x+4y+20 | CORRECT |  |  |  |
| Expand 4(4x + 6). Write your answer without parentheses. | CHECK | 16x + 10 | 16x + 10 | INCORRECT |  |  |  |
| Calculate 4 × 6. | PRACTICE | 24 | 24 | CORRECT |  |  |  |
| Expand 4(4x + 6). Write your answer without parentheses. | PRACTICE | 16x+24 | 16x+24 | CORRECT |  |  |  |
| Which of these expressions are equal to 5(x + 10) for every value of x? Select every one that is. / A) 5x + 50 / B) x + 50 / C) 5 · x + 5 · 10 / D) 5x + 10 | CHECK | A,C | A,C | CORRECT |  |  |  |
| Expand 5(5x + 11). Write your answer without parentheses. | CHECK | 25x+55 | 25x+55 | CORRECT |  |  |  |
| Which of these expressions are equal to 5(x + 6) for every value of x? Select every one that is. / A) x + 30 / B) 5x + 30 / C) 5x + 6 / D) 5 · x + 5 · 6 | CHECK | B,D | B,D | CORRECT |  |  |  |
| Expand 4(x + 3). Write your answer without parentheses. | CHECK | 4x+12 | 4x+12 | CORRECT |  |  |  |
| Solve 2(x + 5) = 18 for x. | CHECK | 5 | 5 | INCORRECT |  |  |  |
| Calculate 18 ÷ 2. | PRACTICE | 9 | 9 | CORRECT |  |  |  |
| Solve 3(x + 4) = 33 for x. | PRACTICE | 7 | 7 | CORRECT |  |  |  |
| Solve 3(x − 1) = 21 for x. | CHECK | 8 | 8 | CORRECT |  |  |  |
| Here is an equation: 2(x − 4) = −2. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 4 to undo the −4: 2x = 2 / B) Divide both sides by 2: x − 4 = −1 / C) Multiply both terms in the parentheses by 2: 2x − 8 = −2 / D) Divide only the left side by 2: x − 4 = −2 | CHECK | B,C | B,C | CORRECT |  |  |  |
| Solve 56 = 7(x − 1) for x. | CHECK | 9 | 9 | CORRECT |  |  |  |
| Solve −8x + 2 = −70 for x. | PRACTICE | 9 | 9 | CORRECT |  |  |  |
| Solve −3x + 5 = 23 for x. | PRACTICE | -6 | -6 | CORRECT |  |  |  |
| Solve −6x + 4 = 46 for x. | PRACTICE | -7 | -7 | CORRECT |  |  |  |
| Solve −9x − 14 = 4 for x. | CHECK | -2 | -2 | CORRECT |  |  |  |
| Solve −5x + 2 = 47 for x. | CHECK | -9 | -9 | CORRECT |  |  |  |
| Solve −4x + 11 = −21 for x. | CHECK | 8 | 8 | CORRECT |  |  |  |
| Solve 9(x + 7) = 72 for x. | CHECK | 1 | 1 | CORRECT |  |  |  |
| Here is an equation: 5(x − 6) = −10. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 6 to undo the −6: 5x = −4 / B) Multiply both terms in the parentheses by 5: 5x − 30 = −10 / C) Divide both sides by 5: x − 6 = −2 / D) Multiply only the x by 5: 5x − 6 = −10 | CHECK | B,C | B,C | CORRECT |  |  |  |
| Solve 3(x + 4) + 5 = 26 for x. | CHECK | 3 | 3 | CORRECT |  |  |  |

## Check answers

| Check problem | Entry | Verdict | Counted as |
|---|---|---|---|
| Which of these expressions are equal to 9(x + 4) for every value of x? Select every one that is. / A) 9 · x + 9 · 4 / B) x + 36 / C) x + 13 / D) 9x + 4 | A | CORRECT | counted unassisted |
| Expand 4(x + y + 5). Write your answer without parentheses. | 4x + 4y + 16 | INCORRECT | counted unassisted |
| Expand 4(4x + 6). Write your answer without parentheses. | 16x + 10 | INCORRECT | counted unassisted |
| Which of these expressions are equal to 5(x + 10) for every value of x? Select every one that is. / A) 5x + 50 / B) x + 50 / C) 5 · x + 5 · 10 / D) 5x + 10 | A,C | CORRECT | counted unassisted |
| Expand 5(5x + 11). Write your answer without parentheses. | 25x+55 | CORRECT | counted unassisted |
| Which of these expressions are equal to 5(x + 6) for every value of x? Select every one that is. / A) x + 30 / B) 5x + 30 / C) 5x + 6 / D) 5 · x + 5 · 6 | B,D | CORRECT | counted unassisted |
| Expand 4(x + 3). Write your answer without parentheses. | 4x+12 | CORRECT | counted unassisted |
| Solve 2(x + 5) = 18 for x. | 5 | INCORRECT | counted unassisted |
| Solve 3(x − 1) = 21 for x. | 8 | CORRECT | counted unassisted |
| Here is an equation: 2(x − 4) = −2. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 4 to undo the −4: 2x = 2 / B) Divide both sides by 2: x − 4 = −1 / C) Multiply both terms in the parentheses by 2: 2x − 8 = −2 / D) Divide only the left side by 2: x − 4 = −2 | B,C | CORRECT | counted unassisted |
| Solve 56 = 7(x − 1) for x. | 9 | CORRECT | counted unassisted |
| Solve −9x − 14 = 4 for x. | -2 | CORRECT | counted unassisted |
| Solve −5x + 2 = 47 for x. | -9 | CORRECT | counted unassisted |
| Solve −4x + 11 = −21 for x. | 8 | CORRECT | counted unassisted |
| Solve 9(x + 7) = 72 for x. | 1 | CORRECT | counted unassisted |
| Here is an equation: 5(x − 6) = −10. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Add 6 to undo the −6: 5x = −4 / B) Multiply both terms in the parentheses by 5: 5x − 30 = −10 / C) Divide both sides by 5: x − 6 = −2 / D) Multiply only the x by 5: 5x − 6 = −10 | B,C | CORRECT | counted unassisted |
| Solve 3(x + 4) + 5 = 26 for x. | 3 | CORRECT | counted unassisted |

## Help on open check problems

- Help moves on open checks: none; with a practice item: 0

## equation_step answers

None.

## Slip eligibility (every answer code found not yet correct)

| Problem | Purpose | Entry | Eligible for the pause | Signatures | Detail sent to the tutor |
|---|---|---|---|---|---|
| Expand 4(x + y + 5). Write your answer without parentheses. | CHECK | 4x + 4y + 16 | yes | PRODUCT_FACT | The other terms match; the constant 16 is a near miss of 4 × 5 = 20. |
| Expand 4(4x + 6). Write your answer without parentheses. | CHECK | 16x + 10 | no | PRODUCT_FACT |  |
| Solve 2(x + 5) = 18 for x. | CHECK | 5 | no | ARITH_FINAL_PLUS |  |

## Detector signals (logged only; the text was shown unchanged, nothing held)

| Guard | Detail | Field | Text |
|---|---|---|---|
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 9 | student_message | Correct — 6(x + 9) expands to 6x + 54. In general, a(b + c) = ab + ac, distributing the factor to every term inside the parentheses. You've expanded two expressions accurately; here is a question about which expressions are equivalent. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 4 | student_message | Correct — 9(x + 4) is equal to 9 · x + 9 · 4, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That choice question is done; here is a fresh expansion to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — 9(x + 4) is equal to 9 · x + 9 · 4, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That choice question is done; here is a fresh expansion to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 4 | student_message | Correct — 4x + 4y + 20 is right, since a(b + c + d) = ab + ac + ad distributes the factor to every term inside the parentheses. That retry is done; here is a fresh expansion to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — 4x + 4y + 20 is right, since a(b + c + d) = ab + ac + ad distributes the factor to every term inside the parentheses. That retry is done; here is a fresh expansion to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — 16x + 24 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That expansion is done; here is a fresh question to try on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 5 | student_message | Correct — A and C are both equal to 5(x + 10), since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. Here is a fresh one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — A and C are both equal to 5(x + 10), since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. Here is a fresh one to try on your own. |
| MASTERY_OR_GOAL_CLAIM | goal is met | student_message | Correct — 25x + 55 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That goal is met for this session on the expansion part. Here is a fresh one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — 25x + 55 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. That goal is met for this session on the expansion part. Here is a fresh one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — B and D are both equal to 5(x + 6), since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. Here is a fresh one to try on your own. |
| MASTERY_OR_GOAL_CLAIM | goal is met | student_message | Correct — 4x + 12 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. The distributive-property goal is met for this session. Your next target is solving equations that contain parentheses; here is one to try on your own. |
| CHECK_CARD_INVITATION | a neutral invitation: next, try | student_message | Correct — 4x + 12 is right, since a(b + c) = ab + ac distributes the factor to every term inside the parentheses. The distributive-property goal is met for this session. Your next target is solving equations that contain parentheses; here is one to try on your own. |
| HELP_CLAIM | on your own | student_message | Correct — 18 ÷ 2 = 9, since dividing undoes multiplying by the same whole number. Now let's return to the equation with parentheses and try it again on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 1, 8 | student_message | Correct — x = 8 is right, since dividing both sides by the factor outside the parentheses undoes that multiplication and leaves the grouped difference equal to the quotient. The goal for this skill still needs a step-choice question and a new kind of equation, so here is a step-choice one. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 1 | student_message | Correct — B and C are right, since dividing both sides by the same nonzero number and distributing the factor over both terms each keep the solution unchanged. The goal for this skill still needs a new kind of equation, so here is one on your own. |
| CHECK_CARD_SHARED_NUMBER | shares numbers with the check question: 2 | student_message | Correct — x = -2 is right, since undoing the subtraction and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your first correct two-step equation on your own this session; here is another to try. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = -2 is right, since undoing the subtraction and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your first correct two-step equation on your own this session; here is another to try. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — x = -9 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your second correct two-step equation on your own this session; here is another to try. |
| HELP_CLAIM | on your own | student_message | Correct — x = 8 is right, since undoing the addition and then dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct two-step equation on your own this session. Now let's move to equations with parentheses. |
| HELP_CLAIM | on your own | student_message | Correct — x = 1 is right, since dividing both sides by the same nonzero value keeps the solution unchanged. That is your first correct parentheses equation on your own this session, and the goal for this skill is met for this session. Here is a question about which first steps keep the same solution. |
| CHECK_CARD_INVITATION | a neutral invitation: try | student_message | Correct — B and C are right, since multiplying every term inside the parentheses by the factor, or dividing both sides by the same nonzero value, keeps the solution unchanged. The goal for this skill is met for this session. Here is a new kind of parentheses equation to try. |
| HELP_CLAIM | on your own | student_message | Correct — x = 3 is right, since dividing both sides by the same nonzero value keeps the solution unchanged. That is your third correct parentheses equation on your own this session, and the goal for this skill is met for this session. |

## Counters (logged only)

None.

## Repairs (rejected output, never shown)

| Call | Attempt | Cause | Errors | Rejected student_message |
|---|---|---|---|---|
| REPLY | 0 | move: slip check not allowed | SLIP_CHECK is only possible right after an answer that code found eligible for the slip pause (SLIP.eligible_for_pause is false) | Not yet — 5 doesn't satisfy 2(x + 5) = 18. The answer differs from the solution by 1, so let's check the arithmetic at the end. What is 18 ÷ 2? |
| REPLY | 0 | item: original problem | item: source original is valid only when ORIGINAL_PROBLEM is not null, with purpose practice | Correct — 18 ÷ 2 = 9, since dividing undoes multiplying by the same whole number. Now let's return to the equation with parentheses and try it again on your own. |

## Holds

None.

## Readings

- READING_NOT_AN_ANSWER: entry “Can we practise expanding brackets first, like 3(x + 4)?”

## Tutor moves

- POSE_PROBLEM → Q07 (from Q07): Start the session with a constructed solve problem on equations with parentheses.
- STEP_BACK → E06 (from Q07): Student asked to practise expanding brackets; E06 is a prerequisite of the open Q07 equation, so step back to it and return to Q07 after.
- POSE_PROBLEM → E06 (from E06): Correct expansion; continue practice with a fresh expansion before moving to a check.
- POSE_PROBLEM → E06 (from E06): Two correct expansions; pose the choice check to supply the contrast variant for the E06 goal.
- POSE_PROBLEM → E06 (from E06): Choice check correct; goal still needs an expansion and a new kind of expression, so pose the transfer check.
- SLIP_CHECK → N18 (from E06): Code matched a product-fact slip on the constant term; isolate 4 × 5 before retrying the expansion.
- CONTINUE → E06 (from N18): Micro-question on the implicated multiplication was correct; retry the original expansion.
- POSE_PROBLEM → E06 (from E06): Retry correct; goal still needs a new kind of expression, so pose a fresh transfer-form check.
- STEP_BACK → N18 (from E06): Constant term in the expansion is off; isolate the single multiplication 4 × 6 before returning to the expansion.
- POSE_PROBLEM → E06 (from N18): Retry the expansion now that the constant multiplication 4 × 6 is confirmed.
- POSE_PROBLEM → E06 (from E06): Practice expansion correct; posing a fresh choice check to supply the choice variant the E06 goal still needs.
- POSE_PROBLEM → E06 (from E06): Choice check correct; goal still needs an expansion and a new form, so pose a fresh new-form expansion check.
- POSE_PROBLEM → E06 (from E06): E06 goal still needs a choice question; posing a fresh choice check.
- POSE_PROBLEM → E06 (from E06): Choice check correct; goal still needs an expansion, so pose a fresh expansion check.
- MOVE_ON → Q07 (from E06): Distributive-property goal met for this session; moving to the original target of solving equations with parentheses with a fresh check problem.
- HINT → Q07 (from Q07): Arithmetic-error signature on the final division; micro-question on the implicated sub-step before retrying.
- CONTINUE → Q07 (from Q07): Arithmetic sub-step confirmed; return to a grouped equation of the same form.
- POSE_PROBLEM → Q07 (from Q07): Practice correct; posing a fresh check problem to begin independent evidence on solving equations with parentheses.
- POSE_PROBLEM → Q07 (from Q07): Check correct; goal still needs a step-choice question, so pose the prepared contrast check.
- POSE_PROBLEM → Q07 (from Q07): Step-choice check correct; goal still needs a new-form equation, so pose a fresh transfer check.
- MOVE_ON → Q07 (from Q07): Goal for solving equations with parentheses met this session; no further check needed now.
- MOVE_ON → Q06 (from Q07): Q07 goal met this session; move to its prerequisite skill Q06, which is not yet met.
- POSE_PROBLEM → Q06 (from Q06): Correct practice answer; continue building two-step equation practice toward the goal.
- POSE_PROBLEM → Q06 (from Q06): Continue building independent evidence on two-step equations after a second correct practice answer.
- POSE_PROBLEM → Q06 (from Q06): Three practice two-step equations correct; posing a fresh check problem to test independent performance.
- POSE_PROBLEM → Q06 (from Q06): Continue building independent evidence on two-step equations with a fresh check problem.
- POSE_PROBLEM → Q06 (from Q06): Second qualifying check correct; goal needs a third without help.
- MOVE_ON → Q07 (from Q06): Q06 goal met with three correct check answers without help; advancing to equations containing parentheses.
- POSE_PROBLEM → Q07 (from Q07): Goal met for this session; offer the choice-form check to cover the step-choice variant.
- POSE_PROBLEM → Q07 (from Q07): Goal met for this session; extend to the new form a(x + b) + d = c with a fresh check problem.
- MOVE_ON → Q07 (from Q07): Third correct check on parentheses equations without help; goal met for this session.

## Model calls

| # | Purpose | Thinking | Effort | Outcome | Finish | ms | Prompt (cached) | Completion (reasoning) |
|---|---|---|---|---|---|---|---|---|
| 1 | BINDING | disabled |  | ACCEPTED | stop | 1344 | 5806 (5632) | 28 (0) |
| 2 | REPLY | disabled |  | ACCEPTED | stop | 1950 | 9381 (7936) | 145 (0) |
| 3 | READING | disabled |  | ACCEPTED | stop | 1555 | 7691 (6656) | 74 (0) |
| 4 | REPLY | disabled |  | ACCEPTED | stop | 2131 | 9542 (6656) | 228 (0) |
| 5 | REPLY | disabled |  | ACCEPTED | stop | 2543 | 10070 (6656) | 253 (0) |
| 6 | REPLY | disabled |  | ACCEPTED | stop | 2670 | 10290 (6656) | 304 (0) |
| 7 | REPLY | disabled |  | ACCEPTED | stop | 2297 | 10571 (6656) | 287 (0) |
| 8 | REPLY | disabled |  | ACCEPTED | stop | 2547 | 10778 (6656) | 366 (0) |
| 9 | REPLY | disabled |  | ACCEPTED | stop | 2879 | 10706 (6656) | 330 (0) |
| 10 | REPLY | disabled |  | ACCEPTED | stop | 3014 | 10846 (6656) | 418 (0) |
| 11 | REPLY | disabled |  | ACCEPTED | stop | 2825 | 10915 (6656) | 367 (0) |
| 12 | REPLY | disabled |  | ACCEPTED | stop | 2106 | 10823 (6656) | 262 (0) |
| 13 | REPLY | disabled |  | ACCEPTED | stop | 2961 | 10815 (6656) | 390 (0) |
| 14 | REPLY | disabled |  | ACCEPTED | stop | 2362 | 10900 (6656) | 259 (0) |
| 15 | REPLY | disabled |  | ACCEPTED | stop | 2410 | 10889 (6656) | 261 (0) |
| 16 | REPLY | disabled |  | ACCEPTED | stop | 2524 | 11062 (6656) | 257 (0) |
| 17 | REPLY | disabled |  | ACCEPTED | stop | 2241 | 10915 (6656) | 274 (0) |
| 18 | REPLY | disabled |  | REJECTED | stop | 2797 | 10910 (6656) | 352 (0) |
| 19 | REPAIR | disabled |  | ACCEPTED_AFTER_REPAIR | stop | 2195 | 11360 (11136) | 351 (0) |
| 20 | REPLY | disabled |  | REJECTED | stop | 1947 | 10775 (6656) | 236 (0) |
| 21 | REPAIR | disabled |  | ACCEPTED_AFTER_REPAIR | stop | 1722 | 11102 (10880) | 239 (0) |
| 22 | REPLY | disabled |  | ACCEPTED | stop | 2869 | 10752 (6656) | 264 (0) |
| 23 | REPLY | disabled |  | ACCEPTED | stop | 2404 | 10720 (6656) | 270 (0) |
| 24 | REPLY | disabled |  | ACCEPTED | stop | 2475 | 10982 (6656) | 271 (0) |
| 25 | REPLY | disabled |  | ACCEPTED | stop | 2023 | 10890 (6656) | 237 (0) |
| 26 | REPLY | disabled |  | ACCEPTED | stop | 2446 | 10775 (6656) | 299 (0) |
| 27 | REPLY | disabled |  | ACCEPTED | stop | 2514 | 10959 (6656) | 257 (0) |
| 28 | REPLY | disabled |  | ACCEPTED | stop | 2134 | 10974 (6656) | 256 (0) |
| 29 | REPLY | disabled |  | ACCEPTED | stop | 2317 | 10982 (6656) | 271 (0) |
| 30 | REPLY | disabled |  | ACCEPTED | stop | 2917 | 11000 (6656) | 261 (0) |
| 31 | REPLY | disabled |  | ACCEPTED | stop | 2380 | 10884 (6656) | 250 (0) |
| 32 | REPLY | disabled |  | ACCEPTED | stop | 2240 | 10880 (6656) | 295 (0) |
| 33 | REPLY | disabled |  | ACCEPTED | stop | 2542 | 10734 (6656) | 274 (0) |
| 34 | REPLY | disabled |  | ACCEPTED | stop | 2692 | 11144 (6656) | 315 (0) |
| 35 | REPLY | disabled |  | ACCEPTED | stop | 2015 | 10966 (6656) | 230 (0) |
