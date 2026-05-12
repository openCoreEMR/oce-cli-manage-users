<?php

/**
 * Stub of OpenEMR\Common\Database\SqlQueryException for unit tests. Mirrors
 * the constructor shape (named args: sqlStatement, message, sqlError) and
 * RuntimeException base class so UserManager's catch (\Throwable) treats it
 * the same as the real one.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Common\Database;

class SqlQueryException extends \RuntimeException
{
    public function __construct(
        public readonly string $sqlStatement = '',
        string $message = '',
        public readonly string $sqlError = '',
    ) {
        parent::__construct($message);
    }
}
