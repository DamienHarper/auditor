<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\StorageServiceInterface;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Traits\AuditorTrait;
use DH\Auditor\User\User;

trait DoctrineProviderTrait
{
    use AuditorTrait;
    use EntityManagerInterfaceTrait;
    use ProviderConfigurationTrait;

    /**
     * Creates an unregistered DoctrineProvider with 1 entity manager used both for auditing and storage.
     */
    private function createUnregisteredDoctrineProvider(?Configuration $configuration = null): DoctrineProvider
    {
        return new DoctrineProvider($configuration ?? $this->createProviderConfiguration());
    }

    /**
     * Creates a registered DoctrineProvider with 1 entity manager used both for auditing and storage.
     */
    private function createDoctrineProvider(?Configuration $configuration = null): DoctrineProvider
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());
        $auditor->registerProvider($provider);

        // Entity manager "default" is used both for auditing and storage
        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ]);
        $provider->registerStorageService(new StorageService('default', $entityManager));
        $provider->registerAuditingService(new AuditingService('default', $entityManager));

        // Set a fake user provider that always returns the same User
        $provider->getAuditor()->getConfiguration()->setUserProvider(static fn () => new User('1', 'dark.vador'));

        // Set a fake security provider that always returns the same IP and firewall name
        $provider->getAuditor()->getConfiguration()->setSecurityProvider(static fn () => ['1.2.3.4', 'main']);

        return $provider;
    }

    /**
     * Creates a registered DoctrineProvider with 1 auditing entity manager and 2 storage entity managers with mapper.
     * => 3 different connections (1 for the auditing em and 1 for each storage em).
     */
    private function createDoctrineProviderWith1AEM2SEM(?Configuration $configuration = null): DoctrineProvider
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());

        $db = self::getConnectionParameters();

        // Entity manager "db1" is used for storage only (db1 connection => db1.sqlite)
//        if (!empty($db)) {
//            $db['dbname'] = 'db1';
//        } else {
        $db = [
            'driver' => 'pdo_sqlite',
            'path' => __DIR__.'/../db1.sqlite',
        ];
//        }

        $provider->registerStorageService(new StorageService('db1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard/Blog',
        ], 'db1', $db)));

        // Entity manager "db2" is used for storage only (db2 connection => db2.sqlite)
//        if (!empty($db)) {
//            $db['dbname'] = 'db2';
//        } else {
        $db = [
            'driver' => 'pdo_sqlite',
            'path' => __DIR__.'/../db2.sqlite',
        ];
//        }

        $provider->registerStorageService(new StorageService('db2', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ], 'db2', $db)));

        // Entity manager "aem1" is used for auditing only (default connection => in memory sqlite db)
        $provider->registerAuditingService(new AuditingService('aem1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard/Blog',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ])));

        $auditor->registerProvider($provider);

        // Set a storage mapper that maps entities to db1 or db2
        $provider->getConfiguration()->setStorageMapper(static fn (string $entity, array $storageServices): StorageServiceInterface => \in_array($entity, [Author::class, Post::class, Comment::class, Tag::class], true) ? $storageServices['db1'] : $storageServices['db2']);

        return $provider;
    }

    /**
     * Creates a registered DoctrineProvider with 2 auditing entity managers and 1 storage entity manager.
     * => 1 connection (1 for each of the auditing em and for the storage em).
     */
    private function createDoctrineProviderWith2AEM1SEM(?Configuration $configuration = null): DoctrineProvider
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());

        // Entity manager "sem1" is used for storage only (default connection => in memory sqlite db)
        $provider->registerStorageService(new StorageService('sem1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard/Blog',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ])));

        // Entity manager "aem1" is used for auditing only (default connection => in memory sqlite db)
        $provider->registerAuditingService(new AuditingService('aem1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard/Blog',
        ])));

        // Entity manager "aem2" is used for auditing only (default connection => in memory sqlite db)
        $provider->registerAuditingService(new AuditingService('aem2', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ])));

        $auditor->registerProvider($provider);

        return $provider;
    }

    /**
     * Creates a registered DoctrineProvider with 2 auditing entity managers and 1 storage entity manager.
     * => 2 different connections (1 for the auditing ems and 1 for the storage em).
     */
    private function createDoctrineProviderWith2AEM1SEMAltConnection(?Configuration $configuration = null): DoctrineProvider
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());

//        $db = self::getConnectionParameters();

        // Entity manager "sem1" is used for storage only (db1 connection => db1.sqlite)
//        if (!empty($db)) {
//            $db['dbname'] = 'sem1';
//        } else {
        $db = [
            'driver' => 'pdo_sqlite',
            'path' => __DIR__.'/../sem1.sqlite',
        ];
//        }

        // Entity manager "sem1" is used for storage only (default connection => in memory sqlite db)
        $provider->registerStorageService(new StorageService('sem1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ], 'sem1', $db)));

        // Entity manager "aem1" is used for auditing only (default connection => in memory sqlite db)
        $provider->registerAuditingService(new AuditingService('aem1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard',
        ])));

        // Entity manager "aem2" is used for auditing only (default connection => in memory sqlite db)
        $provider->registerAuditingService(new AuditingService('aem2', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ])));

        $auditor->registerProvider($provider);

        return $provider;
    }
}
