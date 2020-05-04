<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Updater;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Updater\UpdateManager;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UpdateManagerTest extends TestCase
{
    use DefaultSchemaSetupTrait;
    use ReflectionTrait;

    public function testCreateAuditTable(): void
    {
        $updater = new UpdateManager($this->provider);

        $entityManager = $this->provider->getEntityManagerForEntity(Author::class);
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $fromSchema = $schemaManager->createSchema();

        // at this point, schema is populated but does not contain any audit table
        self::assertNull($this->getTable($schemaManager->listTables(), 'author_audit'), 'author_audit does not exist yet.');

        // create audit table for Author entity
        $authorTable = $this->getTable($schemaManager->listTables(), 'author');
        $toSchema = $updater->createAuditTable(Author::class, $authorTable);
        $this->migrate($fromSchema, $toSchema, $entityManager, $schemaManager->getDatabasePlatform());

        // check audit table has been created
        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');
        self::assertNotNull($authorAuditTable, 'author_audit table has been created.');

        // check expected columns
        $expected = SchemaHelper::getAuditTableColumns();
        foreach ($expected as $name => $options) {
            self::assertTrue($authorAuditTable->hasColumn($name), 'audit table has a column named "'.$name.'".');
        }

        // check expected indices
        $expected = SchemaHelper::getAuditTableIndices($authorAuditTable->getName());
        foreach ($expected as $name => $options) {
            if ('primary' === $options['type']) {
                self::assertTrue($authorAuditTable->hasPrimaryKey(), 'audit table has a primary key named "'.$name.'".');
            } else {
                self::assertTrue($authorAuditTable->hasIndex($options['name']), 'audit table has an index named "'.$name.'".');
            }
        }
    }

    /**
     * @depends testCreateAuditTable
     */
    public function testUpdateAuditTable(): void
    {
        $updater = new UpdateManager($this->provider);

        $entityManager = $this->provider->getEntityManagerForEntity(Author::class);
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $fromSchema = $schemaManager->createSchema();

        // at this point, schema is populated but does not contain any audit table
        $authorTable = $this->getTable($schemaManager->listTables(), 'author');

        // create audit table for Author entity
        $toSchema = $updater->createAuditTable(Author::class, $authorTable);
        $this->migrate($fromSchema, $toSchema, $entityManager, $schemaManager->getDatabasePlatform(), true);

        // new/alternate structure
        $alternateColumns = [
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
            'diffs' => [
                'type' => Types::JSON_ARRAY,
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
        $fromSchema = $schemaManager->createSchema();
        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');
        $table = $toSchema->getTable('author_audit');
        $columns = $schemaManager->listTableColumns($authorAuditTable->getName());

        $reflectedMethod = $this->reflectMethod($updater, 'processColumns');
        $reflectedMethod->invokeArgs($updater, [$table, $columns, $alternateColumns]);

        $reflectedMethod = $this->reflectMethod($updater, 'processIndices');
        $reflectedMethod->invokeArgs($updater, [$table, $alternateIndices]);

        $this->migrate($fromSchema, $toSchema, $entityManager, $schemaManager->getDatabasePlatform(), true);

        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');

        // check expected alternate columns
        foreach ($alternateColumns as $name => $options) {
            self::assertTrue($authorAuditTable->hasColumn($name), 'audit table has a column named "'.$name.'".');
        }

        // check expected alternate indices
        foreach ($alternateIndices as $name => $options) {
            if ('primary' === $options['type']) {
                self::assertTrue($authorAuditTable->hasPrimaryKey(), 'audit table has a primary key named "'.$name.'".');
            } else {
                self::assertTrue($authorAuditTable->hasIndex($options['name']), 'audit table has an index named "'.$name.'".');
            }
        }

        // run UpdateManager::updateAuditTable() to bring author_audit to expected structure
        $fromSchema = $schemaManager->createSchema();
        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');

        $toSchema = $updater->updateAuditTable(Author::class, $authorAuditTable);
        $this->migrate($fromSchema, $toSchema, $entityManager, $schemaManager->getDatabasePlatform(), true);

        $authorAuditTable = $this->getTable($schemaManager->listTables(), 'author_audit');

        // check expected columns
        foreach (SchemaHelper::getAuditTableColumns() as $name => $options) {
            self::assertTrue($authorAuditTable->hasColumn($name), 'audit table has a column named "'.$name.'".');
        }

        // check expected indices
        foreach (SchemaHelper::getAuditTableIndices($authorAuditTable->getName()) as $name => $options) {
            if ('primary' === $options['type']) {
                self::assertTrue($authorAuditTable->hasPrimaryKey(), 'audit table has a primary key named "'.$name.'".');
            } else {
                self::assertTrue($authorAuditTable->hasIndex($options['name']), 'audit table has an index named "'.$name.'".');
            }
        }
    }

    private function migrate(Schema $fromSchema, Schema $toSchema, EntityManagerInterface $entityManager, AbstractPlatform $platform, bool $debug = false): void
    {
        $sqls = $fromSchema->getMigrateToSql($toSchema, $platform);
        foreach ($sqls as $sql) {
            $statement = $entityManager->getConnection()->prepare($sql);
            $statement->execute();
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
        $entityManager = $this->createEntityManager();
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());
        $provider->registerEntityManager($entityManager);
        $auditor->registerProvider($provider);

        // unregister CreateSchemaListener
        $evm = $entityManager->getEventManager();
        foreach ($evm->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof CreateSchemaListener) {
                    $evm->removeEventListener([$event], $listener);
                }
            }
        }

        return $provider;
    }
}
