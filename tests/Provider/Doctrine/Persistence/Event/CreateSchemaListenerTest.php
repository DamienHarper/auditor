<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Event;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
#[\PHPUnit\Framework\Attributes\Small]
final class CreateSchemaListenerTest extends TestCase
{
    use DefaultSchemaSetupTrait;

    /**
     * @var array<class-string<\DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal>>
     */
    private const CLASSES = [Dog::class, Cat::class];

    public function testCorrectSchemaStandard(): void
    {
        $tableNames = $this->getTables();

        self::assertContains('author', $tableNames);
        self::assertContains('author_audit', $tableNames);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testCorrectSchemaStandard')]
    public function testCorrectSchemaForSingleTableInheritance(): void
    {
        $tableNames = $this->getTables();

        self::assertNotContains('bike_audit', $tableNames);
        self::assertNotContains('car_audit', $tableNames);
        self::assertContains('vehicle', $tableNames);
        self::assertContains('vehicle_audit', $tableNames);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testCorrectSchemaForSingleTableInheritance')]
    public function testCorrectSchemaForJoinedTableInheritance(): void
    {
        $configuration = $this->provider->getConfiguration();
        $entities = $configuration->getEntities();
        $tableNames = $this->getTables();

        self::assertNotContains(Animal::class, $entities);

        foreach (self::CLASSES as $entity) {
            self::assertContains($entities[$entity]['computed_table_name'], $tableNames);
            self::assertContains($entities[$entity]['computed_audit_table_name'], $tableNames);
        }
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Vehicle::class => ['enabled' => true],
            Car::class => ['enabled' => true],
            Bike::class => ['enabled' => true],

            Cat::class => ['enabled' => true],
            Dog::class => ['enabled' => true],

            Author::class => ['enabled' => true],
        ]);
    }

    private function getTables(): array
    {
        $tableNames = [];

        /** @var StorageService $storageService */
        foreach ($this->provider->getStorageServices() as $storageService) {
            $schemaManager = DoctrineHelper::createSchemaManager($storageService->getEntityManager()->getConnection());

            foreach ($schemaManager->listTables() as $table) {
                $tableNames[] = $table->getName();
            }
        }

        return $tableNames;
    }
}
