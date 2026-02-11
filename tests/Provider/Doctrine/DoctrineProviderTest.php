<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\StorageServiceInterface;
use DH\Auditor\Security\RoleCheckerInterface;
use DH\Auditor\Security\SecurityProviderInterface;
use DH\Auditor\Tests\Fixtures\Provider\AuditNoStorageProvider;
use DH\Auditor\Tests\Fixtures\Provider\StorageNoAuditProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute\AuditedEntity as AuditedEntityWithAttribute;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\DoctrineProviderTrait;
use DH\Auditor\User\User;
use DH\Auditor\User\UserInterface;
use DH\Auditor\User\UserProviderInterface;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class DoctrineProviderTest extends TestCase
{
    use DoctrineProviderTrait;

    public function testRegisterStorageServiceAgainstNoStorageProvider(): void
    {
        $provider = new AuditNoStorageProvider();

        $this->expectException(ProviderException::class);
        $provider->registerStorageService(new StorageService('storageEM_1', $this->createEntityManager()));
    }

    public function testRegisterAuditingServiceAgainstNoAuditingProvider(): void
    {
        $provider = new StorageNoAuditProvider();

        $this->expectException(ProviderException::class);
        $provider->registerAuditingService(new AuditingService('auditingEM_1', $this->createEntityManager()));
    }

    public function testRegisterStorageEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        $this->assertCount(0, $provider->getStorageServices(), 'There is no storage entity manager registered.');

        $provider->registerStorageService(new StorageService('storageEM_1', $this->createEntityManager()));
        $this->assertCount(1, $provider->getStorageServices(), 'There is 1 storage entity manager registered.');

        $provider->registerStorageService(new StorageService('storageEM_2', $this->createEntityManager()));
        $this->assertCount(2, $provider->getStorageServices(), 'There are 2 storage entity managers registered.');

        $this->expectException(ProviderException::class);
        $provider->registerStorageService(new StorageService('storageEM_1', $this->createEntityManager()));
    }

    public function testRegisterAuditingEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        $this->assertCount(0, $provider->getAuditingServices(), 'There is no auditing entity manager registered.');

        $provider->registerAuditingService(new AuditingService('auditingEM_1', $this->createEntityManager()));
        $this->assertCount(1, $provider->getAuditingServices(), 'There is 1 auditing entity manager registered.');

        $provider->registerAuditingService(new AuditingService('auditingEM_2', $this->createEntityManager()));
        $this->assertCount(2, $provider->getAuditingServices(), 'There are 2 auditing entity managers registered.');

        $this->expectException(ProviderException::class);
        $provider->registerAuditingService(new AuditingService('auditingEM_1', $this->createEntityManager()));
    }

    #[Depends('testRegisterAuditingEntityManager')]
    public function testGetAuditingServiceForEntity(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();
        $provider->registerAuditingService(new AuditingService('auditingEM', $this->createEntityManager()));

        $this->expectException(InvalidArgumentException::class);
        $provider->getAuditingServiceForEntity('My\Fake\Entity');
    }

    #[Depends('testRegisterAuditingEntityManager')]
    #[Depends('testRegisterStorageEntityManager')]
    public function testRegisterEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        $this->assertCount(0, $provider->getAuditingServices(), 'There is no auditing entity manager registered.');
        $this->assertCount(0, $provider->getStorageServices(), 'There is no storage entity manager registered.');

        $provider->registerAuditingService(new AuditingService('auditingEM', $this->createEntityManager([
            __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/Fixtures/Entity/Standard/Blog',
        ])));

        $this->assertCount(1, $provider->getAuditingServices(), 'There is 1 auditing entity manager registered.');
        $this->assertCount(0, $provider->getStorageServices(), 'There is no storage entity manager registered.');

        $provider->registerStorageService(new StorageService('storageEM', $this->createEntityManager([
            __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/Fixtures/Entity/Standard/Blog',
        ])));

        $this->assertCount(1, $provider->getAuditingServices(), 'There is 1 auditing entity manager registered.');
        $this->assertCount(1, $provider->getStorageServices(), 'There is 1 storage entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('default', $entityManager));
        $provider->registerStorageService(new StorageService('default', $entityManager));

        $this->assertCount(2, $provider->getAuditingServices(), 'There are 2 auditing entity managers registered.');
        $this->assertCount(2, $provider->getStorageServices(), 'There are 2 storage entity managers registered.');
    }

    public function testIsStorageMapperRequired(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        $this->assertFalse($provider->isStorageMapperRequired(), 'Mapper is not required since there is strictly less than 2 storage entity manager.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('EM1', $entityManager));
        $provider->registerStorageService(new StorageService('EM1', $entityManager));

        $this->assertFalse($provider->isStorageMapperRequired(), 'Mapper is not required since there is strictly less than 2 storage entity manager.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('EM2', $entityManager));
        $provider->registerStorageService(new StorageService('EM2', $entityManager));

        $this->assertTrue($provider->isStorageMapperRequired(), 'Mapper is required since there is more than 2 storage entity managers.');
    }

    public function testSetStorageMapper(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        // register 2 entity managers for storage (at least)
        $entityManager1 = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('EM1', $entityManager1));
        $provider->registerStorageService(new StorageService('EM1', $entityManager1));

        $entityManager2 = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('EM2', $entityManager2));
        $provider->registerStorageService(new StorageService('EM2', $entityManager2));

        $this->assertNull($provider->getConfiguration()->getStorageMapper(), 'StorageMapper is not set.');

        $provider->setStorageMapper(static fn (string $entity, array $storageServices): StorageServiceInterface => 0 === mb_strpos($entity, 'Foo') ? $storageServices['EM1'] : $storageServices['EM2']);
        $this->assertNotNull($provider->getConfiguration()->getStorageMapper(), 'StorageMapper is set.');
        $this->assertIsCallable($provider->getConfiguration()->getStorageMapper(), 'StorageMapper is a callable.');

        $this->assertSame($entityManager1, $provider->getStorageServiceForEntity('Foo1')->getEntityManager(), 'EM1 is used.');
        $this->assertSame($entityManager1, $provider->getStorageServiceForEntity('Foo2')->getEntityManager(), 'EM1 is used.');
        $this->assertSame($entityManager2, $provider->getStorageServiceForEntity('Bar1')->getEntityManager(), 'EM2 is used.');
        $this->assertSame($entityManager2, $provider->getStorageServiceForEntity('Bar2')->getEntityManager(), 'EM2 is used.');

        $provider->setStorageMapper(new FakeStorageMapper());
        $this->assertNotNull($provider->getConfiguration()->getStorageMapper(), 'StorageMapper is set.');
        $this->assertIsCallable($provider->getConfiguration()->getStorageMapper(), 'StorageMapper is a callable.');

        $this->assertSame($entityManager1, $provider->getStorageServiceForEntity('Foo1')->getEntityManager(), 'EM1 is used.');
        $this->assertSame($entityManager1, $provider->getStorageServiceForEntity('Foo2')->getEntityManager(), 'EM1 is used.');
        $this->assertSame($entityManager2, $provider->getStorageServiceForEntity('Bar1')->getEntityManager(), 'EM2 is used.');
        $this->assertSame($entityManager2, $provider->getStorageServiceForEntity('Bar2')->getEntityManager(), 'EM2 is used.');
    }

    public function testCheckStorageMapperThrowsExceptionWhenNoMapperDefined(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        // register 2 entity managers for storage (at least)
        $entityManager1 = $this->createEntityManager();
        $provider->registerStorageService(new StorageService('EM1', $entityManager1));

        $entityManager2 = $this->createEntityManager();
        $provider->registerStorageService(new StorageService('EM2', $entityManager2));

        $this->expectException(ProviderException::class);
        $provider->getStorageServiceForEntity(DummyEntity::class);
    }

    public function testIsRegistered(): void
    {
        // unregistered provider
        $provider = $this->createUnregisteredDoctrineProvider();
        $this->assertFalse($provider->isRegistered(), 'Provider is not registered.');

        $this->expectException(\Exception::class);
        $provider->getAuditor();

        // registered provider
        $provider = $this->createDoctrineProvider();
        $this->assertTrue($provider->isRegistered(), 'Provider is registered.');
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

        $this->assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');
        $this->assertFalse($provider->isAudited(Comment::class), 'Entity "'.Comment::class.'" is not audited.');
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

        $this->assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
        $this->assertTrue($provider->isAuditable(Post::class), 'Entity "'.Post::class.'" is auditable.');
        $this->assertFalse($provider->isAudited(Comment::class), 'Entity "'.Comment::class.'" is not audited.');
        $this->assertFalse($provider->isAuditable(Comment::class), 'Entity "'.Comment::class.'" is not auditable.');
    }

    #[Depends('testIsAudited')]
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

        $this->assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $entities = [
            Post::class => [
                'enabled' => false,
            ],
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        $this->assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    #[Depends('testIsAudited')]
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

        $this->assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

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

        $this->assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    #[Depends('testIsAudited')]
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

        $this->assertTrue($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is audited.');

        $provider->getAuditor()->getConfiguration()->disable();

        $this->assertFalse($provider->isAudited(Post::class), 'Entity "'.Post::class.'" is not audited.');
    }

    #[Depends('testIsAudited')]
    public function testIsAuditedFieldAuditsAnyFieldByDefault(): void
    {
        $entities = [
            Post::class => null,
        ];

        $configuration = $this->createProviderConfiguration([
            'entities' => $entities,
        ]);
        $provider = $this->createDoctrineProvider($configuration);

        $this->assertTrue($provider->isAuditedField(Post::class, 'id'), 'Every field is audited.');
        $this->assertTrue($provider->isAuditedField(Post::class, 'title'), 'Every field is audited.');
        $this->assertTrue($provider->isAuditedField(Post::class, 'created_at'), 'Every field is audited.');
        $this->assertTrue($provider->isAuditedField(Post::class, 'updated_at'), 'Every field is audited.');
    }

    #[Depends('testIsAuditedFieldAuditsAnyFieldByDefault')]
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

        $this->assertTrue($provider->isAuditedField(Post::class, 'id'), 'Field "'.Post::class.'::$id" is audited.');
        $this->assertTrue($provider->isAuditedField(Post::class, 'title'), 'Field "'.Post::class.'::$title" is audited.');
        $this->assertFalse($provider->isAuditedField(Post::class, 'created_at'), 'Field "'.Post::class.'::$created_at" is not audited.');
        $this->assertFalse($provider->isAuditedField(Post::class, 'updated_at'), 'Field "'.Post::class.'::$updated_at" is not audited.');
    }

    #[Depends('testIsAuditedFieldHonorsLocallyIgnoredColumns')]
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

        $this->assertTrue($provider->isAuditedField(Post::class, 'id'), 'Field "'.Post::class.'::$id" is audited.');
        $this->assertTrue($provider->isAuditedField(Post::class, 'title'), 'Field "'.Post::class.'::$title" is audited.');
        $this->assertFalse($provider->isAuditedField(Post::class, 'created_at'), 'Field "'.Post::class.'::$created_at" is not audited.');
        $this->assertFalse($provider->isAuditedField(Post::class, 'updated_at'), 'Field "'.Post::class.'::$updated_at" is not audited.');
    }

    #[Depends('testIsAuditedFieldHonorsLocallyIgnoredColumns')]
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

        $this->assertFalse($provider->isAuditedField(Comment::class, 'id'), 'Field "'.Comment::class.'::$id" is audited but "'.Comment::class.'" entity is not.');
    }

    public function testLoadEntitiesWithAttributesOnly(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/Fixtures/Entity/Attribute',
            ],
            'default'
        );
        $annotationLoader = new AnnotationLoader($entityManager);
        $loaded = $annotationLoader->load();
        $this->assertCount(2, $loaded);

        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($this->createProviderConfiguration(['entities' => $loaded]));
        $provider->registerStorageService(new StorageService('default', $entityManager));
        $provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($provider);

        $this->assertTrue($provider->isAudited(AuditedEntityWithAttribute::class), '"'.AuditedEntityWithAttribute::class.'" is audited.');
        $this->assertTrue($provider->isAuditedField(AuditedEntityWithAttribute::class, 'auditedField'), 'Field "'.AuditedEntityWithAttribute::class.'::$auditedField" is audited.');
        $this->assertFalse($provider->isAuditedField(AuditedEntityWithAttribute::class, 'ignoredField'), 'Field "'.AuditedEntityWithAttribute::class.'::$ignoredField" is ignored.');
        $this->assertFalse($provider->isAuditedField(AuditedEntityWithAttribute::class, 'ignoredProtectedField'), 'Field "'.AuditedEntityWithAttribute::class.'::$ignoredProtectedField" is ignored.');
        $this->assertFalse($provider->isAuditedField(AuditedEntityWithAttribute::class, 'ignoredPrivateField'), 'Field "'.AuditedEntityWithAttribute::class.'::$ignoredPrivateField" is ignored.');
        $this->assertTrue($provider->isAuditedField(AuditedEntityWithAttribute::class, 'id'), 'Field "'.AuditedEntityWithAttribute::class.'::$id" is audited.');
    }

    #[Depends('testIsAuditedHonorsEnabledFlag')]
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

        $this->assertFalse($provider->isAudited(Post::class), 'entity "'.Post::class.'" is not audited.');

        $configuration->enableAuditFor(Post::class);

        $this->assertTrue($provider->isAudited(Post::class), 'entity "'.Post::class.'" is audited.');
    }

    #[Depends('testIsAuditedHonorsEnabledFlag')]
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

        $this->assertTrue($provider->isAudited(Post::class), 'entity "'.Post::class.'" is audited.');

        $configuration->disableAuditFor(Post::class);

        $this->assertFalse($provider->isAudited(Post::class), 'entity "'.Post::class.'" is not audited.');
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

        $this->assertNotSame($entities2, $entities1, 'Configuration::setEntities() replaces previously configured entities.');
    }

    public function testGetConfiguration(): void
    {
        $provider = $this->createDoctrineProvider();

        $this->assertInstanceOf(Configuration::class, $provider->getConfiguration(), 'Configuration is reachable.');
    }

    public function testSetUserProvider(): void
    {
        $provider = $this->createDoctrineProvider();

        $before = $provider->getAuditor()->getConfiguration()->getUserProvider();
        $provider->getAuditor()->getConfiguration()->setUserProvider(new FakeUserProvider());
        $after = $provider->getAuditor()->getConfiguration()->getUserProvider();

        $this->assertIsCallable($after, 'UserProvider is a callable.');
        $this->assertNotSame($before, $after, 'UserProvider has changed.');

        $this->assertInstanceOf(User::class, $after(), 'UserProvider returns a User instance.');
    }

    public function testSetSecurityProvider(): void
    {
        $provider = $this->createDoctrineProvider();

        $before = $provider->getAuditor()->getConfiguration()->getSecurityProvider();
        $provider->getAuditor()->getConfiguration()->setSecurityProvider(new FakeSecurityProvider());
        $after = $provider->getAuditor()->getConfiguration()->getSecurityProvider();

        $this->assertIsCallable($after, 'SecurityProvider is a callable.');
        $this->assertNotSame($before, $after, 'SecurityProvider has changed.');

        $this->assertIsArray($after(), 'SecurityProvider returns an array.');
    }

    public function testSetRoleChecker(): void
    {
        $provider = $this->createDoctrineProvider();

        $before = $provider->getAuditor()->getConfiguration()->getRoleChecker();
        $provider->getAuditor()->getConfiguration()->setRoleChecker(new FakeRoleChecker());
        $after = $provider->getAuditor()->getConfiguration()->getRoleChecker();

        $this->assertIsCallable($after, 'RoleChecker is a callable.');
        $this->assertNotSame($before, $after, 'RoleChecker has changed.');

        $this->assertIsBool($after('', ''), 'RoleChecker returns a bool.');
    }
}

final class FakeStorageMapper
{
    public function __invoke(string $entity, array $storageServices): StorageServiceInterface
    {
        return 0 === mb_strpos($entity, 'Foo') ? $storageServices['EM1'] : $storageServices['EM2'];
    }
}

final class FakeUserProvider implements UserProviderInterface
{
    public function __invoke(): UserInterface
    {
        return new User();
    }
}

final class FakeSecurityProvider implements SecurityProviderInterface
{
    public function __invoke(): array
    {
        return [];
    }
}

final class FakeRoleChecker implements RoleCheckerInterface
{
    public function __invoke(string $entity, string $scope): bool
    {
        return true;
    }
}
