<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionProcessor;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue115\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue115\DummyEnum;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class Issue115Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    public function testIssue115(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            self::markTestSkipped('PHP > 8.0 is required.');
        }
        $reader = new Reader($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(DummyEntity::class);
        $entityManager = $storageService->getEntityManager();
        $processor = new TransactionProcessor($this->provider);
        $transaction = new Transaction($entityManager);
        $entity = new DummyEntity();
        $entity->setId(DummyEnum::A);
        $transaction->insert($entity, [
            'id' => [DummyEnum::A, DummyEnum::B],
        ]);

        $processor->process($transaction);

        $audits = $reader->createQuery(DummyEntity::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');

        /** @var \DH\Auditor\Model\Entry $audit */
        $audit = $audits[0];
        $diffs = $audit->getDiffs()['id'];
        self::assertSame('a', $diffs['old']);
        self::assertSame('b', $diffs['new']);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Issue115',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($this->provider);
    }
}
