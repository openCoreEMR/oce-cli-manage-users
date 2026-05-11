# Contributing

Thanks for your interest in `oce-cli-manage-users`. This is a small standalone PHP CLI; contributions of any size are welcome.

## Development setup

```bash
git clone https://github.com/opencoreemr/oce-cli-manage-users
cd oce-cli-manage-users
composer install
```

To exercise the CLI against a real OpenEMR install in Docker, see the `task dev:start` workflow in `README.md` and the architecture notes in `CLAUDE.md`.

## Running checks

```bash
composer check     # php -l + phpcs + phpstan + rector --dry-run
composer fix       # phpcbf + rector (apply fixes)
composer phpunit   # unit tests
```

CI runs the same checks on every PR. PHPStan is at level 9 — if you bump it, expect to add types, not ignores.

## Commit and PR style

- **Conventional Commits.** PR titles must follow the [Conventional Commits](https://www.conventionalcommits.org/) format (`feat:`, `fix:`, `docs:`, `chore:`, etc.) — `release-please` uses them to compute the next version. The `conventional-pr-title` workflow enforces this.
- **Scope when useful.** Subcommand-scoped changes read well as `feat(user:create): …` or `fix(user:reset-password): …`.
- **One logical change per PR.** Smaller PRs review faster and make `release-please` changelogs more legible.
- **Tests for new behavior.** Commands have mock-based unit tests under `tests/Unit/Command/`; follow the existing pattern.

## Releasing

Maintainers don't tag manually. `release-please` opens a release PR per merged conventional commit; merging that PR tags the version and triggers `build-phar.yml` to attach the PHAR to the GitHub release.
