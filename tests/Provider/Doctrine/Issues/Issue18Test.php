<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue18\DataObject;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class Issue18Test extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testIssue18(): void
    {
        $storageServices = [
            DataObject::class => $this->provider->getStorageServiceForEntity(DataObject::class),
        ];

        $reader = $this->createReader();

        $dataObject = new DataObject();
        $dataObject->setId(1);
        $dataObject->setData(fopen(__DIR__.'/../Fixtures/Issue18/file1.txt', 'r'));
        $storageServices[DataObject::class]->getEntityManager()->persist($dataObject);
        $this->flushAll($storageServices);

        $dataObject->setData(fopen(__DIR__.'/../Fixtures/Issue18/file2.txt', 'r'));
        $storageServices[DataObject::class]->getEntityManager()->persist($dataObject);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DataObject::class)->execute();
        self::assertCount(2, $audits, 'results count ok.');
        self::assertSame(Transaction::UPDATE, $audits[0]->getType(), 'Transaction::UPDATE operation.');
        self::assertSame(Transaction::INSERT, $audits[1]->getType(), 'Transaction::INSERT operation.');

        self::assertIsArray($audits[0]->getDiffs(), 'Valid diffs');
        self::assertIsArray($audits[1]->getDiffs(), 'Valid diffs');
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Issue18',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DataObject::class => ['enabled' => true],
        ]);
    }
}
