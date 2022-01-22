<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Gedmo\DoctrineExtensions;

trait EntityManagerInterfaceTrait
{
    use ConnectionTrait;

    private array $fixturesPath = [
        __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
        __DIR__.'/../Fixtures',
    ];

    private function createEntityManager(?array $paths = null, string $connectionName = 'default', ?array $params = null, bool $usePHP8Attributes = false): EntityManagerInterface
    {
        if ($usePHP8Attributes) {
            $configuration = Setup::createAttributeMetadataConfiguration(
                $paths ?? $this->fixturesPath,
                true,
                null,
                null
            );
        } else {
            $configuration = Setup::createAnnotationMetadataConfiguration(
                $paths ?? $this->fixturesPath,
                true,
                null,
                null,
                false
            );
        }

        class_exists(Annotation::class, true);
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
