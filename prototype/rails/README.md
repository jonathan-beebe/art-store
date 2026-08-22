# Art Store prototype (Rails)

A two-sided art marketplace prototype: a seller portal at `/seller` and a
customer storefront at `/`. One Rails app, one SQLite file, no JavaScript.

Read [`docs/architecture.md`](docs/architecture.md) before changing code — it is
the spec for layers, naming, routes, and testing conventions.

## Prerequisites

Docker Desktop. Nothing else: Ruby 3.3, Rails 8.1, SQLite, and the Tailwind
standalone binary live in the `app` container.

## First run

```sh
make up
```

The entrypoint installs the bundle into `src/vendor/bundle`, prepares
`src/storage/development.sqlite3`, builds the Tailwind stylesheet, and clears a
stale server pid before starting Puma. The first run takes a few minutes while
the image builds and gems compile; later runs take seconds.

Then open:

- Storefront — <http://localhost:3000/>
- Seller portal — <http://localhost:3000/seller>

`make down` stops the stack. `make logs` follows the server output.

## Commands

Every target is a thin `docker compose` wrapper, so either form works.

| Make | Docker Compose |
| --- | --- |
| `make up` | `docker compose up -d` |
| `make down` | `docker compose down` |
| `make build` | `docker compose build` |
| `make assets` | `docker compose run --rm app bin/rails tailwindcss:build` |
| `make shell` | `docker compose run --rm app bash` |
| `make test` | `docker compose run --rm app bin/rails test app lib` |
| `make coverage` | `docker compose run --rm -e COVERAGE_MIN=80 app bin/rails test app lib` |
| `make migrate` | `docker compose run --rm app bin/rails db:migrate` |
| `make fresh` | `docker compose run --rm app bin/rails db:drop db:create db:migrate db:seed` |
| `make console` | `docker compose run --rm app bin/rails console` |
| `make logs` | `docker compose logs -f` |

Run any other command the same way:

```sh
docker compose run --rm app bin/rails routes
docker compose exec app bin/rails console      # against the running server
```

## Tests

Minitest, and tests are sidecars: `money.rb` and `money_test.rb` sit in the same
directory. `bin/rails test app lib` globs both trees; `config/application.rb`
tells Zeitwerk to ignore `**/*_test.rb` so a test file never has to name a
constant matching its path. `test/test_helper.rb` holds the Rails base and the
coverage setup — there is no `test/unit` or `test/integration`.

```sh
make test                                                                   # whole suite
docker compose run --rm app bin/rails test app/domain/money_test.rb         # one file
docker compose run --rm app bin/rails test app/domain/money_test.rb -n /percent/   # one test
```

Core tests under `app/domain` require `minitest/autorun` and the file under
test, nothing else, so they also run with no Rails boot:

```sh
docker compose run --rm app ruby -Iapp app/domain/money_test.rb
```

Controller tests are `ActionDispatch::IntegrationTest` and require
`test_helper`; they drive HTTP and assert on rendered HTML.

## Coverage

```sh
make coverage
```

SimpleCov writes `src/coverage/` and prints the overall line coverage plus a
line per group (Domain, Actions, Controllers, Models). `COVERAGE_MIN` sets the
overall line minimum and fails the run below it; `make coverage` passes 80.

## Database

SQLite at `src/storage/development.sqlite3`, created on first run. Tests use
`src/storage/test.sqlite3`.

```sh
make migrate    # apply new migrations
make fresh      # drop, create, migrate, re-seed
```

Deleting `src/storage/development.sqlite3` and running `make up` rebuilds it.

## Styling

Tailwind v4 through `tailwindcss-rails`, which ships the standalone binary — no
Node in the image. The entrypoint runs `bin/rails tailwindcss:build` on every
start, so `make down && make up` picks up template changes. To rebuild without
restarting:

```sh
make assets
```

The source is `src/app/assets/tailwind/application.css`; the build lands in
`src/app/assets/builds/tailwind.css`, which is not committed. Layouts reference
it with `stylesheet_link_tag :app`. There is no JavaScript bundle and no
`<script>` tag in any view.

## Layout

```
prototype/rails/
  README.md            this file
  Dockerfile           ruby:3.3-slim + build tools + sqlite + the rails generator
  docker-compose.yml   one service: app
  docker/entrypoint.sh bundle, database, Tailwind, then the container command
  Makefile             host-side wrappers over docker compose
  docs/                architecture and feature docs
  work/                tickets and journal
  src/                 the Rails application
    app/domain/        pure domain core, sidecar tests beside each file
    app/controllers/   one namespace per site: shop/, seller/
    app/views/layouts/ shop, seller, and the _debug_alert partial both render
    config/routes.rb   / and /seller
    test/test_helper.rb SimpleCov and the Rails test base
```

## Magic links

Passwordless on both sides, with no mailbox: the delivery port flashes the URL
and `layouts/_debug_alert` prints it at the top of both layouts whenever
`flash[:debug_magic_link]` is set. Sign-in itself is not built yet.
