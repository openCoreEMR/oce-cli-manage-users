<?php

/**
 * Mirror of OpenEMR's runtime password validation.
 *
 * OpenEMR's actual checks live in AuthUtils::testMinimumPasswordLength,
 * testMaximumPasswordLength, and testPasswordStrength — all private, and the
 * only public entry point (AuthUtils::updatePassword) couples validation with
 * persistence and process-killing edge cases. So we cannot delegate; we
 * reapply the same checks against the install's own $GLOBALS so the policy
 * tracks whatever the operator configured.
 *
 * If OpenEMR ever exposes a side-effect-free validator, replace this class
 * with a thin wrapper around it.
 *
 * @see vendor/openemr/openemr/src/Common/Auth/AuthUtils.php
 *      (testMinimumPasswordLength, testMaximumPasswordLength, testPasswordStrength)
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Service;

final class PasswordPolicy implements PasswordPolicyInterface
{
    /**
     * @return string|null Null on pass; an explanatory message on fail.
     */
    public function validate(string $password): ?string
    {
        /** @var mixed $rawMin */
        $rawMin = $GLOBALS['gbl_minimum_password_length'] ?? 0;
        $min = is_numeric($rawMin) ? (int)$rawMin : 0;
        if ($min > 0 && strlen($password) < $min) {
            return "Password too short (minimum {$min} characters)";
        }

        /** @var mixed $rawMax */
        $rawMax = $GLOBALS['gbl_maximum_password_length'] ?? 0;
        $max = is_numeric($rawMax) ? (int)$rawMax : 0;
        if ($max > 0 && strlen($password) > $max) {
            return "Password too long (maximum {$max} characters)";
        }

        $secure = $GLOBALS['secure_password'] ?? null;
        if ($secure) {
            $classes = [
                '/[a-z]/',    // lowercase
                '/[A-Z]/',    // uppercase
                '/\d/',       // digit
                '/[\W_]/',    // symbol (non-word or underscore)
            ];
            foreach ($classes as $regex) {
                if (preg_match($regex, $password) !== 1) {
                    return "Password must contain a lowercase letter, uppercase letter, digit, and symbol";
                }
            }
        }

        return null;
    }
}
