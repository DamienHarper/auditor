<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Command;

use DateInterval;
use DateTime;
use DH\Auditor\Auditor;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Updater\UpdateManager;
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
     * @var DoctrineProvider
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

        if (is_numeric($input->getArgument('keep'))) {
            $deprecationMessage = "Providing an integer value for the 'keep' argument is deprecated. Please use the ISO 8601 duration format (e.g. P12M).";
            @trigger_error($deprecationMessage, E_USER_DEPRECATED);
            $io->writeln($deprecationMessage);

            $keep = (int) $input->getArgument('keep');

            if ($keep <= 0) {
                $io->error("'keep' argument must be a positive number.");
                $this->release();

                return 0;
            }

            $until = new DateTime();
            $until->modify('-'.$keep.' month');
        } else {
            $keep = (string) ($input->getArgument('keep'));

            try {
                $dateInterval = new DateInterval($keep);
            } catch (Exception $e) {
                $io->error(sprintf("'keep' argument must be a valid ISO 8601 date interval. '%s' given.", $keep));
                $this->release();

                return 0;
            }

            $until = new DateTime();
            $until->sub($dateInterval);
        }

        $this->provider = $this->auditor->getProvider(DoctrineProvider::class);
        $entities = $this->provider->getConfiguration()->getEntities();

        $updateManager = new UpdateManager($this->provider);

        $storageEntityManagers = $this->provider->getStorageEntityManagers();

        // auditable entities by storage entity manager
        $repository = [];
        $count = 0;

        // Collect auditable entities from auditing storage managers
        $auditingEntityManagers = $this->provider->getAuditingEntityManagers();
        foreach ($auditingEntityManagers as $name => $auditingEntityManager) {
            $classes = $updateManager->getAuditableTableNames($auditingEntityManager);
            // Populate the auditable entities repository
            foreach ($classes as $entity => $tableName) {
                $em = $this->provider->getEntityManagerForEntity($entity);
                $key = array_search($em, $this->provider->getStorageEntityManagers(), true);
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
                            preg_quote($this->provider->getConfiguration()->getTablePrefix(), '#'),
                            preg_quote($this->provider->getConfiguration()->getTableSuffix(), '#')
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
