<?php

/**
 * user:unlock — clear users_secure lockout state for a user.
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
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'user:unlock', description: "Clear a user's lockout counter")]
class UnlockCommand extends AbstractUserCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Username to unlock');
    }

    protected function doExecute(InputInterface $input, SymfonyStyle $io): int
    {
        /** @var string|null $username */
        $username = $input->getOption('user');
        if ($username === null || $username === '') {
            throw new ManageUsersException("--user is required");
        }

        $this->users->unlock($username);

        $io->writeln("OK: unlocked <info>{$username}</info>");
        return self::SUCCESS;
    }
}
