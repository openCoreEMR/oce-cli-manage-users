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
```

The split: `OpenEMRConnector` owns the bootstrap; `UserManager` owns the DB API surface; commands own only argument parsing, output formatting, and exit codes. Commands inject both services so unit tests can mock them without touching OpenEMR.

## Running against a dev container

Build the PHAR locally and exec it inside the container:

```bash
task phar:build
docker compose cp build/oce-manage-users.phar openemr:/tmp/oce-manage-users.phar
docker compose exec -T openemr php /tmp/oce-manage-users.phar user:list
```

`task exec -- user:list` is the convenience wrapper (assumes the PHAR is at `/var/www/localhost/htdocs/openemr/oce-manage-users.phar`; override with `CONTAINER_PHAR=...`).

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
