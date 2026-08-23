.PHONY: up down build assets shell test smoke coverage docs-check migrate fresh seed payouts logs image run-image

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

# `check` now runs the coverage-gated suite, not the fast one, so `make test`
# also fails under 95% lines / 90% branches.
test:
	docker compose run --rm app npm run check

smoke:
	docker compose run --rm app node --test app/test/smoke.test.ts

coverage:
	docker compose run --rm app npm run coverage

# Renders every Mermaid block under docs/ through minlag/mermaid-cli. A diagram
# that does not parse fails the build.
docs-check:
	./docker/docs-check.sh

migrate:
	docker compose run --rm app npm run migrate

# The app is stopped for the rebuild: a server left running holds the deleted
# database file open, and every write it makes to that unlinked file is lost.
# Stopping and starting a stack that was never up does nothing.
fresh:
	@docker compose stop app
	docker compose run --rm app npm run fresh
	docker compose run --rm app npm run seed
	@docker compose start app

# Adds the platform admins and the full demo catalog. Nobody else can reach
# /admin without the admins; the demo half does nothing once a seller row
# already exists, so running this twice adds nobody new.
seed:
	docker compose run --rm app npm run seed

# Settles the Monday-to-Sunday week that just ended. `make payouts AS_OF=2026-08-24`
# settles the week before that date instead; re-running a period pays nothing.
payouts:
	docker compose run --rm app npm run payouts -- $(if $(AS_OF),--as-of=$(AS_OF))

logs:
	docker compose logs -f

# Builds the production image (the `runtime` target): no bind mount, production
# dependencies only, USER node. Run migrations against it before serving —
# see the README's Deployment section.
image:
	docker build --target runtime -t art-store-node .

# Runs the image built by `make image` standalone, on a host port that does not
# collide with `make up`'s 4000.
run-image:
	docker run --rm -p 4100:4000 art-store-node
