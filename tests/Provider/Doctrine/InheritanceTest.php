<?php

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
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
    use SchemaSetupTrait;
    use ReaderTrait;

    public function testAuditingSubclass(): void
    {
        $entityManagers = [
            Vehicle::class => $this->provider->getEntityManagerForEntity(Vehicle::class),
            Car::class => $this->provider->getEntityManagerForEntity(Car::class),
            Bike::class => $this->provider->getEntityManagerForEntity(Bike::class),
            Cat::class => $this->provider->getEntityManagerForEntity(Cat::class),
            Dog::class => $this->provider->getEntityManagerForEntity(Dog::class),
        ];

        $reader = $this->createReader();

        $car = new Car();
        $car->setLabel('La Ferrari');
        $car->setWheels(4);
        $entityManagers[Car::class]->persist($car);
        $this->flushAll($entityManagers);

        $bike = new Bike();
        $bike->setLabel('ZX10R');
        $bike->setWheels(2);
        $entityManagers[Bike::class]->persist($bike);
        $this->flushAll($entityManagers);

        $tryke = new Vehicle();
        $tryke->setLabel('Can-am Spyder');
        $tryke->setWheels(3);
        $entityManagers[Vehicle::class]->persist($tryke);
        $this->flushAll($entityManagers);

        $cat = new Cat();
        $cat->setLabel('cat');
        $entityManagers[Cat::class]->persist($cat);
        $this->flushAll($entityManagers);

        $dog = new Dog();
        $dog->setLabel('dog');
        $entityManagers[Dog::class]->persist($dog);
        $this->flushAll($entityManagers);

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
        $entityManagers[Car::class]->persist($car);
        $this->flushAll($entityManagers);

        $cat->setLabel('cat2');
        $entityManagers[Cat::class]->persist($cat);
        $this->flushAll($entityManagers);

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

        $this->provider->registerEntityManager(
            $this->createEntityManager([
                __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/Fixtures/Entity/Inheritance',
            ]),
            DoctrineProvider::BOTH,
            'default'
        );

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
