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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'user:reset-password', description: "Reset an OpenEMR user's password")]
class ResetPasswordCommand extends AbstractUserCommand
{
    private const RANDOM_PASSWORD_BYTES = 12;

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
            $password = $this->generateRandomPassword();
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

    private function generateRandomPassword(): string
    {
        // Hex of N bytes -> 2N chars; safely printable, easy to copy/paste.
        return bin2hex(random_bytes(self::RANDOM_PASSWORD_BYTES));
    }
}
