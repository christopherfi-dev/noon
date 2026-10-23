# Tutor sessions — mock

MOCK (simulated tutor; not a live result, not evidence of learning, latency not meaningful)

Generated 2026-10-23T04:36:13Z by `app/tools/tutor_sessions.php`.

**MOCK:** the tutor is the scripted mock. These numbers show how the loop spends calls; they are not live results, not evidence of learning, and the waits are not a latency measurement.

## Calls per turn

| Calls in the turn | Turns |
|---|---|
| 0 | 2 |
| 1 | 121 |
| 2 | 16 |

Turns with exactly one call: 121 of 139 (87%). The first turn of each session also makes the architecture-binding call; a turn with an entry the AI must read makes a reading call first; a rejected output makes one repair call.

## Learner wait (§3.7: median ≤ 4000 ms, slowest ≤ 10000 ms)

Median 7 ms, slowest 29 ms over 139 turns. (Mock: not meaningful.)

## Sessions

| Session | Turns | Calls | Answers recorded | Goals met (code) | Tokens prompt / completion / reasoning |
|---|---|---|---|---|---|
| B1-1 | 12 | 13 | 11 | Q07, Q06 | 0 / 0 / 0 |
| B1-2 | 12 | 13 | 11 | Q07, E06 | 0 / 0 / 0 |
| B1-3 | 12 | 13 | 11 | Q07, Q06 | 0 / 0 / 0 |
| B1-4 | 12 | 13 | 11 | Q07, Q06 | 0 / 0 / 0 |
| B1-5 | 12 | 13 | 11 | Q07, E06 | 0 / 0 / 0 |
| descent-1 | 13 | 14 | 11 | E06, Q07 | 0 / 0 / 0 |
| descent-2 | 13 | 14 | 11 | E06, Q07 | 0 / 0 / 0 |
| descent-3 | 13 | 14 | 11 | E06, Q07 | 0 / 0 / 0 |
| B11-1 | 10 | 10 | 5 | Q06 | 0 / 0 / 0 |
| B11-2 | 10 | 10 | 5 | E06 | 0 / 0 / 0 |
| N1-1 | 10 | 13 | 8 | E06 | 0 / 0 / 0 |
| N1-2 | 10 | 13 | 8 | E06 | 0 / 0 / 0 |

## Rejected AI output (0)

None.

## Clarifying questions (0)

None.

## Slips

Confirmed by code (2):

- N1-1: 6x + 20 on Expand 6(x + 5). Write your answer without parentheses.
- N1-2: 7x + 74 on Expand 7(x + 12). Write your answer without parentheses.

Claimed by the AI and rejected (0):

None.

## Forward-looking words before a check question (audit list, 0)

None.

## HOLDs

0
