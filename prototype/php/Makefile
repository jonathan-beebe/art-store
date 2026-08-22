.PHONY: up down build assets shell test smoke coverage migrate fresh logs

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

migrate:
	docker compose run --rm app php artisan migrate

fresh:
	docker compose run --rm app php artisan migrate:fresh --seed

logs:
	docker compose logs -f
