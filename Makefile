DC = docker compose
# -T: no TTY, so the targets also work in CI and scripts
PHP = $(DC) exec -T php

.PHONY: hooks start stop up down sh setup install migrate migrate-test admin-sync test-unit test-smoke test-func test-e2e test-regress test lint fix

hooks:         ## Once after cloning: enable branching reminder git hooks
	git config core.hooksPath .githooks

start: .env    ## Deploy and run everything with one command (first run and after git pull)
	$(MAKE) --no-print-directory up
	$(MAKE) --no-print-directory setup
	@echo Ready. Open http://localhost:8080 - admin area: http://localhost:8080/admin
	@echo Port and admin login/password: APP_PORT, ADMIN_USERNAME, ADMIN_PASSWORD in .env

stop: down     ## Stop the project (data in the DB is kept)

# $(file) is built into make, so this works in cmd/PowerShell too, where cp does not exist
.env:
	$(file >$@,$(file <.env.example))
	@echo Created .env from .env.example. Change the passwords and keys in it.

up:            ## Build and start all containers
	$(DC) up -d --build

down:          ## Stop containers
	$(DC) down

sh:            ## Shell into php container
	$(DC) exec php bash

setup: install migrate migrate-test  ## First run after `make up`: dependencies + both DBs

install:       ## Install PHP dependencies, make runtime folders writable for php-fpm
	$(PHP) composer install
	$(PHP) chmod -R a+rwX runtime web/assets

migrate:       ## Apply DB migrations to the app DB
	$(PHP) php yii migrate --interactive=0

migrate-test:  ## Apply DB migrations to the app_test DB
	$(PHP) php tests/Support/bin/yii migrate --interactive=0

admin-sync:    ## Create the admin or update its password from ADMIN_USERNAME / ADMIN_PASSWORD
	$(PHP) php yii admin-user/sync

test-unit:
	$(PHP) vendor/bin/codecept run Unit

test-smoke:
	$(PHP) vendor/bin/codecept run Functional -g smoke

test-func:
	$(PHP) vendor/bin/codecept run Functional

test-e2e:
	$(DC) --profile e2e run --rm playwright

test-regress:
	$(PHP) vendor/bin/codecept run -g regression
	$(DC) --profile e2e run --rm playwright npx playwright test --grep @regression

test: test-unit test-func test-e2e

lint:
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G --no-progress

fix:
	$(PHP) vendor/bin/php-cs-fixer fix
