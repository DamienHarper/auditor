<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\StorageServiceInterface;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95\DummyEntityData;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95\Issue95;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95\RelatedDummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95\RelatedDummyEntityData;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Small]
final class Issue95Test extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testIssue95(): void
    {
        $reader = $this->createReader();

        $em = $this->provider->getStorageServiceForEntity(Issue95::class)->getEntityManager();
        $entity = new Issue95('issue95');
        $em->persist($entity);
        $em->flush();

        $audits = $reader->createQuery(Issue95::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');
        self::assertSame(Transaction::INSERT, $audits[0]->getType(), 'Reader::INSERT operation.');
    }

    public function testIssue95WithFixtures(): void
    {
        /** @var StorageServiceInterface[] $storageServices */
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];

        $em = $storageServices[DummyEntity::class]->getEntityManager();

        $fixtures = [
            new DummyEntityData(),
            new RelatedDummyEntityData(),
        ];

        $executor = new ORMExecutor($em);

        $executor->execute($fixtures, true);

        $reader = $this->createReader();

        $audits = [
            ...$reader->createQuery(DummyEntity::class)->execute(),
            ...$reader->createQuery(RelatedDummyEntity::class)->execute(),
        ];

        self::assertCount(3, $audits, 'results count ok.');
        self::assertSame(Transaction::ASSOCIATE, $audits[0]->getType(), 'Association');
        self::assertSame(Transaction::INSERT, $audits[1]->getType(), 'Insertion');
        self::assertSame(Transaction::INSERT, $audits[2]->getType(), 'Insertion');
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Issue95',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Issue95::class => ['enabled' => true],
            DummyEntity::class => ['enabled' => true],
            RelatedDummyEntity::class => ['enabled' => true],
        ]);
    }
}
