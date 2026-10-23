# descent-2 — MOCK (simulated tutor; not a live result, not evidence of learning, latency not meaningful)

| # | Learner input | Calls (purpose: reasoning) | Wait ms |
|---|---|---|---|
| 0 | (start on Q07) | 2 — BINDING: disabled (binding call); REPLY: disabled (per call: none of the three cases) | 8 |
| 1 | 32/3 | 1 — REPLY: disabled (per call: none of the three cases) | 7 |
| 2 | 5x+40 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 3 | 9x+9 | 1 — REPLY: disabled (per call: none of the three cases) | 7 |
| 4 | I'm stuck: HINT | 1 — REPLY: disabled (per call: none of the three cases) | 9 |
| 5 | A,C | 1 — REPLY: disabled (per call: none of the three cases) | 7 |
| 6 | 3x+12 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 7 | A | 1 — REPLY: disabled (per call: none of the three cases) | 17 |
| 8 | 16x+88 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 9 | 12 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 10 | 0 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 11 | C | 1 — REPLY: disabled (per call: none of the three cases) | 7 |
| 12 | 6 | 1 — REPLY: disabled (per call: none of the three cases) | 9 |

## Conversation as the student saw it

- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 9(x + 1). Write your answer without parentheses.
- **student (message):** 9x+9
- **app (result):** Correct. / On 9(x + 1) you answered 9x+9.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Which of these expressions are equal to 8(x + 9) for every value of x? Select every one that is. / A) 8x + 72 / B) x + 72 / C) 8 · x + 8 · 9 / D) x + 17
- **student (message):** I'm stuck: give me a hint.
- **tutor (message):** Here is a hint: look at what is being done to x, and in what order. What could you undo first while keeping both sides equal?
- **student (message):** A,C
- **app (result):** Correct. / On 8(x + 9) you chose A and C.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 3(x + 4). Write your answer without parentheses.
- **student (message):** 3x+12
- **app (result):** Correct. / On 3(x + 4) you answered 3x+12.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Which of these expressions are equal to 9(x + 5) for every value of x? Select every one that is. / A) 9x + 45 / B) x + 14 / C) 9x + 5 / D) x + 45
- **student (message):** A
- **app (result):** Correct. / On 9(x + 5) you chose A.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 8(2x + 11). Write your answer without parentheses.
- **student (message):** 16x+88
- **app (result):** Correct. / On 8(2x + 11) you answered 16x+88.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses. Let's go back to the topic you started with.
- **tutor (problem):** Solve 5(x − 7) = 25 for x.
- **student (message):** 12
- **app (result):** Correct. / On 5(x − 7) = 25 you answered 12.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Solve 5(x − 9) = −45 for x.
- **student (message):** 0
- **app (result):** Correct. / On 5(x − 9) = −45 you answered 0.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Here is an equation: 6(x − 7) = −6. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide only the left side by 6: x − 7 = −6 / B) Add 7 to undo the −7: 6x = 1 / C) Multiply both terms in the parentheses by 6: 6x − 42 = −6 / D) Multiply only the x by 6: 6x − 7 = −6
- **student (message):** C
- **app (result):** Correct. / On 6(x − 7) = −6 you chose C.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Solve 4(x − 4) + 7 = 15 for x.
- **student (message):** 6
- **app (result):** Correct. / On 4(x − 4) + 7 = 15 you answered 6.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal. That completes the work planned for this session. You can look at your summary now.
- **app (notice):** This session has ended. Your summary is ready.

## Verdicts recorded by code

| Problem | Purpose | Entry | Read as | Verdict | Help | Slip |
|---|---|---|---|---|---|---|
| Solve 6(x + 2) = 66 for x. | PRACTICE | 32/3 | 32/3 | INCORRECT |  |  |
| Expand 5(x + 8). Write your answer without parentheses. | PRACTICE | 5x+40 | 5x+40 | CORRECT |  |  |
| Expand 9(x + 1). Write your answer without parentheses. | CHECK | 9x+9 | 9x+9 | CORRECT |  |  |
| Which of these expressions are equal to 8(x + 9) for every value of x? Select every one that is. / A) 8x + 72 / B) x + 72 / C) 8 · x + 8 · 9 / D) x + 17 | CHECK | A,C | A,C | CORRECT | HINT |  |
| Expand 3(x + 4). Write your answer without parentheses. | CHECK | 3x+12 | 3x+12 | CORRECT |  |  |
| Which of these expressions are equal to 9(x + 5) for every value of x? Select every one that is. / A) 9x + 45 / B) x + 14 / C) 9x + 5 / D) x + 45 | CHECK | A | A | CORRECT |  |  |
| Expand 8(2x + 11). Write your answer without parentheses. | CHECK | 16x+88 | 16x+88 | CORRECT |  |  |
| Solve 5(x − 7) = 25 for x. | PRACTICE | 12 | 12 | CORRECT |  |  |
| Solve 5(x − 9) = −45 for x. | CHECK | 0 | 0 | CORRECT |  |  |
| Here is an equation: 6(x − 7) = −6. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Divide only the left side by 6: x − 7 = −6 / B) Add 7 to undo the −7: 6x = 1 / C) Multiply both terms in the parentheses by 6: 6x − 42 = −6 / D) Multiply only the x by 6: 6x − 7 = −6 | CHECK | C | C | CORRECT |  |  |
| Solve 4(x − 4) + 7 = 15 for x. | CHECK | 6 | 6 | CORRECT |  |  |
