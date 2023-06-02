<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Exception\MappingException;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue33\Offer;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue33\Shop;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue33\ShopOfferPrice;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Small]
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
