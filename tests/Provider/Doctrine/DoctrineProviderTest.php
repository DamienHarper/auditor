<?php

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\DoctrineProviderTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DoctrineProviderTest extends TestCase
{
    use DoctrineProviderTrait;

    public function testRegisterStorageEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        self::assertCount(0, $provider->getStorageEntityManagers(), 'There is no storage entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerStorageEntityManager($entityManager, 'storageEM_1');

        self::assertCount(1, $provider->getStorageEntityManagers(), 'There is 1 storage entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerStorageEntityManager($entityManager, 'storageEM_2');

        self::assertCount(2, $provider->getStorageEntityManagers(), 'There are 2 storage entity managers registered.');

        $this->expectException(ProviderException::class);
        $entityManager = $this->createEntityManager();
        $provider->registerStorageEntityManager($entityManager, 'storageEM_1');
    }

    public function testRegisterAuditingEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        self::assertCount(0, $provider->getAuditingEntityManagers(), 'There is no auditing entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingEntityManager($entityManager, 'auditingEM_1');

        self::assertCount(1, $provider->getAuditingEntityManagers(), 'There is 1 auditing entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingEntityManager($entityManager, 'auditingEM_2');

        self::assertCount(2, $provider->getAuditingEntityManagers(), 'There are 2 auditing entity managers registered.');

        $this->expectException(ProviderException::class);
        $entityManager = $this->createEntityManager();
        $provider->registerAuditingEntityManager($entityManager, 'auditingEM_1');
    }

    /**
     * @depends testRegisterAuditingEntityManager
     * @depends testRegisterStorageEntityManager
     */
    public function testRegisterEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        self::assertCount(0, $provider->getAuditingEntityManagers(), 'There is no auditing entity manager registered.');
        self::assertCount(0, $provider->getStorageEntityManagers(), 'There is no storage entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::AUDITING_ONLY, 'auditingEM');

        self::assertCount(1, $provider->getAuditingEntityManagers(), 'There is 1 auditing entity manager registered.');
        self::assertCount(0, $provider->getStorageEntityManagers(), 'There is no storage entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::STORAGE_ONLY, 'storageEM');

        self::assertCount(1, $provider->getAuditingEntityManagers(), 'There is 1 auditing entity manager registered.');
        self::assertCount(1, $provider->getStorageEntityManagers(), 'There is 1 storage entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::BOTH, 'EM');

        self::assertCount(2, $provider->getAuditingEntityManagers(), 'There are 2 auditing entity managers registered.');
        self::assertCount(2, $provider->getStorageEntityManagers(), 'There are 2 storage entity managers registered.');

        $this->expectException(ProviderException::class);
        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::AUDITING_ONLY, 'auditingEM');

        $this->expectException(ProviderException::class);
        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::STORAGE_ONLY, 'storageEM');

        $this->expectException(ProviderException::class);
        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::BOTH, 'EM');
    }

    public function testRegisterEntityManagerDefaultName(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();
        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager);

        $expected = ['default' => $entityManager];
        self::assertSame($expected, $provider->getStorageEntityManagers(), 'Default name is "default".');
        self::assertSame($expected, $provider->getAuditingEntityManagers(), 'Default name is "default".');
    }

    public function testIsStorageMapperRequired(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        self::assertFalse($provider->isStorageMapperRequired(), 'Mapper is not required since there is strictly less than 2 storage entity manager.');

        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::BOTH, 'EM1');

        self::assertFalse($provider->isStorageMapperRequired(), 'Mapper is not required since there is strictly less than 2 storage entity manager.');

        $entityManager = $this->createEntityManager();
        $provider->registerEntityManager($entityManager, DoctrineProvider::BOTH, 'EM2');

        self::assertTrue($provider->isStorageMapperRequired(), 'Mapper is required since there is more than 2 storage entity managers.');
    }

    public function testSetStorageMapper(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        // register 2 entity managers for storage (at least)
        $entityManager1 = $this->createEntityManager();
        $entityManager2 = $this->createEntityManager();
        $provider->registerEntityManager($entityManager1, DoctrineProvider::BOTH, 'EM1');
        $provider->registerEntityManager($entityManager2, DoctrineProvider::BOTH, 'EM2');

        self::assertNull($provider->getStorageMapper(), 'Mapping closure is not set.');

        $provider->setStorageMapper(function (string $entity, array $storageEntityManagers): EntityManagerInterface {
            // Audit records regarding entities starting with "foo" are mapped to "EM1", others are mapped to "EM2"
            return 0 === strpos($entity, 'Foo') ? $storageEntityManagers['EM1'] : $storageEntityManagers['EM2'];
        });

        self::assertNotNull($provider->getStorageMapper(), 'Mapping closure is set.');

        self::assertSame($entityManager1, $provider->getEntityManagerForEntity('Foo1'), 'EM1 is used.');
        self::assertSame($entityManager1, $provider->getEntityManagerForEntity('Foo2'), 'EM1 is used.');
        self::assertSame($entityManager2, $provider->getEntityManagerForEntity('Bar1'), 'EM2 is used.');
        self::assertSame($entityManager2, $provider->getEntityManagerForEntity('Bar2'), 'EM2 is used.');
    }

    public function testIsRegistered(): void
    {
        // unregistered provider
        $provider = $this->createUnregisteredDoctrineProvider();
        self::assertFalse($provider->isRegistered(), 'Provider is not registered.');

        // registered provider
        $provider = $this->createDoctrineProvider();
        self::assertTrue($provider->isRegistered(), 'Provider is registered.');
    }

    public function testIsAudited(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');
        self::assertFalse($provider->isAudited(Comment::class), 'Entity "'.Comment::class.'" is not audited.');
    }

    public function testIsAuditable(): void
    {
        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
        self::assertTrue($provider->isAuditable(Post::class), 'Entity "'.Post::class.'" is auditable.');
        self::assertFalse($provider->isAudited(Comment::class), 'Entity "'.Comment::class.'" is not audited.');
        self::assertFalse($provider->isAuditable(Comment::class), 'Entity "'.Comment::class.'" is not auditable.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedHonorsEnabledFlag(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedWhenAuditIsEnabled(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);
        $provider->getAuditor()->getConfiguration()->enable();

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);
        $provider->getAuditor()->getConfiguration()->enable();

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedWhenAuditIsDisabled(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $provider->getAuditor()->getConfiguration()->disable();

        self::assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    /**
     * @depends testIsAudited
     */
    public function testIsAuditedFieldAuditsAnyFieldByDefault(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAuditedField(Post::class, 'id'), 'Every field is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'title'), 'Every field is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'created_at'), 'Every field is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'updated_at'), 'Every field is audited.');
    }

    /**
     * @depends testIsAuditedFieldAuditsAnyFieldByDefault
     */
    public function testIsAuditedFieldHonorsLocallyIgnoredColumns(): void
    {
        $entities = [
            Post::class => [
                'ignored_columns' => [
                    'created_at',
                    'updated_at',
                ],
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAuditedField(Post::class, 'id'), 'Field "'.Post::class.'::$id" is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'title'), 'Field "'.Post::class.'::$title" is audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'created_at'), 'Field "'.Post::class.'::$created_at" is not audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'updated_at'), 'Field "'.Post::class.'::$updated_at" is not audited.');
    }

    /**
     * @depends testIsAuditedFieldHonorsLocallyIgnoredColumns
     */
    public function testIsAuditedFieldHonorsGloballyIgnoredColumns(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'ignored_columns' => [
                'created_at',
                'updated_at',
            ],
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAuditedField(Post::class, 'id'), 'Field "'.Post::class.'::$id" is audited.');
        self::assertTrue($provider->isAuditedField(Post::class, 'title'), 'Field "'.Post::class.'::$title" is audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'created_at'), 'Field "'.Post::class.'::$created_at" is not audited.');
        self::assertFalse($provider->isAuditedField(Post::class, 'updated_at'), 'Field "'.Post::class.'::$updated_at" is not audited.');
    }

    /**
     * @depends testIsAuditedFieldHonorsLocallyIgnoredColumns
     */
    public function testIsAuditedFieldReturnsFalseIfEntityIsNotAudited(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'ignored_columns' => [
                'created_at',
                'updated_at',
            ],
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAuditedField(Comment::class, 'id'), 'Field "'.Comment::class.'::$id" is audited but "'.Comment::class.'" entity is not.');
    }

    /**
     * @depends testIsAuditedHonorsEnabledFlag
     */
    public function testEnableAuditFor(): void
    {
        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertFalse($provider->isAudited(Post::class), 'entity "'.Post::class.'" is not audited.');

        $configuration->enableAuditFor(Post::class);

        self::assertTrue($provider->isAudited(Post::class), 'entity "'.Post::class.'" is audited.');
    }

    /**
     * @depends testIsAuditedHonorsEnabledFlag
     */
    public function testDisableAuditFor(): void
    {
        $entities = [
            Post::class => [
                'enabled' => true,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        self::assertTrue($provider->isAudited(Post::class), 'entity "'.Post::class.'" is audited.');

        $configuration->disableAuditFor(Post::class);

        self::assertFalse($provider->isAudited(Post::class), 'entity "'.Post::class.'" is not audited.');
    }

    public function testSetEntities(): void
    {
        $configuration = $this->createProviderConfiguration([
            'entities' => [Tag::class => null],
        ]);
        $entities1 = $configuration->getEntities();

        $entities = [
            Post::class => null,
            Comment::class => null,
        ];

        $configuration->setEntities($entities);
        $entities2 = $configuration->getEntities();

        self::assertNotSame($entities2, $entities1, 'Configuration::setEntities() replaces previously configured entities.');
    }

    public function testGetConfiguration(): void
    {
        $provider = $this->createDoctrineProvider();

        self:self::assertInstanceOf(Configuration::class, $provider->getConfiguration(), 'Configuration is reachable.');
    }
}
