<?php

/**
 * Unit tests for ResetPasswordCommand
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Command;

use OpenCoreEMR\CLI\ManageUsers\Command\ResetPasswordCommand;
use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;
use OpenCoreEMR\CLI\ManageUsers\Service\PasswordPolicyInterface;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ResetPasswordCommandTest extends TestCase
{
    private OpenEMRConnector&MockObject $connector;
    private UserManager&MockObject $users;
    private PasswordPolicyInterface&MockObject $policy;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(OpenEMRConnector::class);
        $this->users = $this->createMock(UserManager::class);
        $this->policy = $this->createMock(PasswordPolicyInterface::class);

        $command = new ResetPasswordCommand($this->connector, $this->users, $this->policy);
        $app = new Application();
        $app->add($command);

        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function commandHasExpectedName(): void
    {
        self::assertSame('user:reset-password', (new ResetPasswordCommand())->getName());
    }

    #[Test]
    public function failsWithoutUserOption(): void
    {
        $this->users->expects(self::never())->method('resetPassword');

        $exit = $this->tester->execute(['--password' => 'whatever']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('--user is required', $this->tester->getDisplay());
    }

    #[Test]
    public function resetWithExplicitPassword(): void
    {
        $this->users->expects(self::once())
            ->method('resetPassword')
            ->with('alice', 'sekret');

        $exit = $this->tester->execute(['--user' => 'alice', '--password' => 'sekret']);

        self::assertSame(0, $exit);
        self::assertStringContainsString('OK: password reset for', $this->tester->getDisplay());
    }

    #[Test]
    public function randomGeneratesPolicyCompliantPassword(): void
    {
        $this->policy->expects(self::atLeastOnce())
            ->method('validate')
            ->willReturn(null);

        $captured = null;
        $this->users->expects(self::once())
            ->method('resetPassword')
            ->with('bob', self::callback(function (string $password) use (&$captured): bool {
                $captured = $password;
                return strlen($password) === 20
                    && preg_match('/[a-z]/', $password) === 1
                    && preg_match('/[A-Z]/', $password) === 1
                    && preg_match('/\d/', $password) === 1
                    && preg_match('/[\W_]/', $password) === 1;
            }));

        $exit = $this->tester->execute(['--user' => 'bob', '--random' => true]);

        self::assertSame(0, $exit);
        self::assertNotNull($captured);
        self::assertStringContainsString("Generated password: {$captured}", $this->tester->getDisplay());
    }

    #[Test]
    public function randomRetriesUntilPolicyAccepts(): void
    {
        $this->policy->expects(self::exactly(3))
            ->method('validate')
            ->willReturnOnConsecutiveCalls('too weak', 'too weak', null);

        $this->users->expects(self::once())->method('resetPassword');

        $exit = $this->tester->execute(['--user' => 'bob', '--random' => true]);

        self::assertSame(0, $exit);
    }

    #[Test]
    public function randomFailsWhenPolicyKeepsRejecting(): void
    {
        $this->policy->method('validate')->willReturn('arbitrary policy says no');
        $this->users->expects(self::never())->method('resetPassword');

        $exit = $this->tester->execute(['--user' => 'bob', '--random' => true]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('policy-compliant', $this->tester->getDisplay());
    }

    #[Test]
    public function randomCannotBeCombinedWithPassword(): void
    {
        $this->users->expects(self::never())->method('resetPassword');

        $exit = $this->tester->execute([
            '--user' => 'eve',
            '--password' => 'foo',
            '--random' => true,
        ]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('cannot be combined', $this->tester->getDisplay());
    }
}
