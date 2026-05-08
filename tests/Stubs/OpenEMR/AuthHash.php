<?php

/**
 * Stub of OpenEMR\Common\Auth\AuthHash for unit tests. Preserves the
 * by-reference passwordHash() signature so any regression where UserManager
 * passes a literal (which PHP rejects) still surfaces here.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Common\Auth;

use OpenCoreEMR\CLI\ManageUsers\Tests\Stubs\SqlSpy;

class AuthHash
{
    public function passwordHash(string &$password): string
    {
        SqlSpy::$authHashCalls[] = $password;
        return 'hashed:' . $password;
    }
}
