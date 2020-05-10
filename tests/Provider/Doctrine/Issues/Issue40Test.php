<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40\CoreCase;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40\DieselCase;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class Issue40Test extends TestCase
{
    use SchemaSetupTrait;
    use ReaderTrait;

    public function testIssue40(): void
    {
        $storageServices = [
            CoreCase::class => $this->provider->getStorageServiceForEntity(CoreCase::class),
            DieselCase::class => $this->provider->getStorageServiceForEntity(DieselCase::class),
        ];

        $reader = $this->createReader();

        $coreCase = new CoreCase();
        $coreCase->type = 'type1';
        $coreCase->status = 'status1';
        $storageServices[CoreCase::class]->getEntityManager()->persist($coreCase);
        $this->flushAll($storageServices);

        $dieselCase = new DieselCase();
        $dieselCase->coreCase = $coreCase;
        $dieselCase->setName('yo');
        $storageServices[DieselCase::class]->getEntityManager()->persist($dieselCase);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(CoreCase::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');
        self::assertSame(Transaction::INSERT, $audits[0]->getType(), 'AuditReader::INSERT operation.');

        $audits = $reader->createQuery(DieselCase::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');
        self::assertSame(Transaction::INSERT, $audits[0]->getType(), 'AuditReader::INSERT operation.');
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Issue40',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));
//        $this->provider->registerEntityManager(
//            $this->createEntityManager([
//                __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
//                __DIR__.'/../Fixtures/Issue40',
//            ]),
//            DoctrineProvider::BOTH,
//            'default'
//        );

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            CoreCase::class => ['enabled' => true],
            DieselCase::class => ['enabled' => true],
        ]);
    }
}
