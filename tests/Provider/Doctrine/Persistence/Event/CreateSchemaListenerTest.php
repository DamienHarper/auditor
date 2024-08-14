<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Event;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHConnection;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHMiddleware;
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
use DH\Auditor\Provider\Doctrine\Service\DoctrineService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(CreateSchemaListener::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(AbstractProvider::class)]
#[CoversClass(AnnotationLoader::class)]
#[CoversClass(DoctrineSubscriber::class)]
#[CoversClass(TransactionHydrator::class)]
#[CoversClass(TransactionManager::class)]
#[CoversClass(TransactionProcessor::class)]
#[CoversClass(\DH\Auditor\Provider\Doctrine\Configuration::class)]
#[CoversClass(DoctrineProvider::class)]
#[CoversClass(TableSchemaListener::class)]
#[CoversClass(DoctrineHelper::class)]
#[CoversClass(PlatformHelper::class)]
#[CoversClass(SchemaHelper::class)]
#[CoversClass(SchemaManager::class)]
#[CoversClass(DoctrineService::class)]
#[CoversClass(AbstractService::class)]
#[CoversClass(DHConnection::class)]
#[CoversClass(DHDriver::class)]
#[CoversClass(DHMiddleware::class)]
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

        $this->assertContains('author', $tableNames);
        $this->assertContains('author_audit', $tableNames);
    }

    #[Depends('testCorrectSchemaStandard')]
    public function testCorrectSchemaForSingleTableInheritance(): void
    {
        $tableNames = $this->getTables();

        $this->assertNotContains('bike_audit', $tableNames);
        $this->assertNotContains('car_audit', $tableNames);
        $this->assertContains('vehicle', $tableNames);
        $this->assertContains('vehicle_audit', $tableNames);
    }

    #[Depends('testCorrectSchemaForSingleTableInheritance')]
    public function testCorrectSchemaForJoinedTableInheritance(): void
    {
        $configuration = $this->provider->getConfiguration();
        $entities = $configuration->getEntities();
        $tableNames = $this->getTables();

        $this->assertNotContains(Animal::class, $entities);

        foreach (self::CLASSES as $entity) {
            $this->assertContains($entities[$entity]['computed_table_name'], $tableNames);
            $this->assertContains($entities[$entity]['computed_audit_table_name'], $tableNames);
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
