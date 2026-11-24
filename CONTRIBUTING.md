# Contributing: branching rules

These rules apply to everyone (humans and Claude Code). `main` is not protected on
GitHub, so they work by convention: the local git hook and Claude Code remind you.

## 1. Never work on `main`

Every change, however small, lives in its own branch and reaches `main` through a Pull Request.

## 2. One branch = one feature (or one test level of a feature)

- Each **feature** gets its own branch. The branch holds **only the production code**.
- Each **test level** of that feature gets its **own separate branch**:

  | Level | Branch | Suite |
  |---|---|---|
  | unit | `test/unit-<feature>` | `make test-unit` |
  | smoke | `test/smoke-<feature>` | `make test-smoke` |
  | functional | `test/functional-<feature>` | `make test-func` |
  | e2e | `test/e2e-<feature>` | `make test-e2e` |
  | regression | `test/regression-<feature>` | `make test-regress` |
  | load | `test/load-<feature>` | JMeter, `tests/load/` |

  Skip a level only if it has nothing to cover for that feature (say why in the PR).
- A feature is **done** only when its feature branch and all its test-level branches are merged.
- **Known trade-off:** between merging `feature/<feature>` and its test branches, `main`
  holds code that is not covered by tests yet. This is a deliberate choice: every PR stays
  small and reviews one thing. Keep the gap short by merging the test branches right after the feature.

### Bug fixes

1. `fix/<bug-name>`: the fix only.
2. `test/regression-<bug-name>`: a test that reproduces the bug, tagged `regression`
   (Codeception `@group regression`, Playwright `@regression` in the title).

Example for the feature `guest-comment-form`:

```
feature/guest-comment-form
test/unit-guest-comment-form
test/smoke-guest-comment-form
test/functional-guest-comment-form
test/e2e-guest-comment-form
```

## 3. Branch names

Format: `<type>/<feature-name>`, where `<feature-name>` is the name of the feature.

| Type | For |
|---|---|
| `feature/` | new functionality |
| `fix/` | bug fix |
| `test/` | tests, always `test/<level>-<feature>` |
| `chore/` | tooling, dependencies, Docker, Makefile |
| `docs/` | documentation only |
| `ci/` | GitHub Actions |

**kebab-case only**: lowercase Latin letters, digits and hyphens. No spaces, Cyrillic,
underscores or capitals. Good: `feature/admin-realtime-list`. Bad: `Feature/Admin_List`, `feature/комменты`.

## 4. Branch life cycle

1. **Start from a fresh `main`:**
   ```bash
   git switch main && git pull
   git switch -c feature/<feature-name>
   ```
2. **Keep it short-lived:** one small task, days, not weeks. One branch = one PR.
3. **Update from `main` before opening the PR** and re-run the tests:
   ```bash
   git fetch origin && git rebase origin/main
   git push --force-with-lease
   ```
4. **Open a PR**; the template asks for what changed, which tests ran, and the DoD checklist.
   The PR title is a Conventional Commit (`feat(comment): add guest comment form`),
   because it becomes the commit message in `main`.
5. **Squash merge** only: the whole branch becomes one commit in `main`.
6. **Delete the branch** after merge. GitHub deletes the remote one automatically; locally:
   ```bash
   git switch main && git pull && git branch -d <branch>
   ```

## 5. Reminders

- **Git hook**: run `make hooks` once after cloning. Before each commit, `.githooks/pre-commit`
  warns if you are on `main` or the branch name breaks the rules. It warns only and does not block the commit.
- **Claude Code** checks the current branch before changing files. If you are on `main`
  or on a branch for another task, it proposes a branch name and asks you first.
