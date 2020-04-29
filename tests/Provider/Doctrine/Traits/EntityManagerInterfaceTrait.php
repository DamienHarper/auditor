<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\Setup;
use Gedmo\DoctrineExtensions;
use Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait EntityManagerInterfaceTrait
{
    use ConnectionTrait;

    private $fixturesPath = [
        __DIR__.'/../../../../src/Provider/Doctrine/Audit/Annotation',
        __DIR__.'/../Fixtures',
    ];

    private function createEntityManager(?array $paths = null, string $connectionName = 'default', array $params = null): EntityManagerInterface
    {
        $configuration = Setup::createAnnotationMetadataConfiguration(
            $paths ?? $this->fixturesPath,
            true,
            null,
            null,
            false
        );

        DoctrineExtensions::registerAnnotations();

        $connection = $this->getConnection($connectionName, $params);

        // get rid of previously attached listeners (connections are re-used)
        // this can be safely removed if connections are not shared (freshly created for each test)
        $evm = $connection->getEventManager();
        foreach ($evm->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $evm->removeEventListener([$event], $listener);
            }
        }

        return EntityManager::create($connection, $configuration);
    }
}
