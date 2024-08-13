<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Command;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\Persistence\Command\CleanAuditLogsCommand;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @small
 */
final class CleanAuditLogsCommandTest extends TestCase
{
    use LockableTrait;
    use SchemaSetupTrait;

    public function testExecuteFailsWithKeepWrongFormat(): void
    {
        $keep = 'WRONG';

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            'keep' => $keep,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString(\sprintf("[ERROR] 'keep' argument must be a valid ISO 8601 date interval, '%s' given.", $keep), $output);
    }

    public function testDumpSQL(): void
    {
        $schemaManager = new SchemaManager($this->provider);

        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();
        $entities = $configuration->getEntities();

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            '--dump-sql' => true,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        foreach ($entities as $entity => $entityOptions) {
            $storageService = $this->provider->getStorageServiceForEntity($entity);
            $platform = $storageService->getEntityManager()->getConnection()->getDatabasePlatform();
            $expected = 'DELETE FROM '.$schemaManager->resolveAuditTableName($entity, $configuration, $platform);
            self::assertStringContainsString($expected, $output);
        }

        self::assertStringContainsString('[OK] Success', $output);
    }

    /**
     * @depends testDumpSQL
     */
    public function testExecute(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[OK] Success', $output);
    }

    /**
     * @depends testExecute
     */
    public function testExecuteFailsWhileLocked(): void
    {
        $this->lock('audit:clean');

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('The command is already running in another process.', $output);
    }

    protected function createCommand(): CleanAuditLogsCommand
    {
        $command = new CleanAuditLogsCommand();
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

            Animal::class => ['enabled' => true],
            Cat::class => ['enabled' => true],
            Dog::class => ['enabled' => true],
        ]);
    }
}
