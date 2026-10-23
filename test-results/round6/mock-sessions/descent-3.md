# descent-3 — MOCK (simulated tutor; not a live result, not evidence of learning, latency not meaningful)

| # | Learner input | Calls (purpose: reasoning) | Wait ms |
|---|---|---|---|
| 0 | (start on Q07) | 2 — BINDING: disabled (binding call); REPLY: disabled (per call: none of the three cases) | 5 |
| 1 | -15/4 | 1 — REPLY: disabled (per call: none of the three cases) | 6 |
| 2 | 4x+48 | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 3 | 3x+18 | 1 — REPLY: disabled (per call: none of the three cases) | 9 |
| 4 | I'm stuck: HINT | 1 — REPLY: disabled (per call: none of the three cases) | 8 |
| 5 | A,C | 1 — REPLY: disabled (per call: none of the three cases) | 9 |
| 6 | 3x+30 | 1 — REPLY: disabled (per call: none of the three cases) | 9 |
| 7 | D | 1 — REPLY: disabled (per call: none of the three cases) | 9 |
| 8 | 15x+60 | 1 — REPLY: disabled (per call: none of the three cases) | 10 |
| 9 | 1 | 1 — REPLY: disabled (per call: none of the three cases) | 10 |
| 10 | 2 | 1 — REPLY: disabled (per call: none of the three cases) | 11 |
| 11 | C | 1 — REPLY: disabled (per call: none of the three cases) | 10 |
| 12 | -8 | 1 — REPLY: disabled (per call: none of the three cases) | 10 |

## Conversation as the student saw it

- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 3(x + 6). Write your answer without parentheses.
- **student (message):** 3x+18
- **app (result):** Correct. / On 3(x + 6) you answered 3x+18.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Which of these expressions are equal to 6(x + 8) for every value of x? Select every one that is. / A) 6x + 48 / B) x + 48 / C) 6 · x + 6 · 8 / D) x + 14
- **student (message):** I'm stuck: give me a hint.
- **tutor (message):** Here is a hint: look at what is being done to x, and in what order. What could you undo first while keeping both sides equal?
- **student (message):** A,C
- **app (result):** Correct. / On 6(x + 8) you chose A and C.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 3(x + 10). Write your answer without parentheses.
- **student (message):** 3x+30
- **app (result):** Correct. / On 3(x + 10) you answered 3x+30.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Which of these expressions are equal to 9(x + 7) for every value of x? Select every one that is. / A) x + 63 / B) x + 16 / C) 9x + 7 / D) 9 · x + 9 · 7
- **student (message):** D
- **app (result):** Correct. / On 9(x + 7) you chose D.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses.
- **app (check):** Expand 5(3x + 12). Write your answer without parentheses.
- **student (message):** 15x+60
- **app (result):** Correct. / On 5(3x + 12) you answered 15x+60.
- **tutor (message):** Yes: you used the distributive property: the number outside multiplies every term inside the parentheses. Let's go back to the topic you started with.
- **tutor (problem):** Solve 3(x − 5) = −12 for x.
- **student (message):** 1
- **app (result):** Correct. / On 3(x − 5) = −12 you answered 1.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Solve 8(x + 8) = 80 for x.
- **student (message):** 2
- **app (result):** Correct. / On 8(x + 8) = 80 you answered 2.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Here is an equation: 2(x − 7) = −22. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply only the x by 2: 2x − 7 = −22 / B) Divide only the left side by 2: x − 7 = −22 / C) Divide both sides by 2: x − 7 = −11 / D) Add 7 to undo the −7: 2x = −15
- **student (message):** C
- **app (result):** Correct. / On 2(x − 7) = −22 you chose C.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal.
- **app (check):** Solve −42 = 3(x − 6) for x.
- **student (message):** -8
- **app (result):** Correct. / On −42 = 3(x − 6) you answered -8.
- **tutor (message):** Yes: you undid the multiplication by the number outside the parentheses and then the number inside, keeping both sides equal. That completes the work planned for this session. You can look at your summary now.
- **app (notice):** This session has ended. Your summary is ready.

## Verdicts recorded by code

| Problem | Purpose | Entry | Read as | Verdict | Help | Slip |
|---|---|---|---|---|---|---|
| Solve 8(x + 6) = −24 for x. | PRACTICE | -15/4 | -15/4 | INCORRECT |  |  |
| Expand 4(x + 12). Write your answer without parentheses. | PRACTICE | 4x+48 | 4x+48 | CORRECT |  |  |
| Expand 3(x + 6). Write your answer without parentheses. | CHECK | 3x+18 | 3x+18 | CORRECT |  |  |
| Which of these expressions are equal to 6(x + 8) for every value of x? Select every one that is. / A) 6x + 48 / B) x + 48 / C) 6 · x + 6 · 8 / D) x + 14 | CHECK | A,C | A,C | CORRECT | HINT |  |
| Expand 3(x + 10). Write your answer without parentheses. | CHECK | 3x+30 | 3x+30 | CORRECT |  |  |
| Which of these expressions are equal to 9(x + 7) for every value of x? Select every one that is. / A) x + 63 / B) x + 16 / C) 9x + 7 / D) 9 · x + 9 · 7 | CHECK | D | D | CORRECT |  |  |
| Expand 5(3x + 12). Write your answer without parentheses. | CHECK | 15x+60 | 15x+60 | CORRECT |  |  |
| Solve 3(x − 5) = −12 for x. | PRACTICE | 1 | 1 | CORRECT |  |  |
| Solve 8(x + 8) = 80 for x. | CHECK | 2 | 2 | CORRECT |  |  |
| Here is an equation: 2(x − 7) = −22. Which of these first steps give an equation with exactly the same solution? Select every one that does. / A) Multiply only the x by 2: 2x − 7 = −22 / B) Divide only the left side by 2: x − 7 = −22 / C) Divide both sides by 2: x − 7 = −11 / D) Add 7 to undo the −7: 2x = −15 | CHECK | C | C | CORRECT |  |  |
| Solve −42 = 3(x − 6) for x. | CHECK | -8 | -8 | CORRECT |  |  |
