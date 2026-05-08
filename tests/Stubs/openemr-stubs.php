<?php

/**
 * Loader for the OpenEMR\… class stubs UserManager touches. Each stub lives
 * in its own file (PSR-1) and is required only when the real class isn't
 * autoloadable — the real ones live in tools/openemr/vendor, intentionally
 * off the root autoload (see tools/openemr/README.md).
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

if (!class_exists(\OpenEMR\Common\Auth\AuthHash::class, false)) {
    require __DIR__ . '/OpenEMR/AuthHash.php';
}

if (!class_exists(\OpenEMR\Common\Uuid\UuidRegistry::class, false)) {
    require __DIR__ . '/OpenEMR/UuidRegistry.php';
}
