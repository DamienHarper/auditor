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
 */
#[\PHPUnit\Framework\Attributes\Small]
final class CleanAuditLogsCommandTest extends TestCase
{
    use LockableTrait;
    use SchemaSetupTrait;

    /**
     * @var string
     */
    private const KEEP = 'WRONG';

    public function testExecuteFailsWithKeepWrongFormat(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            'keep' => self::KEEP,
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString(sprintf("[ERROR] 'keep' argument must be a valid ISO 8601 date interval, '%s' given.", self::KEEP), $output);
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

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        foreach (array_keys($entities) as $entity) {
            $storageService = $this->provider->getStorageServiceForEntity($entity);
            $platform = $storageService->getEntityManager()->getConnection()->getDatabasePlatform();
            $expected = 'DELETE FROM '.$schemaManager->resolveAuditTableName($entity, $configuration, $platform);
            self::assertStringContainsString($expected, $output);
        }

        self::assertStringContainsString('[OK] Success', $output);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testDumpSQL')]
    public function testExecute(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[OK] Success', $output);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testExecute')]
    public function testExecuteFailsWhileLocked(): void
    {
        $this->lock('audit:clean');

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('The command is already running in another process.', $output);

        $this->release();
    }

    public function testDateOption(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--date' => '2023-04-26T09:00:00Z',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('clean audits created before 2023-04-26 09:00:00', $output);
    }

    public function testExcludeOptionSingleValue(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--exclude' => Author::class,
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('6 classes involved', $output);
    }

    public function testExcludeOptionMultipleValues(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--exclude' => [
                Author::class,
                Post::class,
            ],
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('5 classes involved', $output);
    }

    public function testIncludeOptionSignleValue(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--include' => Author::class,
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('1 classes involved', $output);
    }

    public function testIncludeOptionMultipleValues(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--include' => [
                Author::class,
                Post::class,
            ],
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('2 classes involved', $output);
    }

    private function createCommand(): CleanAuditLogsCommand
    {
        $command = new CleanAuditLogsCommand();
        $command->setAuditor($this->provider->getAuditor());

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
