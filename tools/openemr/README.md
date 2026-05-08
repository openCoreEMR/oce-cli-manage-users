# `tools/openemr/` — sub-composer for the OpenEMR copy used in dev

This sub-composer owns `openemr/openemr` so the package stays **out of the root `vendor/`**.

## Why a sub-composer instead of root `require-dev`?

If `openemr/openemr` were in the root `composer.json`, `composer install` would put `vendor/openemr/openemr/src/` on the same PSR-4 prefix (`OpenEMR\`) that an enclosing OpenEMR install also registers. When the CLI is run inside a container that has OpenEMR installed at `/var/www/.../openemr` and the CLI's own `vendor/` is also visible to that PHP process, `OpenEMR\…` lookups can resolve to the *CLI's* shadow copy, and procedural `require_once` calls inside OpenEMR's classes (`__DIR__ . '/../../../library/...inc.php'`) load the same procedural file under a different absolute path — causing fatal `Cannot redeclare …()` errors.

Production deploys aren't affected (they install the PHAR with no dev deps), but it's a real local-dev trap. Putting OpenEMR under `tools/openemr/vendor/` keeps it off the root autoload entirely. See [oce-module-template#20](https://github.com/opencoreemr/oce-module-template/issues/20) for the full write-up.

## How it's used

- **PHPStan** loads `tools/openemr/vendor/autoload.php` via `bootstrapFiles` so it can resolve `OpenEMR\…` symbols.
- **Docker compose** extends the OpenEMR-bundled `docker-compose.yml` from `tools/openemr/vendor/openemr/openemr/docker/development-easy/`.
- The `composer phpstan` script auto-runs `composer install` inside this directory if `vendor/autoload.php` is missing, so a fresh checkout doesn't have to remember.

## When to update

Bump constraints here, not in the root `composer.json`. Keep the version matrix aligned with the supported OpenEMR releases listed in the root `README.md`.
