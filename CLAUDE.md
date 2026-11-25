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

Host prerequisites (install before anything else): Docker Desktop (WSL2 backend on Windows),
Git and `make` (on Windows: `scoop install make`, or use WSL / Git Bash).
PHP, Composer and Node.js are **not** needed on the host.

```bash
make start         # one command: create .env if missing + up + setup
make stop          # = make down (DB data is kept)
make up            # docker compose up -d --build
make down          # docker compose down
make sh            # shell into php container
make setup         # first run after `make up`: composer install + migrations (app + app_test)
make migrate       # php yii migrate --interactive=0 (app DB)
make migrate-test  # migrations on app_test DB
make test-up       # start the test stand: php-test + nginx-test + centrifugo-test, DB app_test, :8081
make test-down     # stop the test stand
make test-unit     # vendor/bin/codecept run Unit
make test-func     # vendor/bin/codecept run Functional
make test-smoke    # vendor/bin/codecept run Smoke (real HTTP to the test stand)
make test-ui       # Playwright, project "ui" (Chromium)
make test-e2e      # Playwright, projects e2e-chromium/-firefox/-webkit
make test-regress  # all suites, group/tag "regression"
make test          # all suites
make lint          # php-cs-fixer (dry-run) + phpstan + ESLint/Prettier/tsc for tests/e2e
make fix           # php-cs-fixer fix + eslint --fix/prettier for tests/e2e
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
tests/Unit/        Codeception unit tests
tests/Functional/  Codeception functional tests (in-process, Yii2 module)
tests/Smoke/       Codeception smoke tests (PhpBrowser, real HTTP to the test stand)
tests/e2e/         Playwright: specs/ui/, specs/e2e/, pages/ (Page Objects), fixtures/
tests/TEST_CHECKLIST.md  All test cases with IDs; each test names its ID
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
  phase 2, after the whole app is built (see "Work plan"). In phase 2 there is one branch
  per suite for the whole app: `test/<level>-comments`.
- A test that finds a bug stays failing (never skipped) and is listed in the PR. After all
  suites are written, each bug gets its own `fix/<bug-name>` branch.
- **Never delete, skip or weaken a test to make it pass.** If a test is wrong, explain why before changing it.
- Test pyramid: many unit, fewer functional, few e2e.
- Arrange–Act–Assert structure; one behaviour per test; descriptive names
  (`testGuestCannotPostEmptyComment`, `admin sees new comment without reload`).
- Tests are independent: each creates its own data (fixtures), no order dependence,
  DB cleaned between tests. Tests use the `app_test` DB only: smoke, UI and E2E go to the
  test stand (`make test-up`), never to the dev app.
- No `sleep()` / fixed timeouts. Playwright: web-first assertions (`await expect(locator).toBeVisible()`).
- Selectors: `data-testid` attributes only (add them to views). No CSS-class or XPath selectors.
  Exception: role/label locators where the test checks accessible names, or for elements Bootstrap
  renders without a test id (menu toggler, pager items).
- E2E uses Page Objects (`tests/e2e/pages/`). Realtime test uses **two browser contexts**
  (guest + admin) in one spec.
- Tags: Codeception `@group smoke|regression`; Playwright `@ui`, `@e2e`, `@regression`,
  `@cross-browser` (also runs in Firefox and WebKit) in test titles.
- Every test names its checklist ID (`U-01`, `F-03`, `UI-17` …) from `tests/TEST_CHECKLIST.md`.
- A test for every fixed bug, tagged `regression`, in branch `test/regression-<bug-name>`.
- On failure keep artifacts: Codeception `tests/Support/output/`, Playwright trace + screenshot + video (CI uploads them).

### Suites

| Suite | Purpose | When | Time budget |
|---|---|---|---|
| unit | Model validation, services with mocked publisher | every commit/PR | < 30 s |
| functional | In-process HTTP: forms, CSRF, access, CRUD, escaping | every commit/PR | < 2 min |
| smoke | App is alive (real HTTP to the test stand): pages open, guest can post, admin can log in, WS connects | after `compose up`/deploy, gates the rest | < 2 min |
| ui | One page in a real browser: states, a11y (axe), screenshots, mobile widths | PR to `main` | < 5 min |
| e2e | Full flow in a real browser incl. realtime, two contexts | PR to `main`, nightly | < 10 min, sharded |
| regression | All suites filtered by `regression` + bug tests | nightly, before release | — |
| load (bonus) | Throughput of POST comment, WS connections | manual / weekly | — |

### Traceability (keep in sync with README)

| # | Flow step | Locked by |
|---|---|---|
| 1 | Guest fills in the form | smoke `AppIsAliveCest`; UI `comment-form.spec.ts`; e2e `comment-flow.spec.ts` |
| 2 | Validation and anti-spam (CSRF, honeypot, rate limit) | unit `CommentFormTest`, `CommentServiceTest` (rate limit); functional `GuestCommentCest` (CSRF, honeypot, rate limit); UI `comment-form.spec.ts` (errors, honeypot); e2e `comment-flow.spec.ts` (E-06) |
| 3 | Comment saved to MySQL | unit `CommentServiceTest`; functional `GuestCommentCest`; smoke `AppIsAliveCest` (migrations); e2e `comment-flow.spec.ts` |
| 4 | Comment shown in the public feed (encoded) | unit `CommentServiceTest` (order); functional `GuestCommentCest` (escaping, order, pager); UI `feed.spec.ts`; e2e `comment-flow.spec.ts` |
| 5 | Event published to Centrifugo (HTTP API) | unit `CommentServiceTest` (mock publisher); smoke `AppIsAliveCest` (Centrifugo API); e2e `realtime.spec.ts` |
| 6 | Admin logs in / guest is denied | unit `UserTest`, `LoginFormTest`; functional `AdminAccessCest`; smoke `AppIsAliveCest` (admin from .env); UI `admin.spec.ts` (login form); e2e `comment-flow.spec.ts` |
| 7 | Admin list subscribes to the channel (badge Live) | unit `CentrifugoTokenIssuerTest`; functional `AdminAccessCest` (token endpoint); smoke `AppIsAliveCest` (WebSocket via nginx); UI `admin.spec.ts` (Live badge, reconnect); e2e `realtime.spec.ts` (Live, reconnect, logout) |
| 8 | New comment appears in the admin list without reload | functional `AdminCrudCest` (escaping in admin list); UI `admin.spec.ts` (live row = reloaded row); e2e `comment-flow.spec.ts`, `realtime.spec.ts` (XSS) |
| 9 | Admin edits / deletes a comment | functional `AdminCrudCest`, `AdminAccessCest` (guest denied); UI `admin.spec.ts` (confirm, edit); e2e `comment-flow.spec.ts` |

Steps match `@flowStep N` in tests and the "Шаг" column of `tests/TEST_CHECKLIST.md`.
Each test branch fills in its suite.

Update this table whenever a test is added, renamed or removed.

## Git workflow

Full branching rules: **`CONTRIBUTING.md`** (source of truth; summary in README).

- **GitHub Flow.** `main` is always green. It is intentionally *not* protected on GitHub;
  no direct commits to `main` by convention. Revisit after phase 2 (see "Work plan").
- **One feature = one branch** `feature/<feature-name>`, containing **production code only**.
- **Each test level = its own branch** `test/<level>-<feature-name>`,
  level ∈ `unit | functional | smoke | ui | e2e | regression | load`.
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

**Phase 2 — tests.** One branch per suite for the whole app (owner's decision, 2026-11-25),
each based on the previous one: `chore/test-infra` (test stand, Playwright, Makefile, README) →
`test/unit-comments` → `test/functional-comments` → `test/smoke-comments` → `test/ui-comments` →
`test/e2e-comments` → `test/regression-comments`; then one `fix/<bug>` branch per bug the
tests found, `ci/github-actions`, load (bonus). Branches are pushed; the owner merges.

**After phase 2 — reminder.** When the last phase-2 branch is merged, **remind the owner**
that `main` is still unprotected and ask whether to enable branch protection on GitHub
(require a PR and green CI). Do not change GitHub settings yourself.

## Definition of done (per feature)

1. Code follows the rules above, `make lint` is clean.
2. Phase 1: the app works in Docker (checked by hand). Phase 2: all test-level branches
   for the feature merged; all suites pass locally in Docker.
3. Traceability table updated (here and in README).
4. `.env.example` / README updated if config or commands changed.
5. Short explanation of the change given to the owner.
