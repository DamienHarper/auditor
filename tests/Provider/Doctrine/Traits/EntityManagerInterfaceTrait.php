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

    private function createEntityManager(?array $paths = null, ?EventDispatcherInterface $eventDispatcher = null): EntityManagerInterface
    {
        $config = Setup::createAnnotationMetadataConfiguration(
            $paths ?? $this->fixturesPath,
            true,
            null,
            null,
            false
        );

        DoctrineExtensions::registerAnnotations();

        return EntityManager::create($this->getConnection(), $config);
//        $entityManager = EntityManager::create($connection, $config);
//
//        // get rid of more global state
//        $evm = $connection->getEventManager();
//        foreach ($evm->getListeners() as $event => $listeners) {
//            foreach ($listeners as $listener) {
//                $evm->removeEventListener([$event], $listener);
//            }
//        }
//
//        return $entityManager;
    }
}
