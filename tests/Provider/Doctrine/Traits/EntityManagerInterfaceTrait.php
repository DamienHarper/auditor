<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\ProxyFactory;
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

    private function createEntityManager(?EventDispatcherInterface $eventDispatcher = null): EntityManagerInterface
    {
        $config = new Configuration();
        $config->setMetadataCacheImpl(new ArrayCache());
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setProxyDir(__DIR__.'/Proxies');
        $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        $config->setProxyNamespace('DH\Auditor\Tests\Provider\Doctrine\Proxies');
        $config->addFilter('soft-deleteable', SoftDeleteableFilter::class);

        $fixturesPath = \is_array($this->fixturesPath) ? $this->fixturesPath : [$this->fixturesPath];
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver($fixturesPath, false));

        DoctrineExtensions::registerAnnotations();

        $connection = $this->getConnection();
//        $connection = $this->getSharedConnection();

        $entityManager = EntityManager::create($connection, $config);

        // get rid of more global state
        $evm = $connection->getEventManager();
        foreach ($evm->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $evm->removeEventListener([$event], $listener);
            }
        }

        return $entityManager;
    }
}
