<?php

/**
 * Unit tests for ListCommand
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Command;

use OpenCoreEMR\CLI\ManageUsers\Command\ListCommand;
use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ListCommandTest extends TestCase
{
    private OpenEMRConnector&MockObject $connector;
    private UserManager&MockObject $users;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(OpenEMRConnector::class);
        $this->users = $this->createMock(UserManager::class);

        $command = new ListCommand($this->connector, $this->users);

        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function rendersTableOfUsers(): void
    {
        $this->users->expects(self::once())
            ->method('listUsers')
            ->with(false, false, false)
            ->willReturn([
                [
                    'id' => 1, 'username' => 'admin', 'fname' => 'Ad', 'lname' => 'Min',
                    'active' => 1, 'authorized' => 1,
                    'last_update_password' => '2026-01-01 00:00:00', 'login_fail_counter' => 0,
                ],
            ]);

        $exit = $this->tester->execute([]);

        self::assertSame(0, $exit);
        $display = $this->tester->getDisplay();
        self::assertStringContainsString('admin', $display);
        self::assertStringContainsString('OK: 1 user(s) listed', $display);
    }

    #[Test]
    public function activeAndInactiveAreMutuallyExclusive(): void
    {
        $this->users->expects(self::never())->method('listUsers');

        $exit = $this->tester->execute(['--active-only' => true, '--inactive-only' => true]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('mutually exclusive', $this->tester->getDisplay());
    }

    #[Test]
    public function nonScalarColumnValueThrows(): void
    {
        $this->users->expects(self::once())
            ->method('listUsers')
            ->willReturn([
                [
                    'id' => 1, 'username' => 'admin', 'fname' => 'Ad', 'lname' => 'Min',
                    'active' => 1, 'authorized' => ['unexpected', 'array'],
                    'last_update_password' => '2026-01-01 00:00:00', 'login_fail_counter' => 0,
                ],
            ]);

        $exit = $this->tester->execute([]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('non-scalar column value', $this->tester->getDisplay());
    }

    #[Test]
    public function lockedFlagPropagates(): void
    {
        $this->users->expects(self::once())
            ->method('listUsers')
            ->with(false, false, true)
            ->willReturn([]);

        $exit = $this->tester->execute(['--locked' => true]);

        self::assertSame(0, $exit);
    }
}
