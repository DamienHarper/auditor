<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
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
        parent::setUp();

        // provider with 1 em for both storage and auditing
        $this->createAndInitDoctrineProvider();

        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $schemaTool = new SchemaTool($storageService->getEntityManager());

            $this->setUpEntitySchema($schemaTool, $storageService->getEntityManager());  // setup entity schema only since audited entites are not declared
            $this->configureEntities();                             // declare audited entites
            $this->setUpAuditSchema($schemaTool, $storageService->getEntityManager());   // setup audit schema based on configured audited entities
        }

        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $this->setupEntities();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $schemaTool = new SchemaTool($storageService->getEntityManager());

            $this->tearDownAuditSchema($schemaTool, $storageService->getEntityManager());
            $this->tearDownEntitySchema($schemaTool, $storageService->getEntityManager());
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

    private function flushAll(array $storageServices): void
    {
        $done = [];
        /**
         * @var string         $entity
         * @var StorageService $storageService
         */
        foreach ($storageServices as $entity => $storageService) {
            $hash = spl_object_hash($storageService);
            if (!\in_array($hash, $done, true)) {
                $storageService->getEntityManager()->flush();
                $done[] = $hash;
            }
        }
    }
}
