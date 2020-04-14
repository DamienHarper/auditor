<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Transaction\TransactionManager;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\ProxyFactory;
use Gedmo\DoctrineExtensions;
use Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter;

trait EntityManagerInterfaceTrait
{
    use ConnectionTrait;

    private $fixturesPath = [
        __DIR__.'/../../../../src/Provider/Doctrine/Annotation',
        __DIR__.'/../Fixtures',
    ];

    private function createEntityManager(): EntityManagerInterface
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

        return EntityManager::create($connection, $config);
//        $this->setAuditConfiguration($this->createAuditConfiguration([], $this->em));
//        $configuration = $this->getAuditConfiguration();
//
//        $this->transactionManager = new TransactionManager($configuration);
//
//        $configuration->getEventDispatcher()->addSubscriber(new AuditSubscriber($this->transactionManager));
//
//        // get rid of more global state
//        $evm = $connection->getEventManager();
//        foreach ($evm->getListeners() as $event => $listeners) {
//            foreach ($listeners as $listener) {
//                $evm->removeEventListener([$event], $listener);
//            }
//        }
//        $evm->addEventSubscriber(new DoctrineSubscriber($this->transactionManager));
//        $evm->addEventSubscriber(new CreateSchemaListener($this->transactionManager, $this->getReader()));
//        $evm->addEventSubscriber(new Gedmo\SoftDeleteable\SoftDeleteableListener());
//
//        return $this->em;
    }
}
