DC = docker compose
# -T: no TTY, so the targets also work in CI and scripts
PHP = $(DC) exec -T php

.PHONY: hooks up down sh setup install migrate migrate-test test-unit test-smoke test-func test-e2e test-regress test lint fix

hooks:         ## Once after cloning: enable branching reminder git hooks
	git config core.hooksPath .githooks

up:            ## Build and start all containers
	$(DC) up -d --build

down:          ## Stop containers
	$(DC) down

sh:            ## Shell into php container
	$(DC) exec php bash

setup: install migrate migrate-test  ## First run after `make up`: dependencies + both DBs

install:       ## Install PHP dependencies, make runtime folders writable for php-fpm
	$(PHP) composer install
	$(PHP) chmod 0777 runtime web/assets

migrate:       ## Apply DB migrations to the app DB
	$(PHP) php yii migrate --interactive=0

migrate-test:  ## Apply DB migrations to the app_test DB
	$(PHP) php tests/Support/bin/yii migrate --interactive=0

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
