<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Command;

use DateInterval;
use DateTime;
use DH\Auditor\Auditor;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not execute SQL queries.')
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Prints SQL related queries.')
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
        $until = $this->validateKeepArgument($keep, $io);

        if (null === $until) {
            return 0;
        }

        /** @var DoctrineProvider $provider */
        $provider = $this->auditor->getProvider(DoctrineProvider::class);
        $schemaManager = new SchemaManager($provider);

//        $entities = $this->provider->getConfiguration()->getEntities();
        /** @var StorageService[] $storageServices */
        $storageServices = $provider->getStorageServices();

        // auditable entities by storage entity manager
        $repository = [];
        $count = 0;

        // Collect auditable entities from auditing storage managers
        [$repository, $count] = $this->collectAuditableEntities($provider, $schemaManager, $repository, $count);

        $message = sprintf(
            "You are about to clean audits created before <comment>%s</comment>: %d entities involved.\n Do you want to proceed?",
            $until->format('Y-m-d'),
            $count
        );

        $confirm = $input->getOption('no-confirm') ? true : $io->confirm($message, false);
        $dryRun = (bool) $input->getOption('dry-run');
        $dumpSQL = (bool) $input->getOption('dump-sql');

        if ($confirm) {
            /** @var Configuration $configuration */
            $configuration = $provider->getConfiguration();

            $progressBar = new ProgressBar($output, $count);
            $progressBar->setBarWidth(70);
            $progressBar->setFormat("%message%\n".$progressBar->getFormatDefinition('debug'));

            $progressBar->setMessage('Starting...');
            $progressBar->start();

            $queries = [];
            foreach ($repository as $name => $entities) {
                foreach ($entities as $entity => $tablename) {
                    $auditTable = $this->computeAuditTablename($tablename, $configuration);

                    /**
                     * @var QueryBuilder
                     */
                    $queryBuilder = $storageServices[$name]->getEntityManager()->getConnection()->createQueryBuilder();
                    $queryBuilder
                        ->delete($auditTable)
                        ->where('created_at < :until')
                        ->setParameter(':until', $until->format('Y-m-d'))
                    ;

                    if ($dumpSQL) {
                        $queries[] = str_replace(':until', "'".$until->format('Y-m-d')."'", $queryBuilder->getSQL());
                    }

                    if (!$dryRun) {
                        $queryBuilder->execute();
                    }

                    $progressBar->setMessage("Cleaning audit tables... (<info>{$auditTable}</info>)");
                    $progressBar->advance();
                }
            }

            $progressBar->setMessage('Cleaning audit tables... (<info>done</info>)');
            $progressBar->display();

            $io->newLine();
            if ($dumpSQL) {
                $io->newLine();
                $io->writeln('SQL queries to be run:');
                foreach ($queries as $query) {
                    $io->writeln($query);
                }
            }

            $io->newLine();
            $io->success('Success.');
        } else {
            $io->success('Cancelled.');
        }

        // if not released explicitly, Symfony releases the lock
        // automatically when the execution of the command ends
        $this->release();

        return 0;
    }

    private function validateKeepArgument(string $keep, SymfonyStyle $io): ?DateTime
    {
        $until = new DateTime();
        if (is_numeric($keep)) {
            $deprecationMessage = "Providing an integer value for the 'keep' argument is deprecated. Please use the ISO 8601 duration format (e.g. P12M).";
            @trigger_error($deprecationMessage, E_USER_DEPRECATED);
            $io->writeln($deprecationMessage);

            if ((int) $keep <= 0) {
                $io->error("'keep' argument must be a positive number.");
                $this->release();

                return null;
            }

            $until->modify('-'.$keep.' month');
        } else {
            try {
                $dateInterval = new DateInterval((string) $keep);
            } catch (Exception $e) {
                $io->error(sprintf("'keep' argument must be a valid ISO 8601 date interval. '%s' given.", (string) $keep));
                $this->release();

                return null;
            }

            $until->sub($dateInterval);
        }

        return $until;
    }

    private function collectAuditableEntities(DoctrineProvider $provider, SchemaManager $schemaManager, array $repository, int $count): array
    {
        /** @var AuditingService[] $auditingServices */
        $auditingServices = $provider->getAuditingServices();
        foreach ($auditingServices as $name => $auditingService) {
            $classes = $schemaManager->getAuditableTableNames($auditingService->getEntityManager());
            // Populate the auditable entities repository
            foreach ($classes as $entity => $tableName) {
                $storageService = $provider->getStorageServiceForEntity($entity);
                $key = array_search($storageService, $provider->getStorageServices(), true);
                if (!isset($repository[$key])) {
                    $repository[$key] = [];
                }
                $repository[$key][$entity] = $tableName;
                ++$count;
            }
        }

        return [$repository, $count];
    }

    private function computeAuditTablename($tablename, Configuration $configuration): ?string
    {
        return preg_replace(
            sprintf('#^([^\.]+\.)?(%s)$#', preg_quote($tablename, '#')),
            sprintf(
                '$1%s$2%s',
                preg_quote($configuration->getTablePrefix(), '#'),
                preg_quote($configuration->getTableSuffix(), '#')
            ),
            $tablename
        );
    }
}
