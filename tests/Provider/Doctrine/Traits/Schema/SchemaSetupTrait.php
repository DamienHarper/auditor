<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Updater\UpdateManager;
use DH\Auditor\Tests\Provider\Doctrine\Traits\DoctrineProviderTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait SchemaSetupTrait
{
    use DoctrineProviderTrait;

    /**
     * @var DoctrineProvider
     */
    private $provider;

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProvider();
    }

    protected function setUp(): void
    {
        // provider with 1 em for both storage and auditing
        $this->createAndInitDoctrineProvider();

        foreach ($this->provider->getStorageEntityManagers() as $name => $entityManager) {
            $schemaTool = new SchemaTool($entityManager);

            $this->configureEntities();
            $this->setUpEntitySchema($schemaTool, $entityManager);
            $this->setUpAuditSchema($schemaTool, $entityManager);
            $this->setupEntities();
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->provider->getStorageEntityManagers() as $name => $entityManager) {
            $schemaTool = new SchemaTool($entityManager);

            $this->tearDownAuditSchema($schemaTool, $entityManager);
            $this->tearDownEntitySchema($schemaTool, $entityManager);
        }
    }

    protected function setUpEntitySchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
//dump(__METHOD__);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($classes);
    }

    protected function tearDownEntitySchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
//dump(__METHOD__);
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $fromSchema = $schemaManager->createSchema();
        $toSchema = clone $fromSchema;

        $tables = $fromSchema->getTables();
//dump('tables:', array_map(static function($t) {return $t->getName();}, $tables));
        foreach ($tables as $table) {
            $toSchema = $toSchema->dropTable($table->getName());
        }

        $sql = $fromSchema->getMigrateToSql($toSchema, $schemaManager->getDatabasePlatform());
        foreach ($sql as $query) {
//            try {
//dump($query);
            $statement = $entityManager->getConnection()->prepare($query);
            $statement->execute();
//            } catch (\Exception $e) {
//            }
        }
    }

    private function setUpAuditSchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
//dump(__METHOD__);
        $updater = new UpdateManager($this->provider);
        $updater->updateAuditSchema();
    }

    private function tearDownAuditSchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
//dump(__METHOD__);
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $schema = $schemaManager->createSchema();
        $fromSchema = clone $schema;

        $tables = $schemaManager->listTables();
        foreach ($tables as $table) {
            $regex = '#^'.$this->provider->getConfiguration()->getTablePrefix().'.*'.$this->provider->getConfiguration()->getTableSuffix().'$#';
            if (preg_match($regex, $table->getName())) {
                $schema->dropTable($table->getName());
            }
        }

        $sqls = $fromSchema->getMigrateToSql($schema, $schemaManager->getDatabasePlatform());

        foreach ($sqls as $sql) {
//            try {
//dump($sql);
            $statement = $entityManager->getConnection()->prepare($sql);
            $statement->execute();
//            } catch (\Exception $e) {
//                // something bad happened here :/
//            }
        }
    }

    private function configureEntities(): void
    {
        // No audited entities configured
    }

    private function setupEntities(): void
    {
    }
}
