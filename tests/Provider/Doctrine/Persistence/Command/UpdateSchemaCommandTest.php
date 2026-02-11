<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Command;

use DH\Auditor\Auditor;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Command\UpdateSchemaCommand;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Small]
final class UpdateSchemaCommandTest extends TestCase
{
    use LockableTrait;
    use SchemaSetupTrait;

    protected function setUp(): void
    {
        // provider with 1 em for both storage and auditing
        $this->createAndInitDoctrineProvider();

        // declare audited entites
        $this->configureEntities();

        // setup entity schema only
        $this->setupEntitySchemas();

        $this->setupEntities();
    }

    public function testExecute(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[CAUTION] This operation should not be executed in a production environment!', $output);
        $this->assertStringContainsString('The Schema-Tool would execute ', $output);
        $this->assertStringContainsString(' queries to update the database.', $output);
    }

    #[Depends('testExecute')]
    public function testExecuteDumpSQL(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--dump-sql' => true]);

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('The following SQL statements will be executed:', $output);
    }

    #[Depends('testExecute')]
    public function testExecuteForce(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--force' => true]);

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Updating database schema...', $output);
        $this->assertStringContainsString('[OK] Database schema updated successfully!', $output);
    }

    #[Depends('testExecute')]
    public function testExecuteForceDumpSQL(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--force' => true,
            '--dump-sql' => true,
        ]);

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('The following SQL statements will be executed:', $output);
        $this->assertStringContainsString('Updating database schema...', $output);
        $this->assertStringContainsString('[OK] Database schema updated successfully!', $output);
    }

    #[Depends('testExecute')]
    public function testExecuteNothingToUpdate(): void
    {
        $this->provider->getConfiguration()->setEntities([]);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--force' => true]);

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[OK] Nothing to update.', $output);
    }

    #[Depends('testExecute')]
    public function testExecuteFailsWhileLocked(): void
    {
        $this->lock('audit:schema:update');

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // the output of the command in the auditor
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('The command is already running in another process.', $output);

        $this->release();
    }

    private function createCommand(): UpdateSchemaCommand
    {
        $command = new UpdateSchemaCommand();
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
        ]);
    }

    /**
     * Creates a DoctrineProvider with 1 entity manager used both for auditing and storage.
     */
    private function createDoctrineProvider(?Configuration $configuration = null): DoctrineProvider
    {
        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../../Fixtures/Entity/Standard',
            __DIR__.'/../../Fixtures/Entity/Inheritance',
        ]);
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());
        $provider->registerStorageService(new StorageService('default', $entityManager));
        $provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($provider);

        // unregister CreateSchemaListener
        $evm = $entityManager->getEventManager();
        foreach ($evm->getAllListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof CreateSchemaListener) {
                    $evm->removeEventListener([$event], $listener);
                }
            }
        }

        return $provider;
    }
}
