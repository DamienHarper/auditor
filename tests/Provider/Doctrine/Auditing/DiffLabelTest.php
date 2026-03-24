<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Fixtures\SimpleServiceLocator;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute\DiffLabelEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute\DummyCategoryResolver;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the DiffLabel resolver feature.
 *
 * @internal
 */
#[Small]
final class DiffLabelTest extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    /**
     * Labels are stored as {'value': x, 'label': y} when a resolver is configured and returns a non-null label.
     */
    public function testDiffLabelIsStoredAsValueLabelPair(): void
    {
        $this->provider->setDiffLabelResolverLocator(new SimpleServiceLocator([
            DummyCategoryResolver::class => static fn () => new DummyCategoryResolver(),
        ]));

        $storageServices = [
            DiffLabelEntity::class => $this->provider->getStorageServiceForEntity(DiffLabelEntity::class),
        ];
        $entityManager = $storageServices[DiffLabelEntity::class]->getEntityManager();
        $reader = $this->createReader();

        $entity = new DiffLabelEntity();
        $entity->name = 'test';
        $entity->categoryId = 1;
        $entityManager->persist($entity);
        $this->flushAll($storageServices);

        // Update: change categoryId from 1 to 2
        $entity->categoryId = 2;
        $entityManager->persist($entity);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DiffLabelEntity::class)->execute();
        $this->assertCount(2, $audits);

        // INSERT audit: categoryId = 1 → label 'Books'
        $insertAudit = $audits[1]; // oldest first
        $diffs = $insertAudit->getDiffs();
        $this->assertArrayHasKey('categoryId', $diffs);
        $this->assertSame(['label' => 'Books', 'value' => 1], $diffs['categoryId']['new']);

        // UPDATE audit: categoryId 1→2
        $updateAudit = $audits[0]; // newest first
        $diffs = $updateAudit->getDiffs();
        $this->assertArrayHasKey('categoryId', $diffs);
        $this->assertSame(['label' => 'Books', 'value' => 1], $diffs['categoryId']['old']);
        $this->assertSame(['label' => 'Electronics', 'value' => 2], $diffs['categoryId']['new']);
    }

    /**
     * When a resolver returns null for a value, the plain scalar is stored (no label wrapper).
     */
    public function testDiffLabelIsOmittedWhenResolverReturnsNull(): void
    {
        $this->provider->setDiffLabelResolverLocator(new SimpleServiceLocator([
            DummyCategoryResolver::class => static fn () => new DummyCategoryResolver(),
        ]));

        $storageServices = [
            DiffLabelEntity::class => $this->provider->getStorageServiceForEntity(DiffLabelEntity::class),
        ];
        $entityManager = $storageServices[DiffLabelEntity::class]->getEntityManager();
        $reader = $this->createReader();

        // categoryId = 99 is not in DummyCategoryResolver's map → resolver returns null
        $entity = new DiffLabelEntity();
        $entity->name = 'test';
        $entity->categoryId = 99;
        $entityManager->persist($entity);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DiffLabelEntity::class)->execute();
        $this->assertCount(1, $audits);

        $diffs = $audits[0]->getDiffs();
        $this->assertArrayHasKey('categoryId', $diffs);
        // No label wrapper — plain scalar
        $this->assertSame(99, $diffs['categoryId']['new']);
    }

    /**
     * Fields without a DiffLabel resolver store plain scalar values, unaffected.
     */
    public function testUnlabeledFieldIsUnaffected(): void
    {
        $this->provider->setDiffLabelResolverLocator(new SimpleServiceLocator([
            DummyCategoryResolver::class => static fn () => new DummyCategoryResolver(),
        ]));

        $storageServices = [
            DiffLabelEntity::class => $this->provider->getStorageServiceForEntity(DiffLabelEntity::class),
        ];
        $entityManager = $storageServices[DiffLabelEntity::class]->getEntityManager();
        $reader = $this->createReader();

        $entity = new DiffLabelEntity();
        $entity->name = 'hello';
        $entity->categoryId = 1;
        $entityManager->persist($entity);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DiffLabelEntity::class)->execute();
        $this->assertCount(1, $audits);

        $diffs = $audits[0]->getDiffs();
        $this->assertArrayHasKey('name', $diffs);
        // Plain string, no label wrapper
        $this->assertSame('hello', $diffs['name']['new']);
    }

    /**
     * Without a resolver locator configured, diff values are plain scalars.
     */
    public function testDiffLabelIsIgnoredWhenNoLocatorConfigured(): void
    {
        // No setDiffLabelResolverLocator() call — locator is null
        $storageServices = [
            DiffLabelEntity::class => $this->provider->getStorageServiceForEntity(DiffLabelEntity::class),
        ];
        $entityManager = $storageServices[DiffLabelEntity::class]->getEntityManager();
        $reader = $this->createReader();

        $entity = new DiffLabelEntity();
        $entity->name = 'test';
        $entity->categoryId = 1;
        $entityManager->persist($entity);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DiffLabelEntity::class)->execute();
        $this->assertCount(1, $audits);

        $diffs = $audits[0]->getDiffs();
        $this->assertArrayHasKey('categoryId', $diffs);
        $this->assertSame(1, $diffs['categoryId']['new']);
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());
        $auditor->registerProvider($this->provider);

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Attribute',
            __DIR__.'/../Fixtures/Entity/Attribute',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DiffLabelEntity::class => ['enabled' => true],
        ]);
    }
}
