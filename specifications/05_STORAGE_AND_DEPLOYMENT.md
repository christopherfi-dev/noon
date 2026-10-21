# 05 — Storage, operational boundaries and Bluehost

## Production shape

No production Node/Python process or queue worker is required. PHP serves the
same-origin API and static HTML/JS/CSS. Use private PDO SQLite for snapshots,
events, immutable items, verdicts, operations and idempotency records. Prefer
private files outside the document root. Proposed layout:

```
account-home/
  algebra-private/
    config.php
    vendor/
    src/
    curriculum/
    architecture/
    var/algebra.sqlite
  public_html/algebra/
    index.html
    api.php
    assets/
```

Only the public subtree is web-accessible. A private config path is a server
setting, never client-selected. A `.htaccess` deny rule is defense in depth,
not the sole protection for DB files, keys or raw reports. Do not upload the
handoff root, sources, fixtures or all curriculum keys under public_html.

## Preflight on the actual hosting plan

Verify supported PHP version, cURL HTTPS with certificate verification,
PDO_SQLite, JSON and multibyte handling, file permissions, document-root paths,
outbound provider access, request execution limits and usable cron. These are
deployment checks, not facts established about the owner's Bluehost account.
If PDO SQLite is absent, report the exact requirement; do not silently replace
it with unsafe concurrent text-file storage. A MySQL adapter can be added with
explicit tested migrations if needed.

## Session and trust

Use server-generated high-entropy session credentials with PHP strict session
handling, cookie-only identifiers, HttpOnly, SameSite=Lax and Secure under
HTTPS. No session IDs in URLs or browser localStorage. Scope cookies to the
deployment path and rotate on session replacement. Release session locks
before provider I/O. Require same-origin checks and a session-bound CSRF token
for mutations; SameSite alone is not sufficient. Secure-cookie exception only
for documented local HTTP development. Cross-session reports/items must be
inaccessible even when IDs are guessed.

No names, ages, emails, student IDs or photographs are required. Session
answers/working are retained in private application storage for continuation
and evidence. User-facing copy must say this; do not copy the older Curiosity
'stores no student data' claim. Model requests send relevant work to the chosen
provider; give a brief accurate notice. Do not invent provider retention or
training assurances. Confirm applicable provider terms before using real
student material. This package requests no compliance certification.

Default idle TTL 24h, absolute TTL 7 days. At either boundary the session is
expired, not available for continuation. 'Forget this session' deletes its
snapshots, events, item work, operations and reports promptly and expires the
cookie. Cleanup via cron where available, plus bounded opportunistic cleanup
on requests. Redacted operational logs expire after 7 days. Backups require a
documented purge window; do not claim deletion from backups unless implemented.
All durations are product defaults, not a legal retention judgment.

## Operational controls

Keep keys in private config/environment; provide config.example.php with no
secrets. Restrict config/DB access permissions. Parameterize SQL. Escape HTML;
use safe text/limited Markdown rendering and safe math settings. CSP and asset
bundling must match actual implementation. Set no-store on session API data,
disable directory listings and production stack traces. Never log keys, entire
prompts, student answers or provider reasoning in general access/error logs.

Rate-limit start, task creation and generation; per-session limits alone cannot
bound anonymous public costs. Use an operator spend ceiling and a deployment
access gate for a private pilot. Do not hash an IP and call it anonymous proof;
minimize retained operational identifiers. Global rate/cost records must be
bounded/expired. Mock mode makes no outbound calls and is clearly labeled.

## Deployment deliverable Claude must produce

app/README.md, config.example.php, installation/preflight script, schema
migrations, test instructions, capability report, deployment upload ZIP split
into public/private trees, and exact cPanel/File Manager placement guidance.
Include rollback steps and a smoke test. Do not publish automatically. The
PHP built-in server is for local development only. Package approved dependencies
so a production shell/Composer installation is not assumed.
