<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\DoctrineExtensions;

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

        // TODO: decide if we keep this
        class_exists(Annotation::class, true);
        DoctrineExtensions::registerAnnotations();

        $connection = $this->getConnection($connectionName, $params);

        $em = EntityManager::create($connection, $configuration);
        $evm = $em->getEventManager();
        foreach ($evm->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $evm->removeEventListener([$event], $listener);
            }
        }

        return $em;
    }
}
