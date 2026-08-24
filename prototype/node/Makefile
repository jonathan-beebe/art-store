.PHONY: up down build assets shell test smoke coverage lint lint-fix check docs-check routes migrate fresh seed payouts outbox sweep logs image run-image

# Compose names its project after this directory, so two checkouts of the
# same prototype (the main repo and a worktree) would share one container.
# Naming it after the checkout as well keeps them apart.
export COMPOSE_PROJECT_NAME ?= $(notdir $(abspath $(CURDIR)/../..))-$(notdir $(CURDIR))

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

# The full suite with the coverage gate: fails under 95% lines / 90% branches.
# `npm test` inside the container runs the same files without the gate, for a
# faster local loop; that raw script is not exposed as a make target.
test:
	docker compose run --rm app npm run coverage

smoke:
	docker compose run --rm app node --test app/test/smoke.test.ts

# Same run as `test` — the coverage reporter always writes coverage/lcov.info
# alongside the table it prints, so there is nothing a dedicated report step
# would add.
coverage:
	docker compose run --rm app npm run coverage

# Read-only: tsc --noEmit, then eslint. Nothing here changes a file.
lint:
	docker compose run --rm app npm run lint

# The auto-fixable subset of lint: eslint --fix. Type errors have no auto-fix
# and are left for `make lint` to report.
lint-fix:
	docker compose run --rm app npm run lint:fix

# The commit gate: lint -> assets -> coverage, composed as one npm script so
# the pre-commit hook and CI run the identical command and cannot drift apart.
check:
	docker compose run --rm app npm run check

# Renders every Mermaid block under docs/ through minlag/mermaid-cli. A diagram
# that does not parse fails the build.
docs-check:
	./docker/docs-check.sh

# Every route the app answers and the plugin tree they hang in, printed by
# Fastify's own introspection: the three sites and their prefixes, as the code
# registers them rather than as a table someone keeps up to date.
routes:
	docker compose run --rm app npm run routes

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

# Writes every queued message out as an .eml file under src/storage/outbox and
# stamps it sent. `make outbox DIR=/somewhere` writes them elsewhere. The
# admin site's Drain button does the same work.
outbox:
	docker compose run --rm app npm run outbox -- $(if $(DIR),--dir=$(DIR))

# The stale-order sweep has no CLI yet; FEAT-019 adds one. Exits 0 so a
# scripted `make sweep` does not fail while the command doesn't exist.
sweep:
	@echo "make sweep: lands with FEAT-019, no CLI yet"

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
