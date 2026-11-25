DC = docker compose
# -T: no TTY, so the targets also work in CI and scripts
PHP = $(DC) exec -T php

.PHONY: hooks start stop up down sh setup install migrate migrate-test admin-sync test-up test-down e2e-deps test-unit test-func test-smoke test-ui test-e2e test-regress test-report test lint fix

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

# ---- Tests ---------------------------------------------------------------------------
# Unit and functional tests run inside the php container (in-process, DB app_test).
# Smoke, UI and E2E tests need the test stand: php-test + nginx-test + centrifugo-test
# on DB app_test (docker-compose.yml, profile "test"), started automatically.
PW = $(DC) --profile test run --rm playwright

test-up:       ## Start the test stand (http://localhost:8081, DB app_test)
	$(DC) --profile test up -d --build php-test nginx-test centrifugo-test

test-down:     ## Stop the test stand (the dev app keeps running)
	$(DC) --profile test rm --stop --force php-test nginx-test centrifugo-test

# npm ci runs only when package-lock.json is newer than the installed packages
tests/e2e/node_modules/.package-lock.json: tests/e2e/package-lock.json
	$(DC) --profile test run --rm --no-deps playwright npm ci --no-audit --no-fund

e2e-deps: tests/e2e/node_modules/.package-lock.json  ## Install Playwright test dependencies

test-unit:     ## Unit tests: models and services, publisher mocked
	$(PHP) vendor/bin/codecept run Unit

test-func:     ## Functional tests: HTTP requests handled in-process by Yii
	$(PHP) vendor/bin/codecept run Functional

test-smoke: test-up  ## Smoke tests: real HTTP against the running test stand
	$(PHP) vendor/bin/codecept run Smoke -g smoke

test-ui: test-up e2e-deps  ## UI tests in Chromium (Playwright); extra options: ARGS="--update-snapshots"
	$(PW) npx playwright test --project=ui $(ARGS)

# CI runs one browser per job: make test-e2e E2E_PROJECTS=--project=e2e-firefox ARGS=--shard=1/2
E2E_PROJECTS ?= --project=e2e-chromium --project=e2e-firefox --project=e2e-webkit

test-e2e: test-up e2e-deps  ## E2E flows: Chromium, plus @cross-browser in Firefox and WebKit
	$(PW) npx playwright test $(E2E_PROJECTS) $(ARGS)

test-regress: test-up e2e-deps  ## Everything tagged regression, in all suites
	$(PHP) vendor/bin/codecept run -g regression
	$(PW) npx playwright test --grep @regression

# One Allure report with every suite. "-" lets make go on when a suite has failures,
# so the report also shows the failed tests; old results are removed first.
test-report: test-up e2e-deps  ## Run all suites, build one report: tests/allure/report/index.html
	$(DC) --profile test run --rm --no-deps playwright rm -rf /allure/results /allure/report
	-$(PHP) vendor/bin/codecept run Unit
	-$(PHP) vendor/bin/codecept run Functional
	-$(PHP) vendor/bin/codecept run Smoke
	-$(PW) npx playwright test
	$(DC) --profile test run --rm --no-deps playwright npx allure generate /allure/results
	@echo Report: open tests/allure/report/index.html in a browser

test: test-unit test-func test-smoke test-ui test-e2e  ## All suites

lint: e2e-deps  ## PHP: PSR-12 + PHPStan; TypeScript tests: ESLint + Prettier + tsc
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	$(DC) --profile test run --rm --no-deps playwright npm run lint

fix: e2e-deps   ## Fix code style in PHP and TypeScript
	$(PHP) vendor/bin/php-cs-fixer fix
	$(DC) --profile test run --rm --no-deps playwright npm run fix
