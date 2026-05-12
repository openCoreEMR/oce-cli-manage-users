<?php

/**
 * user:reset-password — set or randomize a user's password.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openCoreEMR/oce-cli-manage-users/blob/main/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Command;

use OpenCoreEMR\CLI\ManageUsers\Exception\ManageUsersException;
use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;
use OpenCoreEMR\CLI\ManageUsers\Service\PasswordPolicy;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'user:reset-password', description: "Reset an OpenEMR user's password")]
class ResetPasswordCommand extends AbstractUserCommand
{
    private const RANDOM_PASSWORD_LENGTH = 20;
    private const RANDOM_PASSWORD_MAX_ATTEMPTS = 32;
    // Excludes look-alikes (0/O/o, 1/l/I) to reduce transcription errors.
    private const RANDOM_LOWER = 'abcdefghijkmnpqrstuvwxyz';
    private const RANDOM_UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const RANDOM_DIGIT = '23456789';
    // Shell-safe symbols: no quotes, backslash, backtick, $, parens, <, >, |, ;, &, space.
    private const RANDOM_SYMBOL = '!@#%^*-_=+?';

    private readonly PasswordPolicy $policy;

    public function __construct(
        ?OpenEMRConnector $connector = null,
        ?UserManager $users = null,
        ?PasswordPolicy $policy = null,
    ) {
        parent::__construct($connector, $users);
        $this->policy = $policy ?? new PasswordPolicy();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Username to reset')
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_REQUIRED,
                'New password (prompts if absent and --random not given)'
            )
            ->addOption(
                'random',
                null,
                InputOption::VALUE_NONE,
                'Generate and print a random password instead of prompting'
            );
    }

    protected function doExecute(InputInterface $input, SymfonyStyle $io): int
    {
        /** @var string|null $username */
        $username = $input->getOption('user');
        if ($username === null || $username === '') {
            throw new ManageUsersException("--user is required");
        }

        /** @var string|null $password */
        $password = $input->getOption('password');
        /** @var bool $random */
        $random = $input->getOption('random');

        if ($random) {
            if ($password !== null && $password !== '') {
                throw new ManageUsersException("--random cannot be combined with --password");
            }
            $password = $this->generatePolicyCompliantPassword();
            $io->writeln("Generated password: <info>{$password}</info>");
        } elseif ($password === null || $password === '') {
            $question = new Question("New password for {$username}: ");
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            /** @var string|null $entered */
            $entered = $io->askQuestion($question);
            if ($entered === null || $entered === '') {
                throw new ManageUsersException("Password cannot be empty");
            }
            $password = $entered;
        }

        $this->users->resetPassword($username, $password);

        $io->writeln("OK: password reset for <info>{$username}</info>");
        return self::SUCCESS;
    }

    /**
     * Generate a strong random password and verify it satisfies the install's
     * configured policy. The generator already covers all four character
     * classes at length 20, so a single attempt almost always suffices —
     * retries exist only to absorb a future stricter policy without surprising
     * the operator with a "successful" reset to an unusable account.
     */
    private function generatePolicyCompliantPassword(): string
    {
        $lastError = null;
        for ($i = 0; $i < self::RANDOM_PASSWORD_MAX_ATTEMPTS; $i++) {
            $candidate = $this->generateRandomCandidate();
            $lastError = $this->policy->validate($candidate);
            if ($lastError === null) {
                return $candidate;
            }
        }
        throw new ManageUsersException(
            "Could not generate a policy-compliant random password after "
            . self::RANDOM_PASSWORD_MAX_ATTEMPTS . " attempts: {$lastError}"
        );
    }

    private function generateRandomCandidate(): string
    {
        // Guarantee at least one of each class so the strictest default OpenEMR
        // policy (secure_password) accepts the result on the first try.
        $chars = [
            $this->pick(self::RANDOM_LOWER),
            $this->pick(self::RANDOM_UPPER),
            $this->pick(self::RANDOM_DIGIT),
            $this->pick(self::RANDOM_SYMBOL),
        ];

        $alphabet = self::RANDOM_LOWER . self::RANDOM_UPPER . self::RANDOM_DIGIT . self::RANDOM_SYMBOL;
        for ($i = count($chars); $i < self::RANDOM_PASSWORD_LENGTH; $i++) {
            $chars[] = $this->pick($alphabet);
        }

        // Fisher-Yates shuffle with a CSPRNG; str_shuffle is not cryptographic.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /**
     * @param non-empty-string $alphabet
     */
    private function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
