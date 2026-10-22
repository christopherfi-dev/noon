# Conductor Round UI — What the Student Sees

Place in the repository root and read before acting. Record this round as a
separate interval in `BUILD_LOG.md`. Earlier conductor files still govern.

**Scope.** This round changes presentation and learner-facing wording only.
Engine logic, evidence rules, checkers and model handling are unchanged, and
every existing behavioural test keeps its assertions. The engine must still run
on PHP 8.0.

**Offline only:** no live run, no key, nothing deployed.

## 1. Changes

**U1. Show the problem the feedback is about.** When feedback refers to an
earlier answer, show that problem and the learner's answer with it. For
example, "On 8(x + 5) = 64 you answered 13." then the observation. Feedback must
never mention numbers that appear only in a problem the learner can no longer
see.

Test: in the B1 journey, the feedback block that precedes the first guided
problem contains the missed problem and the learner's answer.

**U2. Two-level session summary.**
- **Top, for the learner.** Four short parts in plain words:
  - what you worked on, by skill title, with no IDs;
  - goals met;
  - what was hard: the skills where help was needed;
  - what to practise next.
- **Below, behind a "Details for teachers" control** (collapsed by default):
  the full record, as now.
- Remove raw codes from both levels, for example `AVAILABLE_SCORED`,
  `all_of edge`, `Q07`, `E06`, `A07`. The teacher view may show skill IDs in
  brackets after titles. Replace these terms with plain words in both levels:
  - declared prerequisite
  - descent / descended
  - implicated
  - branch
  - transfer variant
  - retention claim
  - faded support
  - coverage
- Remove duplicate lines. For example, the same observation is listed twice.
- Under 600px the per-skill table becomes stacked cards. No cell may wrap a
  word letter by letter.

Extend the summary wording test to the new terms. Every truth rule from rounds
5 and 5.1 still applies to both levels.

**U3. Multiple-choice items.**
- Each checkbox label is the full option text, for example
  "A) Multiply only the x by 9: 9x − 2 = −81". Do not repeat the options in the
  question stem.
- Every equation or expression is wrapped so it never breaks across lines
  (`white-space: nowrap` on a math span). The surrounding sentence may wrap.
- The question stem is normal weight. Only the item's main expression may be
  emphasised.

**U4. Remove the "Working (optional)" box.** Nothing reads it, and learners
would reasonably assume the tutor does. The API keeps accepting an empty
`working` field so nothing else changes. Update the privacy notice and the
summary to stop mentioning working. Record this in the capability matrix as
"removed until a reader exists".

**U5. Fewer buttons.**
- **Independent items:** Submit, "I'm stuck", Skip (Skip only where the
  engine allows it). "I'm stuck" opens three choices:
  - "Give me a hint" (hint action)
  - "Explain the idea" (explanation action)
  - "I haven't learned this yet" (not-learned action)
- **Guided steps:** Submit and "I'm stuck", which opens:
  - "Give me a hint"
  - "I don't know"
- Each choice sends the same action as today's button, and the engine
  behaviour is unchanged. Typed stuck entries (round 5) still work.

**U6. One notice per fact.** The guided-work notice appears once, as the tag
on the item. Remove the duplicate from the header text.

**U7. Visible goal progress.** On every scored item, show three dots that fill
for each correct answer in a row without help. Next to them, a short label such
as "2 of 3 in a row" and the kinds of item still needed, for example "still
needed: a step choice". When the streak resets, the dots empty. Screen readers
get the same information as text.

**U8. Correct and not-yet feedback look different.**
- Correct: a green-tinted block with a check icon and the word "Correct."
- Incorrect: a neutral block with the words "Not yet." This replaces "That is
  not correct." everywhere, matching the guided steps.
- Colour is never the only signal: an icon and words always accompany it.
- Both colours meet WCAG AA contrast in light and dark mode.

## 2. Unchanged

- The fonts, the base size, the layout width and the blue accent.
- The welcome screen, apart from removing mentions of working.
- Dark mode support.

## 3. Gate and report

All existing tests pass on PHP 8.0.30 and 8.3. Add or update tests for U1, U2,
U3, U5, U7 and U8. Browser tests run at mobile and desktop widths.

Rebuild both bundles. Report the sha256 of `algebra-engine-vps-upload.zip` and
the commit hash.

Save these screenshots in `test-results/browser/ui-round/`:
- the welcome screen;
- an independent scalar item;
- a multiple-choice item;
- feedback before the first guided problem;
- a guided step with "I'm stuck" open;
- the progress dots at 0, 1 and 2;
- the learner summary;
- the teacher details opened.

Take each one at both 390px and 1280px width. Keep the report short.

---
Owner note sent with this file: "From master architect.   You also have the autonomy to make it look as good as possible, but also  make sure the experience is as fluid as possible for a student"
