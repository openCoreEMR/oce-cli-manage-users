<?php

/**
 * Unit tests for UserManager.
 *
 * UserManager calls OpenEMR's global sql* helpers (sqlQuery, sqlStatement,
 * sqlInsert, sqlBeginTrans/Commit/Rollback, sqlFetchArray) and constructs
 * AuthHash / UuidRegistry directly. These tests run without an OpenEMR
 * runtime by:
 *
 *  - Shadowing the sql* helpers inside the UserManager namespace
 *    (tests/Stubs/sql-functions.php) — PHP's namespaced-function fallback
 *    rule means UserManager's unqualified calls hit our stubs first.
 *  - Stubbing OpenEMR\Common\Auth\AuthHash and OpenEMR\Common\Uuid\UuidRegistry
 *    (tests/Stubs/openemr-stubs.php). The AuthHash stub keeps the by-reference
 *    passwordHash() signature so any regression that drops the local-variable
 *    dance fails here too.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Service;

use OpenCoreEMR\CLI\ManageUsers\Exception\ManageUsersException;
use OpenCoreEMR\CLI\ManageUsers\Exception\UserAlreadyExistsException;
use OpenCoreEMR\CLI\ManageUsers\Exception\UserNotFoundException;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use OpenCoreEMR\CLI\ManageUsers\Tests\Stubs\FetchIterator;
use OpenCoreEMR\CLI\ManageUsers\Tests\Stubs\SqlSpy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    private UserManager $users;

    protected function setUp(): void
    {
        SqlSpy::reset();
        $this->users = new UserManager();
    }

    // ---- findByUsername ----------------------------------------------------

    #[Test]
    public function findByUsernameReturnsRowOnHit(): void
    {
        SqlSpy::$sqlQueryReturns[] = [
            'id' => 7,
            'username' => 'alice',
            'fname' => 'Alice',
            'lname' => 'L',
            'active' => 1,
            'authorized' => 0,
        ];

        $row = $this->users->findByUsername('alice');

        self::assertSame(7, $row['id']);
        self::assertSame('alice', $row['username']);

        $calls = SqlSpy::callsOf('sqlQuery');
        self::assertCount(1, $calls);
        self::assertStringContainsString('FROM users', $calls[0]['sql']);
        self::assertStringContainsString('BINARY username = ?', $calls[0]['sql']);
        self::assertSame(['alice'], $calls[0]['params']);
    }

    #[Test]
    public function findByUsernameThrowsOnFalse(): void
    {
        // No queued return => sqlQuery returns false (real OpenEMR returns
        // false when the row is absent).
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found: ghost');

        $this->users->findByUsername('ghost');
    }

    #[Test]
    public function findByUsernameThrowsOnArrayWithoutId(): void
    {
        SqlSpy::$sqlQueryReturns[] = ['username' => 'broken'];

        $this->expectException(UserNotFoundException::class);

        $this->users->findByUsername('broken');
    }

    // ---- resetPassword -----------------------------------------------------

    #[Test]
    public function resetPasswordHashesAndUpdatesSecure(): void
    {
        SqlSpy::$sqlQueryReturns[] = ['id' => 42, 'username' => 'alice'];

        $this->users->resetPassword('alice', 'sekret');

        self::assertSame(['sekret'], SqlSpy::$authHashCalls);

        $statements = SqlSpy::callsOf('sqlStatement');
        self::assertCount(1, $statements);
        self::assertStringContainsString('UPDATE users_secure', $statements[0]['sql']);
        self::assertStringContainsString('login_fail_counter = 0', $statements[0]['sql']);
        self::assertStringContainsString('auto_block_emailed = 0', $statements[0]['sql']);
        self::assertSame(['hashed:sekret', 42], $statements[0]['params']);
    }

    #[Test]
    public function resetPasswordSurfacesUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->users->resetPassword('ghost', 'sekret');
    }

    // ---- create ------------------------------------------------------------

    #[Test]
    public function createInsertsBothRowsAndCommits(): void
    {
        // First sqlQuery (existence check) returns false (no queued value).
        SqlSpy::$sqlInsertReturns[] = 99;
        SqlSpy::$uuidValue = 'fixed-uuid';

        $id = $this->users->create([
            'username' => 'alice',
            'password' => 'sekret',
            'fname' => 'Alice',
            'lname' => 'Liddell',
            'email' => 'alice@example.com',
            'authorized' => true,
            'active' => false,
        ]);

        self::assertSame(99, $id);
        self::assertSame(1, SqlSpy::$beginCount);
        self::assertSame(1, SqlSpy::$commitCount);
        self::assertSame(0, SqlSpy::$rollbackCount);

        $insert = SqlSpy::callsOf('sqlInsert');
        self::assertCount(1, $insert);
        self::assertStringContainsString('INSERT INTO users', $insert[0]['sql']);
        // Mirror of usergroup_admin.php: literal sentinel in users.password.
        self::assertStringContainsString("password = 'NoLongerUsed'", $insert[0]['sql']);
        self::assertSame(
            ['alice', 'Alice', 'Liddell', 'alice@example.com', 1, 0],
            $insert[0]['params']
        );

        $statements = SqlSpy::callsOf('sqlStatement');
        // users_secure insert + uuid backfill update.
        self::assertCount(2, $statements);
        self::assertStringContainsString('INSERT INTO users_secure', $statements[0]['sql']);
        self::assertSame([99, 'alice', 'hashed:sekret'], $statements[0]['params']);
        self::assertStringContainsString('UPDATE users SET uuid = ?', $statements[1]['sql']);
        self::assertSame(['fixed-uuid', 99], $statements[1]['params']);
    }

    #[Test]
    public function createDefaultsActiveTrueAuthorizedFalseEmailNull(): void
    {
        SqlSpy::$sqlInsertReturns[] = 1;

        $this->users->create([
            'username' => 'bob',
            'password' => 'pw',
            'fname' => 'Bob',
            'lname' => 'B',
        ]);

        $insert = SqlSpy::callsOf('sqlInsert')[0];
        // [username, fname, lname, email, authorized, active]
        self::assertSame(['bob', 'Bob', 'B', null, 0, 1], $insert['params']);
    }

    #[Test]
    public function createThrowsWhenUserAlreadyExists(): void
    {
        SqlSpy::$sqlQueryReturns[] = ['id' => 5];

        try {
            $this->users->create([
                'username' => 'dup',
                'password' => 'pw',
                'fname' => 'D',
                'lname' => 'U',
            ]);
            self::fail('expected UserAlreadyExistsException');
        } catch (UserAlreadyExistsException $e) {
            self::assertStringContainsString('dup', $e->getMessage());
        }

        // Existence check ran; no inserts, no transaction work.
        self::assertCount(0, SqlSpy::callsOf('sqlInsert'));
        self::assertCount(0, SqlSpy::callsOf('sqlStatement'));
        self::assertSame(0, SqlSpy::$beginCount);
    }

    #[Test]
    public function createThrowsAndRollsBackWhenInsertReturnsZero(): void
    {
        SqlSpy::$sqlInsertReturns[] = 0;

        try {
            $this->users->create([
                'username' => 'alice',
                'password' => 'pw',
                'fname' => 'A',
                'lname' => 'L',
            ]);
            self::fail('expected ManageUsersException');
        } catch (ManageUsersException $e) {
            self::assertStringContainsString('Failed to insert', $e->getMessage());
        }

        self::assertSame(1, SqlSpy::$beginCount);
        self::assertSame(0, SqlSpy::$commitCount);
        self::assertSame(1, SqlSpy::$rollbackCount);
        // users_secure insert never ran.
        self::assertCount(0, SqlSpy::callsOf('sqlStatement'));
    }

    #[Test]
    public function createSwallowsUuidRegistryFailure(): void
    {
        SqlSpy::$sqlInsertReturns[] = 17;
        SqlSpy::$uuidThrows = true;

        $id = $this->users->create([
            'username' => 'alice',
            'password' => 'pw',
            'fname' => 'A',
            'lname' => 'L',
        ]);

        self::assertSame(17, $id);
        self::assertSame(1, SqlSpy::$commitCount);
        self::assertSame(0, SqlSpy::$rollbackCount);

        // Only the users_secure insert ran on sqlStatement — no uuid update.
        $statements = SqlSpy::callsOf('sqlStatement');
        self::assertCount(1, $statements);
        self::assertStringContainsString('INSERT INTO users_secure', $statements[0]['sql']);
    }

    // ---- listUsers ---------------------------------------------------------

    #[Test]
    public function listUsersWithNoFiltersOmitsWhereClause(): void
    {
        SqlSpy::$sqlStatementReturns[] = new FetchIterator([
            ['id' => 1, 'username' => 'alice'],
            ['id' => 2, 'username' => 'bob'],
        ]);

        $rows = $this->users->listUsers();

        self::assertCount(2, $rows);
        self::assertSame('alice', $rows[0]['username']);

        $sql = SqlSpy::callsOf('sqlStatement')[0]['sql'];
        self::assertStringNotContainsString('WHERE', $sql);
        self::assertStringContainsString('LEFT JOIN users_secure us', $sql);
        self::assertStringContainsString('ORDER BY u.username', $sql);
    }

    #[Test]
    public function listUsersActiveOnlyAddsActiveFilter(): void
    {
        SqlSpy::$sqlStatementReturns[] = new FetchIterator([]);

        $this->users->listUsers(activeOnly: true);

        $sql = SqlSpy::callsOf('sqlStatement')[0]['sql'];
        self::assertStringContainsString('WHERE u.active = 1', $sql);
    }

    #[Test]
    public function listUsersInactiveOnlyAddsInactiveFilter(): void
    {
        SqlSpy::$sqlStatementReturns[] = new FetchIterator([]);

        $this->users->listUsers(inactiveOnly: true);

        $sql = SqlSpy::callsOf('sqlStatement')[0]['sql'];
        self::assertStringContainsString('u.active = 0', $sql);
    }

    #[Test]
    public function listUsersLockedOnlyJoinsAndFilters(): void
    {
        SqlSpy::$sqlStatementReturns[] = new FetchIterator([]);

        $this->users->listUsers(lockedOnly: true);

        $sql = SqlSpy::callsOf('sqlStatement')[0]['sql'];
        self::assertStringContainsString('us.login_fail_counter > 0', $sql);
    }

    #[Test]
    public function listUsersCombinesMultipleFiltersWithAnd(): void
    {
        SqlSpy::$sqlStatementReturns[] = new FetchIterator([]);

        $this->users->listUsers(activeOnly: true, lockedOnly: true);

        $sql = SqlSpy::callsOf('sqlStatement')[0]['sql'];
        self::assertStringContainsString('u.active = 1 AND us.login_fail_counter > 0', $sql);
    }

    #[Test]
    public function listUsersReturnsEmptyWhenStatementResultIsNotIterable(): void
    {
        // No queued return => sqlStatement returns null. Real OpenEMR can
        // return false on driver error; both are non-objects and yield [].
        self::assertSame([], $this->users->listUsers());
    }

    // ---- activate ----------------------------------------------------------

    #[Test]
    public function activateSetsActiveOnly(): void
    {
        SqlSpy::$sqlQueryReturns[] = ['id' => 3];

        $this->users->activate('alice');

        $statements = SqlSpy::callsOf('sqlStatement');
        self::assertCount(1, $statements);
        self::assertSame(
            'UPDATE users SET active = 1 WHERE id = ?',
            $statements[0]['sql']
        );
        self::assertSame([3], $statements[0]['params']);
    }

    #[Test]
    public function activateAlsoAuthorizesWhenRequested(): void
    {
        SqlSpy::$sqlQueryReturns[] = ['id' => 3];

        $this->users->activate('alice', alsoAuthorize: true);

        $sql = SqlSpy::callsOf('sqlStatement')[0]['sql'];
        self::assertStringContainsString('active = 1, authorized = 1', $sql);
    }

    // ---- unlock ------------------------------------------------------------

    #[Test]
    public function unlockClearsLockoutColumns(): void
    {
        SqlSpy::$sqlQueryReturns[] = ['id' => 9];

        $this->users->unlock('alice');

        $statement = SqlSpy::callsOf('sqlStatement')[0];
        self::assertStringContainsString('UPDATE users_secure', $statement['sql']);
        self::assertStringContainsString('login_fail_counter = 0', $statement['sql']);
        self::assertStringContainsString('last_login_fail = NULL', $statement['sql']);
        self::assertStringContainsString('auto_block_emailed = 0', $statement['sql']);
        self::assertSame([9], $statement['params']);
    }
}
