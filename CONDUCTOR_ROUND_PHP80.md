# Conductor Round PHP80 — Run on the Production Server's PHP 8.0
Place in the repository root and read before acting. Record this round as a separate interval in `BUILD_LOG.md`. Earlier conductor files still govern behaviour.
Offline only: no live run, no key, nothing deployed.
1. Why
The production server runs:

* AlmaLinux 9
* nginx with php-fpm, PHP 8.0.30 (both CLI and FPM)
* PHP running as user `nginx`
* SELinux disabled
* extensions `pdo_sqlite`, `mbstring`, `json`, `curl` and `openssl` present

The server's PHP will not be changed. The engine must run on PHP 8.0 with no change in behaviour. Every existing test must pass unchanged in what it asserts.
2. Code changes

1. Remove `readonly` from every property and promoted constructor parameter (about 89 uses). Do not add setters. Where a property was readonly, keep it private or protected if it can be, so immutability holds in practice.
2. Replace the 2 `array_is_list()` calls with a private helper that has the same semantics. Do not add a global polyfill.
3. Find every other construct newer than PHP 8.0, in `app/private`, `app/public` and `tests` (enums, `never`, first-class callables, `new` in initializers, pure intersection types, readonly classes, typed class constants, `#[\Override]`, `json_validate`, `fsync` and so on). Replace each one. List every construct found and how it was replaced in the report.
4. Set the PHP requirement to `>=8.0` in `composer.json`, the preflight and the docs. The preflight passes on 8.0. Production dependencies must declare PHP 8.0 support; report each dependency's constraint.
5. Use a PHPUnit version that runs on PHP 8.0 (9.6). Test intent stays the same; change only API differences between PHPUnit versions.

3. Verify on PHP 8.0 itself

* Run the full gate on PHP 8.0: PHPUnit (all 198 tests or more), browser tests, bundle smoke test and key-leak scan. Use a PHP 8.0 runtime, for example the `php:8.0-cli` container image or a PHP 8.0 binary. Report the exact version string.
* Run the same gate on PHP 8.3 as well, so both versions are known to work.
* If you cannot get a PHP 8.0 runtime, stop and report it. Do not claim 8.0 compatibility on the basis of 8.3 runs or static checks alone.

4. nginx deployment bundle
Build `dist/algebra-engine-vps-upload.zip` containing two top-level folders:

```
algebra-engine/            -> /var/www/html/educational-engines/algebra-engine/  (public)
algebra-engine-private/    -> /var/www/algebra-engine-private/                   (outside the web root)

```

* `algebra-engine/` holds only `index.html`, `api.php` and `assets/`. No `.htaccess` (nginx ignores it), no dotfiles, and nothing else.
* In `api.php`, the default `ALGEBRA_PRIVATE_DIR` is `/var/www/algebra-engine-private`.
* `algebra-engine-private/config.example.php` sets `'base_path' => '/educational-engines/algebra-engine/'`.
* Leave the Bluehost bundle in place, unchanged in behaviour.
* The bundle smoke test checks this layout and confirms no private file appears under `algebra-engine/`.

Write the sha256 of the new zip in the report.
5. Report
Give the following, in order:

* the constructs replaced (from §2.3);
* dependency PHP constraints;
* gate results on PHP 8.0 and on 8.3, with version strings;
* the commit hash;
* the bundle filename and sha256;
* any conflict with this file.

Keep it short.
