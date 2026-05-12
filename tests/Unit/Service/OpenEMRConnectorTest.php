<?php

/**
 * Unit tests for OpenEMRConnector.
 *
 * initialize() requires a real OpenEMR install on disk, so it is exercised
 * end-to-end via the smoke task. The pieces it composes (site validation,
 * globals.php location, CLI env defaults, post-bootstrap DB verification)
 * are protected helpers; this test reaches them via a subclass that exposes
 * each one directly.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Service;

use OpenCoreEMR\CLI\ManageUsers\Exception\OpenEMRConnectorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenEMRConnectorTest extends TestCase
{
    private OpenEMRConnectorTestable $connector;

    /** @var array<string, string|null> */
    private array $serverBackup = [];

    /** @var array<string, mixed> */
    private array $getBackup = [];

    /** @var list<string> */
    private array $tempRoots = [];

    private ?bool $hadDbh = null;
    private mixed $dbhBackup = null;

    protected function setUp(): void
    {
        $this->connector = new OpenEMRConnectorTestable();

        foreach (['HTTP_HOST', 'REQUEST_URI', 'SCRIPT_NAME', 'SERVER_NAME'] as $key) {
            $this->serverBackup[$key] = $_SERVER[$key] ?? null;
            unset($_SERVER[$key]);
        }
        $this->getBackup = $_GET;

        $this->hadDbh = array_key_exists('dbh', $GLOBALS);
        $this->dbhBackup = $GLOBALS['dbh'] ?? null;
    }

    protected function tearDown(): void
    {
        foreach ($this->serverBackup as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
        $_GET = $this->getBackup;

        if ($this->hadDbh) {
            $GLOBALS['dbh'] = $this->dbhBackup;
        } else {
            unset($GLOBALS['dbh']);
        }

        foreach ($this->tempRoots as $root) {
            @unlink($root . '/interface/globals.php');
            @rmdir($root . '/interface');
            @rmdir($root);
        }
        $this->tempRoots = [];
    }

    /** @return iterable<string, array{string}> */
    public static function validSites(): iterable
    {
        yield 'default' => ['default'];
        yield 'lowercase letters' => ['clinic'];
        yield 'mixed case' => ['ClinicA'];
        yield 'digits' => ['site42'];
        yield 'underscore' => ['site_one'];
        yield 'hyphen' => ['site-one'];
        yield 'all allowed chars' => ['Aa0_-'];
    }

    #[Test]
    #[DataProvider('validSites')]
    public function validateSiteAcceptsSafeNames(string $site): void
    {
        $this->expectNotToPerformAssertions();
        $this->connector->callValidateSite($site);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSites(): iterable
    {
        yield 'empty' => [''];
        yield 'path traversal' => ['../etc'];
        yield 'forward slash' => ['a/b'];
        yield 'space' => ['site one'];
        yield 'dot' => ['site.one'];
        yield 'null byte' => ["site\0"];
        yield 'newline' => ["site\n"];
        yield 'unicode' => ['sité'];
    }

    #[Test]
    #[DataProvider('invalidSites')]
    public function validateSiteRejectsUnsafeNames(string $site): void
    {
        $this->expectException(OpenEMRConnectorException::class);
        $this->expectExceptionMessage('Invalid --site value');
        $this->connector->callValidateSite($site);
    }

    #[Test]
    public function resolveGlobalsPathReturnsPathWhenFileExists(): void
    {
        $root = $this->makeFakeOpenemrRoot();

        $resolved = $this->connector->callResolveGlobalsPath($root);

        $this->assertSame($root . '/interface/globals.php', $resolved);
    }

    #[Test]
    public function resolveGlobalsPathTrimsTrailingSlash(): void
    {
        $root = $this->makeFakeOpenemrRoot();

        $resolved = $this->connector->callResolveGlobalsPath($root . '/');

        $this->assertSame($root . '/interface/globals.php', $resolved);
    }

    #[Test]
    public function resolveGlobalsPathThrowsWhenMissing(): void
    {
        $this->expectException(OpenEMRConnectorException::class);
        $this->expectExceptionMessage('OpenEMR globals.php not found');
        $this->connector->callResolveGlobalsPath(sys_get_temp_dir() . '/no-such-openemr-' . uniqid());
    }

    #[Test]
    public function prepareCliEnvironmentSetsDefaults(): void
    {
        $this->connector->callPrepareCliEnvironment('default');

        $this->assertSame('localhost', $_SERVER['HTTP_HOST']);
        $this->assertSame('/', $_SERVER['REQUEST_URI']);
        $this->assertSame('/cli.php', $_SERVER['SCRIPT_NAME']);
        $this->assertSame('localhost', $_SERVER['SERVER_NAME']);
        $this->assertSame('default', $_GET['site']);
    }

    #[Test]
    public function prepareCliEnvironmentDoesNotOverrideExistingServerVars(): void
    {
        $_SERVER['HTTP_HOST'] = 'preset.example';
        $_SERVER['REQUEST_URI'] = '/preset';
        $_SERVER['SCRIPT_NAME'] = '/preset.php';
        $_SERVER['SERVER_NAME'] = 'preset.example';

        $this->connector->callPrepareCliEnvironment('clinic');

        $this->assertSame('preset.example', $_SERVER['HTTP_HOST']);
        $this->assertSame('/preset', $_SERVER['REQUEST_URI']);
        $this->assertSame('/preset.php', $_SERVER['SCRIPT_NAME']);
        $this->assertSame('preset.example', $_SERVER['SERVER_NAME']);
    }

    #[Test]
    public function prepareCliEnvironmentAlwaysOverwritesGetSite(): void
    {
        $_GET['site'] = 'stale';

        $this->connector->callPrepareCliEnvironment('clinic');

        $this->assertSame('clinic', $_GET['site']);
    }

    #[Test]
    public function verifyDatabaseThrowsWhenDbhMissing(): void
    {
        unset($GLOBALS['dbh']);

        $this->expectException(OpenEMRConnectorException::class);
        $this->expectExceptionMessage('OpenEMR database connection failed');
        $this->connector->callVerifyDatabase();
    }

    #[Test]
    public function verifyDatabaseThrowsWhenDbhFalse(): void
    {
        $GLOBALS['dbh'] = false;

        $this->expectException(OpenEMRConnectorException::class);
        $this->expectExceptionMessage('OpenEMR database connection failed');
        $this->connector->callVerifyDatabase();
    }

    #[Test]
    public function verifyDatabaseThrowsWhenSqlQueryMissing(): void
    {
        if (function_exists('sqlQuery')) {
            $this->markTestSkipped('sqlQuery() is defined in this process; cannot test missing-function branch.');
        }

        $GLOBALS['dbh'] = new \stdClass();

        $this->expectException(OpenEMRConnectorException::class);
        $this->expectExceptionMessage('OpenEMR sql functions not loaded after bootstrap');
        $this->connector->callVerifyDatabase();
    }

    #[Test]
    public function freshConnectorIsNotInitialized(): void
    {
        $this->assertFalse($this->connector->isInitialized());
        $this->assertSame('', $this->connector->getOpenEMRPath());
        $this->assertSame('default', $this->connector->getSite());
    }

    #[Test]
    public function initializeIsIdempotentAfterPriorSuccess(): void
    {
        // Simulate a prior successful bootstrap by reaching in via Reflection
        // (the real path requires a live OpenEMR, which unit tests can't
        // provide). A second initialize() call must short-circuit; passing a
        // bogus path here proves it never reaches resolveGlobalsPath.
        $reflection = new \ReflectionClass(\OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector::class);
        $reflection->getProperty('openemrPath')->setValue($this->connector, '/already/booted');
        $reflection->getProperty('site')->setValue($this->connector, 'clinic');
        $reflection->getProperty('initialized')->setValue($this->connector, true);

        $this->connector->initialize('/no/such/openemr', 'whatever');

        $this->assertTrue($this->connector->isInitialized());
        $this->assertSame('/already/booted', $this->connector->getOpenEMRPath());
        $this->assertSame('clinic', $this->connector->getSite());
    }

    private function makeFakeOpenemrRoot(): string
    {
        $root = sys_get_temp_dir() . '/oce-cli-manage-users-test-' . uniqid();
        mkdir($root . '/interface', 0700, true);
        file_put_contents($root . '/interface/globals.php', "<?php\n");
        $this->tempRoots[] = $root;

        return $root;
    }
}
