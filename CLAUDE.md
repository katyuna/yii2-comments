# CLAUDE.md — Yii2 Comments (Site.pro SDET homework)

Rules and context for Claude Code in this repository. Read this file fully before any change.

## Project goal

A simple commenting system on Yii2, used mainly to demonstrate a **test system**:

1. **Front-end** — a guest (no login) posts a comment; it appears in the public feed.
2. **Admin area** — an admin logs in, sees the list of comments, can edit/delete them.
   New comments appear in the admin list **in real time via WebSockets** (no page reload).
3. **Environment** — everything runs in Docker (`docker compose up`).
4. **Tests** — unit, smoke, e2e, regression (+ load as a bonus). Every step of the
   comment flow must be covered by a named test (see "Traceability" below).

The repository owner is new to PHP. **After every change, explain in 2–5 plain sentences
what was changed and why**, and name the Yii2 concept involved (e.g. "ActiveRecord",
"migration", "AccessControl behavior").

## Stack (pinned)

| Part | Choice |
|---|---|
| Language | PHP 8.3 (php-fpm in Docker) |
| Framework | Yii2 basic template (`yiisoft/yii2-app-basic`) |
| DB | MySQL 8.0 (`app` DB + separate `app_test` DB for tests) |
| Web server | nginx |
| WebSockets | Centrifugo v5 (own container); PHP publishes via HTTP API, browser subscribes with `centrifuge-js` |
| Unit / functional tests | Codeception 5 (ships with yii2-app-basic) |
| E2E tests | Playwright (TypeScript), folder `tests/e2e/` |
| Load (bonus) | JMeter plan in `tests/load/` |
| Quality | PHP-CS-Fixer (PSR-12), PHPStan level 6, ESLint + Prettier for TS |
| CI | GitHub Actions |

Do not add new dependencies (Composer or npm) without saying why and asking first.

## Commands

All PHP commands run **inside the container**, never on the host.

```bash
make up            # docker compose up -d --build
make down          # docker compose down
make sh            # shell into php container
make migrate       # php yii migrate --interactive=0 (app DB)
make migrate-test  # migrations on app_test DB
make test-unit     # vendor/bin/codecept run unit
make test-smoke    # vendor/bin/codecept run functional -g smoke
make test-func     # vendor/bin/codecept run functional
make test-e2e      # Playwright in its own container
make test-regress  # all suites, group/tag "regression"
make lint          # php-cs-fixer (dry-run) + phpstan
make fix           # php-cs-fixer fix
```

If a `make` target does not exist yet, create it in `Makefile` rather than typing long commands.

## Directory layout

```
config/            Yii config (web.php, console.php, db.php, test_db.php, params.php)
controllers/       SiteController (feed + form)
modules/admin/     Admin module: controllers, views, AccessControl
models/            Comment (ActiveRecord), CommentForm, User
services/          CommentService, RealtimePublisher (Centrifugo HTTP client)
migrations/        DB schema + admin user seed
views/             Public views
web/               Document root (index.php, js/admin-realtime.js)
tests/unit/        Codeception unit tests
tests/functional/  Codeception functional tests (smoke = group "smoke")
tests/e2e/         Playwright: specs/, pages/ (Page Objects), fixtures/
tests/load/        JMeter .jmx + README
docker/            Dockerfiles and configs per service
.github/workflows/ CI pipelines
```

## Architecture rules

- **Thin controllers.** Controllers only: read request → call service/model → render/redirect.
- Business logic lives in `services/` or models. `CommentService::create()` saves the
  comment and then calls `RealtimePublisher::publishNewComment()`.
- `RealtimePublisher` is behind an interface (`RealtimePublisherInterface`) so unit tests can mock it.
  A WebSocket failure must **not** break saving a comment (log the error, continue).
- Schema changes **only via migrations**. Never edit an applied migration — add a new one.
- Config and secrets come from environment variables (`.env`, read with `getenv()`).
  Never commit `.env`; keep `.env.example` up to date.
- Admin user is created by a migration/console command from `ADMIN_USERNAME` / `ADMIN_PASSWORD`
  env vars; the password is stored only as `Yii::$app->security->generatePasswordHash()`.

## Security checklist (must hold for every change)

- Output of user content always via `Html::encode()` (no raw echo) → XSS.
- CSRF validation stays enabled (Yii default). Do not disable it to make a test pass.
- Model `rules()`: `required`, `string` with `max` (author ≤ 64, text ≤ 1000), `trim`.
- Guest form has anti-spam: honeypot field + simple rate limit per IP.
- Admin module protected by `AccessControl` (`roles => ['@']`); all destructive actions are `POST` only (`VerbFilter`).
- Centrifugo admin channel requires a JWT issued by Yii only to logged-in admins.

## Code style

- **PSR-12** (enforced by PHP-CS-Fixer), `declare(strict_types=1);` in new non-view PHP files.
- Typed properties, parameter and return types everywhere possible.
- Names: classes `PascalCase`, methods/variables `camelCase`, DB tables/columns `snake_case`.
- One class per file; namespaces follow folders (`app\services\CommentService`).
- No commented-out code. No `TODO` without an issue reference (`TODO(#12): ...`).

### Comments

- PHPDoc on every public class and method: short description, `@param`, `@return`, `@throws`.
- Inline comments explain **why**, not **what**.
- In tests, add `@flowStep N` in the docblock when the test locks a step of the comment flow.

## Testing rules

- **Every behaviour change gets tests at each relevant level**, each level in its own
  `test/<level>-<feature>` branch/PR (see Git workflow). The tests are written in
  phase 2, after the whole app is built (see "Work plan").
- **Never delete, skip or weaken a test to make it pass.** If a test is wrong, explain why before changing it.
- Test pyramid: many unit, fewer functional, few e2e.
- Arrange–Act–Assert structure; one behaviour per test; descriptive names
  (`testGuestCannotPostEmptyComment`, `admin sees new comment without reload`).
- Tests are independent: each creates its own data (fixtures), no order dependence,
  DB cleaned between tests. Tests use the `app_test` DB only.
- No `sleep()` / fixed timeouts. Playwright: web-first assertions (`await expect(locator).toBeVisible()`).
- Selectors: `data-testid` attributes only (add them to views). No CSS-class or XPath selectors.
- E2E uses Page Objects (`tests/e2e/pages/`). Realtime test uses **two browser contexts**
  (guest + admin) in one spec.
- Tags: Codeception `@group smoke|regression`; Playwright `@smoke` / `@regression` in test titles.
- A test for every fixed bug, tagged `regression`, in branch `test/regression-<bug-name>`.
- On failure keep artifacts: Codeception `_output/`, Playwright trace + screenshot + video (CI uploads them).

### Suites

| Suite | Purpose | When | Time budget |
|---|---|---|---|
| unit | Model validation, services with mocked publisher | every commit/PR | < 30 s |
| smoke | App is alive: pages open, guest can post, admin can log in, WS connects | after `compose up`/deploy, gates the rest | < 2 min |
| e2e | Full flow in a real browser incl. realtime | PR to `main`, nightly | < 10 min, sharded |
| regression | All suites filtered by `regression` + bug tests | nightly, before release | — |
| load (bonus) | Throughput of POST comment, WS connections | manual / weekly | — |

### Traceability (keep in sync with README)

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

Update this table whenever a test is added, renamed or removed.

## Git workflow

Full branching rules: **`CONTRIBUTING.md`** (source of truth; summary in README).

- **GitHub Flow.** `main` is always green. It is intentionally *not* protected on GitHub;
  no direct commits to `main` by convention.
- **One feature = one branch** `feature/<feature-name>`, containing **production code only**.
- **Each test level = its own branch** `test/<level>-<feature-name>`,
  level ∈ `unit | smoke | functional | e2e | regression | load`.
  A feature is done only when the feature branch and all its test-level branches are merged.
- Other types: `fix/`, `chore/`, `docs/`, `ci/`. Names are kebab-case (`[a-z0-9-]`), named after the feature.
- Branch from a fresh `main`; keep branches short-lived (one branch = one PR);
  rebase on `origin/main` before opening the PR.
- Merge via Pull Request only, after CI is green. **Squash merge only**; the PR title is a
  Conventional Commit (it becomes the commit in `main`). Delete the branch after merge.
- Fill in `.github/pull_request_template.md`.
- `make hooks` enables `.githooks/pre-commit`, which warns (does not block) on `main` or a bad branch name.
- Bug fixes: `fix/<bug-name>` (code only), then `test/regression-<bug-name>` with the test
  that reproduces the bug, tagged `regression`.
- Order: **app first, tests second** (see "Work plan"). Known trade-off: until phase 2
  is done, `main` holds code not covered by tests. Phase 2 starts right after phase 1;
  no new features until every feature has its test branches merged.
- **Conventional Commits**, English, imperative mood, ≤ 72 chars subject:
  `feat(comment): add guest comment form`,
  `test(e2e): cover realtime comment in admin list`,
  `fix(feed): encode comment text`, `ci: shard playwright run`, `docs(readme): ...`.
- Atomic commits: one logical change each.
- Before committing: `make lint` and the relevant test suite must pass.
- Claude Code: **do not commit or push unless explicitly asked.** Propose the commit message.

### Claude Code: branch check before every task

Before changing any file, run `git branch --show-current`.
- If it is `main`, or a branch for a different feature/test level than the task:
  **stop, remind the owner, propose a branch name** (`<type>/<kebab-case-feature-name>`)
  and **ask the owner to confirm or rename it**. Never pick the name silently.
- After confirmation: `git switch main && git pull`, then `git switch -c <branch>`.
- If a task mixes production code and tests, split it: feature branch first, then one branch per test level.

## Work plan

**Phase 1 — app.** Feature branches in this order, each merged before the next starts:

| # | Branch | Scope |
|---|---|---|
| 1 | `chore/yii2-setup` | Yii2 basic app, DB config from env vars, `app_test` config |
| 2 | `feature/guest-comment-form` | `comment` migration, `Comment` model, `CommentService`, form + public feed |
| 3 | `feature/admin-auth` | Admin user from `ADMIN_USERNAME`/`ADMIN_PASSWORD`, login/logout, `admin` module behind `AccessControl` |
| 4 | `feature/admin-crud` | Admin list, edit, delete (`VerbFilter` POST) |
| 5 | `feature/admin-realtime` | `RealtimePublisher` (Centrifugo HTTP API), JWT for admin, `admin-realtime.js` |

`data-testid` attributes are added to views already in phase 1, so phase 2 does not touch views.

**Phase 2 — tests.** For each feature, in the same order: `test/unit-…`, `test/smoke-…`,
`test/functional-…`, `test/e2e-…`; then `ci/github-actions`, regression, load (bonus).

## Definition of done (per feature)

1. Code follows the rules above, `make lint` is clean.
2. Phase 1: the app works in Docker (checked by hand). Phase 2: all test-level branches
   for the feature merged; all suites pass locally in Docker.
3. Traceability table updated (here and in README).
4. `.env.example` / README updated if config or commands changed.
5. Short explanation of the change given to the owner.
