<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Schema;

use DH\Auditor\Exception\InvalidArgumentException;
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
use Doctrine\Persistence\Mapping\Driver\MappingDriver;

/**
 * @see SchemaManagerTest
 */
final readonly class SchemaManager
{
    public function __construct(private DoctrineProvider $provider) {}

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
                $statement->executeStatement();

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
        if ($metadataDriver instanceof MappingDriver) {
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
            $storageSchemaManager = $storageConnection->createSchemaManager();

            $storageSchema = $storageSchemaManager->introspectSchema();
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

        if (!$schema instanceof Schema) {
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
            foreach (SchemaHelper::getAuditTableColumns($connection->getParams()['defaultTableOptions'] ?? []) as $columnName => $struct) {
                if (\in_array($struct['type'], DoctrineHelper::jsonStringTypes(), true)) {
                    $type = $isJsonSupported ? Types::JSON : Types::TEXT;
                } else {
                    $type = $struct['type'];
                }

                $auditTable->addColumn($columnName, $type, $struct['options']);
            }

            // Add indices to audit table
            foreach (SchemaHelper::getAuditTableIndices($auditTablename) as $columnName => $struct) {
                \assert(\is_string($columnName));
                if ('primary' === $struct['type']) {
                    DoctrineHelper::setPrimaryKey($auditTable, $columnName);
                } elseif (isset($struct['name'])) {
                    $auditTable->addIndex(
                        [$columnName],
                        $struct['name'],
                        [],
                        PlatformHelper::isIndexLengthLimited($columnName, $connection) ? ['lengths' => [191]] : []
                    );
                } else {
                    throw new InvalidArgumentException(\sprintf("Missing key 'name' for column '%s'", $columnName));
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
        if (!$schema instanceof Schema) {
            $schema = DoctrineHelper::introspectSchema($schemaManager);
        }

        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        $auditTablename = $this->resolveAuditTableName($entity, $configuration, $connection->getDatabasePlatform());
        \assert(\is_string($auditTablename));
        $table = $schema->getTable($auditTablename);

        // process columns
        $this->processColumns($table, $table->getColumns(), SchemaHelper::getAuditTableColumns($connection->getParams()['defaultTableOptions'] ?? []), $connection);

        // process indices
        $this->processIndices($table, SchemaHelper::getAuditTableIndices($auditTablename), $connection);

        return $schema;
    }

    /**
     * Resolves table name, including namespace/schema.
     */
    public function resolveTableName(string $tableName, string $namespaceName, AbstractPlatform $platform): string
    {
        if ('' === $namespaceName || '0' === $namespaceName) {
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
        $prefix = $configuration->getTablePrefix();
        $suffix = $configuration->getTableSuffix();

        // For performance reasons, we only process the table name with preg_replace_callback and a regex
        // if the entity's table name contains a dot, a quote or a backtick
        if (!str_contains($entityTableName, '.') && !str_contains($entityTableName, '"') && !str_contains($entityTableName, '`')) {
            return $prefix.$entityTableName.$suffix;
        }

        return preg_replace_callback(
            '#^(?:(["`])?([^."`]+)["`]?\.)?(["`]?)([^."`]+)["`]?$#',
            static function (array $matches) use ($prefix, $suffix): string {
                $schemaDelimiter = $matches[1];     // Opening schema quote/backtick
                $schema = $matches[2];              // Captures raw schema name (if exists)
                $tableDelimiter = $matches[3];      // Opening table quote/backtick
                $tableName = $matches[4];           // Captures raw table name

                $newTableName = $prefix.$tableName.$suffix;

                if ('"' === $tableDelimiter || '`' === $tableDelimiter) {
                    $newTableName = $tableDelimiter.$newTableName.$tableDelimiter;
                }

                if ($schema) {
                    if ('"' === $schemaDelimiter || '`' === $schemaDelimiter) {
                        $schema = $schemaDelimiter.$schema.$schemaDelimiter;
                    }

                    return $schema.'.'.$newTableName;
                }

                return $newTableName;
            },
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

                if (\in_array($expectedColumns[$column->getName()]['type'], DoctrineHelper::jsonStringTypes(), true) && !$isJsonSupported) {
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
            \assert(\is_string($columnName));
            if ('primary' === $options['type']) {
                $table->dropPrimaryKey();
                DoctrineHelper::setPrimaryKey($table, $columnName);
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
