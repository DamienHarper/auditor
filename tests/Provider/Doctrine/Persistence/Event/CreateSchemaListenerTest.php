<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Event;

use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class CreateSchemaListenerTest extends TestCase
{
    use DefaultSchemaSetupTrait;

    public function testCorrectSchemaStandard(): void
    {
        $tableNames = $this->getTables();

        self::assertContains('author', $tableNames);
        self::assertContains('author_audit', $tableNames);
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

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
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
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $schemaManager = $storageService->getEntityManager()->getConnection()->getSchemaManager();

            foreach ($schemaManager->listTables() as $table) {
                $tableNames[] = $table->getName();
            }
        }

        return $tableNames;
    }
}
