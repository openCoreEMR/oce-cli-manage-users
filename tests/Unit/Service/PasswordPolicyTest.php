<?php

/**
 * Unit tests for PasswordPolicy.
 *
 * PasswordPolicy reads $GLOBALS, so each test sets the relevant keys and
 * tearDown clears them to keep tests isolated.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers\Tests
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Tests\Unit\Service;

use OpenCoreEMR\CLI\ManageUsers\Service\PasswordPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    private PasswordPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PasswordPolicy();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['secure_password'],
            $GLOBALS['gbl_minimum_password_length'],
            $GLOBALS['gbl_maximum_password_length'],
        );
    }

    #[Test]
    public function passesWhenPolicyDisabled(): void
    {
        self::assertNull($this->policy->validate('hex'));
    }

    #[Test]
    public function rejectsShortPassword(): void
    {
        $GLOBALS['gbl_minimum_password_length'] = 12;
        $error = $this->policy->validate('short');
        self::assertNotNull($error);
        self::assertStringContainsString('too short', $error);
    }

    #[Test]
    public function rejectsLongPassword(): void
    {
        $GLOBALS['gbl_maximum_password_length'] = 8;
        $error = $this->policy->validate('this-is-too-long');
        self::assertNotNull($error);
        self::assertStringContainsString('too long', $error);
    }

    #[Test]
    public function securePasswordRequiresAllFourClasses(): void
    {
        $GLOBALS['secure_password'] = 1;

        // hex-only string (the bug from issue #12) should be rejected
        self::assertNotNull($this->policy->validate('a1b2c3d4e5f6a1b2c3d4'));

        // missing symbol
        self::assertNotNull($this->policy->validate('Abcdefgh1234'));

        // all four classes present
        self::assertNull($this->policy->validate('Abcdefgh1234!'));
    }
}
