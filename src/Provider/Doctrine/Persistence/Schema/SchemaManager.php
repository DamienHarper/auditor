<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Schema;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\PlatformHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Persistence\Schema\SchemaManagerTest;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see SchemaManagerTest
 */
class SchemaManager
{
    private DoctrineProvider $provider;

    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

    public function updateAuditSchema(?array $sqls = null, ?callable $callback = null): void
    {
        if (null === $sqls) {
            $sqls = $this->getUpdateAuditSchemaSql();
        }

        /** @var StorageService[] $storageServices */
        $storageServices = $this->provider->getStorageServices();
        foreach ($sqls as $name => $queries) {
            $connection = $storageServices[$name]->getEntityManager()->getConnection();
            foreach ($queries as $index => $sql) {
                $statement = $connection->prepare($sql);
                DoctrineHelper::executeStatement($statement);

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

    public function collectAuditableEntities(): array
    {
        // auditable entities by storage entity manager
        $repository = [];

        /** @var AuditingService[] $auditingServices */
        $auditingServices = $this->provider->getAuditingServices();
        foreach ($auditingServices as $auditingService) {
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

        return $repository;
    }

    public function getUpdateAuditSchemaSql(): array
    {
        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        /** @var StorageService[] $storageServices */
        $storageServices = $this->provider->getStorageServices();

        // Collect auditable entities from auditing entity managers
        $repository = $this->collectAuditableEntities();

        // Compute and collect SQL queries
        $sqls = [];
        foreach ($repository as $name => $classes) {
            $storageConnection = $storageServices[$name]->getEntityManager()->getConnection();
            $storageSchemaManager = DoctrineHelper::createSchemaManager($storageConnection);
            $storageSchema = DoctrineHelper::introspectSchema($storageSchemaManager);
            $fromSchema = clone $storageSchema;

            $processed = [];
            foreach ($classes as $entityFQCN => $tableName) {
                if (!\in_array($entityFQCN, $processed, true)) {
                    /** @var string $auditTablename */
                    $auditTablename = $this->resolveAuditTableName($entityFQCN, $configuration, $storageConnection->getDatabasePlatform());

                    if ($storageSchema->hasTable($auditTablename)) {
                        // Audit table exists, let's update it if needed
                        $this->updateAuditTable($entityFQCN, $storageSchema);
                    } else {
                        // Audit table does not exists, let's create it
                        $this->createAuditTable($entityFQCN, $storageSchema);
                    }

                    $processed[] = $entityFQCN;
                }
            }
            $sqls[$name] = DoctrineHelper::getMigrateToSql($storageConnection, $fromSchema, $storageSchema);
        }

        return $sqls;
    }

    /**
     * Creates an audit table.
     *
     * @throws Exception
     */
    public function createAuditTable(string $entity, ?Schema $schema = null): Schema
    {
        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity($entity);
        $connection = $storageService->getEntityManager()->getConnection();

        if (null === $schema) {
            $schemaManager = DoctrineHelper::createSchemaManager($connection);
            $schema = DoctrineHelper::introspectSchema($schemaManager);
        }

        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();
        $auditTablename = $this->resolveAuditTableName($entity, $configuration, $connection->getDatabasePlatform());

        if (null !== $auditTablename && !$schema->hasTable($auditTablename)) {
            $auditTable = $schema->createTable($auditTablename);

            // Add columns to audit table
            $isJsonSupported = PlatformHelper::isJsonSupported($connection);
            foreach (SchemaHelper::getAuditTableColumns() as $columnName => $struct) {
                if (Types::JSON === $struct['type'] && !$isJsonSupported) {
                    $type = Types::TEXT;
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
     * @throws Exception
     */
    public function updateAuditTable(string $entity, ?Schema $schema = null): Schema
    {
        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity($entity);
        $connection = $storageService->getEntityManager()->getConnection();

        $schemaManager = DoctrineHelper::createSchemaManager($connection);
        if (null === $schema) {
            $schema = DoctrineHelper::introspectSchema($schemaManager);
        }

        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        $auditTablename = $this->resolveAuditTableName($entity, $configuration, $connection->getDatabasePlatform());
        \assert(\is_string($auditTablename));
        $table = $schema->getTable($auditTablename);

        // process columns
        $this->processColumns($table, $table->getColumns(), SchemaHelper::getAuditTableColumns(), $connection);

        // process indices
        $this->processIndices($table, SchemaHelper::getAuditTableIndices($auditTablename), $connection);

        return $schema;
    }

    /**
     * Resolves table name, including namespace/schema.
     */
    public function resolveTableName(string $tableName, string $namespaceName, AbstractPlatform $platform): string
    {
        if (empty($namespaceName)) {
            $prefix = '';
        } elseif (!$platform->supportsSchemas()) {
            $prefix = $namespaceName.'__';
        } else {
            $prefix = $namespaceName.'.';
        }

        return $prefix.$tableName;
    }

    /**
     * Resolves audit table name, including namespace/schema.
     */
    public function resolveAuditTableName(string $entity, Configuration $configuration, AbstractPlatform $platform): ?string
    {
        $entities = $configuration->getEntities();
        $entityOptions = $entities[$entity];
        $tablename = $this->resolveTableName($entityOptions['table_name'], $entityOptions['audit_table_schema'], $platform);

        return $this->computeAuditTablename($tablename, $configuration);
    }

    /**
     * Computes audit table name **without** namespace/schema.
     */
    public function computeAuditTablename(string $entityTableName, Configuration $configuration): ?string
    {
        return preg_replace(
            sprintf('#^([^\.]+\.)?(%s)$#', preg_quote($entityTableName, '#')),
            sprintf(
                '$1%s$2%s',
                preg_quote($configuration->getTablePrefix(), '#'),
                preg_quote($configuration->getTableSuffix(), '#')
            ),
            $entityTableName
        );
    }

    private function processColumns(Table $table, array $columns, array $expectedColumns, Connection $connection): void
    {
        $processed = [];

        $isJsonSupported = PlatformHelper::isJsonSupported($connection);
        foreach ($columns as $column) {
            if (\array_key_exists($column->getName(), $expectedColumns)) {
                // column is part of expected columns
                $table->dropColumn($column->getName());

                if (Types::JSON === $expectedColumns[$column->getName()]['type'] && !$isJsonSupported) {
                    $type = Types::TEXT;
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
