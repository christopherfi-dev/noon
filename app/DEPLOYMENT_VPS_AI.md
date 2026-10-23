# Installing the AI line beside the beta on the nginx VPS (round 6)

**Nothing has been deployed.** These are instructions for when the owner
decides to deploy. The beta at `/educational-engines/algebra-engine/` stays
live and untouched: this is a separate install, with its own folders, its own
database and its own cookie path. The server is the one in `DEPLOYMENT_VPS.md`
(AlmaLinux 9, nginx with php-fpm, PHP 8.0.30 running as `nginx`).

Upload file: `dist/algebra-engine-ai-vps-upload.zip`. It holds two folders:

```
algebra-engine-ai/            -> /var/www/html/educational-engines/algebra-engine-ai/  (public)
algebra-engine-ai-private/    -> /var/www/algebra-engine-ai-private/                   (outside the web root)
```

- `algebra-engine-ai/api.php` finds its private folder at
  `/var/www/algebra-engine-ai-private`, never the beta's.
- Its database is `/var/www/algebra-engine-ai-private/var/algebra.sqlite`,
  separate from the beta's.
- `config.example.php` sets `'base_path' => '/educational-engines/algebra-engine-ai/'`.
  Its session and progress-code cookies use that path. Under the cookie
  path-matching rules (RFC 6265), the beta's path
  `/educational-engines/algebra-engine/` does not cover this folder, so the two
  installs never see each other's cookies.
- Progress codes are kept in each install's own database, so a beta code does
  not work in the AI install, and an AI-install code does not work in the beta.

Every step below is the same as in `DEPLOYMENT_VPS.md`, with `algebra-engine`
replaced by `algebra-engine-ai` and `algebra-engine-private` replaced by
`algebra-engine-ai-private`. Never run these commands on the beta's folders.

## 1. Place the files (as root)

```bash
unzip algebra-engine-ai-vps-upload.zip -d /tmp/algebra-ai-upload
cp -a /tmp/algebra-ai-upload/algebra-engine-ai /var/www/html/educational-engines/
cp -a /tmp/algebra-ai-upload/algebra-engine-ai-private /var/www/
rm -rf /tmp/algebra-ai-upload
chown -R root:root /var/www/html/educational-engines/algebra-engine-ai
find /var/www/html/educational-engines/algebra-engine-ai -type d -exec chmod 755 {} +
find /var/www/html/educational-engines/algebra-engine-ai -type f -exec chmod 644 {} +
chown -R root:nginx /var/www/algebra-engine-ai-private
find /var/www/algebra-engine-ai-private -type d -exec chmod 750 {} +
find /var/www/algebra-engine-ai-private -type f -exec chmod 640 {} +
install -d -o nginx -g nginx -m 700 /var/www/algebra-engine-ai-private/var
```

## 2. Configure (private)

```bash
cd /var/www/algebra-engine-ai-private
cp config.example.php config.php
chown root:nginx config.php && chmod 640 config.php
```

Mock mode needs no other change. Use mock mode until the owner has reviewed
the stage 2 live results. For a live provider, use the same settings as the
beta (`DEPLOYMENT.md` §3), including `provider_thinking` (the round 6 report
leaves the choice to the owner). The key goes only in
`/var/www/algebra-engine-ai-private/config.php`. It is never given to Claude
Code.

## 3. nginx

If the server block for `/var/www/html` already passes `*.php` to PHP-FPM,
nothing more is needed. Otherwise add, beside the beta's two blocks:

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

Then run `nginx -t && systemctl reload nginx`.

## 4. Preflight, cleanup and smoke test

```bash
sudo -u nginx php /var/www/algebra-engine-ai-private/bin/preflight.php \
  --public=/var/www/html/educational-engines/algebra-engine-ai
```

The preflight must end with `PREFLIGHT PASSED`. Its `ARCHITECTURE_HASH` line
names v1.3.

Add the hourly cleanup job, as `nginx`:

```
0 * * * * php /var/www/algebra-engine-ai-private/bin/cleanup.php
```

Smoke test:

1. Open `https://YOURDOMAIN/educational-engines/algebra-engine-ai/` and work
   through the four steps of `DEPLOYMENT_VPS.md` §6, using the `-ai` paths.
2. Open the beta at `/educational-engines/algebra-engine/` and check that it
   still works and still shows its own sessions.

## Rollback

`mv /var/www/html/educational-engines/algebra-engine-ai /var/www/html/educational-engines/algebra-engine-ai-off`
stops serving the AI line; the beta is unaffected. To remove the AI line
completely, delete both `-ai` folders. This deletes only the AI line's session
data.
