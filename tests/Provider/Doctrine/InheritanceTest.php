<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\Event\AuditEvent;
use DH\Auditor\Event\Dto\AbstractEventDto;
use DH\Auditor\Event\Dto\InsertEventDto;
use DH\Auditor\Event\Dto\UpdateEventDto;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\Transaction;
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
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\DoctrineService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
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
#[CoversClass(DoctrineProvider::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(AuditEvent::class)]
#[CoversClass(AbstractEventDto::class)]
#[CoversClass(InsertEventDto::class)]
#[CoversClass(UpdateEventDto::class)]
#[CoversClass(Entry::class)]
#[CoversClass(Transaction::class)]
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
#[CoversClass(\DH\Auditor\Provider\Doctrine\Model\Transaction::class)]
#[CoversClass(CreateSchemaListener::class)]
#[CoversClass(TableSchemaListener::class)]
#[CoversClass(DoctrineHelper::class)]
#[CoversClass(PlatformHelper::class)]
#[CoversClass(SchemaHelper::class)]
#[CoversClass(SimpleFilter::class)]
#[CoversClass(Query::class)]
#[CoversClass(Reader::class)]
#[CoversClass(SchemaManager::class)]
#[CoversClass(DoctrineService::class)]
#[CoversClass(AbstractService::class)]
final class InheritanceTest extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testAuditingSubclass(): void
    {
        $storageServices = [
            Vehicle::class => $this->provider->getStorageServiceForEntity(Vehicle::class),
            Car::class => $this->provider->getStorageServiceForEntity(Car::class),
            Bike::class => $this->provider->getStorageServiceForEntity(Bike::class),
            Cat::class => $this->provider->getStorageServiceForEntity(Cat::class),
            Dog::class => $this->provider->getStorageServiceForEntity(Dog::class),
        ];

        $reader = $this->createReader();

        $car = new Car();
        $car->setLabel('La Ferrari');
        $car->setWheels(4);
        $storageServices[Car::class]->getEntityManager()->persist($car);
        $this->flushAll($storageServices);

        $bike = new Bike();
        $bike->setLabel('ZX10R');
        $bike->setWheels(2);
        $storageServices[Bike::class]->getEntityManager()->persist($bike);
        $this->flushAll($storageServices);

        $tryke = new Vehicle();
        $tryke->setLabel('Can-am Spyder');
        $tryke->setWheels(3);
        $storageServices[Vehicle::class]->getEntityManager()->persist($tryke);
        $this->flushAll($storageServices);

        $cat = new Cat();
        $cat->setLabel('cat');
        $storageServices[Cat::class]->getEntityManager()->persist($cat);
        $this->flushAll($storageServices);

        $dog = new Dog();
        $dog->setLabel('dog');
        $storageServices[Dog::class]->getEntityManager()->persist($dog);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(Vehicle::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Vehicle::class, ['strict' => false])->execute();
        $this->assertCount(3, $audits, 'results count ok.');

        $audits = $reader->createQuery(Car::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Bike::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Cat::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Dog::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        $car->setLabel('Taycan');
        $storageServices[Car::class]->getEntityManager()->persist($car);
        $this->flushAll($storageServices);

        $cat->setLabel('cat2');
        $storageServices[Cat::class]->getEntityManager()->persist($cat);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(Vehicle::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Car::class)->execute();
        $this->assertCount(2, $audits, 'results count ok.');

        $audits = $reader->createQuery(Dog::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Cat::class)->execute();
        $this->assertCount(2, $audits, 'results count ok.');
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/Fixtures/Entity/Inheritance',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));
        //        $this->provider->registerEntityManager(
        //            $this->createEntityManager([
        //                __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
        //                __DIR__.'/Fixtures/Entity/Inheritance',
        //            ]),
        //            DoctrineProvider::BOTH,
        //            'default'
        //        );

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Vehicle::class => ['enabled' => true],
            Car::class => ['enabled' => true],
            Bike::class => ['enabled' => true],
            Cat::class => ['enabled' => true],
            Dog::class => ['enabled' => true],
        ]);
    }
}
