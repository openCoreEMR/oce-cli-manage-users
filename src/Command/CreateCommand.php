<?php

/**
 * user:create — create a new OpenEMR user with a password.
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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'user:create', description: 'Create a new OpenEMR user')]
class CreateCommand extends AbstractUserCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username for the new user')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for the new user')
            ->addOption('firstname', null, InputOption::VALUE_REQUIRED, 'First name (fname)')
            ->addOption('lastname', null, InputOption::VALUE_REQUIRED, 'Last name (lname)')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address (optional)')
            ->addOption(
                'authorized',
                null,
                InputOption::VALUE_NEGATABLE,
                'Mark as authorized provider (default: false)',
                false
            )
            ->addOption('active', null, InputOption::VALUE_NEGATABLE, 'Mark as active (default: true)', true)
            ->addOption(
                'group',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'ACL group title to add the user to (repeatable). Required unless --authorized is set,'
                . ' in which case it defaults to Administrators.'
            )
            ->addOption(
                'legacy-group',
                null,
                InputOption::VALUE_REQUIRED,
                "Name for the row inserted into the legacy `groups` table (default: Default)."
                . ' Auth rejects login when this row is missing, even with a valid ACL ARO.',
                'Default'
            );
    }

    protected function doExecute(InputInterface $input, SymfonyStyle $io): int
    {
        $authorized = (bool) $input->getOption('authorized');
        $groups = $this->resolveGroups($input, $authorized);

        $spec = [
            'username' => $this->requiredString($input, 'username'),
            'password' => $this->requiredString($input, 'password'),
            'fname' => $this->requiredString($input, 'firstname'),
            'lname' => $this->requiredString($input, 'lastname'),
            'email' => $this->optionalString($input, 'email'),
            'authorized' => $authorized,
            'active' => (bool) $input->getOption('active'),
            'groups' => $groups,
            'legacyGroup' => $this->requiredString($input, 'legacy-group'),
        ];

        $userId = $this->users->create($spec);

        $io->writeln("OK: created user <info>{$spec['username']}</info> (id={$userId})");
        return self::SUCCESS;
    }

    private function requiredString(InputInterface $input, string $option): string
    {
        $value = $input->getOption($option);
        if (!is_string($value) || $value === '') {
            throw new ManageUsersException("--{$option} is required");
        }
        return $value;
    }

    /**
     * @return list<string>
     */
    private function resolveGroups(InputInterface $input, bool $authorized): array
    {
        /** @var list<mixed> $raw */
        $raw = (array) $input->getOption('group');
        $groups = [];
        foreach ($raw as $value) {
            if (!is_string($value) || $value === '') {
                throw new ManageUsersException('--group values must be non-empty strings');
            }
            $groups[] = $value;
        }

        if (count($groups) === 0) {
            if (!$authorized) {
                throw new ManageUsersException(
                    '--group is required (repeatable) so the new user is registered in the ACL system'
                    . ' and can log in. Pass --authorized to default to Administrators.'
                );
            }
            $groups[] = 'Administrators';
        }

        return $groups;
    }

    private function optionalString(InputInterface $input, string $option): ?string
    {
        $value = $input->getOption($option);
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new ManageUsersException("--{$option} must be a string");
        }
        return $value;
    }
}
