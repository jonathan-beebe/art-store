---
id: FEAT-001
type: feature
status: resolved
created: 
---

# FEAT-001: Dockerized Rails foundation with sidecar Minitest and Tailwind

## Problem
`prototype/rails/` contains only `docs/architecture.md` and `work/`. There is no application, no container, no test runner, and no CSS pipeline. Every other ticket depends on this scaffold and on its conventions (sidecar tests, namespaced controllers, two layouts, debug alert partial) being in place.

## Goal
A team member clones the repo, runs one command, and has the app served on `http://localhost:3000` with a green test suite and a coverage report, with nothing installed on the host.

## Outcome
- `make up` serves the app at `http://localhost:3000`; `/` renders a storefront placeholder page and `/seller` renders a seller-portal placeholder page, each in its own layout with Tailwind styles applied.
- `make test` runs the Minitest suite inside the container and is green; `make coverage` prints a SimpleCov summary.
- A sidecar test `app/domain/money_test.rb` beside `app/domain/money.rb` runs with no Rails boot and passes, proving sidecar discovery and the Zeitwerk ignore rule; an integration test beside each placeholder controller proves controller tests work.
- `README.md` documents: prerequisites (Docker only), first run, serving, running tests and coverage, a single test, opening a shell, resetting the database, and the repository layout.
- The debug alert partial renders `flash[:debug_magic_link]` in both layouts.

## Why it matters
All later tickets fan out from this scaffold.

## Discovery notes
Read `docs/architecture.md` first. The PHP spike in `prototype/php/` (its `Dockerfile`, `docker/entrypoint.sh`, `Makefile`, `README.md`) is a working reference for the same shape.
- Create the app from a container so nothing touches the host: `docker run --rm -v "$PWD":/work -w /work ruby:3.3 sh -c 'gem install rails && rails new src --database=sqlite3 --css=tailwind --skip-javascript --skip-hotwire --skip-jbuilder --skip-action-cable --skip-action-mailbox --skip-action-text --skip-kamal --skip-solid --skip-docker --skip-ci --skip-rubocop --skip-brakeman'` (keep Action Mailer — it is the email hook; keep Active Storage for listing images). Fix file ownership afterwards if the container wrote as root (`chown` via a container, or run with `-u "$(id -u):$(id -g)"`).
- Dockerfile: `ruby:3.3-slim`, `build-essential git libsqlite3-dev sqlite3 pkg-config libyaml-dev curl`; `tailwindcss-rails` ships the standalone binary, so no Node. Bundle inside the bind mount (`BUNDLE_PATH=/var/www/src/vendor/bundle`) so `vendor/` survives restarts; one compose service `app`, working dir `/var/www/src`, port `3000:3000`, command `bin/rails server -b 0.0.0.0`. An `entrypoint.sh` that runs `bundle install` when the bundle is missing, `bin/rails db:prepare`, and `bin/rails tailwindcss:build`, removes a stale `tmp/pids/server.pid`, then execs the command.
- `config/application.rb`: `Rails.autoloaders.main.ignore(Rails.root.glob("app/**/*_test.rb"))`; test task: `bin/rails test app lib` (Minitest runner accepts directories). Keep `test/test_helper.rb`; add `simplecov` to the test group; `test_helper` starts SimpleCov with groups `Domain` (`app/domain`), `Actions`, `Controllers`, `Models` and `minimum_coverage` from env. Core tests must pass with `ruby -Iapp app/domain/money_test.rb` too.
- Routes: `namespace :seller` (placeholder `seller/dashboard#show` at `/seller`), `namespace :shop, path: ""` root at `shop/storefront#show`, `namespace :auth` empty for FEAT-002.
- Layouts per the architecture doc; both include `layouts/_debug_alert` (yellow, `role="alert"`, link as `<a>`).
- `Makefile` targets: `up`, `down`, `build`, `assets`, `shell`, `test`, `coverage`, `migrate`, `fresh` (`db:reset` → drop, create, migrate, seed), `logs`, `console`.
- First sidecar: `Domain::Money` — `Data.define(:cents)` or frozen class with `from_cents`, `from_dollars(String)`, `+`, `*(Integer)`, `percent(Integer)` (half-cent rounds away from zero), `format` → `$1,234.56` / `-$12.34`. TDD it.
- Commit with the ticket id when green. Stage only `prototype/rails`.

## Working

### Versions
Ruby 3.3.12, Rails 8.1.3.1, Puma 8.0.2, SQLite3 gem 2.9.6, tailwindcss-rails
4.6.0 / tailwindcss 4.3.3, Propshaft, SimpleCov 1.1.1.

### Decisions
- **Scaffolded from the image, not a one-off `ruby:3.3` run.** The Dockerfile
  installs `rails ~> 8.0` (resolved to 8.1.3.1), then
  `docker compose run --rm --entrypoint bash -u "$(id -u):$(id -g)" app -c 'rails new . …'`
  generated the tree inside the bind mount. Everything is owned by the host
  user and `vendor/bundle` sits in `src/`.
- **`app/domain` is re-pushed under the `Domain` namespace.** Rails makes every
  `app/*` directory a Zeitwerk root, which would map `app/domain/money.rb` to
  `::Money`. `config/application.rb` removes it from `eager_load_paths` and an
  initializer calls `push_dir(app/domain, namespace: Domain)`. Verified with
  `bin/rails zeitwerk:check`.
- **Sidecar ignore is a glob pattern**, `"#{app.root}/{app,lib}/**/*_test.rb"`,
  not an expanded file list, so a new test file needs no restart.
- **SimpleCov starts in `test/test_helper.rb`** before `config/environment` is
  required, and prints per-group lines because there is no browser in the
  container. `SimpleCov.at_exit` calls `result.format!` — calling
  `run_exit_tasks!` there recurses. SimpleCov 1.1 renamed `add_filter`,
  `track_files`, and `add_group` to `skip`, `cover`, and `group`.
- **A `BaseController` per site** (`Shop::BaseController`, `Seller::BaseController`)
  owns the layout, which gives FEAT-002 a place for the session identity.
- **`parallelize(workers: 1)`.** Forked workers mean merging coverage results
  and sharing one SQLite file for a suite that runs in 0.1s.

### Deviations from the discovery notes
- `make fresh` is `db:drop db:create db:migrate db:seed`, not `db:reset`.
  `db:reset` loads `db/schema.rb` and fails until the first migration exists.
- No empty `namespace :auth` in `config/routes.rb`; FEAT-002 adds it with its
  first route.
- Deleted `Procfile.dev`, `bin/dev` (foreman host loop, superseded by the
  container) and `app/views/layouts/application.html.erb` (no controller
  reaches it). Dropped `capybara` and `selenium-webdriver` — no browser in the
  image and no system tests. Pointed `bin/ci` at `bin/rails test app lib`.

### Verified
- `make up` from nothing (no `vendor`, no `.bundle`, no `storage/*.sqlite3`, no
  `app/assets/builds/tailwind.css`): `/` and `/seller` return 200, both link
  `/assets/tailwind-<digest>.css`, which serves 200.
- `docker compose down` then `make up` again: 200 on both.
- `make test` and `make coverage`: 29 runs, 40 assertions, 0 failures.
  86.95% overall line coverage, Domain 100%.
- `ruby -Iapp app/domain/money_test.rb` with no Rails boot: 23 runs, 0 failures.
- `bin/rails zeitwerk:check`, `make fresh`, single-file and `-n /pattern/` runs.
