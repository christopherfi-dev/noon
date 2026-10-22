# Algebra Engine — beta

A PHP host that enforces the Algebra Constraint Architecture **v1.2** for ten
skills (Q07 target; Q06, Q05, Q04, E06, N18, N03, N04, N05, N06). The model
only drafts item parameters and wording; the host chooses every operation,
renders every question, derives every answer key, checks every response and
owns all state. The beta runs on a **simulated (mock) model** until a provider
key is configured.

What is in scope, what is deferred, and how each skill is checked:
`CAPABILITY_MATRIX.md`. Acceptance-scenario status: `ACCEPTANCE_SCENARIOS.md`.
Deployment: `DEPLOYMENT.md`.

## Layout

```
app/
  public/            web root (index.html, api.php, assets/, .htaccess)
  private/           everything else; never web-accessible
    bootstrap.php    loads autoloader + private config.php
    config.example.php
    src/             AlgebraEngine\ classes (see below)
    resources/       architecture v1.2, curriculum map, runtime contracts
    migrations/      SQLite schema
    bin/             preflight.php, cleanup.php (cron)
    var/             SQLite database (git-ignored)
  tests/             PHPUnit Unit + Integration, Browser (Playwright)
  tools/             build_bundle.php, gen_capability_matrix.php, run_all_tests.sh
```

Main classes: `Workflow\Engine` (transactions, routing, D2 descent, repair,
return), `Items\ItemFactory` + `Items\Templates\*` (host keys and error
signatures), `Items\SocraticPlanner` (D1 question-only repair plans),
`Items\AcceptanceGate` (item gate), `Provider\ModelCoordinator` (binding,
envelope schema + semantic checks, bounded repair), `Provider\DisclosureGuard`
(D1 answer-leak heuristic), `Provider\PromptAssembler` (trusted blocks, F2
skeleton), `Evidence\CriteriaCalculator` (criteria recomputed from the
ledger), `Report\ReportBuilder`, `Http\Api`, `Workflow\PublicProjection`.

## Run locally

Requirements: PHP 8.3+ with `pdo_sqlite`, `mbstring`, `json` (and `curl` for a
live provider). Composer for development only.

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
php private/vendor/bin/phpunit                    # unit + integration
node tests/Browser/run_browser.mjs                 # B1 at 360x740 and 1280x800, UI-01, UI-02, UI-03
bash tools/run_all_tests.sh                       # everything, outputs to ../test-results/
```

The browser tests use the globally installed Playwright and Chromium. The
browser "oracle" (`tests/Browser/oracle.php`) reads the private test database
to answer like a scripted learner; it is never deployed.

## Live provider

Set in `private/config.php`: `provider => 'openai_compatible'`,
`provider_base_url` (e.g. the DeepSeek base URL from its current docs),
`provider_model` (confirm the model name with the provider), and
`provider_api_key`. The adapter makes one text-mode binding call, checks the
exact v1.2 activation line, then uses JSON mode for envelopes. Answers and
working are sent to that provider.

Live smoke test (confirms network and adapter compatibility only), with the
key in a config file kept outside the repository:

```bash
ALGEBRA_CONFIG=/path/outside/repo/config.php php tools/live_smoke.php            # 4 steps, about 4 calls
ALGEBRA_CONFIG=/path/outside/repo/config.php php tools/live_smoke.php --journey  # adds a Socratic repair path, about 15 calls
```

Scripted live sessions (Round 2 §4): B1 and B3 paths with a scripted
learner. The script first lists the provider's models and stops (exit 3) when
the configured model is not listed, unless `--accept-unlisted-model` is given
with the owner's approval. With a mock config it is a dry run, labelled MOCK.

```bash
ALGEBRA_CONFIG=/path/outside/repo/config.php php tools/live_sessions.php --out=../test-results/live --b1=5 --b3=3
```

It writes one transcript per session (learner text, tutor text, mode, verdict
per turn, every host rejection with the rejected text) and `METRICS.md`
(binding, repairs per operation, HOLDs, rejections, latency, tokens).

Thinking settings (round 3 ruling) are required for the live provider and are
never left to the provider default. **Default: thinking disabled** (round 4
choice, from the round 3 results: fewest repairs, no HOLDs, no empty responses,
median learner wait 2.7 s). The setting stays configurable.

- `provider_thinking`: `'disabled'` (default in `config.example.php`) or
  `'enabled'`, sent as `"thinking": {"type": ...}` on every call.
- `provider_reasoning_effort`: `'low'`, `'high'` or `'max'`, sent only when
  thinking is enabled (empty otherwise).
- `temperature` is sent only when thinking is disabled (it has no effect in
  thinking mode).
- `provider_max_tokens`: set per configuration; with thinking enabled the
  hidden reasoning counts against it.

Reasoning text (`reasoning_content`) is never read, stored, shown or sent back.
`model_calls` keeps only each call's finish reason, token counts (including
reasoning tokens) and duration. Preflight fails when the thinking settings are
missing or inconsistent.

`ALGEBRA_TRANSPORT_LOG=/path` adds a log of response metadata (HTTP status,
finish reason, token counts, the first 400 characters of content). Request
bodies and headers, including the key, are never logged.

Result on 2026-10-21 (`../test-results/08-live-smoke.txt`): DeepSeek,
`provider_base_url` `https://api.deepseek.com/v1` (the adapter appends
`/chat/completions`), model `deepseek-flash`. The last run, on the final code,
passed (the basic steps plus the journey). This is not evidence of teaching
quality or learning.

- The owner's other app names `deepseek-v4-flash`. The supplied key's
  `/v1/models` list offered only `deepseek-flash` and `deepseek-v4-pro`, so
  `deepseek-flash` was used. The owner should confirm the model.
- Round 2 (2026-10-21): the owner chose `deepseek-v4-flash` with
  `reasoning_effort: high`. The smoke test passed with that name (the provider
  accepts it), but it is still not in the model list, so the scripted live
  sessions were not run (`../test-results/live/`).
- `deepseek-flash` is a reasoning model: hidden reasoning counts against
  `max_tokens`. At 1500 tokens it returned empty content with
  `finish_reason=length`; the default is now 4096. Across the runs, each
  answered JSON call took 3.6–15.4 s (average about 7 s) and used 594–3,254
  completion tokens, so 4096 leaves limited headroom.
- JSON mode occasionally returned empty content with `finish_reason=stop`
  (2 of 14 JSON calls in run 4; none in runs 5 and 6). The host rejects it
  and spends one bounded repair attempt, as the spec requires.
