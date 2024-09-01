<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\Event\AuditEvent;
use DH\Auditor\Event\Dto\AbstractEventDto;
use DH\Auditor\Event\Dto\InsertEventDto;
use DH\Auditor\Event\Dto\UpdateEventDto;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorConnection;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorMiddleware;
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
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue18\DataObject;
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
#[CoversClass(Issue18Test::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(AuditEvent::class)]
#[CoversClass(AbstractEventDto::class)]
#[CoversClass(InsertEventDto::class)]
#[CoversClass(UpdateEventDto::class)]
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
        $this->assertCount(2, $audits, 'results count ok.');
        $this->assertSame(Transaction::UPDATE, $audits[0]->getType(), 'Transaction::UPDATE operation.');
        $this->assertSame(Transaction::INSERT, $audits[1]->getType(), 'Transaction::INSERT operation.');

        $this->assertIsArray($audits[0]->getDiffs(), 'Valid diffs');
        $this->assertIsArray($audits[1]->getDiffs(), 'Valid diffs');
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
