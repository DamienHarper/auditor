<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Command;

use DH\Auditor\Provider\Doctrine\Persistence\Command\CleanAuditLogsCommand;
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
final class CleanAuditLogsCommandTest extends TestCase
{
    use LockableTrait;
    use SchemaSetupTrait;

    public function testDeprecationOutput(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            'keep' => 12,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString("Providing an integer value for the 'keep' argument is deprecated. Please use the ISO 8601 duration format (e.g. P12M).", $output);
    }

    public function testExecuteFailsWithKeepNegative(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            'keep' => -1,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString("[ERROR] 'keep' argument must be a positive number.", $output);
    }

    /**
     * @depends testExecuteFailsWithKeepNegative
     */
    public function testExecuteFailsWithKeepNull(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            'keep' => 0,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString("[ERROR] 'keep' argument must be a positive number.", $output);
    }

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
        self::assertStringContainsString(sprintf("[ERROR] 'keep' argument must be a valid ISO 8601 date interval. '%s' given.", $keep), $output);
    }

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
        ]);
    }
}
