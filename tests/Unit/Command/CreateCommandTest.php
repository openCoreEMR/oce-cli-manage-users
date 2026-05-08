<?php

/**
 * Unit tests for CreateCommand
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Command;

use OpenCoreEMR\CLI\ManageUsers\Command\CreateCommand;
use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CreateCommandTest extends TestCase
{
    private OpenEMRConnector&MockObject $connector;
    private UserManager&MockObject $users;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(OpenEMRConnector::class);
        $this->users = $this->createMock(UserManager::class);

        $command = new CreateCommand($this->connector, $this->users);
        $app = new Application();
        $app->add($command);

        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function failsWhenRequiredFieldsMissing(): void
    {
        $this->users->expects(self::never())->method('create');

        $exit = $this->tester->execute(['--username' => 'alice']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('required', $this->tester->getDisplay());
    }

    #[Test]
    public function createsUserWithExplicitGroup(): void
    {
        $this->users->expects(self::once())
            ->method('create')
            ->with(self::callback(function (array $spec): bool {
                self::assertSame('alice', $spec['username']);
                self::assertSame('sekret', $spec['password']);
                self::assertSame('Alice', $spec['fname']);
                self::assertSame('Liddell', $spec['lname']);
                self::assertNull($spec['email']);
                self::assertFalse($spec['authorized']);
                self::assertTrue($spec['active']);
                self::assertSame(['Clinicians'], $spec['groups']);
                return true;
            }))
            ->willReturn(42);

        $exit = $this->tester->execute([
            '--username' => 'alice',
            '--password' => 'sekret',
            '--firstname' => 'Alice',
            '--lastname' => 'Liddell',
            '--group' => ['Clinicians'],
        ]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('OK: created user', $this->tester->getDisplay());
        self::assertStringContainsString('id=42', $this->tester->getDisplay());
    }

    #[Test]
    public function passesAuthorizedAndEmailFlagsAndDefaultsGroupToAdministrators(): void
    {
        $this->users->expects(self::once())
            ->method('create')
            ->with(self::callback(function (array $spec): bool {
                self::assertSame('admin@example.com', $spec['email']);
                self::assertTrue($spec['authorized']);
                self::assertFalse($spec['active']);
                self::assertSame(['Administrators'], $spec['groups']);
                return true;
            }))
            ->willReturn(7);

        $exit = $this->tester->execute([
            '--username' => 'admin',
            '--password' => 'pw',
            '--firstname' => 'Ad',
            '--lastname' => 'Min',
            '--email' => 'admin@example.com',
            '--authorized' => true,
            '--active' => false,
        ]);

        self::assertSame(0, $exit);
    }

    #[Test]
    public function failsWhenGroupOmittedAndNotAuthorized(): void
    {
        $this->users->expects(self::never())->method('create');

        $exit = $this->tester->execute([
            '--username' => 'alice',
            '--password' => 'pw',
            '--firstname' => 'A',
            '--lastname' => 'L',
        ]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('--group is required', $this->tester->getDisplay());
    }

    #[Test]
    public function passesMultipleGroups(): void
    {
        $this->users->expects(self::once())
            ->method('create')
            ->with(self::callback(function (array $spec): bool {
                self::assertSame(['Administrators', 'Clinicians'], $spec['groups']);
                return true;
            }))
            ->willReturn(11);

        $exit = $this->tester->execute([
            '--username' => 'alice',
            '--password' => 'pw',
            '--firstname' => 'A',
            '--lastname' => 'L',
            '--group' => ['Administrators', 'Clinicians'],
        ]);

        self::assertSame(0, $exit);
    }
}
