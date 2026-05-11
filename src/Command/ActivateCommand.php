<?php

/**
 * user:activate — set users.active = 1, optionally also authorized = 1.
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

#[AsCommand(name: 'user:activate', description: 'Mark a user active (and optionally authorized)')]
class ActivateCommand extends AbstractUserCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Username to activate')
            ->addOption('authorized', null, InputOption::VALUE_NONE, 'Also set users.authorized = 1');
    }

    protected function doExecute(InputInterface $input, SymfonyStyle $io): int
    {
        /** @var string|null $username */
        $username = $input->getOption('user');
        if ($username === null || $username === '') {
            throw new ManageUsersException("--user is required");
        }

        $alsoAuthorize = (bool) $input->getOption('authorized');

        $this->users->activate($username, $alsoAuthorize);

        $message = $alsoAuthorize
            ? "OK: activated and authorized <info>{$username}</info>"
            : "OK: activated <info>{$username}</info>";
        $io->writeln($message);
        return self::SUCCESS;
    }
}
