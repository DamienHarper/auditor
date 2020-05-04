<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Event;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CreateSchemaListenerTest extends TestCase
{
    use DefaultSchemaSetupTrait;

    public function testCorrectSchemaStandard(): void
    {
        $tableNames = $this->getTables();

        self::assertContains('author', $tableNames);
        self::assertContains('author_audit', $tableNames);

        self::assertContains('dummy_entity', $tableNames);
        self::assertNotContains('dummy_entity_audit', $tableNames);
    }

    /**
     * @depends testCorrectSchemaStandard
     */
    public function testCorrectSchemaForSingleTableInheritance(): void
    {
        $tableNames = $this->getTables();

        self::assertNotContains('bike_audit', $tableNames);
        self::assertNotContains('car_audit', $tableNames);
        self::assertContains('vehicle', $tableNames);
        self::assertContains('vehicle_audit', $tableNames);
    }

    /**
     * @depends testCorrectSchemaForSingleTableInheritance
     */
    public function testCorrectSchemaForJoinedTableInheritance(): void
    {
        $tableNames = $this->getTables();

        self::assertContains('animal', $tableNames);
        self::assertContains('dog', $tableNames);
        self::assertContains('dog_audit', $tableNames);
        self::assertContains('cat', $tableNames);
        self::assertContains('cat_audit', $tableNames);

        self::assertNotContains('animal_audit', $tableNames);
    }

    protected function setUpEntitySchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
        $this->provider
            ->getConfiguration()
            ->setEntities([
                Car::class => ['enabled' => true],
                Bike::class => ['enabled' => true],

                Cat::class => ['enabled' => true],
                Dog::class => ['enabled' => true],

                Author::class => ['enabled' => true],
            ])
        ;

        $classes = [
            Vehicle::class,
            Car::class,
            Bike::class,

            Cat::class,
            Dog::class,
            Animal::class,

            Author::class,
            DummyEntity::class,
        ];

        $metaClasses = [];
        foreach ($classes as $class) {
            $metaClasses[] = $entityManager->getMetadataFactory()->getMetadataFor($class);
        }

        $schemaTool->createSchema($metaClasses);    // !!! triggers CreateSchemaListener !!!
    }

    private function getTables(): array
    {
        $entityManager = array_values($this->provider->getStorageEntityManagers())[0];
        $schemaManager = $entityManager->getConnection()->getSchemaManager();

        $tableNames = [];

        foreach ($schemaManager->listTables() as $table) {
            $tableNames[] = $table->getName();
        }

        return $tableNames;
    }
}
