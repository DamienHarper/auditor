<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Schema;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\PlatformHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;

class SchemaManager
{
    /**
     * @var DoctrineProvider
     */
    private $provider;

    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

    public function updateAuditSchema(?array $sqls = null, ?callable $callback = null): void
    {
        // TODO: FIXME will create the same schema on all connections
        if (null === $sqls) {
            $sqls = $this->getUpdateAuditSchemaSql();
        }

        /** @var StorageService[] $storageServices */
        $storageServices = $this->provider->getStorageServices();
        foreach ($sqls as $name => $queries) {
            foreach ($queries as $index => $sql) {
                $statement = $storageServices[$name]->getEntityManager()->getConnection()->prepare($sql);
                $statement->execute();

                if (null !== $callback) {
                    $callback([
                        'total' => \count($sqls),
                        'current' => $index,
                    ]);
                }
            }
        }
    }

    /**
     * Returns an array of audit table names indexed by entity FQN.
     * Only auditable entities are considered.
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function getAuditableTableNames(EntityManagerInterface $entityManager): array
    {
        $metadataDriver = $entityManager->getConfiguration()->getMetadataDriverImpl();
        $entities = [];
        if (null !== $metadataDriver) {
            $entities = $metadataDriver->getAllClassNames();
        }
        $audited = [];
        foreach ($entities as $entity) {
            if ($this->provider->isAuditable($entity)) {
                $audited[$entity] = $entityManager->getClassMetadata($entity)->getTableName();
            }
        }
        ksort($audited);

        return $audited;
    }

    public function getUpdateAuditSchemaSql(): array
    {
        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        /** @var StorageService[] $storageServices */
        $storageServices = $this->provider->getStorageServices();

        // auditable entities by storage entity manager
        $repository = [];

        // Collect auditable entities from auditing storage managers
        /** @var AuditingService[] $auditingServices */
        $auditingServices = $this->provider->getAuditingServices();
        foreach ($auditingServices as $name => $auditingService) {
            $classes = $this->getAuditableTableNames($auditingService->getEntityManager());
            // Populate the auditable entities repository
            foreach ($classes as $entity => $tableName) {
                $storageService = $this->provider->getStorageServiceForEntity($entity);
                $key = array_search($storageService, $this->provider->getStorageServices(), true);
                if (!isset($repository[$key])) {
                    $repository[$key] = [];
                }
                $repository[$key][$entity] = $tableName;
            }
        }
        $findEntityByTablename = static function (string $tableName) use ($repository): ?string {
            foreach ($repository as $emName => $map) {
                $result = array_search($tableName, $map, true);
                if (false !== $result) {
                    return (string) $result;
                }
            }

            return null;
        };

        // Compute and collect SQL queries
        $sqls = [];
        foreach ($repository as $name => $classes) {
            $storageSchemaManager = $storageServices[$name]->getEntityManager()->getConnection()->getSchemaManager();

            $storageSchema = $storageSchemaManager->createSchema();
            $fromSchema = clone $storageSchema;
            $tables = $storageSchema->getTables();
            foreach ($tables as $table) {
                if (\in_array($table->getName(), array_values($repository[$name]), true)) {
                    // table is the one of an auditable entity

                    /** @var string $auditTablename */
                    $auditTablename = preg_replace(
                        sprintf('#^([^\.]+\.)?(%s)$#', preg_quote($table->getName(), '#')),
                        sprintf(
                            '$1%s$2%s',
                            preg_quote($configuration->getTablePrefix(), '#'),
                            preg_quote($configuration->getTableSuffix(), '#')
                        ),
                        $table->getName()
                    );
                    if ($storageSchema->hasTable($auditTablename)) {
                        // Audit table exists, let's update it if needed
                        $this->updateAuditTable($findEntityByTablename($table->getName()), $storageSchema->getTable($auditTablename), $storageSchema);
                    } else {
                        // Audit table does not exists, let's create it
                        $this->createAuditTable($findEntityByTablename($table->getName()), $table, $storageSchema);
                    }
                }
            }

            $sqls[$name] = $fromSchema->getMigrateToSql($storageSchema, $storageSchemaManager->getDatabasePlatform());
        }

        return $sqls;
    }

    /**
     * Creates an audit table.
     */
    public function createAuditTable(string $entity, Table $table, ?Schema $schema = null): Schema
    {
        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity($entity);
        $connection = $storageService->getEntityManager()->getConnection();

        if (null === $schema) {
            $schemaManager = $connection->getSchemaManager();
            $schema = $schemaManager->createSchema();
        }

        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        $auditTablename = preg_replace(
            sprintf('#^([^\.]+\.)?(%s)$#', preg_quote($table->getName(), '#')),
            sprintf(
                '$1%s$2%s',
                preg_quote($configuration->getTablePrefix(), '#'),
                preg_quote($configuration->getTableSuffix(), '#')
            ),
            $table->getName()
        );

        if (null !== $auditTablename && !$schema->hasTable($auditTablename)) {
            $auditTable = $schema->createTable($auditTablename);

            // Add columns to audit table
            $isJsonSupported = PlatformHelper::isJsonSupported($connection);
            foreach (SchemaHelper::getAuditTableColumns() as $columnName => $struct) {
                if (DoctrineHelper::getDoctrineType('JSON') === $struct['type'] && $isJsonSupported) {
                    $type = DoctrineHelper::getDoctrineType('TEXT');
                } else {
                    $type = $struct['type'];
                }

                $auditTable->addColumn($columnName, $type, $struct['options']);
            }

            // Add indices to audit table
            foreach (SchemaHelper::getAuditTableIndices($auditTablename) as $columnName => $struct) {
                if ('primary' === $struct['type']) {
                    $auditTable->setPrimaryKey([$columnName]);
                } else {
                    $auditTable->addIndex(
                        [$columnName],
                        $struct['name'],
                        [],
                        PlatformHelper::isIndexLengthLimited($columnName, $connection) ? ['lengths' => [191]] : []
                    );
                }
            }
        }

        return $schema;
    }

    /**
     * Ensures an audit table's structure is valid.
     *
     * @throws SchemaException
     */
    public function updateAuditTable(string $entity, Table $table, ?Schema $schema = null): Schema
    {
        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity($entity);
        $connection = $storageService->getEntityManager()->getConnection();

        $schemaManager = $connection->getSchemaManager();
        if (null === $schema) {
            $schema = $schemaManager->createSchema();
        }

        $table = $schema->getTable($table->getName());
        $columns = $schemaManager->listTableColumns($table->getName());

        // process columns
        $this->processColumns($table, $columns, SchemaHelper::getAuditTableColumns(), $connection);

        // process indices
        $this->processIndices($table, SchemaHelper::getAuditTableIndices($table->getName()), $connection);

        return $schema;
    }

    private function processColumns(Table $table, array $columns, array $expectedColumns, Connection $connection): void
    {
        $processed = [];

        $isJsonSupported = PlatformHelper::isJsonSupported($connection);
        foreach ($columns as $column) {
            if (\array_key_exists($column->getName(), $expectedColumns)) {
                // column is part of expected columns
                $table->dropColumn($column->getName());

                if (DoctrineHelper::getDoctrineType('JSON') === $expectedColumns[$column->getName()]['type'] && $isJsonSupported) {
                    $type = DoctrineHelper::getDoctrineType('TEXT');
                } else {
                    $type = $expectedColumns[$column->getName()]['type'];
                }

                $table->addColumn($column->getName(), $type, $expectedColumns[$column->getName()]['options']);
            } else {
                // column is not part of expected columns so it has to be removed
                $table->dropColumn($column->getName());
            }

            $processed[] = $column->getName();
        }

        foreach ($expectedColumns as $columnName => $options) {
            if (!\in_array($columnName, $processed, true)) {
                // expected column in not part of concrete ones so it's a new column, we need to add it
                if (DoctrineHelper::getDoctrineType('JSON') === $options['type'] && $isJsonSupported) {
                    $type = DoctrineHelper::getDoctrineType('TEXT');
                } else {
                    $type = $options['type'];
                }

                $table->addColumn($columnName, $options['type'], $options['options']);
            }
        }
    }

    /**
     * @throws SchemaException
     */
    private function processIndices(Table $table, array $expectedIndices, Connection $connection): void
    {
        foreach ($expectedIndices as $columnName => $options) {
            if ('primary' === $options['type']) {
                $table->dropPrimaryKey();
                $table->setPrimaryKey([$columnName]);
            } else {
                if ($table->hasIndex($options['name'])) {
                    $table->dropIndex($options['name']);
                }
                $table->addIndex(
                    [$columnName],
                    $options['name'],
                    [],
                    PlatformHelper::isIndexLengthLimited($columnName, $connection) ? ['lengths' => [191]] : []
                );
            }
        }
    }
}
