<?php

/**
 * Bootstraps the OpenEMR runtime environment for CLI use.
 *
 * Loads interface/globals.php with $ignoreAuth and $sessionAllowWrite set so
 * the CLI can perform privileged operations without a real HTTP session, and
 * verifies the database came up.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Service;

use OpenCoreEMR\CLI\ManageUsers\Exception\OpenEMRConnectorException;

class OpenEMRConnector
{
    // The D modifier makes $ match end-of-string only — without it, "site\n"
    // would slip past, since $ defaults to "end-of-string OR just before a
    // trailing newline."
    private const SITE_PATTERN = '/^[A-Za-z0-9_-]+$/D';

    private string $openemrPath = '';
    private string $site = 'default';
    private bool $initialized = false;

    public function initialize(string $openemrPath, string $site = 'default'): void
    {
        if ($this->initialized) {
            return;
        }

        $this->validateSite($site);

        $this->openemrPath = rtrim($openemrPath, '/');
        $this->site = $site;

        $globalsPath = $this->resolveGlobalsPath($this->openemrPath);

        $this->prepareCliEnvironment($site);

        // Inline, not extracted: globals.php reads $ignoreAuth and
        // $sessionAllowWrite during the require and may touch other locals,
        // so we keep the same local scope as the original implementation.
        $ignoreAuth = true;
        $sessionAllowWrite = true;
        require_once $globalsPath;

        $this->verifyDatabase();

        $this->initialized = true;
    }

    /**
     * Validate the --site value.
     *
     * Site name becomes a filesystem path segment (sites/<site>/sqlconf.php)
     * and is also written into $_GET. Restrict to a safe character set
     * before either use to prevent path traversal during bootstrap.
     */
    protected function validateSite(string $site): void
    {
        if (preg_match(self::SITE_PATTERN, $site) !== 1) {
            throw new OpenEMRConnectorException(
                "Invalid --site value; must match [A-Za-z0-9_-]+, got: {$site}"
            );
        }
    }

    /**
     * Locate interface/globals.php under the given OpenEMR root.
     *
     * @return string Absolute path to globals.php (verified to exist).
     */
    protected function resolveGlobalsPath(string $openemrPath): string
    {
        $globalsPath = rtrim($openemrPath, '/') . '/interface/globals.php';
        if (!file_exists($globalsPath)) {
            throw new OpenEMRConnectorException("OpenEMR globals.php not found at: {$globalsPath}");
        }

        return $globalsPath;
    }

    /**
     * Populate $_SERVER and $_GET so globals.php can be required from a CLI
     * context. globals.php reads these during the require and dies if they
     * are missing.
     */
    protected function prepareCliEnvironment(string $site): void
    {
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/';
        $_SERVER['SCRIPT_NAME'] ??= '/cli.php';
        $_SERVER['SERVER_NAME'] ??= 'localhost';

        $_GET['site'] = $site;
    }

    /**
     * Confirm the OpenEMR database connection came up after bootstrap.
     */
    protected function verifyDatabase(): void
    {
        if (!isset($GLOBALS['dbh']) || $GLOBALS['dbh'] === false) {
            throw new OpenEMRConnectorException(
                "OpenEMR database connection failed; check sqlconf.php and that MySQL is reachable"
            );
        }

        if (!function_exists('sqlQuery')) {
            throw new OpenEMRConnectorException("OpenEMR sql functions not loaded after bootstrap");
        }

        try {
            sqlQuery("SELECT 1");
        } catch (\Throwable $e) {
            throw new OpenEMRConnectorException(
                "OpenEMR database connection test failed",
                (int) $e->getCode(),
                $e
            );
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function getOpenEMRPath(): string
    {
        return $this->openemrPath;
    }

    public function getSite(): string
    {
        return $this->site;
    }
}
