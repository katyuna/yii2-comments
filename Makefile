DC = docker compose
PHP = $(DC) exec php

.PHONY: up down sh install init migrate migrate-test test-unit test-smoke test-func test-e2e test-regress test lint fix

up:            ## Build and start all containers
	$(DC) up -d --build

down:          ## Stop containers
	$(DC) down

sh:            ## Shell into php container
	$(PHP) bash

init:          ## First run only: create Yii2 basic app in the current folder
	$(PHP) composer create-project --prefer-dist yiisoft/yii2-app-basic /tmp/yii && \
	$(PHP) sh -c "cp -rn /tmp/yii/. /app/ && rm -rf /tmp/yii"

install:       ## Install PHP dependencies
	$(PHP) composer install

migrate:
	$(PHP) php yii migrate --interactive=0

migrate-test:
	$(PHP) php tests/bin/yii migrate --interactive=0

test-unit:
	$(PHP) vendor/bin/codecept run unit

test-smoke:
	$(PHP) vendor/bin/codecept run functional -g smoke

test-func:
	$(PHP) vendor/bin/codecept run functional

test-e2e:
	$(DC) --profile e2e run --rm playwright

test-regress:
	$(PHP) vendor/bin/codecept run -g regression
	$(DC) --profile e2e run --rm playwright npx playwright test --grep @regression

test: test-unit test-func test-e2e

lint:
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(PHP) vendor/bin/phpstan analyse

fix:
	$(PHP) vendor/bin/php-cs-fixer fix
