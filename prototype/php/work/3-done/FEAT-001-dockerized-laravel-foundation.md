---
id: FEAT-001
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-001: Dockerized Laravel foundation with sidecar PHPUnit and Tailwind

## Problem
`prototype/php/` contains only `docs/architecture.md` and `work/`. There is no application, no container, no test runner, and no CSS pipeline. Every other ticket depends on this scaffold existing and on its conventions (sidecar tests, route file split, two layouts, debug alert partial) being in place.

## Goal
A team member clones the repo, runs one command, and has the app served on `http://localhost:8000` with a green test suite and a coverage report, with nothing installed on the host.

## Outcome
- `docker compose up` (or `make up`) serves the app at `http://localhost:8000`; `/` renders a storefront placeholder page and `/seller` renders a seller-portal placeholder page, each in its own layout with Tailwind styles applied.
- `make test` runs the PHPUnit suite inside the container and is green; `make coverage` prints a coverage summary.
- A sidecar test (`app/Domain/Money/MoneyTest.php` beside `Money.php`) runs and passes, proving the sidecar discovery works, and an HTTP test beside the placeholder controller proves feature tests work.
- `README.md` documents: prerequisites (Docker only), first run, serving, running tests and coverage, opening a shell, resetting the database, and the repository layout.
- The debug alert partial renders any `debug_magic_link` flashed into the session in both layouts.

## Why it matters
All five feature tickets fan out from this scaffold. Conventions not fixed here get reinvented three different ways.

## Discovery notes
Read `docs/architecture.md` first; it is the spec for conventions.
- Dockerfile: `php:8.3-cli` base, install `pdo_sqlite`, `zip`, `pcov` (pecl), `intl` if cheap; copy composer from `composer:2` image; install Node 20 (for Tailwind CLI). One service `app` in compose, working dir `/var/www/src`, bind-mount `./src`, port `8000:8000`, command `php artisan serve --host=0.0.0.0 --port=8000`. Put the SQLite file at `src/database/database.sqlite`. An `entrypoint.sh` that runs `composer install` when `vendor/` is missing, `npm ci` when `node_modules/` is missing, copies `.env.example` → `.env` and generates the key if missing, touches the sqlite file, runs `php artisan migrate --force`, and builds Tailwind, keeps first-run to one command.
- Create the Laravel app with `docker run --rm -v $PWD:/app composer:2 create-project laravel/laravel src` (so nothing touches the host). Remove the default `tests/Feature` and `tests/Unit` examples; keep `tests/TestCase.php`.
- Tailwind: use the Laravel default Vite + Tailwind v4 setup already in `laravel/laravel`, built with `npm run build` inside the container (Vite manifest). Blade uses `@vite(['resources/css/app.css'])`. No JS bundle is required; leave `app.js` empty or remove it from the Vite input.
- `phpunit.xml`: testsuite `App` with `<directory suffix="Test.php">app</directory>` and `<directory suffix="Test.php">routes</directory>`; env `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`. Composer scripts: `test` → `phpunit`, `test:coverage` → `phpunit --coverage-text --coverage-html coverage`.
- Composer autoload: tests live under `app/` and are PSR-4 resolvable as `App\...`; that is acceptable for the prototype. Exclude `*Test.php` from the classmap-authoritative optimisation if it is turned on.
- Routes: `routes/web.php` requires `routes/auth.php`, `routes/shop.php`, `routes/seller.php`. Seller routes are under the `/seller` prefix and `seller.` name prefix; shop routes are unprefixed with `shop.` names; auth routes carry `auth.` names.
- Layouts: `resources/views/layouts/seller.blade.php` (system font stack, compact, gray-100 background, white panels, stock Tailwind blue for primary actions) and `resources/views/layouts/shop.blade.php` (white background, generous spacing, large type, brand name small in the header). Both include `partials/debug-alert.blade.php` which renders `session('debug_magic_link')` in a yellow `role="alert"` box with the link as an `<a>`.
- `Makefile` targets: `up`, `down`, `build`, `shell`, `test`, `coverage`, `migrate`, `fresh` (migrate:fresh --seed), `logs`. Each is a thin `docker compose` wrapper so the README can show both forms.
- First sidecar: `app/Domain/Money/Money.php` — readonly value object over integer cents with `fromCents`, `add`, `multiply(int)`, `percent(int)`, `format()` → `$12.34`. TDD it.
- Add a `.gitignore` in `src/` that Laravel ships; also ignore `coverage/`.
- Commit with the ticket id when green. Stage only `prototype/php`.

## Working

- Laravel 13.26.1 on PHP 8.3.33, PHPUnit 12.5.33, Tailwind 4.3.3 via Vite 8.
- `composer create-project` ran on the composer:2 image (PHP 8.5), so the lock
  resolved packages requiring PHP >= 8.4.1 and the 8.3 container refused to
  boot. Fixed by pinning `config.platform.php = 8.3.33` in `composer.json` and
  re-resolving inside the container. Every later `composer update` now resolves
  against the runtime PHP.
- Dropped the `bunny('Instrument Sans')` font plugin and the `--font-sans`
  `@theme` override the Laravel skeleton ships: both sites use the system font
  stack, and the font plugin fetches from a CDN at build time.
- Vite input is `resources/css/app.css` only; `resources/js/` is deleted. No JS
  bundle.
- The entrypoint runs `npm run build` on every start rather than only when
  `public/build/manifest.json` is missing — Tailwind scans the Blade files, so a
  cached build goes stale as soon as a view changes. Added `make assets` for a
  rebuild without a restart.
- `make up` is `docker compose up -d` so it composes with `make test`;
  `make logs` follows the server.
- Coverage excludes `*Test.php` from `<source>` so sidecar tests do not count
  themselves.
- `app/Models/User.php`, its factory, and the `users` table migration are stock
  Laravel and untouched. The domain has sellers and customers; FEAT-002 owns
  replacing them. `User` is the only uncovered class (overall lines 80.95%,
  `app/Domain` 100%).
- `routes/auth.php` exists and is empty; `routes/web.php` already requires it.
