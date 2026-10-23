# Algebra Engine — AI tutor (round 8: architecture v1.6)

The AI runs the tutoring session as a conversation, governed by the Algebra
Constraint Architecture **v1.6**; code is its set of instruments and keeps the
guarantees that truth requires (`CONDUCTOR_ROUND_8.md`, `CONDUCTOR_ROUND_7.md`,
`CONDUCTOR_ROUND_6_v3.md`, `CLAUDE.md`). The requirements on code moved out of
the architecture are in `../architecture/HOST_REQUIREMENTS_v1.6.md`; they are
never sent to the model.

- code computes every answer, checks every entry and records every verdict;
- code alone computes goals (3 check questions correct in a row without help;
  for Q07 and E06 a solve or expansion, a choice question and a new form; one
  code-confirmed slip may pause the count);
- every problem, practice or check, is worded by code from its verified spec;
  check questions match a pattern of their skill, stand alone and never
  contain their own single-number answer (one that does after a repair is
  replaced by a prepared check problem); messages during an open check reach
  the AI, and help with it, from "I'm stuck" or from a tutor reply marked as
  help, is recorded before the reply is shown and makes the check practice;
- the gradebook is true, and code writes the report from it; an attempt with
  possible help before its check card or during it (the backstop), or a
  verdict the AI disputes, is held (counted neither for nor against the goal);
- text guards on every learner-visible AI text (answer leaks, internal terms,
  mastery and goal claims, claims about help, verdict contradiction) and two
  counters (steps re-solved after a correct answer; value questions with no
  problem posed) log their findings and never change the text; envelopes and
  problems that fail acceptance are repaired once, then the turn is held with
  a retry.

Ten skills are available (Q07, E06, Q06, Q05, Q04, N18, N03, N04, N05, N06);
all 205 curriculum skills stay browsable. The tests run on a **simulated
(mock) tutor**; the round 7 and round 8 live runs (`test-results/live/round7/`,
`test-results/live/round8/`) are scripted learners against DeepSeek, not
deployment or learning evidence. What is in scope and how each skill is
checked: `CAPABILITY_MATRIX.md`. Acceptance-scenario status:
`ACCEPTANCE_SCENARIOS.md`. Tests retired with the beta's state machine:
`tests/RETIRED_TESTS.md`. Deployment next to the beta: `DEPLOYMENT_VPS.md`.

## Layout

```
app/
  public/            web root (index.html, api.php, assets/)
  private/           everything else; never web-accessible
    bootstrap.php    loads autoloader + private config.php
    config.example.php
    src/             AlgebraEngine\ classes (see below)
    resources/       architecture v1.6, curriculum map, runtime contracts
    migrations/      SQLite schema (001–006)
    bin/             preflight.php, cleanup.php (cron)
    var/             SQLite database (git-ignored)
  tests/             PHPUnit Unit + Integration, Browser (Playwright)
  tools/             build_bundle.php, bundle_smoke.php, tutor_sessions.php, gen_capability_matrix.php, run_all_tests.sh
```

Main classes:

- `Tutor\TutorEngine`: one learner turn per request (transactions, leases,
  idempotency, revision checks), the context for the AI, applying its moves,
  problems and messages, and the commit.
- `Tutor\TutorCoordinator`: the binding call, one call per turn, the envelope
  checks and problem verification; one repair, then a hold. The text guards'
  findings are returned for logging.
- `Tutor\TutorPrompt`: the five-message request, built anew each turn: the
  architecture, the output contract, the binding exchange, one JSON block.
- `Tutor\MockTutor`: the scripted mock tutor and its adversarial faults.
- Instruments: `SpecMath`, `PatternMatcher` (the recipes in reverse),
  `ProblemFactory`, `ProblemVerifier`, `Recipes`, `EntryReader`,
  `ReadingCheck`, `AnswerCheck`, `SlipCheck`, `Gradebook`, `Goals`,
  `TextGuard`.
- `Tutor\TutorProjection`: the only view the browser gets.
- `Provider\ReasoningPolicy`: reasoning chosen per call.
- `Report\TutorReport`, `Report\SummaryView`, `Report\ReportHtml`: the report,
  written by code.
- Kept from the beta: `Items\Templates\*` (the 14 recipes), `Math\*`,
  `Checking\*`, `Progress\*`, `Storage\*`, `Http\Api`.

## Run locally

Requirements: PHP 8.0+ (the production server runs 8.0.30; the gate runs on
8.0 and 8.3) with `pdo_sqlite`, `mbstring`, `ctype`, `json` (and `curl` +
`openssl` for a live provider). Composer for development only (PHPUnit 9.6).

```bash
cd app
composer install                       # dev dependencies (PHPUnit) + opis/json-schema
php private/bin/preflight.php          # environment check
php -S 127.0.0.1:8000 -t public        # then open http://127.0.0.1:8000/
```

With no `private/config.php` the app uses mock mode and stores data in
`private/var/algebra.sqlite`. To keep test data elsewhere set
`ALGEBRA_CONFIG=/path/to/config.php` (a PHP file returning an array; keys as
in `config.example.php`). The PHP built-in server is for local development
only.

## Tests

```bash
cd app
php private/vendor/bin/phpunit                       # unit + integration
php tools/tutor_sessions.php --out=/tmp/sessions     # scripted mock sessions: calls per turn
node tests/Browser/run_browser.mjs                   # conversation UI at 360, 390 and 1280 wide
bash tools/run_all_tests.sh                          # everything, outputs to ../test-results/
```

The integration harness re-checks after every request that every presented
problem has an answer code computed, every recorded verdict is code's verdict
for that exact problem and entry, and the goals are what code computes. The
browser "oracle" (`tests/Browser/oracle.php`) reads the private test database
to answer like a scripted learner; it is never deployed.

## Live provider (Stage 2)

Set in a private `config.php`: `provider => 'openai_compatible'`,
`provider_base_url`, `provider_model` (confirm the model name with the
provider), `provider_api_key`, and `provider_thinking`:

- `'per_call'` (default in `config.example.php`): code enables thinking, at
  `provider_reasoning_effort`, only for an entry the AI must read, a wrong
  answer with no code signature, or a free-text message about the maths;
- `'disabled'` or `'enabled'`: the same setting on every call.

The setting is always sent explicitly; temperature only when thinking is
disabled. The adapter makes one text-mode binding call (the v1.6 activation
line), then JSON mode for envelopes; no function calling. Reasoning text
(`reasoning_content`) is never read, stored, shown or sent back. Every call
is logged with its purpose, its reasoning setting and the turn it belongs to.

Live runs (conductor round 8 §5) use the session runner with the key in a
config file kept outside the repository:

```bash
ALGEBRA_CONFIG=/path/outside/repo/config.php php tools/tutor_sessions.php --out=../test-results/live/round8/A --b1=2 --descent=0 --b11=1 --n1=2 --help-seeker=1 --breaker=1
```

It writes one transcript per session (learner input, what the student saw,
code's verdicts, the calls per turn with their reasoning setting, the wait,
check answers with how each was counted, help and backstop holds, guard
findings, counters, substitutions, repairs, holds, readings) and
`METRICS.md` (round 8 §5: goals met; check answers counted unassisted, with
help recorded and held, each with its cause; help moves and backstop holds;
guard findings by type and phrase by phrase; both counters; repairs, holds
and substitutions; readings and ungraded answers to value questions; calls
per turn, latency, empty replies and binding). No cost is estimated.

Earlier live results (rounds 3–5, `../test-results/live/`) were for the
beta's state machine and say nothing about this tutor.
