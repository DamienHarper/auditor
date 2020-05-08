<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
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

    protected function setUp(): void
    {
        // provider with 1 em for both storage and auditing
        $this->createAndInitDoctrineProvider();

        foreach ($this->provider->getStorageEntityManagers() as $name => $entityManager) {
            $schemaTool = new SchemaTool($entityManager);

            $this->setUpEntitySchema($schemaTool, $entityManager);  // setup entity schema only since audited entites are not declared
            $this->configureEntities();                             // declare audited entites
            $this->setUpAuditSchema($schemaTool, $entityManager);   // setup audit schema based on configured audited entities
        }

        foreach ($this->provider->getStorageEntityManagers() as $name => $entityManager) {
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
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($classes);
    }

    protected function tearDownEntitySchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
        $schemaManager = $entityManager->getConnection()->getSchemaManager();
        $fromSchema = $schemaManager->createSchema();
        $toSchema = clone $fromSchema;

        $tables = $fromSchema->getTables();
        foreach ($tables as $table) {
            $toSchema = $toSchema->dropTable($table->getName());
        }

        $sqls = $fromSchema->getMigrateToSql($toSchema, $schemaManager->getDatabasePlatform());
        foreach ($sqls as $sql) {
            $statement = $entityManager->getConnection()->prepare($sql);
            $statement->execute();
        }
    }

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProvider();
    }

    private function setUpAuditSchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
        $updater = new SchemaManager($this->provider);
        $updater->updateAuditSchema();
    }

    private function tearDownAuditSchema(SchemaTool $schemaTool, EntityManagerInterface $entityManager): void
    {
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
            $statement = $entityManager->getConnection()->prepare($sql);
            $statement->execute();
        }
    }

    private function configureEntities(): void
    {
        // No audited entities configured
    }

    private function setupEntities(): void
    {
    }

    private function flushAll(array $entityManagers): void
    {
        $done = [];
        foreach ($entityManagers as $entity => $entityManager) {
            $hash = spl_object_hash($entityManager);
            if (!\in_array($hash, $done, true)) {
                $entityManager->flush();
                $done[] = $hash;
            }
        }
    }
}
