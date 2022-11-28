<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Service\StorageServiceInterface;
use DH\Auditor\Tests\Provider\Doctrine\Traits\DoctrineProviderTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Exception;

trait SchemaSetupTrait
{
    use DoctrineProviderTrait;

    private DoctrineProvider $provider;

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

    protected function tearDownEntitySchema(EntityManagerInterface $entityManager): void
    {
        $storageConnection = $entityManager->getConnection();
        $schemaManager = DoctrineHelper::createSchemaManager($storageConnection);
        $fromSchema = DoctrineHelper::introspectSchema($schemaManager);
        $toSchema = clone $fromSchema;

        $tables = $fromSchema->getTables();
        foreach ($tables as $table) {
            $toSchema = $toSchema->dropTable($table->getName());
        }

        $sqls = DoctrineHelper::getMigrateToSql($storageConnection, $fromSchema, $toSchema);
        foreach ($sqls as $sql) {
            $statement = $storageConnection->prepare($sql);
            DoctrineHelper::executeStatement($statement);
        }

        try {
            foreach ($schemaManager->listSchemaNames() as $schemaName) {
                if ($storageConnection->getDatabasePlatform()->supportsSchemas() && 'public' !== $schemaName) {
                    $entityManager->getConnection()->executeStatement('DROP SCHEMA IF EXISTS '.$schemaName);
                }
            }
        } catch (Exception) {
        }
    }

    private function setupEntitySchemas(): void
    {
        foreach ($this->provider->getAuditingServices() as $auditingService) {
            $schemaTool = new SchemaTool($auditingService->getEntityManager());
            $this->setUpEntitySchema($schemaTool, $auditingService->getEntityManager());
        }
    }

    private function tearDownEntitySchemas(): void
    {
        foreach ($this->provider->getAuditingServices() as $auditingService) {
            $this->tearDownEntitySchema($auditingService->getEntityManager());
        }
    }

    private function setupAuditSchemas(): void
    {
        $this->setUpAuditSchema();
    }

    private function tearDownAuditSchemas(): void
    {
        foreach ($this->provider->getStorageServices() as $storageService) {
            $this->tearDownAuditSchema($storageService->getEntityManager());
        }
    }

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProvider();
    }

    private function setUpAuditSchema(): void
    {
        $updater = new SchemaManager($this->provider);
        $updater->updateAuditSchema();
    }

    private function tearDownAuditSchema(EntityManagerInterface $entityManager): void
    {
        $storageConnection = $entityManager->getConnection();
        $schemaManager = DoctrineHelper::createSchemaManager($storageConnection);
        $schema = DoctrineHelper::introspectSchema($schemaManager);
        $fromSchema = clone $schema;

        $tables = $schemaManager->listTables();
        foreach ($tables as $table) {
            $regex = '#^'.$this->provider->getConfiguration()->getTablePrefix().'.*'.$this->provider->getConfiguration()->getTableSuffix().'$#';
            if (preg_match($regex, $table->getName())) {
                $schema->dropTable($table->getName());
            }
        }

        $sqls = DoctrineHelper::getMigrateToSql($storageConnection, $fromSchema, $schema);
        foreach ($sqls as $sql) {
            $statement = $storageConnection->prepare($sql);
            DoctrineHelper::executeStatement($statement);
        }

        try {
            foreach ($schemaManager->listSchemaNames() as $schemaName) {
                if ($storageConnection->getDatabasePlatform()->supportsSchemas() && 'public' !== $schemaName) {
                    $entityManager->getConnection()->executeStatement('DROP SCHEMA IF EXISTS '.$schemaName);
                }
            }
        } catch (Exception) {
        }
    }

    private function configureEntities(): void
    {
        // No audited entities configured
    }

    private function setupEntities(): void
    {
    }

    /**
     * @param array<StorageServiceInterface> $storageServices
     */
    private function flushAll(array $storageServices): void
    {
        $done = [];

        foreach ($storageServices as $storageService) {
            $hash = spl_object_hash($storageService);
            if (!\in_array($hash, $done, true)) {
                $storageService->getEntityManager()->flush();
                $done[] = $hash;
            }
        }
    }
}
