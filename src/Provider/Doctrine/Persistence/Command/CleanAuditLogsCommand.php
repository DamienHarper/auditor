<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Command;

use DateInterval;
use DateTimeImmutable;
use DH\Auditor\Auditor;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @see \DH\Auditor\Tests\Provider\Doctrine\Persistence\Command\CleanAuditLogsCommandTest
 */
class CleanAuditLogsCommand extends Command
{
    use LockableTrait;

    /**
     * @var string
     */
    private const UNTIL_DATE_FORMAT = 'Y-m-d H:i:s';

    private Auditor $auditor;

    public function setAuditor(Auditor $auditor): self
    {
        $this->auditor = $auditor;

        return $this;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Cleans audit tables')
            ->setName('audit:clean')
            ->addOption('no-confirm', null, InputOption::VALUE_NONE, 'No interaction mode')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not execute SQL queries.')
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Prints SQL related queries.')
            ->addOption('exclude', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Entities to exclude from cleaning')
            ->addOption('include', 'i', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Entities to include in cleaning')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Specify a custom date to clean audits until (must be expressed as an ISO 8601 date, e.g. 2023-04-24).')
            ->addArgument('keep', InputArgument::OPTIONAL, 'Audits retention period (must be expressed as an ISO 8601 date interval, e.g. P12M to keep the last 12 months or P7D to keep the last 7 days).', 'P12M')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        $keep = $input->getArgument('keep');
        $keep = (\is_array($keep) ? $keep[0] : $keep);

        /** @var string $date */
        $date = $input->getOption('date');
        $until = null;

        if ($date) {
            // Use custom date if provided
            try {
                $until = new DateTimeImmutable($date);
            } catch (Exception $e) {
                $io->error(sprintf('Invalid date format provided: %s', $date));
            }
        } else {
            // Fall back to default retention period
            $until = $this->validateKeepArgument($keep, $io);
        }

        if (null === $until) {
            return Command::SUCCESS;
        }

        /** @var DoctrineProvider $provider */
        $provider = $this->auditor->getProvider(DoctrineProvider::class);
        $schemaManager = new SchemaManager($provider);

        /** @var StorageService[] $storageServices */
        $storageServices = $provider->getStorageServices();

        // auditable classes by storage entity manager
        $count = 0;

        // Collect auditable classes from auditing storage managers
        $rawExcludeValues = $input->getOption('exclude') ?? [];
        $rawIncludeValues = $input->getOption('include') ?? [];
        $excludeEntities = \is_array($rawExcludeValues) ? $rawExcludeValues : [$rawExcludeValues];
        $includeEntities = \is_array($rawIncludeValues) ? $rawIncludeValues : [$rawIncludeValues];
        $repository = $schemaManager->collectAuditableEntities();
        $filteredRepository = [];

        foreach ($repository as $name => $entityClasses) {
            foreach ($entityClasses as $entityClass => $table) {
                if (
                    !\in_array($entityClass, $excludeEntities, true)
                    && (
                        empty($includeEntities)
                        || \in_array($entityClass, $includeEntities, true)
                    )
                ) {
                    $filteredRepository[$name][$entityClass] = $table;
                }
            }
        }

        $repository = $filteredRepository;

        foreach ($repository as $name => $entities) {
            $count += \count($entities);
        }

        $message = sprintf(
            "You are about to clean audits created before <comment>%s</comment>: %d classes involved.\n Do you want to proceed?",
            $until->format(self::UNTIL_DATE_FORMAT),
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

            /**
             * @var string                $name
             * @var array<string, string> $classes
             */
            foreach ($repository as $name => $classes) {
                foreach ($classes as $entity => $tablename) {
                    $connection = $storageServices[$name]->getEntityManager()->getConnection();
                    $auditTable = $schemaManager->resolveAuditTableName($entity, $configuration, $connection->getDatabasePlatform());

                    $queryBuilder = $connection->createQueryBuilder();
                    $queryBuilder
                        ->delete($auditTable)
                        ->where('created_at < :until')
                        ->setParameter('until', $until->format(self::UNTIL_DATE_FORMAT))
                    ;

                    if ($dumpSQL) {
                        $queries[] = str_replace(':until', "'".$until->format(self::UNTIL_DATE_FORMAT)."'", $queryBuilder->getSQL());
                    }

                    if (!$dryRun) {
                        DoctrineHelper::executeStatement($queryBuilder);
                    }

                    $progressBar->setMessage(sprintf('Cleaning audit tables... (<info>%s</info>)', $auditTable));
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

        return Command::SUCCESS;
    }

    private function validateKeepArgument(string $keep, SymfonyStyle $io): ?DateTimeImmutable
    {
        try {
            $dateInterval = new DateInterval($keep);
        } catch (Exception) {
            $io->error(sprintf("'keep' argument must be a valid ISO 8601 date interval, '%s' given.", $keep));
            $this->release();

            return null;
        }

        return (new DateTimeImmutable())->sub($dateInterval);
    }
}
