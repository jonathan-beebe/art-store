.PHONY: up down build logs shell test smoke coverage lint lint-fix assets check migrate fresh seed routes payouts sweep outbox console image run-image

# Compose names its project after this directory, so two checkouts of the
# same prototype (the main repo and a worktree) would share one container.
# Naming it after the checkout as well keeps them apart.
export COMPOSE_PROJECT_NAME ?= $(notdir $(abspath $(CURDIR)/../..))-$(notdir $(CURDIR))

# Compose names its project after this directory, so two checkouts of the
# same prototype (the main repo and a worktree) would share one container.
# Naming it after the checkout as well keeps them apart.
export COMPOSE_PROJECT_NAME ?= $(notdir $(abspath $(CURDIR)/../..))-$(notdir $(CURDIR))

up:
	docker compose up -d
	@echo "http://localhost:3300"

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f

shell:
	docker compose run --rm app bash

# db:test:prepare loads the schema fresh before every run, so a run killed
# mid-way never leaves rows in test.sqlite3 for the next run to trip over.
# The full suite, ungated per docs/alignment.md §6.1: no COVERAGE_MIN, no
# coverage boot.
test:
	docker compose run --rm app sh -c "bin/rails db:test:prepare && bin/rails test"

smoke:
	docker compose run --rm app sh -c "bin/rails db:test:prepare && RUBYOPT='-r./test/coverage_boot' bin/rails test test/smoke_test.rb"

# The suite with the coverage gate (COVERAGE_MIN=100) and the SimpleCov
# report. RUBYOPT is scoped to the `bin/rails test` command only —
# `db:test:prepare` runs as its own process first, and starting coverage on
# it too would open a second, empty run that clobbers the real one.
coverage:
	docker compose run --rm -e COVERAGE_MIN=100 app sh -c "bin/rails db:test:prepare && RUBYOPT='-r./test/coverage_boot' bin/rails test"

lint:
	docker compose run --rm app bin/rubocop

lint-fix:
	docker compose run --rm app bin/rubocop -a

assets:
	docker compose run --rm app bin/rails tailwindcss:build

check: lint assets coverage

migrate:
	docker compose run --rm app bin/rails db:migrate

fresh:
	docker compose run --rm app bin/rails db:drop db:create db:migrate db:seed

seed:
	docker compose run --rm app bin/rails db:seed

routes:
	docker compose run --rm app bin/rails routes

# Settles the Monday-to-Sunday week that just ended. `make payouts AS_OF=2026-08-24`
# settles the week before that date instead; re-running a period pays nothing.
payouts:
	docker compose run --rm app bin/rails "payouts:run[$(AS_OF)]"

# Cancels the orders left unverified past STALE_ORDER_HOURS and hands their
# stock back. `make sweep AS_OF=2026-08-24` measures the cutoff from that
# moment instead of now; a second run finds nothing to do.
sweep:
	docker compose run --rm app bin/rails "orders:sweep[$(AS_OF)]"

outbox:
	@echo "Rails has no outbox; notifications and mail are written and delivered in the same request or job."

console:
	docker compose run --rm app bin/rails console

# Builds the production image (the `runtime` target): no bind mount, production
# bundle, precompiled assets, USER rails. `bin/deploy` prepares the database,
# seeds, and serves — see the README's Deployment section.
image:
	docker build --target runtime -t art-store-rails .

# Runs the image built by `make image` standalone, on a host port that does not
# collide with `make up`'s 3300. SECRET_KEY_BASE must come from the environment.
run-image:
	docker run --rm -p 3400:3000 -e SECRET_KEY_BASE=$(SECRET_KEY_BASE) art-store-rails bin/deploy
