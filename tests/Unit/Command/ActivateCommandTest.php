<?php

/**
 * Unit tests for ActivateCommand
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Command;

use OpenCoreEMR\CLI\ManageUsers\Command\ActivateCommand;
use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ActivateCommandTest extends TestCase
{
    private OpenEMRConnector&MockObject $connector;
    private UserManager&MockObject $users;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(OpenEMRConnector::class);
        $this->users = $this->createMock(UserManager::class);

        $command = new ActivateCommand($this->connector, $this->users);

        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function failsWithoutUserOption(): void
    {
        $this->users->expects(self::never())->method('activate');

        $exit = $this->tester->execute([]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('--user is required', $this->tester->getDisplay());
    }

    #[Test]
    public function activatesByUsername(): void
    {
        $this->users->expects(self::once())
            ->method('activate')
            ->with('alice', false);

        $exit = $this->tester->execute(['--user' => 'alice']);

        self::assertSame(0, $exit);
        self::assertStringContainsString('OK: activated', $this->tester->getDisplay());
    }

    #[Test]
    public function alsoAuthorizesWhenFlagSet(): void
    {
        $this->users->expects(self::once())
            ->method('activate')
            ->with('alice', true);

        $exit = $this->tester->execute(['--user' => 'alice', '--authorized' => true]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('authorized', $this->tester->getDisplay());
    }
}
