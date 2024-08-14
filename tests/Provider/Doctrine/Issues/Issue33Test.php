<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\Event\AuditEvent;
use DH\Auditor\Event\Dto\AbstractEventDto;
use DH\Auditor\Event\Dto\InsertEventDto;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Exception\MappingException;
use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHConnection;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHMiddleware;
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
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\DoctrineService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue33\Offer;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue33\Shop;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue33\ShopOfferPrice;
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
#[CoversClass(Issue33Test::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(AuditEvent::class)]
#[CoversClass(AbstractEventDto::class)]
#[CoversClass(InsertEventDto::class)]
#[CoversClass(Transaction::class)]
#[CoversClass(AbstractProvider::class)]
#[CoversClass(AnnotationLoader::class)]
#[CoversClass(DoctrineSubscriber::class)]
#[CoversClass(DHConnection::class)]
#[CoversClass(DHDriver::class)]
#[CoversClass(DHMiddleware::class)]
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
#[CoversClass(SchemaManager::class)]
#[CoversClass(DoctrineService::class)]
#[CoversClass(AbstractService::class)]
final class Issue33Test extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testIssue33(): void
    {
        $storageServices = [
            Offer::class => $this->provider->getStorageServiceForEntity(Offer::class),
            Shop::class => $this->provider->getStorageServiceForEntity(Shop::class),
            ShopOfferPrice::class => $this->provider->getStorageServiceForEntity(ShopOfferPrice::class),
        ];

        $offer = new Offer('offer1');
        $storageServices[Offer::class]->getEntityManager()->persist($offer);

        $shop = new Shop('shop1');
        $storageServices[Shop::class]->getEntityManager()->persist($shop);

        $this->flushAll($storageServices);

        $shopOfferPrice = new ShopOfferPrice($shop, $offer, 123);
        $storageServices[ShopOfferPrice::class]->getEntityManager()->persist($shopOfferPrice);

        self::expectException(MappingException::class);

        $this->flushAll($storageServices);
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Issue33',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Offer::class => ['enabled' => true],
            Shop::class => ['enabled' => true],
            ShopOfferPrice::class => ['enabled' => true],
        ]);
    }
}
