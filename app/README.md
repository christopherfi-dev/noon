# Algebra Engine — AI tutor (round 6, Stage 1)

The AI runs the tutoring session as a conversation, governed by the Algebra
Constraint Architecture **v1.3**; code is its set of instruments and keeps the
guarantees that truth requires (`CONDUCTOR_ROUND_6_v3.md` §1–§2, `CLAUDE.md`):

- code computes every answer, checks every entry and records every verdict;
- code alone computes goals (3 check questions correct in a row without help;
  for Q07 and E06 a solve or expansion, a choice question and a new form; one
  code-confirmed slip may pause the count);
- check questions are worded by code from a pattern of their skill and stand
  alone (the conversation pauses while one is open);
- the gradebook is true, and code writes the report from it;
- every learner-visible AI text is guarded (answer leaks, internal terms,
  mastery and goal claims, claims about help); questions are allowed.

Ten skills are available (Q07, E06, Q06, Q05, Q04, N18, N03, N04, N05, N06);
all 205 curriculum skills stay browsable. Stage 1 runs on a **simulated (mock)
tutor** only: no key was used, nothing about a live model, about learning or
about deployment has been tested. What is in scope and how each skill is
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
    resources/       architecture v1.3, curriculum map, runtime contracts
    migrations/      SQLite schema (001–005)
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
  checks, problem verification and text guards; one repair, then code's
  fallback.
- `Tutor\TutorPrompt`: the five-message request, built anew each turn.
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
disabled. The adapter makes one text-mode binding call (the v1.3 activation
line), then JSON mode for envelopes; no function calling. Reasoning text
(`reasoning_content`) is never read, stored, shown or sent back. Every call
is logged with its purpose, its reasoning setting and the turn it belongs to.

Stage 2 runs (conductor round 6 §10) use the session runner with the key in a
config file kept outside the repository:

```bash
ALGEBRA_CONFIG=/path/outside/repo/config.php php tools/tutor_sessions.php --out=../test-results/live/round6/RUN --b1=5 --descent=3 --b11=2 --n1=2
```

It writes one transcript per session (learner input, what the student saw,
code's verdicts, the calls per turn with their reasoning setting, the wait)
and `METRICS.md` (calls per turn, learner wait against §3.7, every rejected AI
output with its text and reasons, clarifying questions, slips confirmed and
rejected, the forward-looking audit list, HOLDs, tokens per session).

Earlier live results (rounds 3–5, `../test-results/live/`) were for the
beta's state machine and say nothing about this tutor.
