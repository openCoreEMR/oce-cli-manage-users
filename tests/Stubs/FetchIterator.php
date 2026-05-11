<?php

/**
 * Stand-in for the mysqli_result-like object returned by OpenEMR's sqlStatement.
 * sqlFetchArray() pops from this in order, returning false when exhausted —
 * matching the real helper's contract.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Stubs;

class FetchIterator
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows)
    {
    }

    /**
     * @return array<string, mixed>|false
     */
    public function next(): array|false
    {
        if (count($this->rows) === 0) {
            return false;
        }
        return array_shift($this->rows);
    }
}
