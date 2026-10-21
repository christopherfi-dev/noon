# Expected walkthrough — synthetic implementation scenario

No real learner data. This is a development script, not evidence of efficacy.

1. Student chooses a topic and confirms Q07, equations containing parentheses.
2. The host accepts a fresh task: Solve 3(x − 2) = 15.
3. Student submits the scalar response `17/3` with work `3x − 2 = 15`.
4. The numeric checker rejects that value. The visible transformation supports
   the observation that this expansion did not multiply every term. It does
   not yet establish the student's general understanding or a permanent cause.
5. The engine asks a discriminating distribution task under E06, such as
   expanding 2(x + 4). A second incomplete expansion supports a bounded pattern.
6. The host retains Q07 as the original target and E06 as the current repair.
   It records the relevant declared prerequisite edge and triggering events.
7. The engine teaches distribution using a representation or explanation that
   matches the learner's response, then fades support. These are assisted items.
8. Fresh independent expansion tasks follow. The implemented factory must
   provide the required variation and conceptual check, not just three copies
   with new numbers. If only expansion checking exists, report its component
   criterion and keep other E06 requirements unresolved.
9. When the declared repair/resumption conditions are satisfied, return to a
   fresh Q07 task, e.g. 4(x + 1) = 28, whose numeric solution is 6. No automatic
   parent pass follows from E06 success. Whole-target criteria require the
   remaining fresh independent variants/components.
10. Report observed work, support, bounded hypothesis, repair, return outcome,
    untested requirements and next evidence. Do not say the student has mastered
    all equations or has a confirmed stable misconception.

For the first mock demo, use fixed scripted student responses to exercise these
branches. The real application must not force every learner down this route.
