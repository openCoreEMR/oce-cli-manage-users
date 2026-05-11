<?php

/**
 * Namespace-shadowed copies of OpenEMR's global sql* helpers, defined inside
 * the UserManager namespace so unqualified calls from UserManager.php resolve
 * here first (PHP's namespaced-function fallback rule). Lets the SUT exercise
 * its real SQL-construction logic without an OpenEMR runtime.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Service;

use OpenCoreEMR\CLI\ManageUsers\Tests\Stubs\SqlSpy;

/**
 * @param list<mixed> $params
 */
function sqlQuery(string $sql, array $params = []): mixed
{
    SqlSpy::$calls[] = ['fn' => 'sqlQuery', 'sql' => $sql, 'params' => $params];
    if (count(SqlSpy::$sqlQueryReturns) === 0) {
        return false;
    }
    return array_shift(SqlSpy::$sqlQueryReturns);
}

/**
 * @param list<mixed> $params
 */
function sqlStatement(string $sql, array $params = []): mixed
{
    SqlSpy::$calls[] = ['fn' => 'sqlStatement', 'sql' => $sql, 'params' => $params];
    if (count(SqlSpy::$sqlStatementReturns) === 0) {
        return null;
    }
    return array_shift(SqlSpy::$sqlStatementReturns);
}

/**
 * @param list<mixed> $params
 */
function sqlInsert(string $sql, array $params = []): mixed
{
    SqlSpy::$calls[] = ['fn' => 'sqlInsert', 'sql' => $sql, 'params' => $params];
    if (count(SqlSpy::$sqlInsertReturns) === 0) {
        return 0;
    }
    return array_shift(SqlSpy::$sqlInsertReturns);
}

function sqlBeginTrans(): void
{
    SqlSpy::$beginCount++;
}

function sqlCommitTrans(): void
{
    SqlSpy::$commitCount++;
}

function sqlRollbackTrans(): void
{
    SqlSpy::$rollbackCount++;
}

function sqlFetchArray(mixed $result): array|false
{
    if ($result instanceof \OpenCoreEMR\CLI\ManageUsers\Tests\Stubs\FetchIterator) {
        return $result->next();
    }
    return false;
}
