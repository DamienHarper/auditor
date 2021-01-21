<?php

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
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
        self::assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Vehicle::class, ['strict' => false])->execute();
        self::assertCount(3, $audits, 'results count ok.');

        $audits = $reader->createQuery(Car::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Bike::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Cat::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Dog::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');

        $car->setLabel('Taycan');
        $storageServices[Car::class]->getEntityManager()->persist($car);
        $this->flushAll($storageServices);

        $cat->setLabel('cat2');
        $storageServices[Cat::class]->getEntityManager()->persist($cat);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(Vehicle::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Car::class)->execute();
        self::assertCount(2, $audits, 'results count ok.');

        $audits = $reader->createQuery(Dog::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');

        $audits = $reader->createQuery(Cat::class)->execute();
        self::assertCount(2, $audits, 'results count ok.');
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
