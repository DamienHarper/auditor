<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95\Issue95;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
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
        ]);
    }
}
