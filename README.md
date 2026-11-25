# Yii2 Comments

A simple commenting system on Yii2, built mainly to demonstrate a test system.
Guests post comments to a public feed. Admins moderate them and see new comments
in real time over WebSockets (Centrifugo).

Простая система комментариев на Yii2, сделана в первую очередь для демонстрации системы тестов.
Гости пишут комментарии в публичную ленту. Админы их модерируют и видят новые комментарии
в реальном времени через WebSocket (Centrifugo).

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
make up        # start (rebuilds images if Dockerfiles changed)
make down      # stop
make migrate   # apply new DB migrations
make admin-sync  # create the admin or update its password from ADMIN_USERNAME / ADMIN_PASSWORD in .env
make sh        # shell inside the php container
make lint      # code style (PSR-12) + static analysis
```

After `git pull`, run `make up && make setup` again: it installs new dependencies and applies new migrations.

### Troubleshooting

- **Port is busy** (`port is already allocated`): change `APP_PORT`, `MYSQL_PORT` or `CENTRIFUGO_PORT` in `.env`, then `make up`.
- **DB settings changed but nothing happens**: MySQL reads them only on the first start. Reset the DB (deletes all data): `docker compose down -v && make up && make setup`.
- **Logs**: `docker compose logs -f php nginx`; Yii logs are in `runtime/logs/app.log`.
- **Admin badge stays "Connecting…" or shows "Offline"**: `CENTRIFUGO_ALLOWED_ORIGINS` must match
  the address in the browser (for example `http://localhost:8080`; change it together with `APP_PORT`),
  then `make up`. Details: `docker compose logs centrifugo`.
- **Cannot log in as admin after changing the password in `.env`**: run `make admin-sync`.

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
make up        # запустить (пересоберёт образы, если менялись Dockerfile)
make down      # остановить
make migrate   # применить новые миграции БД
make admin-sync  # создать админа или обновить его пароль из ADMIN_USERNAME / ADMIN_PASSWORD в .env
make sh        # консоль внутри контейнера php
make lint      # стиль кода (PSR-12) + статический анализ
```

После `git pull` снова выполните `make up && make setup`: так установятся новые зависимости и применятся новые миграции.

### Если что-то не работает

- **Порт занят** (`port is already allocated`): поменяйте `APP_PORT`, `MYSQL_PORT` или `CENTRIFUGO_PORT` в `.env`, затем `make up`.
- **Поменяли настройки БД, а ничего не изменилось**: MySQL читает их только при первом запуске. Сбросить БД (удалит все данные): `docker compose down -v && make up && make setup`.
- **Логи**: `docker compose logs -f php nginx`; логи Yii — в `runtime/logs/app.log`.
- **Метка в админке так и остаётся «Connecting…» или показывает «Offline»**: `CENTRIFUGO_ALLOWED_ORIGINS`
  должен совпадать с адресом в браузере (например `http://localhost:8080`; меняйте вместе с `APP_PORT`),
  затем `make up`. Подробности: `docker compose logs centrifugo`.
- **Не получается войти в админку после смены пароля в `.env`**: выполните `make admin-sync`.

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
