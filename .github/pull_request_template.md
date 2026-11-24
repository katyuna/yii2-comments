## What and why

<!-- What does this PR change, and why? -->

## Type

- [ ] `feature/` new functionality (production code only)
- [ ] `test/<level>-<feature>`: level: unit / smoke / functional / e2e / regression / load
- [ ] `fix/` bug fix (its regression test goes in `test/regression-<bug-name>`)
- [ ] `chore/` / `docs/` / `ci/`

Feature this belongs to: `<feature-name>`

## Tests run

<!-- Commands you ran and their result, e.g. `make test-unit`: 12 passed -->

## Checklist

- [ ] Branch name follows [CONTRIBUTING.md](https://github.com/katyuna/yii2-comments/blob/main/CONTRIBUTING.md) (`<type>/<kebab-case>`)
- [ ] Branch is up to date with `main` (rebased before opening this PR)
- [ ] PR title is a Conventional Commit (`feat(comment): ...`)
- [ ] `make lint` is clean
- [ ] Relevant suites pass locally in Docker
- [ ] Traceability table updated (CLAUDE.md + README), if tests were added/renamed
- [ ] `.env.example` / README updated, if config or commands changed
