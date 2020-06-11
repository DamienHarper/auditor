<?php

namespace DH\Auditor\Tests\Provider\Doctrine;

use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\StorageServiceInterface;
use DH\Auditor\Security\IpProviderInterface;
use DH\Auditor\Security\RoleCheckerInterface;
use DH\Auditor\Tests\Fixtures\Provider\AuditNoStorageProvider;
use DH\Auditor\Tests\Fixtures\Provider\StorageNoAuditProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\DoctrineProviderTrait;
use DH\Auditor\User\User;
use DH\Auditor\User\UserInterface;
use DH\Auditor\User\UserProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
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

        self::assertCount(0, $provider->getStorageServices(), 'There is no storage entity manager registered.');

        $provider->registerStorageService(new StorageService('storageEM_1', $this->createEntityManager()));
        self::assertCount(1, $provider->getStorageServices(), 'There is 1 storage entity manager registered.');

        $provider->registerStorageService(new StorageService('storageEM_2', $this->createEntityManager()));
        self::assertCount(2, $provider->getStorageServices(), 'There are 2 storage entity managers registered.');

        $this->expectException(ProviderException::class);
        $provider->registerStorageService(new StorageService('storageEM_1', $this->createEntityManager()));
    }

    public function testRegisterAuditingEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        self::assertCount(0, $provider->getAuditingServices(), 'There is no auditing entity manager registered.');

        $provider->registerAuditingService(new AuditingService('auditingEM_1', $this->createEntityManager()));
        self::assertCount(1, $provider->getAuditingServices(), 'There is 1 auditing entity manager registered.');

        $provider->registerAuditingService(new AuditingService('auditingEM_2', $this->createEntityManager()));
        self::assertCount(2, $provider->getAuditingServices(), 'There are 2 auditing entity managers registered.');

        $this->expectException(ProviderException::class);
        $provider->registerAuditingService(new AuditingService('auditingEM_1', $this->createEntityManager()));
    }

    /**
     * @depends testRegisterAuditingEntityManager
     * @depends testRegisterStorageEntityManager
     */
    public function testRegisterEntityManager(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        self::assertCount(0, $provider->getAuditingServices(), 'There is no auditing entity manager registered.');
        self::assertCount(0, $provider->getStorageServices(), 'There is no storage entity manager registered.');

        $provider->registerAuditingService(new AuditingService('auditingEM', $this->createEntityManager([
            __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/Fixtures/Entity/Standard/Blog',
        ])));

        self::assertCount(1, $provider->getAuditingServices(), 'There is 1 auditing entity manager registered.');
        self::assertCount(0, $provider->getStorageServices(), 'There is no storage entity manager registered.');

        $provider->registerStorageService(new StorageService('storageEM', $this->createEntityManager([
            __DIR__.'/../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/Fixtures/Entity/Standard/Blog',
        ])));

        self::assertCount(1, $provider->getAuditingServices(), 'There is 1 auditing entity manager registered.');
        self::assertCount(1, $provider->getStorageServices(), 'There is 1 storage entity manager registered.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('default', $entityManager));
        $provider->registerStorageService(new StorageService('default', $entityManager));

        self::assertCount(2, $provider->getAuditingServices(), 'There are 2 auditing entity managers registered.');
        self::assertCount(2, $provider->getStorageServices(), 'There are 2 storage entity managers registered.');

//        $this->expectException(ProviderException::class);
//        $entityManager = $this->createEntityManager();
//        $provider->registerEntityManager($entityManager, DoctrineProvider::AUDITING_ONLY, 'auditingEM');
//
//        $this->expectException(ProviderException::class);
//        $entityManager = $this->createEntityManager();
//        $provider->registerEntityManager($entityManager, DoctrineProvider::STORAGE_ONLY, 'storageEM');
//
//        $this->expectException(ProviderException::class);
//        $entityManager = $this->createEntityManager();
//        $provider->registerEntityManager($entityManager, DoctrineProvider::BOTH, 'EM');
    }

//    public function testRegisterEntityManagerDefaultName(): void
//    {
//        $provider = $this->createUnregisteredDoctrineProvider();
//        $entityManager = $this->createEntityManager();
//        $provider->registerEntityManager($entityManager);
//
//        $expected = ['default' => $entityManager];
//        self::assertSame($expected, $provider->getStorageEntityManagers(), 'Default name is "default".');
//        self::assertSame($expected, $provider->getAuditingEntityManagers(), 'Default name is "default".');
//    }

    public function testIsStorageMapperRequired(): void
    {
        $provider = $this->createUnregisteredDoctrineProvider();

        self::assertFalse($provider->isStorageMapperRequired(), 'Mapper is not required since there is strictly less than 2 storage entity manager.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('EM1', $entityManager));
        $provider->registerStorageService(new StorageService('EM1', $entityManager));
//        $provider->registerEntityManager($entityManager, DoctrineProvider::BOTH, 'EM1');

        self::assertFalse($provider->isStorageMapperRequired(), 'Mapper is not required since there is strictly less than 2 storage entity manager.');

        $entityManager = $this->createEntityManager();
        $provider->registerAuditingService(new AuditingService('EM2', $entityManager));
        $provider->registerStorageService(new StorageService('EM2', $entityManager));
//        $provider->registerEntityManager($entityManager, DoctrineProvider::BOTH, 'EM2');

        self::assertTrue($provider->isStorageMapperRequired(), 'Mapper is required since there is more than 2 storage entity managers.');
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
//        $provider->registerEntityManager($entityManager1, DoctrineProvider::BOTH, 'EM1');
//        $provider->registerEntityManager($entityManager2, DoctrineProvider::BOTH, 'EM2');

        self::assertNull($provider->getConfiguration()->getStorageMapper(), 'Mapping closure is not set.');

        $provider->getConfiguration()->setStorageMapper(function (string $entity, array $storageServices): StorageServiceInterface {
            // Audit records regarding entities starting with "foo" are mapped to "EM1", others are mapped to "EM2"
            return 0 === mb_strpos($entity, 'Foo') ? $storageServices['EM1'] : $storageServices['EM2'];
        });

        self::assertNotNull($provider->getConfiguration()->getStorageMapper(), 'Mapping closure is set.');

        self::assertSame($entityManager1, $provider->getStorageServiceForEntity('Foo1')->getEntityManager(), 'EM1 is used.');
        self::assertSame($entityManager1, $provider->getStorageServiceForEntity('Foo2')->getEntityManager(), 'EM1 is used.');
        self::assertSame($entityManager2, $provider->getStorageServiceForEntity('Bar1')->getEntityManager(), 'EM2 is used.');
        self::assertSame($entityManager2, $provider->getStorageServiceForEntity('Bar2')->getEntityManager(), 'EM2 is used.');
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

        self::assertInstanceOf(Configuration::class, $provider->getConfiguration(), 'Configuration is reachable.');
    }

    public function testSetUserProvider(): void
    {
        $provider = $this->createDoctrineProvider();

        $before = $provider->getConfiguration()->getUserProvider();
        $provider->setUserProvider(new FakeUserProvider());
        $after = $provider->getConfiguration()->getUserProvider();

        self::assertIsCallable($after, 'UserProvider is a Closure.');
        self::assertNotSame($before, $after, 'UserProvider has changed.');

        self::assertInstanceOf(User::class, $after->call($this), 'UserProvider returns a User instance.');
    }

    public function testSetIpProvider(): void
    {
        $provider = $this->createDoctrineProvider();

        $before = $provider->getConfiguration()->getIpProvider();
        $provider->setIpProvider(new FakeIpProvider());
        $after = $provider->getConfiguration()->getIpProvider();

        self::assertIsCallable($after, 'IpProvider is a Closure.');
        self::assertNotSame($before, $after, 'IpProvider has changed.');

        self::assertIsArray($after->call($this), 'IpProvider returns an array.');
    }

    public function testSetRoleChecker(): void
    {
        $provider = $this->createDoctrineProvider();

        $before = $provider->getConfiguration()->getRoleChecker();
        $provider->setRoleChecker(new FakeRoleChecker());
        $after = $provider->getConfiguration()->getRoleChecker();

        self::assertIsCallable($after, 'RoleChecker is a Closure.');
        self::assertNotSame($before, $after, 'RoleChecker has changed.');

        self::assertIsBool($after->call($this, '', ''), 'RoleChecker returns a bool.');
    }
}

class FakeUserProvider implements UserProviderInterface
{
    public function getUser(): ?UserInterface
    {
        return new User();
    }
}

class FakeIpProvider implements IpProviderInterface
{
    public function getClientIpAndFirewall(): array
    {
        return [];
    }
}

class FakeRoleChecker implements RoleCheckerInterface
{
    public function isGranted(string $entity, string $scope): bool
    {
        return true;
    }
}
