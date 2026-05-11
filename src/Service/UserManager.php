<?php

/**
 * Encapsulates user-mutation operations against an initialized OpenEMR runtime.
 *
 * All methods assume the OpenEMR environment has already been bootstrapped via
 * OpenEMRConnector::initialize() (so sqlQuery/sqlStatement/sqlInsert and the
 * OpenEMR\Common\Auth\AuthHash class are available).
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Service;

use OpenCoreEMR\CLI\ManageUsers\Exception\ManageUsersException;
use OpenCoreEMR\CLI\ManageUsers\Exception\UserAlreadyExistsException;
use OpenCoreEMR\CLI\ManageUsers\Exception\UserNotFoundException;

class UserManager
{
    /**
     * @return array<string, mixed> The users.id row for the username
     * @throws UserNotFoundException
     */
    public function findByUsername(string $username): array
    {
        /** @var array<string, mixed>|false|null $row */
        $row = sqlQuery(
            "SELECT id, username, fname, lname, active, authorized FROM users WHERE BINARY username = ?",
            [$username]
        );

        if (!is_array($row) || !isset($row['id'])) {
            throw new UserNotFoundException("User not found: {$username}");
        }

        return $row;
    }

    /**
     * Set a user's password, clear any lockout state, and stamp last_update_password.
     *
     * The password is passed by-reference into AuthHash::passwordHash() because
     * that signature requires a variable (not a literal).
     */
    public function resetPassword(string $username, string $password): void
    {
        $user = $this->findByUsername($username);

        $hash = $this->hashPassword($password);

        sqlStatement(
            "UPDATE users_secure"
            . " SET password = ?,"
            . " last_update_password = NOW(),"
            . " login_fail_counter = 0,"
            . " auto_block_emailed = 0"
            . " WHERE id = ?",
            [$hash, $user['id']]
        );
    }

    /**
     * Create a new user row in `users` and a matching credential row in `users_secure`.
     *
     * @param array{
     *     username: string,
     *     password: string,
     *     fname: string,
     *     lname: string,
     *     email?: ?string,
     *     authorized?: bool,
     *     active?: bool,
     *     groups?: list<string>,
     *     legacyGroup?: ?string,
     * } $spec
     */
    public function create(array $spec): int
    {
        $username = $spec['username'];

        /** @var array<string, mixed>|false|null $existing */
        $existing = sqlQuery(
            "SELECT id FROM users WHERE BINARY username = ?",
            [$username]
        );
        if (is_array($existing) && isset($existing['id'])) {
            throw new UserAlreadyExistsException("User already exists: {$username}");
        }

        $hash = $this->hashPassword($spec['password']);

        $authorized = ($spec['authorized'] ?? false) ? 1 : 0;
        $active = ($spec['active'] ?? true) ? 1 : 0;
        $email = $spec['email'] ?? null;

        // Two-table create (users + users_secure, plus optional uuid backfill)
        // wrapped in a transaction so a failure on the secondary writes doesn't
        // leave an orphaned users row with no credential.
        sqlBeginTrans();
        try {
            // Mirror the field shape from interface/usergroup/usergroup_admin.php:
            // password column is the literal string 'NoLongerUsed'; the real hash
            // lives in users_secure.password.
            $userId = sqlInsert(
                "INSERT INTO users SET"
                . " username = ?,"
                . " password = 'NoLongerUsed',"
                . " fname = ?,"
                . " lname = ?,"
                . " email = ?,"
                . " authorized = ?,"
                . " active = ?",
                [$username, $spec['fname'], $spec['lname'], $email, $authorized, $active]
            );

            if (!is_int($userId) || $userId <= 0) {
                throw new ManageUsersException("Failed to insert into users for {$username}");
            }

            sqlStatement(
                "INSERT INTO users_secure SET"
                . " id = ?,"
                . " username = ?,"
                . " password = ?,"
                . " last_update_password = NOW()",
                [$userId, $username, $hash]
            );

            $this->assignUuidIfPossible($userId);

            $groups = $spec['groups'] ?? [];
            if (count($groups) > 0) {
                $this->registerAclGroups(
                    $groups,
                    $username,
                    $spec['fname'],
                    $spec['lname']
                );
            }

            $legacyGroup = $spec['legacyGroup'] ?? null;
            if ($legacyGroup !== null && $legacyGroup !== '') {
                // Legacy `groups` table (id, name, user) — separate from the
                // gAcl tables. AuthUtils::confirmUserPassword() rejects login
                // when UserService::getAuthGroupForUser() finds no row here,
                // even with a valid gAcl ARO. Mirror the upstream insert at
                // interface/usergroup/usergroup_admin.php:443.
                sqlStatement(
                    "INSERT INTO `groups` SET name = ?, user = ?",
                    [$legacyGroup, $username]
                );
            }
        } catch (\Throwable $e) {
            sqlRollbackTrans();
            throw $e;
        }
        sqlCommitTrans();

        return $userId;
    }

    /**
     * Register the user as an ARO and assign them to the named ACL groups.
     *
     * Mirrors the call shape used by interface/usergroup/usergroup_admin.php
     * after a `users` insert. Without this step, the user has a valid
     * credential but is rejected at login because they have no `gacl_aro` row.
     *
     * Extracted as a protected method so unit tests can override it without
     * needing OpenEMR's ACL stack on the autoload path.
     *
     * @param list<string> $groups
     */
    protected function registerAclGroups(array $groups, string $username, string $fname, string $lname): void
    {
        if (!class_exists(\OpenEMR\Common\Acl\AclExtended::class)) {
            throw new ManageUsersException(
                "OpenEMR\\Common\\Acl\\AclExtended not available; OpenEMR bootstrap must run first"
            );
        }

        \OpenEMR\Common\Acl\AclExtended::setUserAro($groups, $username, $fname, '', $lname);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUsers(bool $activeOnly = false, bool $inactiveOnly = false, bool $lockedOnly = false): array
    {
        $where = [];
        $params = [];

        if ($activeOnly) {
            $where[] = "u.active = 1";
        }
        if ($inactiveOnly) {
            $where[] = "u.active = 0";
        }
        if ($lockedOnly) {
            $where[] = "us.login_fail_counter > 0";
        }

        $sql = "SELECT u.id, u.username, u.fname, u.lname, u.active, u.authorized,"
            . " us.last_update_password, COALESCE(us.login_fail_counter, 0) AS login_fail_counter"
            . " FROM users u"
            . " LEFT JOIN users_secure us ON us.id = u.id";
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY u.username";

        $result = sqlStatement($sql, $params);
        if (!is_object($result)) {
            return [];
        }

        $rows = [];
        while (($row = sqlFetchArray($result)) !== false) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function activate(string $username, bool $alsoAuthorize = false): void
    {
        $user = $this->findByUsername($username);

        if ($alsoAuthorize) {
            sqlStatement(
                "UPDATE users SET active = 1, authorized = 1 WHERE id = ?",
                [$user['id']]
            );
        } else {
            sqlStatement(
                "UPDATE users SET active = 1 WHERE id = ?",
                [$user['id']]
            );
        }
    }

    public function unlock(string $username): void
    {
        $user = $this->findByUsername($username);

        sqlStatement(
            "UPDATE users_secure"
            . " SET login_fail_counter = 0,"
            . " last_login_fail = NULL,"
            . " auto_block_emailed = 0"
            . " WHERE id = ?",
            [$user['id']]
        );
    }

    /**
     * Hash a password using OpenEMR's AuthHash.
     *
     * AuthHash::passwordHash() takes its argument by reference — passing a
     * literal throws "Argument #1 cannot be passed by reference". Always
     * pass through a local variable.
     */
    private function hashPassword(string $password): string
    {
        if (!class_exists(\OpenEMR\Common\Auth\AuthHash::class)) {
            throw new ManageUsersException(
                "OpenEMR\\Common\\Auth\\AuthHash not available; OpenEMR bootstrap must run first"
            );
        }

        $authHash = new \OpenEMR\Common\Auth\AuthHash();

        $passwordRef = $password;
        $hash = $authHash->passwordHash($passwordRef);

        if (!is_string($hash) || $hash === '') {
            throw new ManageUsersException("AuthHash returned empty hash");
        }

        return $hash;
    }

    /**
     * Best-effort uuid backfill. Newer OpenEMR has UuidRegistry; older builds
     * may not — in that case we leave users.uuid NULL, matching what an upgrade
     * script would do later.
     */
    private function assignUuidIfPossible(int $userId): void
    {
        if (!class_exists(\OpenEMR\Common\Uuid\UuidRegistry::class)) {
            return;
        }

        try {
            $registry = \OpenEMR\Common\Uuid\UuidRegistry::getRegistryForTable('users');
            $uuid = $registry->createUuid();
            sqlStatement("UPDATE users SET uuid = ? WHERE id = ?", [$uuid, $userId]);
        } catch (\Throwable) {
            // Non-fatal; row is otherwise valid without a UUID.
        }
    }
}
