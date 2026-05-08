<?php

/**
 * In-memory recorder/queue for the OpenEMR sql* helpers shadowed under the
 * UserManager namespace. Tests reset it in setUp(), enqueue canned returns,
 * then assert against the recorded calls.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Stubs;

class SqlSpy
{
    /** @var list<array{fn: string, sql: string, params: list<mixed>}> */
    public static array $calls = [];

    /** @var list<mixed> */
    public static array $sqlQueryReturns = [];

    /** @var list<mixed> */
    public static array $sqlStatementReturns = [];

    /** @var list<mixed> */
    public static array $sqlInsertReturns = [];

    public static int $beginCount = 0;
    public static int $commitCount = 0;
    public static int $rollbackCount = 0;

    public static bool $uuidThrows = false;
    public static ?string $uuidValue = null;

    /** @var list<string> */
    public static array $authHashCalls = [];

    public static function reset(): void
    {
        self::$calls = [];
        self::$sqlQueryReturns = [];
        self::$sqlStatementReturns = [];
        self::$sqlInsertReturns = [];
        self::$beginCount = 0;
        self::$commitCount = 0;
        self::$rollbackCount = 0;
        self::$uuidThrows = false;
        self::$uuidValue = null;
        self::$authHashCalls = [];
    }

    /**
     * @return list<array{fn: string, sql: string, params: list<mixed>}>
     */
    public static function callsOf(string $fn): array
    {
        return array_values(array_filter(self::$calls, static fn (array $c): bool => $c['fn'] === $fn));
    }
}
