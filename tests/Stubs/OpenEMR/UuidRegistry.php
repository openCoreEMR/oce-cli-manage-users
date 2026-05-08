<?php

/**
 * Stub of OpenEMR\Common\Uuid\UuidRegistry for unit tests. Test code drives
 * its behavior via SqlSpy::$uuidThrows / ::$uuidValue so create()'s best-effort
 * uuid backfill can be exercised both ways.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Common\Uuid;

use OpenCoreEMR\CLI\ManageUsers\Tests\Stubs\SqlSpy;

class UuidRegistry
{
    public static function getRegistryForTable(string $table): self
    {
        if (SqlSpy::$uuidThrows) {
            throw new \RuntimeException("uuid registry unavailable for {$table}");
        }
        return new self();
    }

    public function createUuid(): string
    {
        return SqlSpy::$uuidValue ?? 'stub-uuid';
    }
}
