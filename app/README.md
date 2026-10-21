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
node tests/Browser/run_browser.mjs                 # B1 at 360x740 and 1280x800, UI-01, UI-02
bash tools/run_all_tests.sh                       # everything, outputs to ../test-results/
```

The browser tests use the globally installed Playwright and Chromium. The
browser "oracle" (`tests/Browser/oracle.php`) reads the private test database
to answer like a scripted learner; it is never deployed.

## Live provider (not yet run)

Set in `private/config.php`: `provider => 'openai_compatible'`,
`provider_base_url` (e.g. the DeepSeek base URL from its current docs),
`provider_model` (confirm the model name with the provider), and
`provider_api_key`. The adapter makes one text-mode binding call, checks the
exact v1.2 activation line, then uses JSON mode for envelopes. Answers and
working are sent to that provider. No live call has been made in this build.
