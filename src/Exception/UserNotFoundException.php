<?php

/**
 * Thrown when an operation references a username that does not exist
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Exception;

class UserNotFoundException extends ManageUsersException
{
}
