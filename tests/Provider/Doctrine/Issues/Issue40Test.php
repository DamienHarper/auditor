<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
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
        $entityManagers = [
            CoreCase::class => $this->provider->getEntityManagerForEntity(CoreCase::class),
            DieselCase::class => $this->provider->getEntityManagerForEntity(DieselCase::class),
        ];

        $reader = $this->createReader();

        $coreCase = new CoreCase();
        $coreCase->type = 'type1';
        $coreCase->status = 'status1';
        $entityManagers[CoreCase::class]->persist($coreCase);
        $this->flushAll($entityManagers);

        $dieselCase = new DieselCase();
        $dieselCase->coreCase = $coreCase;
        $dieselCase->setName('yo');
        $entityManagers[DieselCase::class]->persist($dieselCase);
        $this->flushAll($entityManagers);

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

        $this->provider->registerEntityManager(
            $this->createEntityManager([
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Issue40',
            ]),
            DoctrineProvider::BOTH,
            'default'
        );

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
