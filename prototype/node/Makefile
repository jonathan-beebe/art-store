.PHONY: help up down build assets shell test smoke coverage lint lint-fix check docs-check routes migrate fresh seed payouts outbox sweep logs image run-image

.DEFAULT_GOAL := help

# Compose names its project after this directory, so two checkouts of the
# same prototype (the main repo and a worktree) would share one container.
# Naming it after the checkout as well keeps them apart.
export COMPOSE_PROJECT_NAME ?= $(notdir $(abspath $(CURDIR)/../..))-$(notdir $(CURDIR))

# docker-compose.yml reads these to run the container as the host user.
export UID := $(shell id -u)
export GID := $(shell id -g)

help: ## list every target with its one-line description
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "} {printf "%-12s  %s\n", $$1, $$2}'

up: ## start the compose stack (http://localhost:4000)
	docker compose up -d
	@echo "http://localhost:4000"

down: ## stop the compose stack
	docker compose down

build: ## build the compose image
	docker compose build

assets: ## build CSS/JS
	docker compose run --rm app npm run assets

shell: ## open a bash shell in the app container
	docker compose run --rm app bash

# The full suite, ungated per docs/alignment.md §6.1: same test files as
# `coverage`, no V8 coverage collection, no lcov report.
test: ## the full suite, ungated
	docker compose run --rm app npm test

smoke: ## the smoke walk only
	docker compose run --rm app node --test app/test/smoke.test.ts

# The suite with the coverage gate: fails under 95% lines / 90% branches, and
# writes coverage/lcov.info alongside the table it prints.
coverage: ## the suite with the coverage gate and its LCOV report
	docker compose run --rm app npm run coverage

# Read-only: tsc --noEmit, then eslint. Nothing here changes a file.
lint: ## style + static analysis, read-only (tsc, then eslint)
	docker compose run --rm app npm run lint

# The auto-fixable subset of lint: eslint --fix. Type errors have no auto-fix
# and are left for `make lint` to report.
lint-fix: ## the auto-fixable subset applied (eslint --fix)
	docker compose run --rm app npm run lint:fix

# The commit gate: lint -> assets -> coverage, composed as one npm script so
# the pre-commit hook and CI run the identical command and cannot drift apart.
check: ## lint -> assets -> coverage; the commit gate
	docker compose run --rm app npm run check

# Renders every Mermaid block under docs/ through minlag/mermaid-cli. A diagram
# that does not parse fails the build.
docs-check: ## render every Mermaid block under docs/; a bad diagram fails
	./docker/docs-check.sh

# Every route the app answers and the plugin tree they hang in, printed by
# Fastify's own introspection: the three sites and their prefixes, as the code
# registers them rather than as a table someone keeps up to date.
routes: ## print the route table
	docker compose run --rm app npm run routes

migrate: ## apply pending migrations
	docker compose run --rm app npm run migrate

# The app is stopped for the rebuild: a server left running holds the deleted
# database file open, and every write it makes to that unlinked file is lost.
# `stop` on a stack that was never up is a no-op (exit 0), but `start` refuses
# with "has no container to start" in that case, so it only runs when a
# container for the service actually exists — which also covers the case
# where `stop` above just stopped one.
fresh: ## delete the database, rebuild it, seed it, restart the server
	@docker compose stop app
	docker compose run --rm app npm run fresh
	docker compose run --rm app npm run seed
	@if [ -n "$$(docker compose ps -aq app)" ]; then docker compose start app; fi

# Adds the platform admins and the full demo catalog. Nobody else can reach
# /admin without the admins; the demo half does nothing once a seller row
# already exists, so running this twice adds nobody new.
seed: ## add the platform admins and demo data
	docker compose run --rm app npm run seed

# Settles the Monday-to-Sunday week that just ended. `make payouts AS_OF=2026-08-24`
# settles the week before that date instead; re-running a period pays nothing.
payouts: ## settle the payout week just ended (AS_OF= for an earlier week)
	docker compose run --rm app npm run payouts -- $(if $(AS_OF),--as-of=$(AS_OF))

# Writes every queued message out as an .eml file under src/storage/outbox and
# stamps it sent. `make outbox DIR=/somewhere` writes them elsewhere. The
# admin site's Drain button does the same work.
outbox: ## write queued messages as .eml files (DIR= for another directory)
	docker compose run --rm app npm run outbox -- $(if $(DIR),--dir=$(DIR))

# Cancels every order left unverified longer than STALE_ORDER_HOURS (24 by
# default), handing its stock back to the storefront, and prunes expired
# rate-limit windows. `make sweep AS_OF=2026-08-24` runs both as though the
# run happened then; running it twice cancels nothing new.
sweep: ## cancel stale unverified orders, prune rate-limit windows (AS_OF=)
	docker compose run --rm app npm run sweep -- $(if $(AS_OF),--as-of=$(AS_OF))

logs: ## follow the server output
	docker compose logs -f

# Builds the production image (the `runtime` target): no bind mount, production
# dependencies only, USER node. Run migrations against it before serving —
# see the README's Deployment section.
image: ## build the production image (the Dockerfile's runtime target)
	docker build --target runtime -t art-store-node .

# Runs the image built by `make image` standalone, on a host port that does not
# collide with `make up`'s 4000.
run-image: ## run the production image standalone on port 4100
	docker run --rm -p 4100:4000 art-store-node
