<?php

/**
 * Contract for runtime password policy validation.
 *
 * Exists so callers (commands, tests) depend on the abstraction rather than
 * the concrete PasswordPolicy. Tests mock this interface; the concrete
 * implementation stays final.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Service;

interface PasswordPolicyInterface
{
    /**
     * @return string|null Null on pass; an explanatory message on fail.
     */
    public function validate(string $password): ?string;
}
