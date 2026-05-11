<?php

/**
 * PHPUnit bootstrap. Loads composer's autoloader, the OpenEMR class stubs,
 * and the namespace-shadowed sql* helpers used by UserManager tests.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Stubs/SqlSpy.php';
require __DIR__ . '/Stubs/FetchIterator.php';
require __DIR__ . '/Stubs/openemr-stubs.php';
require __DIR__ . '/Stubs/sql-functions.php';
