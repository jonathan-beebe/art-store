.PHONY: up down build assets shell test smoke coverage lint lint-fix check migrate fresh seed routes payouts sweep outbox analyse logs

# Compose names its project after this directory, so two checkouts of the
# same prototype (the main repo and a worktree) would share one container.
# Naming it after the checkout as well keeps them apart.
export COMPOSE_PROJECT_NAME ?= $(notdir $(abspath $(CURDIR)/../..))-$(notdir $(CURDIR))

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
	@echo "http://localhost:8000"

down:
	docker compose down

build:
	docker compose build

assets:
	docker compose run --rm app npm run build

shell:
	docker compose run --rm app bash

# The full suite under pcov, gated at 100% of lines.
test:
	docker compose run --rm app composer test

# The end-to-end walk alone. Ungated: it exercises one path through the app,
# not the whole tree, so `test`'s coverage gate does not apply here.
smoke:
	docker compose run --rm app php vendor/bin/pest --testsuite Smoke

coverage:
	docker compose run --rm app composer test:coverage

# Style plus static analysis, read-only: Pint's --test mode, then PHPStan.
lint:
	docker compose run --rm --no-deps --entrypoint composer app lint
	docker compose run --rm --no-deps --entrypoint composer app analyse

# The auto-fixable subset of `lint`: Pint rewrites files in place.
lint-fix:
	docker compose run --rm --no-deps --entrypoint composer app lint:fix

# PHPStan alone, against the file tree only (no web server needed for a
# static run). `lint` already runs this; kept as a target for a quicker
# static-analysis-only pass.
analyse:
	docker compose run --rm --no-deps --entrypoint composer app analyse

# The commit gate: lint, then the asset build, then the coverage-gated suite.
check: lint assets test

migrate:
	docker compose run --rm app php artisan migrate

fresh:
	docker compose run --rm app php artisan migrate:fresh --seed

seed:
	docker compose run --rm app php artisan db:seed

# Every route the app answers, printed by Laravel's own introspection.
routes:
	docker compose run --rm app php artisan route:list

# Settles the Monday-to-Sunday week that just ended. `make payouts AS_OF=2026-08-24`
# settles the week before that date instead; re-running a period pays nothing.
payouts:
	docker compose run --rm app php artisan payouts:run $(if $(AS_OF),--as-of=$(AS_OF))

# Cancels the orders a guest never verified once they are older than
# STALE_ORDER_HOURS (default 24) and hands their stock back. Idempotent: a
# second run finds nothing.
sweep:
	docker compose run --rm app php artisan orders:sweep

# The PHP prototype has no outbox: notifications are in-app, rendered from
# the database channel (app/Notifications/, config/notifications.php).
outbox:
	@echo "outbox: notifications are in-app (app/Notifications/, config/notifications.php); this prototype has no outbox"

logs:
	docker compose logs -f
