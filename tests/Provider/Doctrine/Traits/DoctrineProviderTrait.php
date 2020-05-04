<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Traits\AuditorTrait;
use Doctrine\ORM\EntityManagerInterface;

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

        // Entity manager "default" is used both for auditing and storage
        $provider->registerEntityManager($this->createEntityManager(
            [
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Entity/Standard/Blog',
            ]
        ));
        $auditor->registerProvider($provider);

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
        $provider->registerEntityManager(
            $this->createEntityManager(
                [
                    __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                    __DIR__.'/../Fixtures/Entity/Standard',
                ]
            ),
            DoctrineProvider::AUDITING_ONLY,
            'aem1'
        );

        $db = self::getConnectionParameters();

        // Entity manager "sem1" is used for storage only
        if (!empty($db)) {
            $db['dbname'] = 'db1';
        } else {
            $db = [
                'driver' => 'pdo_sqlite',
                'path' => __DIR__.'/../../db1.sqlite',
            ];
        }
        $provider->registerEntityManager(
            $this->createEntityManager(
                [
                    __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                    __DIR__.'/../Fixtures/Entity/Standard/Blog',
                ],
                'db1',
                $db
            ),
            DoctrineProvider::STORAGE_ONLY,
            'sem1'
        );

        // Entity manager "sem2" is used for storage only
        if (!empty($db)) {
            $db['dbname'] = 'db2';
        } else {
            $db = [
                'driver' => 'pdo_sqlite',
                'path' => __DIR__.'/../../db2.sqlite',
            ];
        }
        $provider->registerEntityManager(
            $this->createEntityManager(
                [
                    __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                    __DIR__.'/../Fixtures/Entity/Inheritance',
                ],
                'db2',
                $db
            ),
            DoctrineProvider::STORAGE_ONLY,
            'sem2'
        );

        $auditor->registerProvider($provider);

        $provider->setStorageMapper(function (string $entity, array $storageEntityManagers): EntityManagerInterface {
            return \in_array($entity, [Author::class, Post::class, Comment::class, Tag::class], true) ? $storageEntityManagers['sem1'] : $storageEntityManagers['sem2'];
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
        $provider->registerEntityManager(
            $this->createEntityManager([
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Entity/Standard',
            ]),
            DoctrineProvider::AUDITING_ONLY,
            'aem1'
        );

        // Entity manager "aem2" is used for auditing only
        $provider->registerEntityManager(
            $this->createEntityManager([
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Entity/Inheritance',
            ]),
            DoctrineProvider::AUDITING_ONLY,
            'aem2'
        );

        // Entity manager "sem1" is used for storage only
        $provider->registerEntityManager(
            $this->createEntityManager([
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Entity/Standard',
            ]),
            DoctrineProvider::STORAGE_ONLY,
            'sem1'
        );

        $auditor->registerProvider($provider);

        return $provider;
    }
}
