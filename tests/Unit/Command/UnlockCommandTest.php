<?php

/**
 * Unit tests for UnlockCommand
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Command;

use OpenCoreEMR\CLI\ManageUsers\Command\UnlockCommand;
use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UnlockCommandTest extends TestCase
{
    private OpenEMRConnector&MockObject $connector;
    private UserManager&MockObject $users;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(OpenEMRConnector::class);
        $this->users = $this->createMock(UserManager::class);

        $command = new UnlockCommand($this->connector, $this->users);

        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function failsWithoutUserOption(): void
    {
        $this->users->expects(self::never())->method('unlock');

        $exit = $this->tester->execute([]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('--user is required', $this->tester->getDisplay());
    }

    #[Test]
    public function unlocksByUsername(): void
    {
        $this->users->expects(self::once())
            ->method('unlock')
            ->with('alice');

        $exit = $this->tester->execute(['--user' => 'alice']);

        self::assertSame(0, $exit);
        self::assertStringContainsString('OK: unlocked', $this->tester->getDisplay());
    }

    #[Test]
    public function userNotFoundErrorPropagatesAsFailure(): void
    {
        $this->users->expects(self::once())
            ->method('unlock')
            ->willThrowException(
                new \OpenCoreEMR\CLI\ManageUsers\Exception\UserNotFoundException('User not found: ghost')
            );

        $exit = $this->tester->execute(['--user' => 'ghost']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('User not found: ghost', $this->tester->getDisplay());
    }
}
