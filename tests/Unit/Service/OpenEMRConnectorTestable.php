<?php

/**
 * Test-only subclass that promotes OpenEMRConnector's protected helpers to
 * public so tests can call them without Reflection. Each pass-through is
 * named call*() to make it obvious at the call site that this is a test
 * shim, not part of the production API.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Service;

use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;

class OpenEMRConnectorTestable extends OpenEMRConnector
{
    public function callValidateSite(string $site): void
    {
        $this->validateSite($site);
    }

    public function callResolveGlobalsPath(string $openemrPath): string
    {
        return $this->resolveGlobalsPath($openemrPath);
    }

    public function callPrepareCliEnvironment(string $site): void
    {
        $this->prepareCliEnvironment($site);
    }

    public function callVerifyDatabase(): void
    {
        $this->verifyDatabase();
    }
}
