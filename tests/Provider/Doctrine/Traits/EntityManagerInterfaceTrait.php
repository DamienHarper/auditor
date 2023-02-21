<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;

trait EntityManagerInterfaceTrait
{
    use ConnectionTrait;

    private array $fixturesPath = [
        __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
        __DIR__.'/../Fixtures',
    ];

    private function createEntityManager(?array $paths = null, string $connectionName = 'default', ?array $params = null): EntityManagerInterface
    {
        $configuration = DoctrineHelper::createAttributeMetadataConfiguration(
            $paths ?? $this->fixturesPath,
            true,
        );
        $configuration->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));

        $connection = $this->getConnection($connectionName, $params);

        $em = EntityManager::create($connection, $configuration);
        $evm = $em->getEventManager();
        $allListeners = method_exists($evm, 'getAllListeners') ? $evm->getAllListeners() : $evm->getListeners();
        foreach ($allListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                $evm->removeEventListener([$event], $listener);
            }
        }

        return $em;
    }
}
