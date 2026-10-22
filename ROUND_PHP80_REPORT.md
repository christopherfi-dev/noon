# Algebra Engine — round PHP80 report

Branch `claude/elegant-volta-blu3uj`; `CONDUCTOR_ROUND_PHP80.md`; `BUILD_LOG.md`, "Round PHP80". Offline only: no live run, no key, nothing deployed.

## 1. Constructs replaced (§2)

How I searched:
- a token scan of `app/private`, `app/public`, `app/tests` and `app/tools`;
- a lint of all 124 PHP files under PHP 8.0.30;
- a check, run inside PHP 8.0.30, that every function, class and constant used exists.

| Construct (minimum PHP) | Found | Replacement |
|---|---|---|
| `readonly` properties and promoted parameters (8.1) | 98 in 36 files: 55 public, 43 private | Modifier removed; no setters added. Private ones stay private. Public ones stay public because other classes read them; nothing writes them outside the constructor (the suite passed on 8.3 with `readonly` in place). `StorageUnavailable::$reason` had no reader, so it is now private |
| `array_is_list()` (8.1) | 2: `Http/Api.php`, `Items/ClassificationSupport.php` | A private static `isList()` in each class with the same semantics (keys exactly 0, 1, 2, … in order; empty is a list). It matched `array_is_list()` on edge cases and 20,000 random arrays |
| `#[DataProvider]` attribute (PHPUnit 10+ API, not a PHP construct) | 3 tests | `@dataProvider` annotation (PHPUnit 9.6) |
| Enums, `never`, first-class callables, `new` in initializers, intersection or DNF types, readonly classes, typed or final class constants, `#[\Override]`, `0o` literals, `json_validate`, `fsync`, string-key array unpacking, other post-8.0 functions or classes | **none** | — (the two "`&`" scan hits are bitwise operators in preflight) |

Runtime differences found under 8.0:
- **SQLite integers are strings on 8.0.** `pdo_sqlite` returns them as strings there. Every integer column the engine compares (revision, call counts, counters, HTTP status) was already cast, so there was no change.
- **PHPUnit configuration.** `cacheDirectory` became `cacheResultFile`. The PHPUnit 10+ display option and the runner's `--display-notices` were removed; PHPUnit 9.6 turns notices into errors, which is stricter. `memory_limit` is set to 512M in `phpunit.xml`, because the suite needs about 250 MB.
- **Preflight.** It requires PHP 8.0 or newer (previously 8.3) and now also checks `ctype`, which the engine uses.

## 2. Dependency PHP constraints

| Package | Locked version | PHP constraint |
|---|---|---|
| opis/json-schema (production) | 2.6.0 | `^7.4 \|\| ^8.0` |
| opis/string (production) | 2.1.0 | `^7.4 \|\| ^8.0` |
| opis/uri (production) | 1.1.0 | `^7.4 \|\| ^8.0` |
| phpunit/phpunit (dev) | 9.6.38 | `>=7.3` |
| other dev packages (27 in total) | — | each allows 8.0 (`>=7.3`, `>=7.4`, `^7.1 \|\| ^8.0`, `^7.2 \|\| ^8.0` or `^8.0`) |

`composer.json`: `"php": ">=8.0"`, platform `8.0.30`. The bundles' Composer platform check requires PHP 8.0 or newer.

## 3. Gate results

| Runtime | Version string | Interval (UTC) | Result |
|---|---|---|---|
| **PHP 8.0**, native build: official `php-8.0.30.tar.xz` (sha256 `216ab305…d777d9`), extensions as shared modules like AlmaLinux; SQLite 3.45.1, OpenSSL 3.0.13 | `PHP 8.0.30 (cli) (built: Oct 22 2026 12:12:22) ( NTS )` | 12:15:30–12:16:40 | **PASS**, every step, all on 8.0 including the bundle build: preflight PASS; PHPUnit **198/198**; browser **5/5**; bundle smoke PASS (Bluehost and VPS); key-leak scan 0 hits (330 files, 1,341 ZIP entries) |
| **PHP 8.3** (host) | `PHP 8.3.6 (cli) (built: Oct  7 2026 18:06:38) (NTS)` | 12:14:08–12:15:23 | **PASS**, every step; PHPUnit 198/198; browser 5/5; smoke PASS; key-leak 0 hits |
| Supplementary: `php:8.0-cli` image (SQLite 3.34.1, as on AlmaLinux 9; no zip extension) | `PHP 8.0.30 (cli) (built: Nov 21 2023 16:10:15) ( NTS )` | 12:16:57–12:18:02 | PHPUnit **197/197** with DEPLOY-02 excluded (see conflict 2); smoke PASS; key-leak PASS through the `PharData` path |

Raw output: `test-results/gate-php80/`, `test-results/gate-php83/`, `test-results/supplementary-php80-image/`.

## 4. Commit

`a1f9e16`: code, bundles and gate results. This report and the closing BUILD_LOG entry are in the next commit.

## 5. Bundle

`dist/algebra-engine-vps-upload.zip`, 664 files. **sha256 `d32e7fe3d0cdea1eeb0b42c39aac5e3b9da4368b792c6052e5240ae3e96b9383`**.
- **Layout:** `algebra-engine/` holds only `index.html`, `api.php` and `assets/` (`app.css`, `app.js`, `favicon.svg`): 5 files; the rest is `algebra-engine-private/`.
- **No dotfiles:** none at all. The dependencies' `.gitattributes`, `.github/` and `.gitignore` were dropped.
- **Settings:** in `api.php` the default `ALGEBRA_PRIVATE_DIR` is `/var/www/algebra-engine-private`, and `config.example.php` sets `'base_path' => '/educational-engines/algebra-engine/'`.
- **Smoke test:** it checks all of the above. It also checks that the ZIP equals the staged tree, serves the bundle at `/educational-engines/algebra-engine/`, checks the cookie path, and refuses 6 private paths.
- **Guide:** `app/DEPLOYMENT_VPS.md`.

The Bluehost bundle was rebuilt with the same layout and behaviour.

## 6. Conflicts with the conductor file

1. **The official PHP 8.0 image could not run the whole gate.**
   - It lacks the `zip` extension and `git`, and adding them failed: Debian mirrors are blocked here, and Docker Hub rate-limited further pulls.
   - So the primary 8.0 runtime is PHP 8.0.30 built from the official source. Building it needed one OpenSSL 3 compatibility line (`#ifdef RSA_SSLV23_PADDING`), as RHEL 9 builds of 8.0 also need. It does not touch anything the engine uses.
2. **DEPLOY-02 cannot run in the official image.**
   - The test simulates a missing `pdo_sqlite` with `php -n`, but that image compiles `pdo_sqlite` in.
   - The test passes on the native 8.0.30 build, where `pdo_sqlite` is a loadable module as on AlmaLinux. The test itself is unchanged.
3. **"About 89" `readonly` uses: I found 98.** On 8.0, the 55 public ones are no longer enforced as read-only by the language, because other classes read them; nothing writes them after construction.
4. **Tooling changes beyond §2.**
   - The key-leak scanner (and its test fixture) now work without `git` (tracked-file list written by the caller) or the `zip` extension (`PharData`). The test assertions are unchanged.
   - The runner records the PHP version strings.
   - `BUILD_PHP` can run the bundle build on another PHP. It was not needed in the 8.0 gate.
5. **`CLAUDE.md` still says "PHP 8.3+".** It is part of the handoff, which it asks to keep intact, so I left it unchanged. This round's PHP 8.0 requirement supersedes it.
6. **Composer warning during the bundle build.** It prints "lock file is not up to date" because the build writes a reduced `composer.json` for production. This is unchanged from earlier rounds, and installation uses the committed lock file.
