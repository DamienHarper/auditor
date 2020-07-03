<?php

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
            __DIR__.'/../Fixtures/Entity/Standard/Blog',
        ]);
        $provider->registerStorageService(new StorageService('default', $entityManager));
        $provider->registerAuditingService(new AuditingService('default', $entityManager));

        $provider->getAuditor()->getConfiguration()->setUserProvider(function () {
            return new User(1, 'dark.vador');
        });

        $provider->getAuditor()->getConfiguration()->setSecurityProvider(function () {
            return ['1.2.3.4', 'main'];
        });

        return $provider;
    }

    /**
     * Creates a registered DoctrineProvider with 1 auditing entity managers and 2 storage entity manager with mapper.
     */
    private function createDoctrineProviderWith1AEM2SEM(?Configuration $configuration = null): DoctrineProvider
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());

        // Entity manager "aem1" is used for auditing only
        $provider->registerAuditingService(new AuditingService('aem1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard',
        ])));

        $db = self::getConnectionParameters();

        // Entity manager "db1" is used for storage only
        if (!empty($db)) {
            $db['dbname'] = 'db1';
        } else {
            $db = [
                'driver' => 'pdo_sqlite',
                'path' => __DIR__.'/../db1.sqlite',
            ];
        }

        $provider->registerStorageService(new StorageService('db1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard/Blog',
        ], 'db1', $db)));

        // Entity manager "db2" is used for storage only
        if (!empty($db)) {
            $db['dbname'] = 'db2';
        } else {
            $db = [
                'driver' => 'pdo_sqlite',
                'path' => __DIR__.'/../db2.sqlite',
            ];
        }

        $provider->registerStorageService(new StorageService('db2', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ], 'db2', $db)));

        $auditor->registerProvider($provider);

        $provider->getConfiguration()->setStorageMapper(function (string $entity, array $storageServices): StorageServiceInterface {
            return \in_array($entity, [Author::class, Post::class, Comment::class, Tag::class], true) ? $storageServices['db1'] : $storageServices['db2'];
        });

        return $provider;
    }

    /**
     * Creates a registered DoctrineProvider with 2 auditing entity managers and 1 storage entity manager.
     */
    private function createDoctrineProviderWith2AEM1SEM(?Configuration $configuration = null): DoctrineProvider
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider($configuration ?? $this->createProviderConfiguration());

        // Entity manager "aem1" is used for auditing only
        $provider->registerAuditingService(new AuditingService('aem1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard',
        ])));

        // Entity manager "aem2" is used for auditing only
        $provider->registerAuditingService(new AuditingService('aem2', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Inheritance',
        ])));

        // Entity manager "db1" is used for storage only
        $provider->registerStorageService(new StorageService('db1', $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Entity/Standard',
        ])));

        $auditor->registerProvider($provider);

        return $provider;
    }
}
