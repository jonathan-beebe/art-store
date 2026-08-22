.PHONY: up down build assets shell test smoke coverage migrate fresh logs console

up:
	docker compose up -d
	@echo "http://localhost:3000"

down:
	docker compose down

build:
	docker compose build

assets:
	docker compose run --rm app bin/rails tailwindcss:build

shell:
	docker compose run --rm app bash

test:
	docker compose run --rm app bin/rails test app lib db test

smoke:
	docker compose run --rm app bin/rails test test/smoke_test.rb

coverage:
	docker compose run --rm -e COVERAGE_MIN=80 app bin/rails test app lib db test

migrate:
	docker compose run --rm app bin/rails db:migrate

fresh:
	docker compose run --rm app bin/rails db:drop db:create db:migrate db:seed

logs:
	docker compose logs -f

console:
	docker compose run --rm app bin/rails console
