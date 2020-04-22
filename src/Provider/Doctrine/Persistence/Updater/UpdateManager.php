<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Updater;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class UpdateManager
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
//dump(__METHOD__);
        $storageEntityManagers = $this->provider->getStorageEntityManagers();

        // TODO: FIXME will create the same schema on all connections
        if (null === $sqls) {
            $sqls = $this->getUpdateAuditSchemaSql();
        }

//dump('Run SQL queries');
        foreach ($sqls as $entityManagerName => $queries) {
            foreach ($queries as $index => $sql) {
//dump(__METHOD__.' => '.$sql);
                $statement = $storageEntityManagers[$entityManagerName]->getConnection()->prepare($sql);
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
     *
     * @return array
     */
    public function getAuditableTableNames(EntityManagerInterface $entityManager): array
    {
//dump(__METHOD__);
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
//dump(__METHOD__);
        $storageEntityManagers = $this->provider->getStorageEntityManagers();

        // schema A1 et schema A2 source d'audit
        // schema S stockage

        $repository = [];   // auditable entities by storage entity manager

        // Collect auditable entities from auditing storage managers
        $auditingEntityManagers = $this->provider->getAuditingEntityManagers();
        foreach ($auditingEntityManagers as $name => $auditingEntityManager) {
//dump('Collecting auditable entities from auditing storage manager "'.$name.'"');
            $classes = $this->getAuditableTableNames($auditingEntityManager);
//dump('classes:', $classes);

            // Populate the auditable entities repository
            foreach ($classes as $entity => $tableName) {
                $em = $this->provider->getEntityManagerForEntity($entity);
                $key = array_search($em, $this->provider->getStorageEntityManagers(), true);
                if (!isset($repository[$key])) {
                    $repository[$key] = [];
                }
                $repository[$key][$entity] = $tableName;
            }
        }
//dump('repository:', $repository);
        $repositoryFindByTablename = static function(string $tableName) use ($repository): ?string {
            foreach ($repository as $emName => $map) {
                return array_search($tableName, $map, true);
            }
        };

        // Compute and collect SQL queries
        $sqls = [];
        foreach ($repository as $name => $classes) {
//dump('Processing auditable entities from storage entity manager "'.$name.'"');
            $storageSchemaManager = $storageEntityManagers[$name]->getConnection()->getSchemaManager();

            $storageSchema = $storageSchemaManager->createSchema();
            $fromSchema = clone $storageSchema;
            $tables = $storageSchema->getTables();
//dump('Current tables from storage entity manager "'.$name.'"', array_map(static function($t) {return $t->getName();}, $tables));
            foreach ($tables as $table) {
                if (\in_array($table->getName(), array_values($repository[$name]), true)) {
                    // table is the one of an auditable entity

                    $auditTablename = preg_replace(
                        sprintf('#^([^\.]+\.)?(%s)$#', preg_quote($table->getName(), '#')),
                        sprintf(
                            '$1%s$2%s',
                            preg_quote($this->provider->getConfiguration()->getTablePrefix(), '#'),
                            preg_quote($this->provider->getConfiguration()->getTableSuffix(), '#')
                        ),
                        $table->getName()
                    );
//dump('table "'.$table->getName().'" => audit table "'.$auditTablename.'"');

                    if ($storageSchema->hasTable($auditTablename)) {
                        // Audit table does not exists, let's create it
//dump('table "'.$auditTablename.'" already exists => update it');
                        $this->updateAuditTable($repositoryFindByTablename($table->getName()), $storageSchema->getTable($auditTablename), $storageSchema);
                    } else {
                        // Audit table exists, let's update it if needed
//dump('table "'.$auditTablename.'" does not exist => create it');
                        $this->createAuditTable($repositoryFindByTablename($table->getName()), $table, $storageSchema);
                    }
                }
            }

            $sqls[$name] = $fromSchema->getMigrateToSql($storageSchema, $storageSchemaManager->getDatabasePlatform());
        }
//dump($sqls);

        return $sqls;
    }

    /**
     * Creates an audit table.
     */
    public function createAuditTable(string $entity, Table $table, ?Schema $schema = null): Schema
    {
//dump(__METHOD__.'('.$table->getName().')');
        if (null === $schema) {
            $entityManager = $this->provider->getEntityManagerForEntity($entity);
            $schemaManager = $entityManager->getConnection()->getSchemaManager();
            $schema = $schemaManager->createSchema();
        }

        $auditTablename = preg_replace(
            sprintf('#^([^\.]+\.)?(%s)$#', preg_quote($table->getName(), '#')),
            sprintf(
                '$1%s$2%s',
                preg_quote($this->provider->getConfiguration()->getTablePrefix(), '#'),
                preg_quote($this->provider->getConfiguration()->getTableSuffix(), '#')
            ),
            $table->getName()
        );

        if (null !== $auditTablename && !$schema->hasTable($auditTablename)) {
            $auditTable = $schema->createTable($auditTablename);

            // Add columns to audit table
            foreach (SchemaHelper::getAuditTableColumns() as $columnName => $struct) {
                $auditTable->addColumn($columnName, $struct['type'], $struct['options']);
            }

            // Add indices to audit table
            foreach (SchemaHelper::getAuditTableIndices($auditTablename) as $columnName => $struct) {
                if ('primary' === $struct['type']) {
                    $auditTable->setPrimaryKey([$columnName]);
                } else {
                    $auditTable->addIndex([$columnName], $struct['name']);
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
//dump(__METHOD__.'('.$table->getName().')');
        $entityManager = $this->provider->getEntityManagerForEntity($entity);
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        if (null === $schema) {
            $schema = $schemaManager->createSchema();
        }

        $table = $schema->getTable($table->getName());
        $columns = $schemaManager->listTableColumns($table->getName());

        // process columns
        $this->processColumns($table, $columns, SchemaHelper::getAuditTableColumns());

        // process indices
        $this->processIndices($table, SchemaHelper::getAuditTableIndices($table->getName()));

        return $schema;
    }

    private function processColumns(Table $table, array $columns, array $expectedColumns): void
    {
        $processed = [];

        foreach ($columns as $column) {
            if (\array_key_exists($column->getName(), $expectedColumns)) {
                // column is part of expected columns
                $table->dropColumn($column->getName());
                $table->addColumn($column->getName(), $expectedColumns[$column->getName()]['type'], $expectedColumns[$column->getName()]['options']);
            } else {
                // column is not part of expected columns so it has to be removed
                $table->dropColumn($column->getName());
            }

            $processed[] = $column->getName();
        }

        foreach ($expectedColumns as $columnName => $options) {
            if (!\in_array($columnName, $processed, true)) {
                // expected column in not part of concrete ones so it's a new column, we need to add it
                $table->addColumn($columnName, $options['type'], $options['options']);
            }
        }
    }

    /**
     * @throws SchemaException
     */
    private function processIndices(Table $table, array $expectedIndices): void
    {
        foreach ($expectedIndices as $columnName => $options) {
            if ('primary' === $options['type']) {
                $table->dropPrimaryKey();
                $table->setPrimaryKey([$columnName]);
            } else {
                if ($table->hasIndex($options['name'])) {
                    $table->dropIndex($options['name']);
                }
                $table->addIndex([$columnName], $options['name']);
            }
        }
    }
}
