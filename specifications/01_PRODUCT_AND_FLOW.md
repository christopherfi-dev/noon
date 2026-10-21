# 01 — Product, screens and instructional entry

## Aim

A learner can start at a known algebra target or find a reasonable provisional
starting point, receive evidence-led teaching, repair relevant prerequisites,
and return to independent work on the original target. Curriculum availability,
runtime coverage and learning evidence are separate facts.

## Screens

1. **Welcome:** Learn algebra; Help me find a starting point; My teacher gave
   me a task. A secondary 'Prepare a task' opens the account-free setup screen.
2. **Topic chooser:** readable topic groups derived from curriculum domains;
   expand to plain-language skill titles, search by title or ID, show core /
   foundation / extension and honest runtime availability. Do not present 205
   undifferentiated choices. No RIT or grade-band gate.
3. **Problem entry:** paste/type a problem or describe class work. No image
   upload/OCR in the first release. Offer a suggested canonical skill and ask
   confirmation. A suggestion cannot itself start scoring or change policy.
4. **Starting-point check:** optional class-topic question including 'Not sure',
   then one task at a time, progress as 'Question 2 of up to 6', with no claimed
   percentage ability. Allow skip, not learned, help, topic switch and stop.
5. **Recommendation:** one proposed target, a short evidence-based reason and
   'Start here' / 'Choose another topic'. State that other topics remain untested.
6. **Learning workspace:** active topic, current task, answer box, separate
   optional working/explanation field, Submit, Hint, Explain this, Not learned,
   Skip, Change topic and End session. Math entry examples are type-specific.
7. **Task setup:** choose one target, optional public task title/instructions
   and session duration (10–60 minutes). A generated public link pre-fills that
   target in the learner's new session. A teacher can also launch in this browser.
8. **Session summary:** current browser's evidence, assistance, repairs, return
   outcomes, unresolved scope and next suggested evidence; download JSON and
   print-friendly HTML. It is a session summary, not a private teacher dashboard.

## Three entry routes

| Route | Trusted action | First instructional operation |
|---|---|---|
| Known topic | SELECT_TARGET with canonical ID | Representative accepted target item |
| Pasted/described task | SUGGEST_TARGET then CONFIRM_TARGET | Same after confirmation |
| Unsure | START_ORIENTATION | Host-controlled bounded orientation |
| Public teacher task | APPLY_TASK after public settings validation | Independent check of its target |

Host controls render the welcome and menus without a model call. Numeric chat
choices are optional accessibility alternatives resolved against a retained
menu_id, option_id map and revision; never match an earlier model sentence.

## Orientation algorithm (authored policy; not calibrated placement)

Budget six presented items, hard cap eight for unresolved clarification. Use
the seed definitions in examples/entry-probes.json. Generate/instantiate a fresh
accepted item inside the seed scope, not the published anchor answer. The
orientation selector is deterministic host code, with recorded selection reason.

- No class topic: start at Q06. Known class topic: choose the supported entry
  seed closest to that topic. Advanced topic without a supported seed: offer
  its target check or explain the capability limit rather than imply low ability.
- For the Q06 seed, a correct response can sample Q07, then Q08. An incorrect
  response samples Q04 or Q05 to narrow a useful instructional starting trial.
- Difficulty with Q07 can sample E06. Difficulty with signed-number work can
  sample N03, when the item and evidence warrant that branch.
- Correct responses are item-level observations. Never transitively pass
  prerequisites or promote a skill from one orientation item.
- Choose at most two probes of the same skill. Help marks the item assisted;
  'not learned', skip and ambiguity do not count as incorrect. Repeated lack of
  usable evidence leads to a provisional introductory trial or topic choice.
- Stop with at least one scoreable observation plus a useful narrowed starting
  trial, or at the budget. At zero usable observations explicitly recommend a
  trial, not an evidence-established placement. Selection can remain unresolved.
- Recommended target = nearest observed unsuccessful supported target whose
  relevant lower probe succeeded, or the next target needing evidence after
  observed successes. The policy is a practical routing heuristic.
- Orientation does not teach between items, does not reveal solutions, and does
  not count toward an immediate-success streak. If the learner needs teaching,
  offer to end orientation and accept the target for instruction.

## Teaching behavior

The state machine, not the UI, controls item roles. A wrong answer produces a
diagnostic opportunity, not an automatic diagnosis. Keep the target while
descending only through relevant declared edges. Vary the teaching method,
fade support, use fresh independent work, then test the parent target again.
Support requests are allowed at any point; update support provenance before
displaying a hint. A spontaneous explanation can be assessed only by the
declared rubric route. Do not equate verbosity with understanding.

## Initial teacher boundary

The account-free task creator is public. It cannot lock another learner's
settings, grant manual scoring authority, browse sessions, attach historical
'passes', or receive an automatic report. A task token carries settings, never
session credentials. The student may change topic; record the change.
Reports can be shown/exported by that learner. Multi-user assignment tracking,
authenticated teacher review, parent accounts and rostering are later features.

## Presentation and accessibility

Responsive at 360px width and desktop. Keyboard-operable controls, visible
focus, labels, readable contrast, and screen-reader progress/status messages.
Use a pinned bundled math renderer with a text/accessible fallback. Render
user/model text safely; disable raw HTML and unsafe TeX commands. Math cannot
overflow the page. Preserve inputs on recoverable errors. Distinguish sending,
waiting, retry and accepted states. Do not show raw provider errors or internals.
Avoid rewards/celebration that obscure mathematical feedback. Product copy
uses 'demonstrated in this session', not unqualified 'mastered'.
