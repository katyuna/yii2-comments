# Yii2 Comments

[![CI](https://github.com/katyuna/yii2-comments/actions/workflows/ci.yml/badge.svg)](https://github.com/katyuna/yii2-comments/actions/workflows/ci.yml)
[![Nightly regression](https://github.com/katyuna/yii2-comments/actions/workflows/nightly.yml/badge.svg)](https://github.com/katyuna/yii2-comments/actions/workflows/nightly.yml)

A simple commenting system on Yii2, built mainly to demonstrate a test system.
Guests post comments to a public feed. Admins moderate them and see new comments
in real time over WebSockets (Centrifugo).

Простая система комментариев на Yii2, сделана в первую очередь для демонстрации системы тестов.
Гости пишут комментарии в публичную ленту. Админы их модерируют и видят новые комментарии
в реальном времени через WebSocket (Centrifugo).

## Status / Статус

| Part | State |
|---|---|
| App: guest form, public feed, admin login, CRUD, real-time admin list | done |
| Tests: unit, functional, smoke, UI, E2E (3 browsers), regression — every flow step covered ([traceability](#traceability-keep-in-sync-with-claudemd)) | done, all green |
| Bugs found by the tests | 4 found, fixed, locked by regression tests |
| CI: GitHub Actions on every PR + nightly regression ([when the tests run](#when-the-tests-run-ci)) | done |
| Load test (JMeter, bonus) | not done |

## Build and run (English)

### Prerequisites

Install on the host first:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (on Windows with the WSL2 backend), check: `docker compose version`
- Git, check: `git --version`
- `make`, check: `make --version` (Windows: `scoop install make`, or run commands from WSL / Git Bash)

PHP, Composer and Node.js are not needed on the host: everything runs in containers.

### Start

```bash
git clone https://github.com/katyuna/yii2-comments.git
cd yii2-comments
make start
```

`make start` does everything in one command, so it is the same for the first run and every next run:

1. creates `.env` from `.env.example` if there is no `.env` yet;
2. builds the images and starts the containers (`make up`; the first time takes several minutes);
3. installs PHP dependencies and applies DB migrations to `app` and `app_test` (`make setup`);
4. prints the addresses to open.

When it says **Ready**, open <http://localhost:8080>.

For anything except a local try-out, change the passwords and keys in `.env` (they are copied
from `.env.example`), then run `make start` again.

### Stop

```bash
make stop
```

This stops and removes the containers. Comments and the admin user stay in the DB:
the next `make start` shows them again. To delete all data as well: `docker compose down -v`.

| Service | Address |
|---|---|
| Public comment feed | <http://localhost:8080> (`APP_PORT`) |
| Admin area | <http://localhost:8080/admin>, login and password: `ADMIN_USERNAME` / `ADMIN_PASSWORD` from `.env` |
| WebSocket (browser) | `ws://localhost:8080/connection/websocket`, nginx proxies it to Centrifugo |
| MySQL | `localhost:3307` (`MYSQL_PORT`), DBs `app` and `app_test` |
| Centrifugo, direct | `localhost:8000` (`CENTRIFUGO_PORT`), for debugging only |

### How to use

1. **Guest**: open <http://localhost:8080>, enter a name and a comment, press **Post comment**.
   The comment appears at the top of the feed. No login is needed.
2. **Admin**: open <http://localhost:8080/admin> and log in. You see all comments, newest first,
   and can **Edit** or **Delete** each one.
3. **Real time**: keep the admin list open (the badge top right says **Live**). Post a comment
   as a guest in another browser or a private window: it appears in the admin list at once,
   without a page reload.

Anti-spam for guests: a hidden honeypot field and a limit of `COMMENT_RATE_LIMIT_PER_MINUTE`
comments per minute from one IP (default 5).

### Everyday commands

```bash
make start     # deploy and run: .env, containers, dependencies, migrations
make stop      # stop (data is kept)
make up        # only start the containers (rebuilds images if Dockerfiles changed)
make migrate   # apply new DB migrations
make admin-sync  # create the admin or update its password from ADMIN_USERNAME / ADMIN_PASSWORD in .env
make sh        # shell inside the php container
make lint      # code style (PSR-12) + static analysis
```

After `git pull`, run `make start` again: it installs new dependencies and applies new migrations.

### Troubleshooting

- **Port is busy** (`port is already allocated`): change `APP_PORT`, `MYSQL_PORT` or `CENTRIFUGO_PORT` in `.env`, then `make start`.
- **DB settings changed but nothing happens**: MySQL reads them only on the first start. Reset the DB (deletes all data): `docker compose down -v && make start`.
- **Logs**: `docker compose logs -f php nginx`; Yii logs are in `runtime/logs/app.log`.
- **Admin badge stays "Connecting…" or shows "Offline"**: `CENTRIFUGO_ALLOWED_ORIGINS` must match
  the address in the browser (for example `http://localhost:8080`; change it together with `APP_PORT`),
  then `make start`. Details: `docker compose logs centrifugo`.
- **Cannot log in as admin after changing the password in `.env`**: run `make admin-sync`.

## Сборка и запуск (по-русски)

### Что установить заранее

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (на Windows — с WSL2), проверка: `docker compose version`
- Git, проверка: `git --version`
- `make`, проверка: `make --version` (Windows: `scoop install make` или запускать команды из WSL / Git Bash)

PHP, Composer и Node.js на компьютер ставить не нужно: всё работает в контейнерах.

### Запуск

```bash
git clone https://github.com/katyuna/yii2-comments.git
cd yii2-comments
make start
```

`make start` делает всё одной командой, поэтому она одна и та же и для первого запуска, и для всех следующих:

1. создаёт `.env` из `.env.example`, если `.env` ещё нет;
2. собирает образы и запускает контейнеры (`make up`; в первый раз — несколько минут);
3. ставит зависимости PHP и применяет миграции к базам `app` и `app_test` (`make setup`);
4. выводит адреса, которые нужно открыть.

Когда появится **Ready**, откройте <http://localhost:8080>.

Если проект нужен не только для проверки на своём компьютере, поменяйте пароли и ключи в `.env`
(они скопированы из `.env.example`) и снова выполните `make start`.

### Остановка

```bash
make stop
```

Команда останавливает и удаляет контейнеры. Комментарии и админ остаются в базе:
после следующего `make start` они снова на месте. Чтобы удалить и все данные: `docker compose down -v`.

| Сервис | Адрес |
|---|---|
| Публичная лента комментариев | <http://localhost:8080> (`APP_PORT`) |
| Админка | <http://localhost:8080/admin>, логин и пароль: `ADMIN_USERNAME` / `ADMIN_PASSWORD` из `.env` |
| WebSocket (для браузера) | `ws://localhost:8080/connection/websocket`, nginx передаёт его в Centrifugo |
| MySQL | `localhost:3307` (`MYSQL_PORT`), базы `app` и `app_test` |
| Centrifugo напрямую | `localhost:8000` (`CENTRIFUGO_PORT`), только для отладки |

### Как пользоваться

1. **Гость**: откройте <http://localhost:8080>, введите имя и комментарий, нажмите **Post comment**.
   Комментарий появится вверху ленты. Входить не нужно.
2. **Админ**: откройте <http://localhost:8080/admin> и войдите. Видны все комментарии, новые сверху;
   каждый можно отредактировать (**Edit**) или удалить (**Delete**).
3. **В реальном времени**: оставьте список в админке открытым (справа вверху метка **Live**).
   Напишите комментарий как гость в другом браузере или в окне инкогнито: он сразу появится
   в списке админки, без перезагрузки страницы.

Защита от спама: скрытое поле-ловушка (honeypot) и не больше `COMMENT_RATE_LIMIT_PER_MINUTE`
комментариев в минуту с одного IP (по умолчанию 5).

### Команды на каждый день

```bash
make start     # развернуть и запустить: .env, контейнеры, зависимости, миграции
make stop      # остановить (данные сохраняются)
make up        # только запустить контейнеры (пересоберёт образы, если менялись Dockerfile)
make migrate   # применить новые миграции БД
make admin-sync  # создать админа или обновить его пароль из ADMIN_USERNAME / ADMIN_PASSWORD в .env
make sh        # консоль внутри контейнера php
make lint      # стиль кода (PSR-12) + статический анализ
```

После `git pull` снова выполните `make start`: так установятся новые зависимости и применятся новые миграции.

### Если что-то не работает

- **Порт занят** (`port is already allocated`): поменяйте `APP_PORT`, `MYSQL_PORT` или `CENTRIFUGO_PORT` в `.env`, затем `make start`.
- **Поменяли настройки БД, а ничего не изменилось**: MySQL читает их только при первом запуске. Сбросить БД (удалит все данные): `docker compose down -v && make start`.
- **Логи**: `docker compose logs -f php nginx`; логи Yii — в `runtime/logs/app.log`.
- **Метка в админке так и остаётся «Connecting…» или показывает «Offline»**: `CENTRIFUGO_ALLOWED_ORIGINS`
  должен совпадать с адресом в браузере (например `http://localhost:8080`; меняйте вместе с `APP_PORT`),
  затем `make start`. Подробности: `docker compose logs centrifugo`.
- **Не получается войти в админку после смены пароля в `.env`**: выполните `make admin-sync`.

## Testing (English)

The full list of test cases with IDs (U-01, F-03, UI-17 …) and priorities is in
[tests/TEST_CHECKLIST.md](tests/TEST_CHECKLIST.md). Every test names its checklist ID in its docblock.

### Suites

| Suite | What it checks | Tool | Command |
|---|---|---|---|
| **Unit** | One class at a time: form validation, `CommentService` with a mocked publisher, rate limit, password hash, JWT | Codeception (PHPUnit inside) | `make test-unit` |
| **Functional** | HTTP requests handled by Yii in the same PHP process: forms, CSRF, access control, admin CRUD, XSS escaping | Codeception + Yii2 module | `make test-func` |
| **Smoke** | The running app is alive: pages open, a guest can post, the admin can log in, WebSocket connects through nginx | Codeception + PhpBrowser (real HTTP) | `make test-smoke` |
| **UI** | How pages look and behave in a real browser: states, errors, accessibility, screenshots, mobile widths | Playwright (Chromium) | `make test-ui` |
| **E2E** | Whole user flows: a guest posts, the admin sees it live in a second browser, edits, deletes | Playwright, Chromium + Firefox + WebKit | `make test-e2e` |
| **Regression** | Tests for bugs found earlier, tagged `regression` in every suite | all of the above | `make test-regress` |

`make test` runs all suites. Unit and functional tests run inside the `php` container.
Smoke, UI and E2E tests start the **test stand** first (`make test-up`, stop it with `make test-down`).

### When the tests run (CI)

GitHub Actions, `.github/workflows/`. Every job starts its own Docker stand with the same
`make` targets as on a laptop.

| When | What | Workflow |
|---|---|---|
| Every pull request | lint, unit and functional, in parallel | `ci.yml` |
| Pull request to `main` | smoke first (the gate); when it passes, UI and E2E. E2E is a matrix: Chromium, Firefox, WebKit × 2 shards (`--shard=1/2`), 6 jobs, each with its own stand | `ci.yml` |
| Every night, 02:00 UTC (and on demand) | regression: everything tagged `regression` in all suites | `nightly.yml` |

A failed job uploads its artifacts (Actions → the run → Artifacts): Playwright trace, screenshot,
video and HTML report, Codeception `tests/Support/output/`.

**To scale**: more E2E shards (raise the shard count in `ci.yml`); every shard gets its own stand,
so tests in different shards never share a DB.

Time of a full local run on a clean clone (Docker Desktop, Windows 11, 2026-11-25):

| Step | Time |
|---|---|
| `make start` (build images, composer, migrations) | 255 s |
| `make lint` (incl. first `npm ci`) | 224 s |
| unit | 25 s |
| functional | 32 s |
| smoke (incl. starting the test stand) | 28 s |
| UI — 32 tests | 158 s |
| E2E — 14 tests, 3 browsers | 235 s |
| regression — 9 Codeception + 3 Playwright | 142 s |

### Test stand

A second copy of the app that only tests use, so tests never touch your data:

| Container | Role |
|---|---|
| `php-test` + `nginx-test` | The same code, but DB `app_test`, production-like mode (no debug panel), rate limit raised. Open it at <http://localhost:8081> (`APP_TEST_PORT`) |
| `centrifugo-test` | Its own WebSocket server, so test comments never reach the real admin list |
| `playwright` | Browsers (Chromium, Firefox, WebKit) and Node.js for the UI and E2E tests |

### Libraries and why they are used

| Library | Used for |
|---|---|
| [Codeception 5](https://codeception.com/) | PHP test runner shipped with the Yii2 template; suites, groups (`@group smoke`), reports |
| Codeception `Yii2` module | Runs requests through the Yii app without a web server; wraps every test in a DB transaction, so the DB is clean after each test |
| Codeception `PhpBrowser` module | Real HTTP requests to nginx for the smoke suite (Guzzle inside) |
| PHPUnit mocks (`createMock`) | Replace the Centrifugo publisher in unit tests and check how it was called |
| [Playwright Test](https://playwright.dev/) | Browser tests in TypeScript: auto-waiting, web-first assertions, several browser contexts in one test (guest + admin), trace viewer, screenshots and video on failure |
| Playwright `routeWebSocket` | Simulates a WebSocket outage and reconnect without stopping containers |
| Playwright `toHaveScreenshot` | Visual regression: compares pages with approved screenshots |
| [@axe-core/playwright](https://github.com/dequelabs/axe-core-npm) | Automatic accessibility check (WCAG) of pages |
| [mysql2](https://github.com/sidorares/node-mysql2) | Test setup only: empties `app_test` before each browser test and inserts data with fixed dates |
| ESLint + `eslint-plugin-playwright` + Prettier + `tsc` | Style and common mistakes in the TypeScript tests (missing `await`, fixed waits); part of `make lint` |
| [Allure Report](https://allurereport.org/) (`allure-codeception`, `allure-playwright`, `allure` CLI) | One report for all suites: `make test-report` |

### Rules the tests follow

- **Arrange – Act – Assert**, one behaviour per test, a name that says what is expected.
- **Independent tests**: each one creates its own data. Codeception rolls back the DB transaction,
  Playwright empties the comment table before every test. Tests can run in any order.
- **Selectors**: `data-testid` attributes (`getByTestId`), never CSS classes or XPath. Role and label
  locators (`getByRole`, accessible names) only where the test checks what a screen reader gets, or for
  elements Bootstrap renders without a test id (menu button, pager items).
- **Page Objects** (`tests/e2e/pages/`): one class per page hides selectors and actions,
  so a markup change is fixed in one place.
- **No fixed waits** (`sleep`, `waitForTimeout`): Playwright waits for the expected state itself.
- **Tags**: Codeception `@group smoke|regression`, Playwright `@ui`, `@e2e`, `@regression`,
  `@cross-browser` in the test title. `@flowStep N` in the docblock marks the step of the comment flow.
- **A failing test is never skipped or weakened.** If it finds a bug, the bug is fixed in a `fix/` branch.
- **Artifacts of failed tests** are kept: see "Viewing the results" below.
- **Screenshots** (UI-18) are approved images in `tests/e2e/specs/ui/__screenshots__/`. After an intended
  design change: `make test-ui ARGS="--update-snapshots"`, then review the new images in the PR.

### Viewing the results

**All suites in one report (Allure).** `make test-report` runs every suite (unit, functional, smoke
incl. regression, UI, E2E in three browsers) and builds one
[Allure Report](https://allurereport.org/): `tests/allure/report/index.html`. It is a single file —
double-click it (Windows: `start tests\allure\report\index.html`). The report shows the total,
every suite as a tree, each test with its steps, the error and, for browser tests, the screenshot,
video and trace. Failed tests do not stop the run, so they appear in the report too. Each run replaces
the previous report. Settings: `tests/e2e/allurerc.mjs` (e.g. `reportLanguage: 'ru'`).

The per-tool views below are useful while working on one suite.

**1. In the terminal.** Every `make test-…` command prints each test and a summary:
Codeception — `✔` / `✖` per test and `OK (42 tests, …)` or `FAILURES!` with the reason of each failure;
Playwright — `✓` / `✘` per test and `32 passed` / `1 failed`.
Use `make -k test` to run all suites without stopping at the first failing one.

**2. Playwright HTML report (UI, E2E).** Open `tests/e2e/playwright-report/index.html` in a browser
(Windows: double-click it, or `start tests\e2e\playwright-report\index.html`). It lists every test of the
**last** run with its steps and time; a failed test also has a screenshot, a video and a trace.
Each run overwrites the report, so after `make test-ui` it shows UI tests, after `make test-e2e` E2E tests.
`tests/e2e/test-results/junit.xml` holds the same result for CI.

**3. Trace of a failed browser test.** `tests/e2e/test-results/<test name>/trace.zip` is a step-by-step
recording: the page before and after every action, network, console. Drag the file onto
<https://trace.playwright.dev> — it opens in your browser and is not uploaded anywhere.
(`npx playwright show-trace` needs Node.js on the host, which this project does not require.)

**4. Codeception (unit, functional, smoke).** When a test fails, the HTML page it received is saved in
`tests/Support/output/*.fail.html`; open it in a browser to see what the test saw. Codeception does not
delete old files there, so look at the date or empty the folder before a run. For a full HTML report
of a suite add `--html`, e.g. `docker compose exec php vendor/bin/codecept run Unit --html`,
then open `tests/Support/output/report.html`.

## Тестирование (по-русски)

Полный список тестов с номерами (U-01, F-03, UI-17 …) и приоритетами — в
[tests/TEST_CHECKLIST.md](tests/TEST_CHECKLIST.md). В описании каждого теста указан его номер из чек-листа.

### Наборы тестов (сьюты)

| Сьют | Что проверяет | Инструмент | Команда |
|---|---|---|---|
| **Unit** | Один класс за раз: валидация формы, `CommentService` с заглушкой вместо Centrifugo, лимит отправок, хэш пароля, JWT | Codeception (внутри PHPUnit) | `make test-unit` |
| **Functional** | HTTP-запросы, которые Yii обрабатывает в том же процессе PHP: формы, CSRF, доступ в админку, редактирование и удаление, экранирование HTML | Codeception + модуль Yii2 | `make test-func` |
| **Smoke** | Запущенное приложение живо: страницы открываются, гость может написать, админ может войти, WebSocket подключается через nginx | Codeception + PhpBrowser (настоящий HTTP) | `make test-smoke` |
| **UI** | Как страницы выглядят и ведут себя в настоящем браузере: состояния, ошибки, доступность, скриншоты, мобильные экраны | Playwright (Chromium) | `make test-ui` |
| **E2E** | Сценарии целиком: гость пишет, админ во втором браузере сразу видит комментарий, редактирует, удаляет | Playwright, Chromium + Firefox + WebKit | `make test-e2e` |
| **Regression** | Тесты на найденные раньше ошибки, во всех сьютах с тегом `regression` | все перечисленные | `make test-regress` |

`make test` запускает все сьюты. Unit и functional работают внутри контейнера `php`.
Smoke, UI и E2E сначала поднимают **тестовый стенд** (`make test-up`, остановить — `make test-down`).

### Когда запускаются тесты (CI)

GitHub Actions, `.github/workflows/`. Каждая задача поднимает свой Docker-стенд теми же
командами `make`, что и на ноутбуке.

| Когда | Что | Файл |
|---|---|---|
| Каждый pull request | lint, unit и functional, параллельно | `ci.yml` |
| Pull request в `main` | сначала smoke («ворота»); если прошёл — UI и E2E. E2E — матрица: Chromium, Firefox, WebKit × 2 части (`--shard=1/2`), 6 задач, у каждой свой стенд | `ci.yml` |
| Каждую ночь в 02:00 UTC (и вручную) | регрессия: всё с тегом `regression` во всех сьютах | `nightly.yml` |

Упавшая задача сохраняет артефакты (Actions → запуск → Artifacts): trace, скриншот, видео
и HTML-отчёт Playwright, папку Codeception `tests/Support/output/`.

**Как масштабировать**: больше частей E2E (увеличить число shard в `ci.yml`); у каждой части свой
стенд, поэтому тесты разных частей не делят одну БД.

Время полного прогона на чистом клоне (Docker Desktop, Windows 11, 2026-11-25):

| Шаг | Время |
|---|---|
| `make start` (сборка образов, composer, миграции) | 255 с |
| `make lint` (с первым `npm ci`) | 224 с |
| unit | 25 с |
| functional | 32 с |
| smoke (с запуском тестового стенда) | 28 с |
| UI — 32 теста | 158 с |
| E2E — 14 тестов, 3 браузера | 235 с |
| regression — 9 Codeception + 3 Playwright | 142 с |

### Тестовый стенд

Вторая копия приложения только для тестов, чтобы тесты никогда не трогали ваши данные:

| Контейнер | Зачем |
|---|---|
| `php-test` + `nginx-test` | Тот же код, но база `app_test`, режим как в продакшене (без панели отладки), лимит отправок поднят. Адрес: <http://localhost:8081> (`APP_TEST_PORT`) |
| `centrifugo-test` | Свой WebSocket-сервер, чтобы тестовые комментарии не приходили в настоящую админку |
| `playwright` | Браузеры (Chromium, Firefox, WebKit) и Node.js для тестов UI и E2E |

### Библиотеки и зачем они нужны

| Библиотека | Для чего |
|---|---|
| [Codeception 5](https://codeception.com/) | Запуск PHP-тестов, идёт в комплекте с шаблоном Yii2; сьюты, группы (`@group smoke`), отчёты |
| Модуль Codeception `Yii2` | Прогоняет запросы через приложение Yii без веб-сервера; каждый тест идёт в транзакции БД, поэтому после теста база чистая |
| Модуль Codeception `PhpBrowser` | Настоящие HTTP-запросы к nginx для smoke-тестов (внутри Guzzle) |
| Моки PHPUnit (`createMock`) | Подменяют отправку в Centrifugo в unit-тестах и проверяют, как её вызвали |
| [Playwright Test](https://playwright.dev/) | Браузерные тесты на TypeScript: сам ждёт нужного состояния, несколько браузерных контекстов в одном тесте (гость + админ), трассировка, скриншот и видео при падении |
| Playwright `routeWebSocket` | Имитирует обрыв и восстановление WebSocket без остановки контейнеров |
| Playwright `toHaveScreenshot` | Визуальная регрессия: сравнивает страницы с утверждёнными скриншотами |
| [@axe-core/playwright](https://github.com/dequelabs/axe-core-npm) | Автоматическая проверка доступности страниц (WCAG) |
| [mysql2](https://github.com/sidorares/node-mysql2) | Только подготовка данных: очищает `app_test` перед каждым браузерным тестом и добавляет записи с фиксированными датами |
| ESLint + `eslint-plugin-playwright` + Prettier + `tsc` | Стиль и типичные ошибки в TypeScript-тестах (забытый `await`, фиксированные паузы); входит в `make lint` |
| [Allure Report](https://allurereport.org/) (`allure-codeception`, `allure-playwright`, сборщик `allure`) | Один отчёт по всем сьютам: `make test-report` |

### Правила, по которым написаны тесты

- **Подготовка – действие – проверка** (Arrange – Act – Assert), одно поведение на тест, название говорит, что ожидается.
- **Тесты независимы**: каждый создаёт свои данные. Codeception откатывает транзакцию БД,
  Playwright очищает таблицу комментариев перед каждым тестом. Порядок запуска не важен.
- **Селекторы**: атрибуты `data-testid` (`getByTestId`), никаких CSS-классов и XPath. Поиск по роли и
  подписи (`getByRole`, доступное имя) — только там, где тест проверяет, что услышит скринридер, или для
  элементов, которые Bootstrap рисует без test id (кнопка меню, пункты пагинации).
- **Page Objects** (`tests/e2e/pages/`): по классу на страницу, в нём селекторы и действия.
  Если поменяется вёрстка, исправлять придётся в одном месте.
- **Никаких фиксированных пауз** (`sleep`, `waitForTimeout`): Playwright сам ждёт нужного состояния.
- **Теги**: Codeception `@group smoke|regression`, Playwright `@ui`, `@e2e`, `@regression`,
  `@cross-browser` в названии теста. `@flowStep N` в описании отмечает шаг пути комментария.
- **Упавший тест никогда не отключают и не ослабляют.** Если он нашёл ошибку, её исправляют в ветке `fix/`.
- **Что остаётся от упавших тестов** — см. «Как смотреть результаты» ниже.
- **Скриншоты** (UI-18) — утверждённые картинки в `tests/e2e/specs/ui/__screenshots__/`. После намеренного
  изменения дизайна: `make test-ui ARGS="--update-snapshots"`, затем просмотреть новые картинки в PR.

### Как смотреть результаты

**Все сьюты в одном отчёте (Allure).** `make test-report` прогоняет все сьюты (unit, functional, smoke
вместе с регрессионными, UI, E2E в трёх браузерах) и собирает один
[Allure Report](https://allurereport.org/): `tests/allure/report/index.html`. Это один файл —
откройте его двойным кликом (в Windows: `start tests\allure\report\index.html`). В отчёте общий итог,
дерево сьютов, у каждого теста шаги, текст ошибки, а у браузерных тестов ещё скриншот, видео и
трассировка. Упавшие тесты не останавливают прогон, поэтому тоже попадают в отчёт. Каждый запуск
заменяет прошлый отчёт. Настройки — `tests/e2e/allurerc.mjs` (например, `reportLanguage: 'ru'`).

Способы ниже удобны, когда работаете с одним сьютом.

**1. В терминале.** Каждая команда `make test-…` выводит каждый тест и итог:
Codeception — `✔` / `✖` по тестам и `OK (42 tests, …)` или `FAILURES!` с причиной каждого падения;
Playwright — `✓` / `✘` по тестам и `32 passed` / `1 failed`.
`make -k test` прогоняет все сьюты и не останавливается на первом упавшем.

**2. HTML-отчёт Playwright (UI, E2E).** Откройте в браузере `tests/e2e/playwright-report/index.html`
(в Windows — двойной клик по файлу или `start tests\e2e\playwright-report\index.html`). В нём все тесты
**последнего** прогона с шагами и временем; у упавшего теста ещё скриншот, видео и трассировка.
Каждый запуск перезаписывает отчёт: после `make test-ui` в нём UI-тесты, после `make test-e2e` — E2E.
`tests/e2e/test-results/junit.xml` — тот же результат для CI.

**3. Трассировка упавшего браузерного теста.** `tests/e2e/test-results/<имя теста>/trace.zip` — пошаговая
запись: страница до и после каждого действия, сеть, консоль. Перетащите файл на
<https://trace.playwright.dev> — он откроется в вашем браузере и никуда не отправляется.
(`npx playwright show-trace` требует Node.js на компьютере, а проекту он не нужен.)

**4. Codeception (unit, functional, smoke).** Когда тест падает, полученная им HTML-страница сохраняется в
`tests/Support/output/*.fail.html`; откройте её в браузере, чтобы увидеть то же, что видел тест.
Старые файлы Codeception там не удаляет — смотрите на дату или очищайте папку перед прогоном.
Полный HTML-отчёт по сьюту — с флагом `--html`, например
`docker compose exec php vendor/bin/codecept run Unit --html`, затем откройте `tests/Support/output/report.html`.

## For contributors

```bash
make hooks   # once after cloning: enables the branching reminder hook
```

## Branching rules (short version)

Full rules: **[CONTRIBUTING.md](CONTRIBUTING.md)**.

- Never commit to `main`. Every change goes in its own branch and reaches `main` through a Pull Request.
- One feature = one branch with production code only: `feature/<feature-name>`.
- Each test level of a feature gets its own branch: `test/<level>-<feature-name>`
  (`unit`, `functional`, `smoke`, `ui`, `e2e`, `regression`, `load`).
  Phase 2 covered the whole app with one branch per suite: `test/<level>-comments`.
- Order: app first (all feature branches), then tests (all test branches). Plan: `CLAUDE.md`.
- Bug fix: `fix/<bug-name>`, then its regression test in `test/regression-<bug-name>`.
- Names use kebab-case: `feature/guest-comment-form`, `test/e2e-guest-comment-form`.
- Start from a fresh `main`, keep branches short-lived, rebase on `main` before the PR.
- Merge with squash only, after CI is green; the branch is deleted after merge.
- `main` is not protected on GitHub; the rules hold by convention (git hook, PR template, CI on every PR).

## Traceability (keep in sync with CLAUDE.md)

| # | Flow step | Locked by |
|---|---|---|
| 1 | Guest fills in the form | smoke `AppIsAliveCest`; UI `comment-form.spec.ts`, `accessibility.spec.ts` (keyboard only), `responsive.spec.ts` (375–1280 px); e2e `comment-flow.spec.ts` |
| 2 | Validation and anti-spam (CSRF, honeypot, rate limit) | unit `CommentFormTest`, `CommentServiceTest` (rate limit); functional `GuestCommentCest` (CSRF, honeypot, rate limit); smoke `AppIsAliveCest` (real form with CSRF); UI `comment-form.spec.ts` (errors, honeypot); e2e `comment-flow.spec.ts` (E-06); regression `EnvironmentRegressionCest` (rate limit on the test stand) |
| 3 | Comment saved to MySQL | unit `CommentServiceTest`; functional `GuestCommentCest`; smoke `AppIsAliveCest` (migrations); UI `comment-form.spec.ts` (double click = one comment); e2e `comment-flow.spec.ts`, `realtime.spec.ts` (saved while WebSocket is down); regression `PublisherFailureLogTest`, `RegressionCest` (utf8mb4) |
| 4 | Comment shown in the public feed (encoded) | unit `CommentServiceTest` (order); functional `GuestCommentCest` (escaping, order, pager); smoke `AppIsAliveCest` (new comment first); UI `feed.spec.ts`, `comment-form.spec.ts` (feed updated after post), `responsive.spec.ts`; e2e `comment-flow.spec.ts`; regression `RegressionCest` (utf8mb4) |
| 5 | Event published to Centrifugo (HTTP API) | unit `CommentServiceTest` (mock publisher); smoke `AppIsAliveCest` (Centrifugo API); e2e `comment-flow.spec.ts`, `realtime.spec.ts`; regression `PublisherFailureLogTest` (Centrifugo down: error logged), `regression.spec.ts` (unicode) |
| 6 | Admin logs in / guest is denied | unit `UserTest`, `LoginFormTest`; functional `AdminAccessCest`, `AdminCrudCest` (admin list); smoke `AppIsAliveCest` (admin from .env); UI `admin.spec.ts` (login form), `accessibility.spec.ts` (keyboard login); e2e `comment-flow.spec.ts`; regression `RegressionCest` (login page), `EnvironmentRegressionCest` (php-fpm env) |
| 7 | Admin list subscribes to the channel (badge Live) | unit `CentrifugoTokenIssuerTest`; functional `AdminAccessCest` (token endpoint); smoke `AppIsAliveCest` (WebSocket via nginx); UI `admin.spec.ts` (Live badge, reconnect); e2e `comment-flow.spec.ts`, `realtime.spec.ts` (Live, reconnect, logout); regression `EnvironmentRegressionCest` (JWT signed in php-fpm) |
| 8 | New comment appears in the admin list without reload | functional `AdminCrudCest` (escaping in admin list); UI `admin.spec.ts` (live row = reloaded row), `feed.spec.ts` (long text wraps); e2e `comment-flow.spec.ts`, `realtime.spec.ts` (XSS); regression `regression.spec.ts` (unicode live row) |
| 9 | Admin edits / deletes a comment | functional `AdminCrudCest`, `AdminAccessCest` (guest denied); UI `admin.spec.ts` (confirm, edit); e2e `comment-flow.spec.ts` |

Steps match `@flowStep N` in tests and the "Шаг" column of `tests/TEST_CHECKLIST.md`.
Each test branch fills in its suite.
