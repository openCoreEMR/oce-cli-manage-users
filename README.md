# oce-cli-manage-users

Standalone PHP CLI for managing OpenEMR users — what `bin/console openemr:user` would be if it existed upstream. Designed for operators bringing up an account on a freshly-restored database dump, and for general user administration across forks (vanilla openemr/openemr, openemr-internal, customer forks).

Distributed as a single PHAR. Runs against any OpenEMR install from `rel-702` through `master`.

## Install

Grab the PHAR from the [latest release](https://github.com/opencoreemr/oce-cli-manage-users/releases/latest) and drop it next to (or inside) the OpenEMR install:

```bash
curl -L -o oce-manage-users.phar \
  https://github.com/opencoreemr/oce-cli-manage-users/releases/latest/download/oce-manage-users.phar
chmod +x oce-manage-users.phar
```

Or install from source for development:

```bash
git clone https://github.com/opencoreemr/oce-cli-manage-users
cd oce-cli-manage-users
composer install
./bin/oce-manage-users list
```

## Usage

Every command takes:

| Option | Default | Notes |
|---|---|---|
| `--openemr-path=<path>` | `/var/www/localhost/htdocs/openemr` | Path to OpenEMR root (must contain `interface/globals.php`) |
| `--site=<name>` | `default` | OpenEMR site (`sites/<name>/sqlconf.php`) |

### `user:reset-password`

Set or randomize a user's password.

```bash
# Prompt for the new password
oce-manage-users.phar user:reset-password --user=admin

# Provide it on the command line (visible in process list — prefer prompt or --random)
oce-manage-users.phar user:reset-password --user=admin --password='hunter2'

# Generate a random password and print it once to stdout
oce-manage-users.phar user:reset-password --user=admin --random
```

Updates `users_secure.password`, stamps `last_update_password = NOW()`, and clears `login_fail_counter` and `auto_block_emailed`.

### `user:create`

Create a new user.

```bash
oce-manage-users.phar user:create \
  --username=alice \
  --password='sekret' \
  --firstname=Alice \
  --lastname=Liddell \
  --email=alice@example.com \
  --authorized \
  --active
```

Defaults: `--active` is on, `--authorized` is off. Inserts into both `users` and `users_secure` and backfills `users.uuid` if `OpenEMR\Common\Uuid\UuidRegistry` is available.

### `user:list`

List users as a table.

```bash
oce-manage-users.phar user:list
oce-manage-users.phar user:list --active-only
oce-manage-users.phar user:list --inactive-only
oce-manage-users.phar user:list --locked
```

Columns: `id, username, fname, lname, active, authorized, last_update_password, login_fail_counter`.

### `user:activate`

Set `users.active = 1`. Optionally also set `users.authorized = 1`.

```bash
oce-manage-users.phar user:activate --user=alice
oce-manage-users.phar user:activate --user=alice --authorized
```

Idempotent.

### `user:unlock`

Clear the lockout counter.

```bash
oce-manage-users.phar user:unlock --user=alice
```

Sets `users_secure.login_fail_counter = 0`, `last_login_fail = NULL`, `auto_block_emailed = 0`. Idempotent.

## Running against a docker compose stack

Copy the PHAR into the running OpenEMR container, then exec it:

```bash
docker compose cp oce-manage-users.phar openemr:/tmp/oce-manage-users.phar
docker compose exec -T openemr php /tmp/oce-manage-users.phar user:list
```

Or mount it into the container in `compose.override.yml`:

```yaml
services:
  openemr:
    volumes:
    - ./oce-manage-users.phar:/usr/local/bin/oce-manage-users.phar:ro
```

## Compatibility

Designed and tested against OpenEMR `rel-702` through `master`. The only OpenEMR APIs this CLI touches are:

- `interface/globals.php` (bootstrap)
- `OpenEMR\Common\Auth\AuthHash::passwordHash()` (password hashing — same signature in 7.2 and master)
- `OpenEMR\Common\Uuid\UuidRegistry` (if present — graceful fallback if not)
- `sqlQuery`, `sqlStatement`, `sqlInsert`, `sqlFetchArray` (since pre-7.0)

No dependence on `bin/console`, OEModule autoload, or anything that varies between forks.

## Out of scope (for now)

- ACL groups, `users_facility`, MFA management — separate commands later
- LDAP / external auth backends
- Bulk operations (CSV, etc.)
- Wrapping the CLI in a docker image

## License

GPL-3.0-or-later.
