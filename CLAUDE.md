# oce-cli-manage-users — dev guide

Standalone PHP CLI for managing OpenEMR users. Distributed as a PHAR. Mirrors the shape of `oce-cli-import-codes`.

## Architecture

```
bin/oce-manage-users         # PHAR-aware entrypoint, registers commands
src/
  Command/
    AbstractUserCommand.php  # Shared base: --openemr-path, --site, bootstrap, error handling
    ResetPasswordCommand.php
    CreateCommand.php
    ListCommand.php
    ActivateCommand.php
    UnlockCommand.php
  Service/
    OpenEMRConnector.php     # Bootstraps interface/globals.php with $ignoreAuth=true
    UserManager.php          # All DB mutations against users / users_secure
  Exception/
    ManageUsersException.php (base)
    OpenEMRConnectorException.php
    UserNotFoundException.php
    UserAlreadyExistsException.php
tests/Unit/Command/          # Mock-based tests for argument parsing, exit codes, output
tools/openemr/               # Sub-composer that owns openemr/openemr (see below)
```

The split: `OpenEMRConnector` owns the bootstrap; `UserManager` owns the DB API surface; commands own only argument parsing, output formatting, and exit codes. Commands inject both services so unit tests can mock them without touching OpenEMR.

## Why `tools/openemr/` instead of root `require-dev`

`openemr/openemr` lives under `tools/openemr/composer.json`, **not** in the root `composer.json`. If it were in the root, `vendor/openemr/openemr/src/` would land on the root PSR-4 autoload (`OpenEMR\\`) — which can shadow the OpenEMR install's own copy when this CLI is exercised inside a container that has OpenEMR also visible. Procedural `require_once` calls inside OpenEMR's classes then load the same `library/*.inc.php` file under a different absolute path, fataling with `Cannot redeclare`. See [oce-module-template#20](https://github.com/opencoreemr/oce-module-template/issues/20). `tools/openemr/README.md` covers the contract.

PHPStan still resolves `OpenEMR\…` symbols via `bootstrapFiles: [tools/openemr/vendor/autoload.php]`. The `composer phpstan` script self-installs the sub-vendor if missing, so a fresh checkout doesn't have to remember the extra step.

## Running against a dev container

The bundled `compose.yml` extends OpenEMR's bundled `development-easy` stack from `tools/openemr/vendor/openemr/openemr/docker/development-easy/docker-compose.yml`. The CLI source is bind-mounted into the openemr container at `/var/www/localhost/htdocs/openemr/oce-cli-manage-users` (rw), so host edits are visible immediately.

```bash
task tools:install     # idempotent: installs openemr/openemr into tools/openemr/vendor
task dev:start         # brings up openemr + mysql + phpmyadmin on random loopback ports
task dev:port          # show host ports
task exec -- user:list # invoke CLI subcommands inside the container
task smoke             # quick end-to-end: list, reset-password --random, unlock for admin
```

`task exec -- <args>` runs `bin/oce-manage-users` (the source, via composer's `vendor/autoload.php` inside the bind mount) — fastest iteration loop. `task exec:phar -- <args>` builds and runs the PHAR instead — useful for catching PHAR-only bugs before release.

## Compatibility testing across versions

To smoke against a different OpenEMR version, edit `tools/openemr/composer.json`'s constraint, then:

```bash
task tools:clean
task tools:install
task dev:reset      # purges OpenEMR's data volumes
task dev:start
```

## Code-quality checks

```bash
composer check          # php-lint + phpcs + phpstan + rector --dry-run
composer phpunit        # unit tests
```

PHPStan is at level 9. The OpenEMR ignore patterns in `phpstan.neon` exist because OpenEMR's global SQL helpers (`sqlQuery`, etc.) live in `library/sql.inc.php` — not in any PSR-4 path — so they are unresolved during `composer install` of the openemr package. `reportUnmatched: false` keeps the rule passive when the function names happen to be defined.

## Compatibility target

OpenEMR `rel-702` → `master`. The composer dev requirement pulls each as a tagged package. Verified API surface:

- `OpenEMR\Common\Auth\AuthHash::passwordHash(string &$password)` — signature unchanged 7.2 → master.
- `users` / `users_secure` schema — column set unchanged 7.2 → master.
- `OpenEMR\Common\Uuid\UuidRegistry::getRegistryForTable('users')->createUuid()` — present in both; gracefully skipped if missing on a stripped fork.

If you add a new field/table that diverges between versions, gate it on a `class_exists` / column-existence check rather than bumping the minimum version.

## Gotchas

These are real bugs encountered while building the requirement that motivated this CLI. Documented here so the next maintainer doesn't relearn them.

### 1. `AuthHash::passwordHash()` is by-reference

```php
// FAILS: "Argument #1 cannot be passed by reference"
(new AuthHash())->passwordHash('literal');

// WORKS
$pw = 'literal';
$hash = (new AuthHash())->passwordHash($pw);
```

`UserManager::hashPassword()` already does this; preserve it if you refactor.

### 2. `$ignoreAuth` and `$sessionAllowWrite` must be set BEFORE requiring globals.php

They are consumed *during* the include, not after. `OpenEMRConnector::initialize()` sets them right before the `require_once`. Don't reorder.

### 3. `$_SERVER['HTTP_HOST']` etc. must exist before bootstrap

`globals.php` reads them and dies if they're missing. The connector sets safe CLI defaults (`localhost`, `/`, `/cli.php`) before the require.

### 4. OpenEMR's audit logger writes to a `log` table

If the table doesn't exist (lazy-installed; older dumps lack it), every UPDATE blows up with `Table 'X.log' doesn't exist` AND a secondary `Array to string conversion in sql.inc.php:576` from a pre-existing bug in OpenEMR's `HelpfulDie`. **The actual UPDATE succeeds before the audit log fires** — behavior is correct but stderr is loud. Don't suppress it in the CLI; the right fix is operator-side: create `log` and `log_comment_encrypt` from `sql/database.sql`.

### 5. Customer dumps may have triggers/views with stale `DEFINER`s

```
ERROR 1449 (HY000): The user specified as a definer ('X'@'%') does not exist
```

Out of scope for this CLI to fix. Document for the operator.

### 6. Don't depend on `bin/console`

OpenEMR ships a Symfony app at `bin/console`, but it has no user-management commands. Customer forks (e.g. `vh-702-module`) lag the upstream namespace. This CLI is intentionally standalone so it works against any fork.

## Releasing

`release-please.yml` watches `main` and opens a release PR per Conventional Commits. Merging the release PR tags the version and triggers `build-phar.yml`, which attaches `oce-manage-users.phar` (and a versioned copy) to the GitHub Release.
