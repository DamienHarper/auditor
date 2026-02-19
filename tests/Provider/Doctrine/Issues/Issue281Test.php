<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ConnectionTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ProviderConfigurationTrait;
use DH\Auditor\Tests\Traits\AuditorTrait;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for issue #281: entity metadata cannot be loaded with multiple entity managers.
 *
 * In a real Symfony application with multiple entity managers, each EM's metadata driver is
 * configured as a MappingDriverChain where each sub-driver handles only a specific namespace
 * prefix. When two auditing services with disjoint namespace mappings are registered,
 * Configuration::getEntities() iterates all entities for every EM. Calling getClassMetadata()
 * for an entity whose namespace is not covered by the current EM's driver chain causes
 * Doctrine to throw because isTransient() returns true for that class → crash on flush.
 *
 * Fix: before calling getClassMetadata(), skip entities that are not known to the current
 * entity manager (using getMetadataFactory()->getAllMetadata() to build a per-EM lookup).
 *
 * NOTE: the standard test infrastructure uses a plain AttributeDriver (path-based, not
 * namespace-restricted). With AttributeDriver, isTransient() checks #[Entity] attributes
 * only and never throws for a cross-EM entity, so the bug cannot be reproduced there.
 * This test therefore builds two EMs with namespace-restricted MappingDriverChain instances
 * that exactly mimic Symfony's doctrine bundle configuration.
 *
 * @see https://github.com/DamienHarper/auditor/issues/281
 *
 * @internal
 */
#[Small]
final class Issue281Test extends TestCase
{
    use AuditorTrait;
    use ConnectionTrait;
    use ProviderConfigurationTrait;

    /**
     * With two auditing entity managers each mapping a disjoint namespace (Symfony-style
     * MappingDriverChain), getEntities() must not throw a MappingException when iterating
     * the cross-product of EMs × entities.
     *
     * aem1 only knows DH\…\Entity\Standard\Blog\* (Post, …).
     * aem2 only knows DH\…\Entity\Inheritance\* (Animal, …).
     *
     * Without the fix, aem1 calls getClassMetadata(Animal::class) → isTransient() returns
     * true (no matching namespace prefix) → Doctrine throws invalidEntityName.
     * With the fix, aem1 skips Animal and aem2 skips Post; each entity is resolved
     * by the EM that actually owns its namespace.
     */
    public function testGetEntitiesDoesNotCrashWithDisjointNamespaceRestrictedEntityManagers(): void
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($this->createProviderConfiguration());
        $auditor->registerProvider($provider);

        $params = ['driver' => 'pdo_sqlite', 'memory' => true];

        // Storage EM knows all entities (single storage DB for the test).
        $storageEm = $this->createEntityManagerWithNamespace(
            [
                __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Attribute',
                __DIR__.'/../Fixtures/Entity/Standard/Blog',
                __DIR__.'/../Fixtures/Entity/Inheritance',
            ],
            null, // no namespace restriction for storage EM
            $params
        );
        $provider->registerStorageService(new StorageService('sem1', $storageEm));

        // aem1: namespace-restricted to Blog entities (simulates Symfony first EM config).
        $aem1 = $this->createEntityManagerWithNamespace(
            [
                __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Attribute',
                __DIR__.'/../Fixtures/Entity/Standard/Blog',
            ],
            'DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog',
            $params
        );
        $provider->registerAuditingService(new AuditingService('aem1', $aem1));

        // aem2: namespace-restricted to Inheritance entities (simulates Symfony second EM config).
        $aem2 = $this->createEntityManagerWithNamespace(
            [
                __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Attribute',
                __DIR__.'/../Fixtures/Entity/Inheritance',
            ],
            'DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance',
            $params
        );
        $provider->registerAuditingService(new AuditingService('aem2', $aem2));

        // Register entities from both namespaces.
        $provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
            Animal::class => ['enabled' => true],
        ]);

        // Without the fix: aem1 tries getClassMetadata(Animal::class).
        // The MappingDriverChain for aem1 has no driver for the Inheritance namespace →
        // isTransient(Animal::class) = true → Doctrine throws invalidEntityName.
        // With the fix: aem1 skips Animal (not in getAllMetadata()); aem2 skips Post.
        $entities = $provider->getConfiguration()->getEntities();

        $this->assertArrayHasKey(Post::class, $entities, 'Post must be present in the resolved entities.');
        $this->assertArrayHasKey(Animal::class, $entities, 'Animal must be present in the resolved entities.');

        $this->assertNotEmpty(
            $entities[Post::class]['computed_audit_table_name'],
            'Post audit table name must be resolved by aem1.'
        );
        $this->assertNotEmpty(
            $entities[Animal::class]['computed_audit_table_name'],
            'Animal audit table name must be resolved by aem2.'
        );
    }

    /**
     * Creates an EntityManager whose metadata driver is either a plain AttributeDriver
     * (when $namespacePrefix is null) or a MappingDriverChain restricted to a single
     * namespace prefix (when $namespacePrefix is provided), matching Symfony's behaviour.
     */
    private function createEntityManagerWithNamespace(array $paths, ?string $namespacePrefix, array $connParams): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $config->enableNativeLazyObjects(true);

        if (null !== $namespacePrefix) {
            // Replace the driver with a namespace-restricted chain to mimic Symfony's
            // doctrine bundle configuration where each EM only handles its own namespace.
            $chain = new MappingDriverChain();
            $chain->addDriver(new AttributeDriver($paths), $namespacePrefix);
            $config->setMetadataDriverImpl($chain);
        }

        return new EntityManager($this->getConnection('default', $connParams), $config);
    }
}
