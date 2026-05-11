<?php

/**
 * user:list — list OpenEMR users with optional filters.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Command;

use OpenCoreEMR\CLI\ManageUsers\Exception\ManageUsersException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'user:list', description: 'List OpenEMR users')]
class ListCommand extends AbstractUserCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('active-only', null, InputOption::VALUE_NONE, 'Only show active users')
            ->addOption('inactive-only', null, InputOption::VALUE_NONE, 'Only show inactive users')
            ->addOption('locked', null, InputOption::VALUE_NONE, 'Only show users with login_fail_counter > 0');
    }

    protected function doExecute(InputInterface $input, SymfonyStyle $io): int
    {
        $activeOnly = (bool) $input->getOption('active-only');
        $inactiveOnly = (bool) $input->getOption('inactive-only');
        $lockedOnly = (bool) $input->getOption('locked');

        if ($activeOnly && $inactiveOnly) {
            throw new ManageUsersException("--active-only and --inactive-only are mutually exclusive");
        }

        $rows = $this->users->listUsers($activeOnly, $inactiveOnly, $lockedOnly);

        $table = new Table($io);
        $table->setHeaders([
            'id',
            'username',
            'fname',
            'lname',
            'active',
            'authorized',
            'last_update_password',
            'login_fail_counter',
        ]);

        foreach ($rows as $row) {
            $table->addRow([
                $this->stringify($row['id'] ?? null),
                $this->stringify($row['username'] ?? null),
                $this->stringify($row['fname'] ?? null),
                $this->stringify($row['lname'] ?? null),
                $this->stringify($row['active'] ?? null),
                $this->stringify($row['authorized'] ?? null),
                $this->stringify($row['last_update_password'] ?? null),
                $this->stringify($row['login_fail_counter'] ?? 0),
            ]);
        }
        $table->render();

        $count = count($rows);
        $io->writeln("");
        $io->writeln("OK: {$count} user(s) listed");
        return self::SUCCESS;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw new ManageUsersException(sprintf(
            'Unexpected non-scalar column value of type %s; ListCommand needs an updated formatter.',
            get_debug_type($value),
        ));
    }
}
