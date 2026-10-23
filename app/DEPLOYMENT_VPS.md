# Deploying the AI tutor next to the beta (nginx VPS, AlmaLinux 9, PHP 8.0)

**Nothing has been deployed.** These are instructions for when the owner
decides to deploy (conductor round 6 §11). The live beta at
`/educational-engines/algebra-engine/` stays as it is: this install has its
own public folder, its own private folder, its own database and its own cookie
path, and touches nothing of the beta's. Progress codes do not carry between
the two, because they use separate databases. The beta's own guide is in this
file at tag `beta-1.0`.

The server, as described by the owner: AlmaLinux 9, nginx with php-fpm, PHP
8.0.30 (CLI and FPM), PHP running as user `nginx`, SELinux disabled,
extensions `pdo_sqlite`, `mbstring`, `json`, `curl` and `openssl` present. The
engine also needs `ctype`; the preflight checks it.

Upload file: `dist/algebra-engine-ai-vps-upload.zip`. It holds two folders:

```
algebra-engine-ai/            -> /var/www/html/educational-engines/algebra-engine-ai/  (public)
algebra-engine-ai-private/    -> /var/www/algebra-engine-ai-private/                   (outside the web root)
```

`algebra-engine-ai/` holds only `index.html`, `api.php` and `assets/`. The ZIP
contains no `config.php` and nothing inside `var/`. Its `api.php` finds the
private folder at `/var/www/algebra-engine-ai-private` (the
`ALGEBRA_PRIVATE_DIR` line at the top; the environment variable of the same
name overrides it). The bundle has no `.htaccess` files, because nginx ignores
them: the private folder is protected by being outside `/var/www/html`.

## 1. Place the files (as root)

```bash
unzip algebra-engine-ai-vps-upload.zip -d /tmp/algebra-ai-upload
cp -a /tmp/algebra-ai-upload/algebra-engine-ai /var/www/html/educational-engines/
cp -a /tmp/algebra-ai-upload/algebra-engine-ai-private /var/www/
rm -rf /tmp/algebra-ai-upload
```

Ownership and permissions (PHP-FPM runs as `nginx`):

```bash
chown -R root:root /var/www/html/educational-engines/algebra-engine-ai
find /var/www/html/educational-engines/algebra-engine-ai -type d -exec chmod 755 {} +
find /var/www/html/educational-engines/algebra-engine-ai -type f -exec chmod 644 {} +
chown -R root:nginx /var/www/algebra-engine-ai-private
find /var/www/algebra-engine-ai-private -type d -exec chmod 750 {} +
find /var/www/algebra-engine-ai-private -type f -exec chmod 640 {} +
install -d -o nginx -g nginx -m 700 /var/www/algebra-engine-ai-private/var
```

The SQLite database is created at
`/var/www/algebra-engine-ai-private/var/algebra.sqlite` on first use; only
`nginx` can read or write that folder. It is never the beta's database.

## 2. Configure (private)

```bash
cd /var/www/algebra-engine-ai-private
cp config.example.php config.php
chown root:nginx config.php && chmod 640 config.php
```

`config.example.php` already sets this install's cookie path
(`'base_path' => '/educational-engines/algebra-engine-ai/'`) and its own
database (`'db_path' => '/var/www/algebra-engine-ai-private/var/algebra.sqlite'`).
Mock mode needs no other change.

For a live provider (Stage 2): `provider => 'openai_compatible'`,
`provider_base_url`, `provider_model` (confirmed with the provider),
`provider_api_key`, and `provider_thinking`: `'per_call'` (the default in the
example: code turns thinking on, at `provider_reasoning_effort`, only for an
entry the tutor must read, a wrong answer with no known pattern, or a question
about the maths), `'disabled'` or `'enabled'` on every call. The key goes only
in `/var/www/algebra-engine-ai-private/config.php`, never under
`/var/www/html/`, and is never given to Claude Code. Students' messages and
answers are then sent to that provider.

## 3. nginx

If the server block for `/var/www/html` already passes `*.php` to PHP-FPM (the
AlmaLinux default `/etc/nginx/default.d/php.conf` does), nothing more is
needed. Otherwise add, inside that server block, next to the beta's blocks:

```nginx
location /educational-engines/algebra-engine-ai/ {
    index index.html;
    try_files $uri $uri/ =404;
}
location = /educational-engines/algebra-engine-ai/api.php {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php-fpm/www.sock;
}
```

Then `nginx -t && systemctl reload nginx`. A live provider call can take
several seconds; PHP-FPM's `request_terminate_timeout` (if set) must exceed
`provider_timeout` (45 s by default).

## 4. Preflight (as the PHP-FPM user)

```bash
sudo -u nginx php /var/www/algebra-engine-ai-private/bin/preflight.php \
  --public=/var/www/html/educational-engines/algebra-engine-ai
```

It must end with `PREFLIGHT PASSED`. The gate for this bundle ran on PHP
8.0.30 and 8.3.6.

## 5. Cleanup job

Hourly, as `nginx` (`crontab -u nginx -e`), next to the beta's line:

```
5 * * * * php /var/www/algebra-engine-ai-private/bin/cleanup.php
```

## 6. Smoke test

1. Open `https://YOURDOMAIN/educational-engines/algebra-engine-ai/`. The
   welcome screen shows the "Simulated tutor (mock mode)" badge.
2. Start Q07. The tutor writes, and a practice question is pinned above the
   input. Answer it, tap "I'm stuck" and choose a hint, then end the session
   and open the summary. It must show "MOCK (simulated tutor; no live provider
   was used)".
3. These URLs must **not** return files:
   `/educational-engines/algebra-engine-ai/config.php`,
   `/educational-engines/algebra-engine-ai/bootstrap.php`,
   `/educational-engines/algebra-engine-ai-private/config.php`.
4. `https://YOURDOMAIN/educational-engines/algebra-engine-ai/api.php?action=bootstrap`
   returns JSON with `"provider_mode":"mock"`, and its `Set-Cookie` path is
   `/educational-engines/algebra-engine-ai/`.
5. The beta at `/educational-engines/algebra-engine/` still works as before.

## Updating this install later

Back up `algebra-engine-ai-private/config.php` and `algebra-engine-ai-private/var/`,
replace the code folders from a new ZIP (as in §1, but keep `config.php` and
`var/`), set permissions again, and run the preflight. The app adds database
columns on the next request (migration 005 adds the call details); it never
deletes or rewrites existing data.

## Rollback

1. `mv /var/www/html/educational-engines/algebra-engine-ai /var/www/html/educational-engines/algebra-engine-ai-off`
   stops serving it immediately; the beta is unaffected.
2. To remove it completely, delete both `algebra-engine-ai` folders and the
   cron line (this deletes all of its session data and progress records).
