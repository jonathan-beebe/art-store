.PHONY: help up down build assets shell test smoke coverage lint lint-fix check precommit migrate fresh seed seed-activity routes payouts sweep outbox analyse logs image run-image

.DEFAULT_GOAL := help

# Compose names its project after this directory, so two checkouts (the
# main repo and a worktree) would share one container. Naming it after the
# checkout as well keeps them apart: art-store-app in the main checkout.
export COMPOSE_PROJECT_NAME ?= $(notdir $(abspath $(CURDIR)/..))-$(notdir $(CURDIR))

help: ## list every target with its one-line description
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "} {printf "%-12s  %s\n", $$1, $$2}'

up: ## start the compose stack (http://localhost:8000)
	docker compose up -d
	@echo "http://localhost:8000"

down: ## stop the compose stack
	docker compose down

build: ## build the compose image
	docker compose build

assets: ## build CSS/JS
	docker compose run --rm app npm run build

shell: ## open a bash shell in the app container
	docker compose run --rm app bash

# The full suite, ungated per docs/spec.md §6.1: Pest with no pcov and
# no coverage minimum.
test: ## the full suite, ungated
	docker compose run --rm app composer test

# The end-to-end walk alone.
smoke: ## the smoke walk only
	docker compose run --rm app php vendor/bin/pest --testsuite Smoke

# The suite under pcov, gated at 100% of lines, writing the text summary and
# the HTML report to coverage/.
coverage: ## the suite with the coverage gate (95% line floor) and its HTML report
	docker compose run --rm app composer test:coverage

# Style plus static analysis, read-only: Pint's --test mode, then PHPStan, in
# one container (`lint:all` composes the two composer scripts) rather than
# two spawns.
lint: ## style + static analysis, read-only (pint --test, then phpstan)
	docker compose run --rm --no-deps --entrypoint composer app lint:all

# The auto-fixable subset of `lint`: Pint rewrites files in place.
lint-fix: ## the auto-fixable subset applied (pint)
	docker compose run --rm --no-deps --entrypoint composer app lint:fix

# PHPStan alone, against the file tree only (no web server needed for a
# static run). `lint` already runs this; kept as a target for a quicker
# static-analysis-only pass.
analyse: ## PHPStan alone
	docker compose run --rm --no-deps --entrypoint composer app analyse

# The per-commit gate: style + static analysis, then the ungated suite, in
# one container spawn — no asset build, no coverage instrumentation, no HTML
# report. `.githooks/pre-commit` runs this on every commit that touches app/
# outside docs/; `check` (below) still runs once before a PR, and CI
# (.github/workflows/check.yml) runs `check` again on push/PR as the backstop.
precommit: ## the commit gate: lint + ungated tests, one container
	docker compose run --rm app sh -c "composer lint:all && composer test"

# The full gate, run once per branch before a PR opens rather than per
# commit: lint, then the asset build, then the coverage-gated suite.
check: lint assets coverage ## lint -> assets -> coverage; the pre-PR gate

migrate: ## apply pending migrations
	docker compose run --rm app php artisan migrate

fresh: ## clear every database and re-seed the demo data
	docker compose run --rm app sh -c 'f="$${LOG_DATABASE_FILE:-storage/logs.sqlite3}"; rm -f "$$f" "$$f-wal" "$$f-shm"; php artisan migrate:fresh --seed'

seed: ## add the platform admins and demo data
	docker compose run --rm app php artisan db:seed

# Fills the app database and analytics store with a ninety-plus day ramp of
# customers, browsing, orders, listings, and payouts — refuses in production
# and refuses a second run against a database that already carries its
# marker row. Run once, after `make fresh`.
seed-activity: ## fill the store with a ramp of ninety days of activity (local dev only)
	docker compose run --rm app php artisan seed:activity

# Every route the app answers, printed by Laravel's own introspection.
routes: ## print the route table
	docker compose run --rm app php artisan route:list

# Settles the Monday-to-Sunday week that just ended. `make payouts AS_OF=2026-08-24`
# settles the week before that date instead; re-running a period pays nothing.
payouts: ## settle the payout week just ended (AS_OF= for an earlier week)
	docker compose run --rm app php artisan payouts:run $(if $(AS_OF),--as-of=$(AS_OF))

# Cancels the orders a guest never verified once they are older than
# STALE_ORDER_HOURS (default 24) and hands their stock back. Idempotent: a
# second run finds nothing.
sweep: ## cancel stale unverified orders
	docker compose run --rm app php artisan orders:sweep

# The app has no outbox: notifications are in-app, rendered from the
# database channel (app/Notifications/, config/notifications.php).
outbox: ## print that the app has no outbox (notifications are in-app)
	@echo "outbox: notifications are in-app (app/Notifications/, config/notifications.php); the app has no outbox"

logs: ## follow the server output
	docker compose logs -f

# Builds the production image (the `runtime` target): no bind mount, production
# vendor tree, built assets, USER www-data. `composer run deploy` migrates,
# seeds, and serves — see the README's Deployment section.
image: ## build the production image (the Dockerfile's runtime target)
	docker build --target runtime -t art-store-php .

# Runs the image built by `make image` standalone, on a host port that does not
# collide with `make up`'s 8000. APP_KEY must come from the environment.
# --cap-drop and --security-opt emulate the restrictions of Render's sandboxed
# production runtime, which grants no capabilities and no privilege gains —
# an image that only boots with Docker's default privileges fails there, so
# this target refuses it too.
run-image: ## run the production image standalone on port 8100, under production-shaped restrictions
	docker run --rm --cap-drop=ALL --security-opt no-new-privileges \
		-p 8100:8000 -e APP_KEY=$(APP_KEY) art-store-php composer run deploy
