# descent-1 — MOCK (simulated tutor; not a live result, not evidence of learning, latency not meaningful)

| # | Learner input | Calls (purpose: reasoning) | Wait ms |
|---|---|---|---|
| 0 | (start on Q07) | 2 — BINDING: disabled (binding call); REPLY: disabled (per call: none of the three cases) | 6 |
| 1 | 13/5 | 1 — REPLY: disabled (per call: none of the three cases) | 5 |
| 2 | 8x+24 | 1 — REPLY: disabled (per call: none of the three cases) | 6 |
| 3 | 2x+2 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 4 | I'm stuck: HINT | 1 — REPLY: disabled (per call: none of the three cases) | 5 |
| 5 | C | 1 — REPLY: disabled (per call: none of the three cases) | 6 |
| 6 | 7x+21 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 7 | C,D | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 8 | 12x+12 | 1 — REPLY: disabled (per call: none of the three cases) | 12 |
| 9 | 10 | 1 — REPLY: disabled (per call: none of the three cases) | 10 |
| 10 | -1 | 1 — REPLY: disabled (per call: none of the three cases) | 11 |
| 11 | A | 1 — REPLY: disabled (per call: none of the three cases) | 11 |
| 12 | -2 | 1 — REPLY: disabled (per call: none of the three cases) | 11 |

## Conversation as the student saw it

- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 2(x + 1). Write your answer without parentheses.
- **student (message):** 2x+2
- **app (result):** Correct. / On 2(x + 1) you answered 2x+2.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Which of these expressions are equal to 5(x + 8) for every value of x? Select every one that is. / A) 5x + 8 / B) x + 40 / C) 5x + 40 / D) x + 13
- **student (message):** I'm stuck: give me a hint.
- **tutor (message):** Here is a hint: look at what is being done to x, and in what order. What could you undo first while keeping both sides equal?
- **student (message):** C
- **app (result):** Correct. / On 5(x + 8) you chose C.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 7(x + 3). Write your answer without parentheses.
- **student (message):** 7x+21
- **app (result):** Correct. / On 7(x + 3) you answered 7x+21.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Which of these expressions are equal to 7(x + 2) for every value of x? Select every one that is. / A) 7x + 2 / B) x + 9 / C) 7 · x + 7 · 2 / D) 7x + 14
- **student (message):** C,D
- **app (result):** Correct. / On 7(x + 2) you chose C and D.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 6(2x + 2). Write your answer without parentheses.
- **student (message):** 12x+12
- **app (result):** Correct. / On 6(2x + 2) you answered 12x+12.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses. Let's go back to the topic you started with.
- **tutor (problem):** Solve 4(x − 8) = 8 for x.
- **student (message):** 10
- **app (result):** Correct. / On 4(x − 8) = 8 you answered 10.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Solve 9(x + 9) = 72 for x.
- **student (message):** -1
- **app (result):** Correct. / On 9(x + 9) = 72 you answered -1.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Here is an equation: 7(x − 4) = 14. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply both terms in the parentheses by 7: 7x − 28 = 14 / B) Divide only the left side by 7: x − 4 = 14 / C) Add 4 to undo the −4: 7x = 18 / D) Multiply only the x by 7: 7x − 4 = 14
- **student (message):** A
- **app (result):** Correct. / On 7(x − 4) = 14 you chose A.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Solve 9(x + 8) + 1 = 55 for x.
- **student (message):** -2
- **app (result):** Correct. / On 9(x + 8) + 1 = 55 you answered -2.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal. That completes the work planned for this session. You can look at your summary now.
- **app (notice):** This session has ended. Your summary is ready.

## Verdicts recorded by code

| Problem | Purpose | Entry | Read as | Verdict | Help | Slip |
|---|---|---|---|---|---|---|
| Solve 5(x + 7) = 20 for x. | PRACTICE | 13/5 | 13/5 | INCORRECT |  |  |
| Expand 8(x + 3). Write your answer without parentheses. | PRACTICE | 8x+24 | 8x+24 | CORRECT |  |  |
| Expand 2(x + 1). Write your answer without parentheses. | CHECK | 2x+2 | 2x+2 | CORRECT |  |  |
| Which of these expressions are equal to 5(x + 8) for every value of x? Select every one that is. / A) 5x + 8 / B) x + 40 / C) 5x + 40 / D) x + 13 | CHECK | C | C | CORRECT | HINT |  |
| Expand 7(x + 3). Write your answer without parentheses. | CHECK | 7x+21 | 7x+21 | CORRECT |  |  |
| Which of these expressions are equal to 7(x + 2) for every value of x? Select every one that is. / A) 7x + 2 / B) x + 9 / C) 7 · x + 7 · 2 / D) 7x + 14 | CHECK | C,D | C,D | CORRECT |  |  |
| Expand 6(2x + 2). Write your answer without parentheses. | CHECK | 12x+12 | 12x+12 | CORRECT |  |  |
| Solve 4(x − 8) = 8 for x. | PRACTICE | 10 | 10 | CORRECT |  |  |
| Solve 9(x + 9) = 72 for x. | CHECK | -1 | -1 | CORRECT |  |  |
| Here is an equation: 7(x − 4) = 14. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply both terms in the parentheses by 7: 7x − 28 = 14 / B) Divide only the left side by 7: x − 4 = 14 / C) Add 4 to undo the −4: 7x = 18 / D) Multiply only the x by 7: 7x − 4 = 14 | CHECK | A | A | CORRECT |  |  |
| Solve 9(x + 8) + 1 = 55 for x. | CHECK | -2 | -2 | CORRECT |  |  |
