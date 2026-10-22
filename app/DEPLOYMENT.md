# Deploying the beta to Bluehost (cPanel)

For the nginx VPS (AlmaLinux 9, PHP 8.0), use `DEPLOYMENT_VPS.md` and
`dist/algebra-engine-vps-upload.zip` instead.

**Nothing has been deployed.** These are instructions for when the owner
decides to deploy. The checks below are deployment checks for your account;
nothing here is known about your Bluehost plan in advance.

Upload file: `dist/algebra-engine-bluehost-upload.zip`. It holds two folders:

```
algebra-private/         -> goes in your account home, NOT under public_html
public_html/algebra/     -> goes under public_html (site URL /algebra/)
```

`public_html/algebra/api.php` finds the private folder at
`../../algebra-private` (that is, `/home/ACCOUNT/algebra-private`). If you use
a different layout, edit the one `ALGEBRA_PRIVATE_DIR` line at the top of
`api.php`, or set the `ALGEBRA_PRIVATE_DIR` environment variable.

## 1. Check the hosting plan

In cPanel → *Select PHP Version* (or *MultiPHP Manager*) choose PHP 8.0 or
newer for the domain, and enable the
extensions `pdo_sqlite`, `mbstring`, `ctype`, `json`, and `curl` + `openssl` if you
will use a live provider. The app refuses to run without PDO SQLite; there is
no file-based fallback.

## 2. Upload and place the files

1. cPanel → *File Manager* → your home folder (`/home/ACCOUNT`). Upload the
   ZIP there and choose *Extract*.
2. You now have `/home/ACCOUNT/algebra-private/` and
   `/home/ACCOUNT/public_html/algebra/`. If extraction created
   `/home/ACCOUNT/public_html/algebra` inside another folder, move it so the
   path is exactly `public_html/algebra`.
3. Confirm `algebra-private` is **beside** `public_html`, not inside it.
4. Delete the uploaded ZIP.

## 3. Configure (private)

1. In `algebra-private/`, copy `config.example.php` to `config.php`.
2. Edit `config.php`. Mock mode needs no changes. Set `'base_path' =>
   '/algebra/'` so the cookie is scoped to the app.
3. Set permissions: `config.php` 600. The app creates `algebra-private/var/`
   (permissions 700) for its database on first use; the ZIP contains no
   `config.php` and nothing inside `var/`.
4. Optional private pilot: set `access_gate_token`. Every request must then
   carry it (cookie `ae_gate` or header `X-Access-Gate`).
5. Live provider (only after the owner chooses one): set `provider` to
   `openai_compatible`, `provider_base_url` (https only), `provider_model`
   (confirmed with the provider), `provider_api_key`, and keep the spend
   ceiling `global_provider_calls_per_day`. Keys stay in this private file and
   never reach the browser. Keep `provider_thinking` at `disabled` (the
   default in `config.example.php`, chosen in round 4; `temperature` 0.2 then
   applies). It stays configurable: with `enabled`, also set
   `provider_reasoning_effort` (`low`, `high` or `max`). Keep
   `provider_max_tokens` (4096 with thinking disabled). Preflight fails if
   thinking is not set explicitly.

### Where the production API key goes (owner only)

The owner puts the production DeepSeek key on Bluehost. It is never given to
Claude Code, never committed and never uploaded in the ZIP.

- **File:** `/home/ACCOUNT/algebra-private/config.php`
  (`ACCOUNT` is your cPanel user name). This folder is beside `public_html`,
  not inside it, so the web server cannot serve it.
- **Line:** `'provider_api_key' => 'sk-…',` (replace `sk-…` with the key),
  together with `'provider' => 'openai_compatible'`,
  `'provider_base_url' => 'https://api.deepseek.com/v1'`,
  `'provider_model' => 'deepseek-flash'` and `'provider_thinking' => 'disabled'`.
- **Permissions:** `chmod 600 /home/ACCOUNT/algebra-private/config.php`.
- Never put the key in any file under `/home/ACCOUNT/public_html/`. Check that
  `https://YOURDOMAIN/algebra-private/config.php` and
  `https://YOURDOMAIN/algebra/config.php` do not return the file (step 6).

## 4. Run the preflight

cPanel → *Terminal* (if available):

```bash
php ~/algebra-private/bin/preflight.php --public=$HOME/public_html/algebra
```

It must end with `PREFLIGHT PASSED`. Without Terminal, use cPanel *Cron Jobs*
to run the same command once and read the emailed output.

## 5. Cleanup job

cPanel → *Cron Jobs* → hourly:

```
php /home/ACCOUNT/algebra-private/bin/cleanup.php
```

Sessions expire after 24 h idle or 7 days absolute (configurable). The app
also cleans up opportunistically. Deleting data from server backups is not
implemented; backups follow Bluehost's own retention.

## 6. Smoke test

1. Open `https://YOURDOMAIN/algebra/`. You should see the welcome screen with
   the "Simulated tutor (mock mode)" badge.
2. Start Q07, answer one item, request a hint, end the session, open the
   summary. The summary must show "MOCK (simulated model; no live provider
   was used)".
3. These URLs must **not** return files: `/algebra-private/config.php`,
   `/algebra/config.php`, `/algebra/.htaccess`,
   `/algebra/api.php/../../algebra-private/bootstrap.php`.
4. `https://YOURDOMAIN/algebra/api.php?action=bootstrap` returns JSON with
   `"provider_mode":"mock"`.
5. Click "Forget this session" and confirm the welcome screen returns.

A live-provider smoke test (after a key is added) only confirms network and
adapter compatibility, not teaching quality.

## Rollback

1. Rename `public_html/algebra` to `public_html/algebra-off` (the site stops
   serving immediately).
2. To restore a previous version, re-extract the previous ZIP over both
   folders, keeping `algebra-private/config.php` and `algebra-private/var/`.
3. To remove completely, delete both folders. This deletes all session data
   in `algebra-private/var/algebra.sqlite`.

## Security notes

- Only `index.html`, `api.php` and `assets/*` are served; `.htaccess` denies
  everything else and disables listings. The private folder is protected by
  its location; its own `.htaccess` (`Require all denied`) is defence in depth.
- Sessions use an HttpOnly, SameSite=Lax cookie (Secure under HTTPS), a
  session-bound CSRF token and same-origin checks. No session IDs in URLs or
  browser storage.
- Answers are stored in the private SQLite file for the browser
  session; "Forget this session" deletes them.

## Updating an existing install

The upload ZIP contains no `config.php` and nothing inside `var/`, so it never
replaces your settings or your database. Sessions in progress continue after
the update; on the first request the app adds the progress-code table to the
database without deleting or rewriting existing data.

1. Back up `algebra-private/config.php` and the folder `algebra-private/var/`
   (cPanel → *File Manager* → *Compress*, then download).
2. Delete these code folders and files inside `algebra-private/`: `bin`,
   `migrations`, `resources`, `src`, `vendor`, `bootstrap.php`,
   `composer.json`, `config.example.php`. Keep `config.php` and `var/`.
3. Delete `public_html/algebra/` and extract the new ZIP in your home folder as
   in §2. Because the ZIP holds no `config.php` and no `var/` files, your
   settings and database are untouched.
4. Run the preflight (`php algebra-private/bin/preflight.php
   --public=public_html/algebra`). It must end with `PREFLIGHT PASSED`; its
   `SCHEMA` line says whether a database update is still to be applied (the app
   applies it on the next request).
5. If `config.php` has no `progress_retention_days` line, progress records are
   kept 90 days after their last use.
