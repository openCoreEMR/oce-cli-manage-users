<?php

/**
 * Stub of OpenEMR\Common\Database\QueryUtils for unit tests. Routes the two
 * methods UserManager calls (sqlInsert, sqlStatementThrowException) into the
 * same SqlSpy buckets as the namespace-shadowed global helpers, so existing
 * tests that assert on `sqlInsert` / `sqlStatement` calls keep matching.
 *
 * Tests can also seed SqlSpy::$throwOnNextSql to make the next intercepted
 * call raise a SqlQueryException — that simulates the exit()-via-HelpfulDie
 * path the throwing variants are here to avoid.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Common\Database;

use OpenCoreEMR\CLI\ManageUsers\Tests\Stubs\SqlSpy;

class QueryUtils
{
    /**
     * @param list<mixed>|false $binds
     */
    public static function sqlInsert(string $statement, array|false $binds = []): mixed
    {
        $params = is_array($binds) ? array_values($binds) : [];
        SqlSpy::$calls[] = ['fn' => 'sqlInsert', 'sql' => $statement, 'params' => $params];

        if (SqlSpy::$throwOnNextSql !== null) {
            $error = SqlSpy::$throwOnNextSql;
            SqlSpy::$throwOnNextSql = null;
            throw new SqlQueryException(
                sqlStatement: $statement,
                message: "Insert failed. SQL error {$error} Query: {$statement}",
                sqlError: $error,
            );
        }

        if (count(SqlSpy::$sqlInsertReturns) === 0) {
            return 0;
        }
        return array_shift(SqlSpy::$sqlInsertReturns);
    }

    /**
     * @param list<mixed>|false $binds
     */
    public static function sqlStatementThrowException(
        string $statement,
        array|false $binds = [],
        bool $noLog = false
    ): mixed {
        unset($noLog);
        $params = is_array($binds) ? array_values($binds) : [];
        SqlSpy::$calls[] = ['fn' => 'sqlStatement', 'sql' => $statement, 'params' => $params];

        if (SqlSpy::$throwOnNextSql !== null) {
            $error = SqlSpy::$throwOnNextSql;
            SqlSpy::$throwOnNextSql = null;
            throw new SqlQueryException(
                sqlStatement: $statement,
                message: "Failed to execute statement. Error: {$error} Statement: {$statement}",
                sqlError: $error,
            );
        }

        if (count(SqlSpy::$sqlStatementReturns) === 0) {
            return null;
        }
        return array_shift(SqlSpy::$sqlStatementReturns);
    }
}
