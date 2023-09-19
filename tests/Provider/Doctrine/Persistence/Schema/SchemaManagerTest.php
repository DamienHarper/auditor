<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Schema;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Cat;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Dog;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Bike;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Car;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Small]
final class SchemaManagerTest extends TestCase
{
    use DefaultSchemaSetupTrait;
    use ReflectionTrait;

    // new/alternate structure
    /**
     * @var array<string, array<string, array<string, int|true>|array<string, null|bool>|array<string, null|false|int>|array<string, null|true>|array<string, true>|string>>
     */
    private const ALTERNATE_COLUMNS = [
        'id' => [
            'type' => Types::INTEGER,
            'options' => [
                'autoincrement' => true,
                'unsigned' => true,
            ],
        ],
        'type' => [
            'type' => Types::STRING,
            'options' => [
                'notnull' => true,
                'length' => 10,
            ],
        ],
        'object_id' => [
            'type' => Types::STRING,
            'options' => [
                'notnull' => true,
                'length' => 50,
            ],
        ],
        'discriminator' => [
            'type' => Types::STRING,
            'options' => [
                'default' => null,
                'notnull' => false,
            ],
        ],
        'diffs' => [
            'type' => Types::JSON,
            'options' => [
                'default' => null,
                'notnull' => false,
            ],
        ],
        'blame_id' => [
            'type' => Types::STRING,
            'options' => [
                'default' => null,
                'notnull' => false,
                'unsigned' => true,
            ],
        ],
        'blame_user' => [
            'type' => Types::STRING,
            'options' => [
                'default' => null,
                'notnull' => false,
                'length' => 100,
            ],
        ],
        'created_at' => [
            'type' => Types::DATETIME_IMMUTABLE,
            'options' => [
                'notnull' => true,
            ],
        ],
        'locale' => [
            'type' => Types::STRING,
            'options' => [
                'default' => null,
                'notnull' => false,
                'length' => 5,
            ],
        ],
        'version' => [
            'type' => Types::INTEGER,
            'options' => [
                'default' => null,
                'notnull' => true,
            ],
        ],
    ];

    public function testStorageServicesSetup(): void
    {
        $authorStorageService = $this->provider->getStorageServiceForEntity(Author::class);
        $postStorageService = $this->provider->getStorageServiceForEntity(Post::class);
        $commentStorageService = $this->provider->getStorageServiceForEntity(Comment::class);
        $tagStorageService = $this->provider->getStorageServiceForEntity(Tag::class);

        self::assertSame($authorStorageService, $postStorageService, 'Author and Post use the same storage entity manager.');
        self::assertSame($authorStorageService, $commentStorageService, 'Author and Comment use the same storage entity manager.');
        self::assertSame($authorStorageService, $tagStorageService, 'Author and Tag use the same storage entity manager.');

        $animalStorageService = $this->provider->getStorageServiceForEntity(Animal::class);
        $catStorageService = $this->provider->getStorageServiceForEntity(Cat::class);
        $dogStorageService = $this->provider->getStorageServiceForEntity(Dog::class);
        $vehicleStorageService = $this->provider->getStorageServiceForEntity(Vehicle::class);

        self::assertSame($authorStorageService, $animalStorageService, 'Author and Animal use the same storage entity manager.');
        self::assertSame($animalStorageService, $catStorageService, 'Animal and Cat use the same storage entity manager.');
        self::assertSame($animalStorageService, $dogStorageService, 'Animal and Dog use the same storage entity manager.');
        self::assertSame($animalStorageService, $vehicleStorageService, 'Animal and Vehicle use the same storage entity manager.');
    }

    public function testCreateAuditTable(): void
    {
        $updater = new SchemaManager($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();
        $storageConnection = $entityManager->getConnection();
        $schemaManager = $storageConnection->createSchemaManager();
        $fromSchema = $schemaManager->introspectSchema();

        // at this point, schema is populated but does not contain any audit table
        self::assertNull($this->getTable($schemaManager->listTables(), 'author_audit'), 'author_audit does not exist yet.');

        // create audit table for Author entity
        $this->doConfigureEntities();
        $toSchema = $updater->createAuditTable(Author::class);
        $this->migrate($fromSchema, $toSchema, $entityManager);

        // check audit table has been created
        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');
        self::assertNotNull($authorAuditTable, 'author_audit table has been created.');

        // check expected columns
        $expected = SchemaHelper::getAuditTableColumns();
        foreach (array_keys($expected) as $name) {
            self::assertTrue($authorAuditTable->hasColumn($name), 'audit table has a column named "'.$name.'".');
        }

        // check expected indices
        $expected = SchemaHelper::getAuditTableIndices($authorAuditTable->getName());
        foreach ($expected as $name => $options) {
            if ('primary' === $options['type']) {
                self::assertNotNull($authorAuditTable->getPrimaryKey(), 'audit table has a primary key named "'.$name.'".');
            } else {
                self::assertTrue($authorAuditTable->hasIndex($options['name']), 'audit table has an index named "'.$name.'".');
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Depends('testCreateAuditTable')]
    public function testUpdateAuditTable(): void
    {
        $updater = new SchemaManager($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();
        $storageConnection = $entityManager->getConnection();
        $schemaManager = DoctrineHelper::createSchemaManager($storageConnection);
        $fromSchema = DoctrineHelper::introspectSchema($schemaManager);

        // at this point, schema is populated but does not contain any audit table
        self::assertNull($this->getTable($schemaManager->listTables(), 'author_audit'), 'author_audit does not exist yet.');

        // create audit table for Author entity
        $this->doConfigureEntities();
        $toSchema = $updater->createAuditTable(Author::class);
        $this->migrate($fromSchema, $toSchema, $entityManager);

        $hash = md5('author_audit');
        $alternateIndices = [
            'id' => [
                'type' => 'primary',
            ],
            'type' => [
                'type' => 'index',
                'name' => 'type_'.$hash.'_idx',
            ],
            'object_id' => [
                'type' => 'index',
                'name' => 'object_id_'.$hash.'_idx',
            ],
            'blame_id' => [
                'type' => 'index',
                'name' => 'blame_id_'.$hash.'_idx',
            ],
            'created_at' => [
                'type' => 'index',
                'name' => 'created_at_'.$hash.'_idx',
            ],
        ];

        // apply new structure to author_audit table
        $fromSchema = DoctrineHelper::introspectSchema($schemaManager);
        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');
        $table = $toSchema->getTable('author_audit');
        $columns = $schemaManager->listTableColumns($authorAuditTable->getName());

        $reflectedMethod = $this->reflectMethod($updater, 'processColumns');
        $reflectedMethod->invokeArgs($updater, [$table, $columns, self::ALTERNATE_COLUMNS, $entityManager->getConnection()]);

        $reflectedMethod = $this->reflectMethod($updater, 'processIndices');
        $reflectedMethod->invokeArgs($updater, [$table, $alternateIndices, $entityManager->getConnection()]);

        $this->migrate($fromSchema, $toSchema, $entityManager);

        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');

        // check expected alternate columns
        foreach (array_keys(self::ALTERNATE_COLUMNS) as $name) {
            self::assertTrue($authorAuditTable->hasColumn($name), 'audit table has a column named "'.$name.'".');
        }

        // check expected alternate indices
        foreach ($alternateIndices as $name => $options) {
            if ('primary' === $options['type']) {
                self::assertNotNull($authorAuditTable->getPrimaryKey(), 'audit table has a primary key named "'.$name.'".');
            } else {
                self::assertTrue($authorAuditTable->hasIndex($options['name']), 'audit table has an index named "'.$name.'".');
            }
        }

        // run UpdateManager::updateAuditTable() to bring author_audit to expected structure
        $fromSchema = DoctrineHelper::introspectSchema($schemaManager);

        $toSchema = $updater->updateAuditTable(Author::class);
        $this->migrate($fromSchema, $toSchema, $entityManager);

        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');

        // check expected columns
        foreach (array_keys(SchemaHelper::getAuditTableColumns()) as $name) {
            self::assertTrue($authorAuditTable->hasColumn($name), 'audit table has a column named "'.$name.'".');
        }

        // check expected indices
        foreach (SchemaHelper::getAuditTableIndices($authorAuditTable->getName()) as $name => $options) {
            if ('primary' === $options['type']) {
                self::assertNotNull($authorAuditTable->getPrimaryKey(), 'audit table has a primary key named "'.$name.'".');
            } else {
                self::assertTrue($authorAuditTable->hasIndex($options['name']), 'audit table has an index named "'.$name.'".');
            }
        }
    }

    private function migrate(Schema $fromSchema, Schema $toSchema, EntityManagerInterface $entityManager): void
    {
        $sqls = DoctrineHelper::getMigrateToSql($entityManager->getConnection(), $fromSchema, $toSchema);
        foreach ($sqls as $sql) {
            $statement = $entityManager->getConnection()->prepare($sql);
            $statement->executeStatement();
        }
    }

    private function getTable(array $tables, string $name): ?Table
    {
        foreach ($tables as $table) {
            if ($name === $table->getName()) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Creates a DoctrineProvider with 1 entity manager used both for auditing and storage.
     */
    private function createDoctrineProvider(?Configuration $configuration = null): DoctrineProvider
    {
        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../../Fixtures/Entity/Standard',
            __DIR__.'/../../Fixtures/Entity/Inheritance',
        ]);
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());
        $provider->registerStorageService(new StorageService('default', $entityManager));
        $provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($provider);

        // unregister CreateSchemaListener
        $evm = $entityManager->getEventManager();
        $allListeners = method_exists($evm, 'getAllListeners') ? $evm->getAllListeners() : $evm->getListeners();
        foreach ($allListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof CreateSchemaListener) {
                    $evm->removeEventListener([$event], $listener);
                }
            }
        }

        return $provider;
    }

    private function doConfigureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
            Post::class => ['enabled' => true],
            Comment::class => ['enabled' => true],
            Tag::class => ['enabled' => true],

            Animal::class => ['enabled' => true],
            Cat::class => ['enabled' => true],
            Dog::class => ['enabled' => true],
            Vehicle::class => ['enabled' => true],
            Bike::class => ['enabled' => true],
            Car::class => ['enabled' => true],
        ]);
    }
}
