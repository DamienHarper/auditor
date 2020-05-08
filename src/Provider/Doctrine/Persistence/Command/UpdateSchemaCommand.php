<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Command;

use DH\Auditor\Auditor;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Updater\UpdateManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateSchemaCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'audit:schema:update';

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
            ->setDescription('Update audit tables structure')
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements to the screen (does not execute them).')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Causes the generated SQL statements to be physically executed against your database.')
            ->setName(self::$defaultName)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $io = new SymfonyStyle($input, $output);

        $dumpSql = true === $input->getOption('dump-sql');
        $force = true === $input->getOption('force');

        /** @var DoctrineProvider $provider */
        $provider = $this->auditor->getProvider(DoctrineProvider::class);
        $updateManager = new UpdateManager($provider);

        $sqls = $updateManager->getUpdateAuditSchemaSql();

        $count = 0;
        foreach ($sqls as $entityManagerName => $queries) {
            $count += \count($queries);
        }

        if (0 === $count) {
            $io->success('Nothing to update.');
            $this->release();

            return 0;
        }

        if ($dumpSql) {
            $io->text('The following SQL statements will be executed:');
            $io->newLine();

            foreach ($sqls as $entityManagerName => $queries) {
                foreach ($queries as $index => $sql) {
                    $io->text(sprintf('    %s;', $sql));
                }
            }
        }

        if ($force) {
            if ($dumpSql) {
                $io->newLine();
            }
            $io->text('Updating database schema...');
            $io->newLine();

            $progressBar = new ProgressBar($output, \count($sqls));
            $progressBar->start();

            $updateManager->updateAuditSchema($sqls, static function (array $progress) use ($progressBar): void {
                $progressBar->advance();
            });

            $progressBar->finish();
            $io->newLine(2);

            $pluralization = (1 === $count) ? 'query was' : 'queries were';

            $io->text(sprintf('    <info>%s</info> %s executed', $count, $pluralization));
            $io->success('Database schema updated successfully!');
        }

        if ($dumpSql || $force) {
            $this->release();

            return 0;
        }

        $io->caution('This operation should not be executed in a production environment!');
        $io->text(
            [
                sprintf('The Schema-Tool would execute <info>"%s"</info> queries to update the database.', $count),
                '',
                'Please run the operation by passing one - or both - of the following options:',
                '',
                sprintf('    <info>%s --force</info> to execute the command', $this->getName()),
                sprintf('    <info>%s --dump-sql</info> to dump the SQL statements to the screen', $this->getName()),
            ]
        );

        $this->release();

        return 1;
    }
}
