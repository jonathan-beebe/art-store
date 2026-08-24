.PHONY: up down build logs shell test smoke coverage lint lint-fix assets check migrate fresh seed routes payouts sweep outbox console

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
# RUBYOPT is scoped to the `bin/rails test` command only — `db:test:prepare`
# runs as its own process first, and starting coverage on it too would open
# a second, empty run that clobbers the real one.
test:
	docker compose run --rm -e COVERAGE_MIN=100 app sh -c "bin/rails db:test:prepare && RUBYOPT='-r./test/coverage_boot' bin/rails test"

smoke:
	docker compose run --rm app sh -c "bin/rails db:test:prepare && RUBYOPT='-r./test/coverage_boot' bin/rails test test/smoke_test.rb"

coverage:
	docker compose run --rm app sh -c "bin/rails db:test:prepare && RUBYOPT='-r./test/coverage_boot' bin/rails test"

lint:
	docker compose run --rm app bin/rubocop

lint-fix:
	docker compose run --rm app bin/rubocop -a

assets:
	docker compose run --rm app bin/rails tailwindcss:build

check: lint assets test

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
