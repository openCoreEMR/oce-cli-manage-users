<?php

/**
 * Shared base for the user:* commands.
 *
 * Centralizes the --openemr-path / --site options, the OpenEMR bootstrap, and
 * the boilerplate that turns thrown ManageUsersException-family errors into
 * non-zero exits with a clear stderr message.
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @link      https://opencoreemr.com
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenCoreEMR\CLI\ManageUsers\Command;

use OpenCoreEMR\CLI\ManageUsers\Service\OpenEMRConnector;
use OpenCoreEMR\CLI\ManageUsers\Service\UserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractUserCommand extends Command
{
    protected const DEFAULT_OPENEMR_PATH = '/var/www/localhost/htdocs/openemr';
    protected const DEFAULT_SITE = 'default';

    protected OpenEMRConnector $connector;
    protected UserManager $users;

    public function __construct(
        ?OpenEMRConnector $connector = null,
        ?UserManager $users = null,
    ) {
        parent::__construct();
        $this->connector = $connector ?? new OpenEMRConnector();
        $this->users = $users ?? new UserManager();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'openemr-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to OpenEMR installation',
                self::DEFAULT_OPENEMR_PATH
            )
            ->addOption(
                'site',
                null,
                InputOption::VALUE_REQUIRED,
                'OpenEMR site name',
                self::DEFAULT_SITE
            );
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $openemrPath */
        $openemrPath = $input->getOption('openemr-path');
        /** @var string $site */
        $site = $input->getOption('site');

        try {
            $this->connector->initialize($openemrPath, $site);
            return $this->doExecute($input, $io);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    abstract protected function doExecute(InputInterface $input, SymfonyStyle $io): int;
}
