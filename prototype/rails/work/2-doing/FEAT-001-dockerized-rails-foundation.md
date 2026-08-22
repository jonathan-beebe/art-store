---
id: FEAT-001
type: feature
status: open
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
