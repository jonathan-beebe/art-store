.PHONY: up down build assets shell test smoke coverage migrate fresh seed payouts logs

# docker-compose.yml reads these to run the container as the host user.
export UID := $(shell id -u)
export GID := $(shell id -g)

up:
	docker compose up -d
	@echo "http://localhost:4000"

down:
	docker compose down

build:
	docker compose build

assets:
	docker compose run --rm app npm run assets

shell:
	docker compose run --rm app bash

test:
	docker compose run --rm app npm run check

smoke:
	docker compose run --rm app node --test app/test/smoke.test.ts

coverage:
	docker compose run --rm app npm run coverage

migrate:
	docker compose run --rm app npm run migrate

# A running server holds the deleted database file open, so it restarts onto the
# rebuilt one. Restarting a stopped stack does nothing.
fresh:
	docker compose run --rm app npm run fresh
	docker compose run --rm app npm run seed
	@docker compose restart app

# Adds the platform operators. Nobody else can reach /admin, and running it
# twice adds nobody.
seed:
	docker compose run --rm app npm run seed

# Settles the Monday-to-Sunday week that just ended. `make payouts AS_OF=2026-08-24`
# settles the week before that date instead; re-running a period pays nothing.
payouts:
	docker compose run --rm app npm run payouts -- $(if $(AS_OF),--as-of=$(AS_OF))

logs:
	docker compose logs -f
