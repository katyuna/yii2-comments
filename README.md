# Yii2 Comments

A simple commenting system on Yii2, built mainly to demonstrate a test system.
Guests post comments to a public feed. Admins moderate them and see new comments
in real time over WebSockets (Centrifugo).

## Prerequisites

Install on the host first:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (on Windows with the WSL2 backend), check: `docker compose version`
- Git, check: `git --version`
- `make`, check: `make --version` (Windows: `scoop install make`, or run commands from WSL / Git Bash)

PHP, Composer and Node.js are not needed on the host: everything runs in containers.

## Quick start

```bash
cp .env.example .env   # then change the secrets
make hooks             # once after cloning: enables the branching reminder hook
make up                # docker compose up -d --build
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
