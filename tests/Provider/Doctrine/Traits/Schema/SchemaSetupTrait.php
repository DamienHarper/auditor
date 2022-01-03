<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
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

        // declare audited entites
        $this->configureEntities();

        // setup entity and audit schemas
        $this->setupEntitySchemas();
        $this->setupAuditSchemas();

        // setup (seed) entities
        $this->setupEntities();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // tear down entity and audit schemas
        $this->tearDownEntitySchemas();
        $this->tearDownAuditSchemas();
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

    private function setupEntitySchemas(): void
    {
        /**
         * @var string          $name
         * @var AuditingService $auditingService
         */
        foreach ($this->provider->getAuditingServices() as $name => $auditingService) {
            $schemaTool = new SchemaTool($auditingService->getEntityManager());
            $this->setUpEntitySchema($schemaTool, $auditingService->getEntityManager());
        }
    }

    private function tearDownEntitySchemas(): void
    {
        /**
         * @var string          $name
         * @var AuditingService $auditingService
         */
        foreach ($this->provider->getAuditingServices() as $name => $auditingService) {
            $schemaTool = new SchemaTool($auditingService->getEntityManager());
            $this->tearDownEntitySchema($schemaTool, $auditingService->getEntityManager());
        }
    }

    private function setupAuditSchemas(): void
    {
        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $schemaTool = new SchemaTool($storageService->getEntityManager());
            $this->setUpAuditSchema($schemaTool, $storageService->getEntityManager());
        }
    }

    private function tearDownAuditSchemas(): void
    {
        /**
         * @var string         $name
         * @var StorageService $storageService
         */
        foreach ($this->provider->getStorageServices() as $name => $storageService) {
            $schemaTool = new SchemaTool($storageService->getEntityManager());
            $this->tearDownAuditSchema($schemaTool, $storageService->getEntityManager());
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
