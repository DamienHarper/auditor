<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\Event\AuditEvent;
use DH\Auditor\Event\Dto\AbstractEventDto;
use DH\Auditor\Event\Dto\InsertEventDto;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorConnection;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorMiddleware;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\AuditTrait;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionHydrator;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionProcessor;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Event\TableSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\PlatformHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\DoctrineService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40\CoreCase;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40\DieselCase;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(CoreCase::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(AuditEvent::class)]
#[CoversClass(AbstractEventDto::class)]
#[CoversClass(InsertEventDto::class)]
#[CoversClass(Entry::class)]
#[CoversClass(Transaction::class)]
#[CoversClass(AbstractProvider::class)]
#[CoversClass(AnnotationLoader::class)]
#[CoversClass(DoctrineSubscriber::class)]
#[CoversClass(AuditorConnection::class)]
#[CoversClass(AuditorDriver::class)]
#[CoversClass(AuditorMiddleware::class)]
#[CoversTrait(AuditTrait::class)]
#[CoversClass(TransactionHydrator::class)]
#[CoversClass(TransactionManager::class)]
#[CoversClass(TransactionProcessor::class)]
#[CoversClass(\DH\Auditor\Provider\Doctrine\Configuration::class)]
#[CoversClass(DoctrineProvider::class)]
#[CoversClass(\DH\Auditor\Provider\Doctrine\Model\Transaction::class)]
#[CoversClass(CreateSchemaListener::class)]
#[CoversClass(TableSchemaListener::class)]
#[CoversClass(DoctrineHelper::class)]
#[CoversClass(PlatformHelper::class)]
#[CoversClass(SchemaHelper::class)]
#[CoversClass(Query::class)]
#[CoversClass(Reader::class)]
#[CoversClass(SchemaManager::class)]
#[CoversClass(DoctrineService::class)]
#[CoversClass(AbstractService::class)]
final class Issue40Test extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

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
        $this->assertCount(1, $audits, 'results count ok.');
        $this->assertSame(Transaction::INSERT, $audits[0]->getType(), 'Reader::INSERT operation.');

        $audits = $reader->createQuery(DieselCase::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');
        $this->assertSame(Transaction::INSERT, $audits[0]->getType(), 'Reader::INSERT operation.');
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
