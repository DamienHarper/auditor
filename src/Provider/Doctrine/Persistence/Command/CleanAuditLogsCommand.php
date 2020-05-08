<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Command;

use DateInterval;
use DateTime;
use DH\Auditor\Auditor;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Updater\UpdateManager;
use DH\Auditor\Provider\ProviderInterface;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CleanAuditLogsCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'audit:clean';

    /**
     * @var Auditor
     */
    private $auditor;

    /**
     * @var ProviderInterface
     */
    private $provider;

    public function unlock(): void
    {
        $this->release();
    }

    public function setAuditor(Auditor $auditor): self
    {
        $this->auditor = $auditor;

        return $this;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Cleans audit tables')
            ->setName(self::$defaultName)
            ->addOption('no-confirm', null, InputOption::VALUE_NONE, 'No interaction mode')
            ->addArgument('keep', InputArgument::OPTIONAL, 'Audits retention period (must be expressed as an ISO 8601 date interval, e.g. P12M to keep the last 12 months or P7D to keep the last 7 days).', 'P12M')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $io = new SymfonyStyle($input, $output);

        $keep = $input->getArgument('keep');
        $keep = (\is_array($keep) ? $keep[0] : $keep);
        if (is_numeric($keep)) {
            $deprecationMessage = "Providing an integer value for the 'keep' argument is deprecated. Please use the ISO 8601 duration format (e.g. P12M).";
            @trigger_error($deprecationMessage, E_USER_DEPRECATED);
            $io->writeln($deprecationMessage);

            if ((int) $keep <= 0) {
                $io->error("'keep' argument must be a positive number.");
                $this->release();

                return 0;
            }

            $until = new DateTime();
            $until->modify('-'.$keep.' month');
        } else {
            try {
                $dateInterval = new DateInterval((string) $keep);
            } catch (Exception $e) {
                $io->error(sprintf("'keep' argument must be a valid ISO 8601 date interval. '%s' given.", (string) $keep));
                $this->release();

                return 0;
            }

            $until = new DateTime();
            $until->sub($dateInterval);
        }

        $this->provider = $this->auditor->getProvider(DoctrineProvider::class);

        /** @var DoctrineProvider $provider */
        $provider = $this->provider;
        $updateManager = new UpdateManager($provider);

//        $entities = $this->provider->getConfiguration()->getEntities();
        $storageEntityManagers = $this->provider->getStorageServices();

        // auditable entities by storage entity manager
        $repository = [];
        $count = 0;

        // Collect auditable entities from auditing storage managers
        $auditingEntityManagers = $this->provider->getAuditingServices();
        foreach ($auditingEntityManagers as $name => $auditingEntityManager) {
            $classes = $updateManager->getAuditableTableNames($auditingEntityManager);
            // Populate the auditable entities repository
            foreach ($classes as $entity => $tableName) {
                $em = $this->provider->getEntityManagerForEntity($entity);
                $key = array_search($em, $this->provider->getStorageServices(), true);
                if (!isset($repository[$key])) {
                    $repository[$key] = [];
                }
                $repository[$key][$entity] = $tableName;
                ++$count;
            }
        }

        $message = sprintf(
            "You are about to clean audits created before <comment>%s</comment>: %d entities involved.\n Do you want to proceed?",
            $until->format('Y-m-d'),
            $count
        );

        $confirm = $input->getOption('no-confirm') ? true : $io->confirm($message, false);

        if ($confirm) {
            /** @var Configuration $configuration */
            $configuration = $this->provider->getConfiguration();

            $progressBar = new ProgressBar($output, $count);
            $progressBar->setBarWidth(70);
            $progressBar->setFormat("%message%\n".$progressBar->getFormatDefinition('debug'));

            $progressBar->setMessage('Starting...');
            $progressBar->start();

            foreach ($repository as $name => $entities) {
                foreach ($entities as $entity => $tablename) {
                    $auditTable = preg_replace(
                        sprintf('#^([^\.]+\.)?(%s)$#', preg_quote($tablename, '#')),
                        sprintf(
                            '$1%s$2%s',
                            preg_quote($configuration->getTablePrefix(), '#'),
                            preg_quote($configuration->getTableSuffix(), '#')
                        ),
                        $tablename
                    );

                    /**
                     * @var QueryBuilder
                     */
                    $queryBuilder = $storageEntityManagers[$name]->getConnection()->createQueryBuilder();
                    $queryBuilder
                        ->delete($auditTable)
                        ->where('created_at < :until')
                        ->setParameter(':until', $until->format('Y-m-d'))
                        ->execute()
                    ;

                    $progressBar->setMessage("Cleaning audit tables... (<info>{$auditTable}</info>)");
                    $progressBar->advance();
                }
            }

            $progressBar->setMessage('Cleaning audit tables... (<info>done</info>)');
            $progressBar->display();

            $io->newLine(2);

            $io->success('Success.');
        } else {
            $io->success('Cancelled.');
        }

        // if not released explicitly, Symfony releases the lock
        // automatically when the execution of the command ends
        $this->release();

        return 0;
    }
}
