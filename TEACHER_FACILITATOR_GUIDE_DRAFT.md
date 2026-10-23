# Facilitator guide — draft for the beta

This describes how the beta is meant to be used. It is not a report of an
observed learner session, and the beta has not been tested with learners yet.
Until a live provider is configured, the tutor runs on a simulated model and
says so on every screen and report ("Simulated tutor (mock mode)").

Updated after the architect's rulings on the round UI v3 report: the working
box is gone; the session report, its download and the progress code are new.

## Before a session

The beta covers ten skills, with Solve equations containing parentheses as
the default topic. The full curriculum map can be browsed, but only skills
marked as available can be practised; the rest are not yet supported and are
never treated as gaps in a learner's knowledge. Check the label: for most beta
skills only the numeric part is checked, so a goal met there does not cover
the explanation or modelling parts of that skill.

Teacher task links ("Prepare a task") are not in the beta. Choose the topic
with the learner on the welcome screen. There is no class dashboard and no
teacher account: results belong to the learner's browser session, so sit with
the learner or ask them to share their downloaded report.

## During a session

The learner types answers only; the tutor does not read any working, and the
page has no working box. If an adult supplies a method or answer outside the
app, treat the result as work with help: the app cannot detect help it is not
told about. Help inside the app ("I'm stuck": a hint, an explanation, "I
haven't learned this yet" or, on a step-by-step question, "I don't know") is
recorded automatically, and an answer given after a hint starts the count of
correct answers in a row again.

Let the tutor investigate a wrong answer before explaining the whole solution.
It may check a building block, work through it step by step with questions,
and then return to the topic. If it does not return, the topic is unfinished
and that return still needs to be checked. Stopping, skipping, saying "I
haven't learned this yet" and changing topic are not mathematical failures.

The goal for a skill is 3 correct answers in a row without help in one
session (for the two skills with three kinds of question, all three kinds).
The dots above each counted question show the run so far.

## The progress code

From the learner's first answer, the screen shows "Your code", for example
`K7QM-4XRT-9WPC`. It carries the learner's skill results into a later session:

- On the welcome screen, "Continue with a progress code" shows a "Welcome
  back" summary and a Start button. Lower case, spaces and dashes are all
  accepted.
- If that session is still open, Start continues it exactly where it stopped,
  on whichever device the code is entered. The earlier device then shows "This
  progress continued on another device." and accepts no more answers. Use one
  device per learner at a time.
- Otherwise Start begins a new session at the skill that still needs work,
  usually the building block being repaired, and then offers "Next:" back to
  the original topic.
- Results from earlier sessions are listed under "From earlier sessions" with
  their dates. They never count as goals met in the new session.

Treat a code like a password: anyone with the code, or with the downloaded
report that shows it, can continue that learner's progress. Do not post codes
where other learners can see them, and do not collect them in a shared list.
The server keeps only skill results (status, date and observed mistake
patterns) for up to 90 days after the code was last used, and no names.
"Forget this session" deletes the session, its summary and its code. "Start
over" begins a fresh session and leaves an existing code as it is.

## Reading the report

At the end of a session, the learner sees four headings in plain words: What
you did well; Where you had trouble; What we worked on; What to work on next
time. A heading with nothing to report shows a fixed line rather than invented
praise or problems. "Practise with your teacher: … (not in this tutor yet)"
names a skill outside the beta that the learner's mistakes pointed to while a
goal that depends on it was still unmet: that is for you to pick up.

"Details for teachers" holds the full record, with skill IDs in brackets:
answers with and without help per skill, what the tutor observed (mistake
patterns are observations, not confirmed causes), the route taken, step-by-
step work, what is still open, and what would show more. Keep these apart:

- a goal met is a check from that session only, not mastery or retention;
- a new kind of question answered without help shows only that form;
- work with help never counts toward a goal;
- an unsupported or unavailable skill is a limit of the app, not of the
  learner, and untested skills have no pass or fail label.

To share the report, the learner chooses "Download report". This saves one
file, `algebra-report-YYYY-MM-DD.html`, which opens in any browser without a
connection and contains the four headings, the teacher details, the date and
the progress code. "Print or save as PDF" prints the same content. The raw
record (JSON) is inside "Details for teachers" for anyone who needs the data.

## Pilot observations to collect

Record whether each question was clear, whether a correct response was
rejected or an incorrect one accepted, whether a hint helped, whether the
building-block route was relevant, whether the learner could return to the
original topic without help, and whether continuing with a code worked as
expected. Record exact examples before changing anything. Avoid collecting
names or unnecessary details.

Evidence that learning lasts needs later work without help, collected with
its timing; a second question in the same session is not retention. Report
learning outcomes only after real learner testing.
