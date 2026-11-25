# Yii2 Comments

A simple commenting system on Yii2, built mainly to demonstrate a test system.
Guests post comments to a public feed. Admins moderate them and see new comments
in real time over WebSockets (Centrifugo).

## Build and run (English)

### Prerequisites

Install on the host first:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (on Windows with the WSL2 backend), check: `docker compose version`
- Git, check: `git --version`
- `make`, check: `make --version` (Windows: `scoop install make`, or run commands from WSL / Git Bash)

PHP, Composer and Node.js are not needed on the host: everything runs in containers.

### First run

```bash
git clone https://github.com/katyuna/yii2-comments.git
cd yii2-comments
cp .env.example .env   # then change the secrets (passwords, keys)
make up                # build images and start containers (first time: several minutes)
make setup             # composer install + DB migrations (app and app_test)
```

Open <http://localhost:8080>.

| Service | Address |
|---|---|
| Web app (nginx + php-fpm) | <http://localhost:8080> (`APP_PORT`) |
| MySQL | `localhost:3307` (`MYSQL_PORT`), DBs `app` and `app_test` |
| Centrifugo (WebSockets) | `localhost:8000` (`CENTRIFUGO_PORT`) |

### Everyday commands

```bash
make up        # start (rebuilds images if Dockerfiles changed)
make down      # stop
make migrate   # apply new DB migrations
make sh        # shell inside the php container
make lint      # code style (PSR-12) + static analysis
```

After `git pull`, run `make up && make setup` again: it installs new dependencies and applies new migrations.

### Troubleshooting

- **Port is busy** (`port is already allocated`): change `APP_PORT`, `MYSQL_PORT` or `CENTRIFUGO_PORT` in `.env`, then `make up`.
- **DB settings changed but nothing happens**: MySQL reads them only on the first start. Reset the DB (deletes all data): `docker compose down -v && make up && make setup`.
- **Logs**: `docker compose logs -f php nginx`; Yii logs are in `runtime/logs/app.log`.

## Сборка и запуск (по-русски)

### Что установить заранее

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (на Windows — с WSL2), проверка: `docker compose version`
- Git, проверка: `git --version`
- `make`, проверка: `make --version` (Windows: `scoop install make` или запускать команды из WSL / Git Bash)

PHP, Composer и Node.js на компьютер ставить не нужно: всё работает в контейнерах.

### Первый запуск

```bash
git clone https://github.com/katyuna/yii2-comments.git
cd yii2-comments
cp .env.example .env   # затем поменяйте секреты (пароли, ключи)
make up                # собрать образы и запустить контейнеры (в первый раз — несколько минут)
make setup             # composer install + миграции БД (app и app_test)
```

Откройте <http://localhost:8080>.

| Сервис | Адрес |
|---|---|
| Веб-приложение (nginx + php-fpm) | <http://localhost:8080> (`APP_PORT`) |
| MySQL | `localhost:3307` (`MYSQL_PORT`), базы `app` и `app_test` |
| Centrifugo (WebSockets) | `localhost:8000` (`CENTRIFUGO_PORT`) |

### Команды на каждый день

```bash
make up        # запустить (пересоберёт образы, если менялись Dockerfile)
make down      # остановить
make migrate   # применить новые миграции БД
make sh        # консоль внутри контейнера php
make lint      # стиль кода (PSR-12) + статический анализ
```

После `git pull` снова выполните `make up && make setup`: так установятся новые зависимости и применятся новые миграции.

### Если что-то не работает

- **Порт занят** (`port is already allocated`): поменяйте `APP_PORT`, `MYSQL_PORT` или `CENTRIFUGO_PORT` в `.env`, затем `make up`.
- **Поменяли настройки БД, а ничего не изменилось**: MySQL читает их только при первом запуске. Сбросить БД (удалит все данные): `docker compose down -v && make up && make setup`.
- **Логи**: `docker compose logs -f php nginx`; логи Yii — в `runtime/logs/app.log`.

## For contributors

```bash
make hooks   # once after cloning: enables the branching reminder hook
```

## Branching rules (short version)

Full rules: **[CONTRIBUTING.md](CONTRIBUTING.md)**.

- Never commit to `main`. Every change goes in its own branch and reaches `main` through a Pull Request.
- One feature = one branch with production code only: `feature/<feature-name>`.
- Each test level of a feature gets its own branch: `test/<level>-<feature-name>`
  (`unit`, `smoke`, `functional`, `e2e`, `regression`, `load`).
- Order: app first (all feature branches), then tests (all test branches). Plan: `CLAUDE.md`.
- Bug fix: `fix/<bug-name>`, then its regression test in `test/regression-<bug-name>`.
- Names use kebab-case: `feature/guest-comment-form`, `test/e2e-guest-comment-form`.
- Start from a fresh `main`, keep branches short-lived, rebase on `main` before the PR.
- Merge with squash only; the branch is deleted after merge.
- `main` is intentionally not protected on GitHub; revisit after all tests are written.

## Traceability (keep in sync with CLAUDE.md)

| # | Flow step | Locked by |
|---|---|---|
| 1 | Guest opens form | smoke: `FeedCest::formIsVisible` |
| 2 | Input validated (empty, too long, XSS) | unit: `CommentTest` |
| 3 | Comment saved to DB | functional: `PostCommentCest` |
| 4 | Comment shown in public feed (encoded) | functional + e2e `guest-posts-comment.spec.ts` |
| 5 | Event published to Centrifugo | unit: `CommentServiceTest` (mock publisher) |
| 6 | Admin logs in / guest is denied | functional: `AdminAccessCest` |
| 7 | New comment appears in admin list without reload | e2e: `admin-realtime.spec.ts` |
| 8 | Admin edits / deletes comment | functional `AdminCrudCest` + e2e `admin-crud.spec.ts` |
