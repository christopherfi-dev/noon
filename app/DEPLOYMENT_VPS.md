# Deploying the beta to the nginx VPS (AlmaLinux 9, PHP 8.0)

**Nothing has been deployed.** These are instructions for when the owner
decides to deploy. They are written for the server described by the owner:
AlmaLinux 9, nginx with php-fpm, PHP 8.0.30 (CLI and FPM), PHP running as
user `nginx`, SELinux disabled, extensions `pdo_sqlite`, `mbstring`, `json`,
`curl` and `openssl` present. The engine also needs `ctype` (part of PHP's
common package on AlmaLinux); the preflight checks it.

Upload file: `dist/algebra-engine-vps-upload.zip`. It holds two folders:

```
algebra-engine/            -> /var/www/html/educational-engines/algebra-engine/  (public)
algebra-engine-private/    -> /var/www/algebra-engine-private/                   (outside the web root)
```

`algebra-engine/` holds only `index.html`, `api.php` and `assets/`. The ZIP
contains no `config.php` and nothing inside `var/`, so it can never replace a
server's settings or its database (see "Updating an existing install"). Its
`api.php` finds the private folder at `/var/www/algebra-engine-private`
(the `ALGEBRA_PRIVATE_DIR` line at the top; the environment variable of the
same name overrides it). The bundle has no `.htaccess` files, because nginx
ignores them: the private folder is protected by being outside
`/var/www/html`.

## 1. Place the files (as root)

```bash
unzip algebra-engine-vps-upload.zip -d /tmp/algebra-upload
mkdir -p /var/www/html/educational-engines
cp -a /tmp/algebra-upload/algebra-engine /var/www/html/educational-engines/
cp -a /tmp/algebra-upload/algebra-engine-private /var/www/
rm -rf /tmp/algebra-upload
```

Ownership and permissions (PHP-FPM runs as `nginx`):

```bash
chown -R root:root /var/www/html/educational-engines/algebra-engine
find /var/www/html/educational-engines/algebra-engine -type d -exec chmod 755 {} +
find /var/www/html/educational-engines/algebra-engine -type f -exec chmod 644 {} +
chown -R root:nginx /var/www/algebra-engine-private
find /var/www/algebra-engine-private -type d -exec chmod 750 {} +
find /var/www/algebra-engine-private -type f -exec chmod 640 {} +
install -d -o nginx -g nginx -m 700 /var/www/algebra-engine-private/var
```

The SQLite database is created at
`/var/www/algebra-engine-private/var/algebra.sqlite` on first use; only
`nginx` can read or write that folder.

## 2. Configure (private)

```bash
cd /var/www/algebra-engine-private
cp config.example.php config.php
chown root:nginx config.php && chmod 640 config.php
```

`config.example.php` already sets `'base_path' => '/educational-engines/algebra-engine/'`
(the cookie path). Mock mode needs no other change. For a live provider, see
the same settings as in `DEPLOYMENT.md` §3; the production key goes only in
`/var/www/algebra-engine-private/config.php` (`'provider_api_key' => 'sk-…'`),
never under `/var/www/html/`. It is never given to Claude Code.

## 3. nginx

The public folder needs static files plus one PHP entry point, `api.php`. If
the server block for `/var/www/html` already passes `*.php` to PHP-FPM (the
AlmaLinux default `/etc/nginx/default.d/php.conf` does), nothing more is
needed. Otherwise add, inside that server block:

```nginx
location /educational-engines/algebra-engine/ {
    index index.html;
    try_files $uri $uri/ =404;
}
location = /educational-engines/algebra-engine/api.php {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php-fpm/www.sock;
}
```

Then `nginx -t && systemctl reload nginx`. SELinux is disabled on this server,
so no file contexts are needed (if it is ever enabled, the `var/` folder needs
a read-write context for PHP-FPM).

## 4. Preflight (as the PHP-FPM user)

```bash
sudo -u nginx php /var/www/algebra-engine-private/bin/preflight.php \
  --public=/var/www/html/educational-engines/algebra-engine
```

It must end with `PREFLIGHT PASSED`. PHP 8.0 or newer is required; the
gate for this bundle ran on PHP 8.0.30.

## 5. Cleanup job

Hourly, as `nginx` (`crontab -u nginx -e`):

```
0 * * * * php /var/www/algebra-engine-private/bin/cleanup.php
```

## 6. Smoke test

1. Open `https://YOURDOMAIN/educational-engines/algebra-engine/`. The welcome
   screen shows the "Simulated tutor (mock mode)" badge.
2. Start Q07, answer one item, request a hint, end the session, open the
   summary. It must show "MOCK (simulated model; no live provider was used)".
3. These URLs must **not** return files:
   `/educational-engines/algebra-engine/config.php`,
   `/educational-engines/algebra-engine/bootstrap.php`,
   `/educational-engines/algebra-engine-private/config.php`.
4. `https://YOURDOMAIN/educational-engines/algebra-engine/api.php?action=bootstrap`
   returns JSON with `"provider_mode":"mock"`, and its `Set-Cookie` path is
   `/educational-engines/algebra-engine/`.

## Updating an existing install

Use this when an earlier version is already running, with sessions in its
database. Replace the code folders, keep `config.php` and `var/`, then run the
preflight. Sessions in progress continue after the update. On the first
request the app adds the progress-code table to the database; it never
deletes or rewrites existing data, and doing it twice is harmless.

1. Back up the settings and the database (as root):

   ```bash
   cp -a /var/www/algebra-engine-private/config.php /root/algebra-config-$(date +%F).php
   cp -a /var/www/algebra-engine-private/var /root/algebra-var-$(date +%F)
   ```

2. Unpack the new ZIP to a temporary folder:

   ```bash
   unzip algebra-engine-vps-upload.zip -d /tmp/algebra-upload
   ```

3. Replace the code folders. `config.php` and `var/` are left where they are
   (the ZIP holds neither):

   ```bash
   cd /var/www/algebra-engine-private
   rm -rf bin migrations resources src vendor bootstrap.php composer.json config.example.php
   cp -a /tmp/algebra-upload/algebra-engine-private/. /var/www/algebra-engine-private/
   rm -rf /var/www/html/educational-engines/algebra-engine
   cp -a /tmp/algebra-upload/algebra-engine /var/www/html/educational-engines/
   rm -rf /tmp/algebra-upload
   ```

4. Set ownership and permissions again with the commands in §1 (they do not
   touch the database file inside `var/`).
5. Run the preflight (§4). It must end with `PREFLIGHT PASSED`. Its `SCHEMA`
   line says whether a database update is still to be applied; the app applies
   it on the next request.
6. Open the page: a session that was in progress continues where it was, and
   from its next answer it gets a progress code.
7. If `config.php` has no `progress_retention_days` line, progress records are
   kept 90 days after their last use; add the line to change that.

## Rollback

1. `mv /var/www/html/educational-engines/algebra-engine /var/www/html/educational-engines/algebra-engine-off`
   stops serving immediately.
2. To restore a previous version, replace the code folders with the previous
   bundle's as in "Updating an existing install", keeping
   `algebra-engine-private/config.php` and `algebra-engine-private/var/`. An
   earlier version ignores the progress-code table, so it runs on the updated
   database; codes created meanwhile cannot be used until you update again.
3. To remove completely, delete both folders (this deletes all session data).
