<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Command;

use DH\Auditor\Provider\Doctrine\Persistence\Command\UpdateSchemaCommand;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class UpdateSchemaCommandTest extends TestCase
{
    use LockableTrait;
    use SchemaSetupTrait;

    protected function setUp(): void
    {
        // provider with 1 em for both storage and auditing
        $this->createAndInitDoctrineProvider();

        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $schemaTool = new SchemaTool($storageService->getEntityManager());

            $this->setUpEntitySchema($schemaTool, $storageService->getEntityManager()); // setup entity schema only since audited entites are not declared
            $this->configureEntities();                                                 // declare audited entites
        }

        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $this->setupEntities();
        }
    }

    public function testExecute(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $command->unlock();

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[CAUTION] This operation should not be executed in a production environment!', $output);
        self::assertStringContainsString('The Schema-Tool would execute ', $output);
        self::assertStringContainsString(' queries to update the database.', $output);
    }

    /**
     * @depends testExecute
     */
    public function testExecuteDumpSQL(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--dump-sql' => true]);
        $command->unlock();

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('The following SQL statements will be executed:', $output);
    }

    /**
     * @depends testExecute
     */
    public function testExecuteForce(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--force' => true]);
        $command->unlock();

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Updating database schema...', $output);
        self::assertStringContainsString('[OK] Database schema updated successfully!', $output);
    }

    /**
     * @depends testExecute
     */
    public function testExecuteForceDumpSQL(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--force' => true,
            '--dump-sql' => true,
        ]);
        $command->unlock();

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('The following SQL statements will be executed:', $output);
        self::assertStringContainsString('Updating database schema...', $output);
        self::assertStringContainsString('[OK] Database schema updated successfully!', $output);
    }

    /**
     * @depends testExecute
     */
    public function testExecuteNothingToUpdate(): void
    {
//        foreach ($this->provider->getStorageEntityManagers() as $name => $entityManager) {
//            $schemaTool = new SchemaTool($entityManager);
//            $this->setUpAuditSchema($schemaTool, $entityManager);   // setup audit schema based on configured audited entities
//        }
        $this->provider->getConfiguration()->setEntities([]);   // workaround because above fails on Travis CI with PHP 7.3.17

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--force' => true]);
        $command->unlock();

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[OK] Nothing to update.', $output);
    }

    /**
     * @depends testExecute
     */
    public function testExecuteFailsWhileLocked(): void
    {
        $this->lock('audit:schema:update');

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $command->unlock();

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('The command is already running in another process.', $output);
    }

    protected function createCommand(): UpdateSchemaCommand
    {
        $command = new UpdateSchemaCommand();
        $command->setAuditor($this->provider->getAuditor());
        $command->unlock();

        return $command;
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
            Post::class => ['enabled' => true],
            Comment::class => ['enabled' => true],
            Tag::class => ['enabled' => true],
        ]);
    }
}
