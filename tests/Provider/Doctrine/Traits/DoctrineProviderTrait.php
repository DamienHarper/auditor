<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Post;
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
        $provider->registerEntityManager($this->createEntityManager());
        $auditor->registerProvider($provider);

        return $provider;
    }

    /**
     * Creates a registered DoctrineProvider with 2 auditing entity managers and 1 storage entity manager.
     */
    protected function createDoctrineProviderWith2AEM1SEM(?Configuration $configuration = null): DoctrineProvider
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
                __DIR__.'/../Fixtures/Entity/Standard',
            ]),
            DoctrineProvider::AUDITING_ONLY,
            'aem2'
        );

        // Entity manager "sem1" is used for storage only
        $provider->registerEntityManager(
            $this->createEntityManager([
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Entity/Inheritance',
            ]),
            DoctrineProvider::STORAGE_ONLY,
            'sem1'
        );

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
            $this->createEntityManager([
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Entity/Standard',
            ]),
            DoctrineProvider::AUDITING_ONLY,
            'aem1'
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

        // Entity manager "sem2" is used for storage only
        $provider->registerEntityManager(
            $this->createEntityManager([
                __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
                __DIR__.'/../Fixtures/Entity/Inheritance',
            ]),
            DoctrineProvider::STORAGE_ONLY,
            'sem2'
        );

        $auditor->registerProvider($provider);

        $provider->setMappingClosure(function (string $entity, array $storageEntityManagers): EntityManagerInterface {
            return in_array($entity, [Author::class, Post::class]) ? $storageEntityManagers['sem1'] : $storageEntityManagers['sem2'];
        });

        return $provider;
    }
}
