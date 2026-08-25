.PHONY: help up down build logs shell test smoke coverage lint lint-fix assets check migrate fresh seed routes payouts sweep outbox console image run-image

.DEFAULT_GOAL := help

# Compose names its project after this directory, so two checkouts of the
# same prototype (the main repo and a worktree) would share one container.
# Naming it after the checkout as well keeps them apart.
export COMPOSE_PROJECT_NAME ?= $(notdir $(abspath $(CURDIR)/../..))-$(notdir $(CURDIR))

help: ## list every target with its one-line description
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "} {printf "%-12s  %s\n", $$1, $$2}'

up: ## start the compose stack (http://localhost:3300)
	docker compose up -d
	@echo "http://localhost:3300"

down: ## stop the compose stack
	docker compose down

build: ## build the compose image
	docker compose build

logs: ## follow the server output
	docker compose logs -f

shell: ## open a bash shell in the app container
	docker compose run --rm app bash

# db:test:prepare loads the schema fresh before every run, so a run killed
# mid-way never leaves rows in test.sqlite3 for the next run to trip over.
# The full suite, ungated per docs/alignment.md §6.1: no COVERAGE_MIN, no
# coverage boot.
test: ## the full suite, ungated
	docker compose run --rm app sh -c "bin/rails db:test:prepare && bin/rails test"

smoke: ## the smoke walk only
	docker compose run --rm app sh -c "bin/rails db:test:prepare && RUBYOPT='-r./test/coverage_boot' bin/rails test test/smoke_test.rb"

# The suite with the coverage gate (COVERAGE_MIN=100) and the SimpleCov
# report. RUBYOPT is scoped to the `bin/rails test` command only —
# `db:test:prepare` runs as its own process first, and starting coverage on
# it too would open a second, empty run that clobbers the real one.
coverage: ## the suite with the coverage gate (COVERAGE_MIN=100) and its HTML report
	docker compose run --rm -e COVERAGE_MIN=100 app sh -c "bin/rails db:test:prepare && RUBYOPT='-r./test/coverage_boot' bin/rails test"

lint: ## style + static analysis, read-only (rubocop)
	docker compose run --rm app bin/rubocop

lint-fix: ## the auto-fixable subset applied (rubocop -a)
	docker compose run --rm app bin/rubocop -a

assets: ## build CSS/JS
	docker compose run --rm app bin/rails tailwindcss:build

check: lint assets coverage ## lint -> assets -> coverage; the commit gate

migrate: ## apply pending migrations
	docker compose run --rm app bin/rails db:migrate

fresh: ## drop, create, migrate, re-seed
	docker compose run --rm app bin/rails db:drop db:create db:migrate db:seed

seed: ## add the platform admins and demo data
	docker compose run --rm app bin/rails db:seed

routes: ## print the route table
	docker compose run --rm app bin/rails routes

# Settles the Monday-to-Sunday week that just ended. `make payouts AS_OF=2026-08-24`
# settles the week before that date instead; re-running a period pays nothing.
payouts: ## settle the payout week just ended (AS_OF= for an earlier week)
	docker compose run --rm app bin/rails "payouts:run[$(AS_OF)]"

# Cancels the orders left unverified past STALE_ORDER_HOURS and hands their
# stock back. `make sweep AS_OF=2026-08-24` measures the cutoff from that
# moment instead of now; a second run finds nothing to do.
sweep: ## cancel stale unverified orders (AS_OF= for another cutoff)
	docker compose run --rm app bin/rails "orders:sweep[$(AS_OF)]"

outbox: ## print that Rails has no outbox (notifications deliver in-request)
	@echo "Rails has no outbox; notifications and mail are written and delivered in the same request or job."

console: ## open a Rails console in the app container
	docker compose run --rm app bin/rails console

# Builds the production image (the `runtime` target): no bind mount, production
# bundle, precompiled assets, USER rails. `bin/deploy` prepares the database,
# seeds, and serves — see the README's Deployment section.
image: ## build the production image (the Dockerfile's runtime target)
	docker build --target runtime -t art-store-rails .

# Runs the image built by `make image` standalone, on a host port that does not
# collide with `make up`'s 3300. SECRET_KEY_BASE must come from the environment.
run-image: ## run the production image standalone on port 3400
	docker run --rm -p 3400:3000 -e SECRET_KEY_BASE=$(SECRET_KEY_BASE) art-store-rails bin/deploy
