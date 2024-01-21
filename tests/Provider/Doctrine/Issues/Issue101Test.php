<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue101\ChildEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class Issue101Test extends TestCase
{
    use SchemaSetupTrait;

    protected function setUp(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            self::markTestSkipped('PHP > 8.0 is required.');
        }

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

    public function testIssue101(): void
    {
        self::assertTrue($this->provider->isAudited(ChildEntity::class), '"'.ChildEntity::class.'" is audited.');
        self::assertTrue($this->provider->isAuditedField(ChildEntity::class, 'auditedField'), 'Field "'.ChildEntity::class.'::$auditedField" is audited.');
        self::assertFalse($this->provider->isAuditedField(ChildEntity::class, 'ignoredField'), 'Field "'.ChildEntity::class.'::$ignoredField" is ignored.');
        self::assertFalse($this->provider->isAuditedField(ChildEntity::class, 'ignoredProtectedField'), 'Field "'.ChildEntity::class.'::$ignoredProtectedField" is ignored.');
        self::assertFalse($this->provider->isAuditedField(ChildEntity::class, 'ignoredPrivateField'), 'Field "'.ChildEntity::class.'::$ignoredPrivateField" is ignored.');
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/../Fixtures/Issue101',
            ],
            'default',
            null
        );

        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($this->provider);
    }
}
