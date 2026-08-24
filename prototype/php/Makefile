.PHONY: up down build assets shell test smoke coverage migrate fresh logs analyse lint check

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

test:
	docker compose run --rm app composer test

smoke:
	docker compose run --rm app composer test -- --testsuite Smoke

coverage:
	docker compose run --rm app composer test:coverage

analyse:
	docker compose run --rm --no-deps --entrypoint composer app analyse

lint:
	docker compose run --rm --no-deps --entrypoint composer app lint

check:
	docker compose run --rm --no-deps --entrypoint composer app check

migrate:
	docker compose run --rm app php artisan migrate

fresh:
	docker compose run --rm app php artisan migrate:fresh --seed

logs:
	docker compose logs -f
