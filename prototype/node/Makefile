.PHONY: up down build assets shell test smoke coverage migrate fresh logs

# docker-compose.yml reads these to run the container as the host user.
export UID := $(shell id -u)
export GID := $(shell id -g)

up:
	docker compose up -d
	@echo "http://localhost:3000"

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
	@docker compose restart app

logs:
	docker compose logs -f
