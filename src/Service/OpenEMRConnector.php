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
    private string $openemrPath = '';
    private string $site = 'default';
    private bool $initialized = false;

    public function initialize(string $openemrPath, string $site = 'default'): void
    {
        if ($this->initialized) {
            return;
        }

        // Site name becomes a filesystem path segment (sites/<site>/sqlconf.php)
        // and is also written into $_GET. Restrict to a safe character set
        // before either use to prevent path traversal during bootstrap.
        if (preg_match('/^[A-Za-z0-9_-]+$/', $site) !== 1) {
            throw new OpenEMRConnectorException(
                "Invalid --site value; must match [A-Za-z0-9_-]+, got: {$site}"
            );
        }

        $this->openemrPath = rtrim($openemrPath, '/');
        $this->site = $site;

        $globalsPath = $this->openemrPath . '/interface/globals.php';
        if (!file_exists($globalsPath)) {
            throw new OpenEMRConnectorException("OpenEMR globals.php not found at: {$globalsPath}");
        }

        // globals.php reads $_SERVER values during the require; set safe CLI defaults.
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/';
        $_SERVER['SCRIPT_NAME'] ??= '/cli.php';
        $_SERVER['SERVER_NAME'] ??= 'localhost';

        $_GET['site'] = $site;

        // Must be set BEFORE the require — globals.php consumes them during inclusion.
        $ignoreAuth = true;
        $sessionAllowWrite = true;

        require_once $globalsPath;

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

        $this->initialized = true;
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
