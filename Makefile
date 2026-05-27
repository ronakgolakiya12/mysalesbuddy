.PHONY: up down build fresh test-backend test-frontend coverage-backend coverage-frontend coverage migrate seed shell logs minio-setup

# ---------------------------------------------------------------------------
# Docker dev environment
# ---------------------------------------------------------------------------
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

fresh: build up
	docker compose exec app php artisan migrate:fresh --seed

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

shell:
	docker compose exec app bash

logs:
	docker compose logs -f

minio-setup:
	docker compose exec minio sh -c "mc alias set local http://127.0.0.1:9000 $$MINIO_ROOT_USER $$MINIO_ROOT_PASSWORD && mc mb -p local/$${AWS_BUCKET:-mysalesbuddy} || true"

# ---------------------------------------------------------------------------
# Tests (run directly — drop the `docker compose exec app` prefix
# so they work on bare-metal hosts including Windows + Laragon).
# ---------------------------------------------------------------------------
test-backend:
	php artisan test

test-frontend:
	npx vitest run --no-file-parallelism

# ---------------------------------------------------------------------------
# Coverage
# ---------------------------------------------------------------------------
# NOTE: backend coverage requires a coverage driver (pcov or xdebug).
# CI installs pcov via shivammathur/setup-php. On Linux dev hosts:
#   apt-get install php8.3-pcov     # then add `pcov.enabled=1` to php.ini
# Windows / Laragon hosts: no pcov binary is shipped — run inside CI or WSL.
coverage-backend:
	php -d pcov.enabled=1 -d pcov.directory=app vendor/bin/phpunit \
		--coverage-clover=storage/coverage/clover.xml \
		--coverage-html=storage/coverage/html \
		--coverage-text

coverage-frontend:
	npx vitest run --no-file-parallelism --coverage

coverage: coverage-backend coverage-frontend

# Future improvement (out of scope for Phase 8):
#   make mutation — run Infection (https://infection.github.io) against
#   the backend code. Requires composer require --dev infection/infection
#   and a long-running CI matrix entry; revisit when 80% line coverage
#   is stable and the test suite is faster.
