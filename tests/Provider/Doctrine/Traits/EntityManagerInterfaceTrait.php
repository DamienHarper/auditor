<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

trait EntityManagerInterfaceTrait
{
    use ConnectionTrait;

    private array $fixturesPath = [
        __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
        __DIR__.'/../Fixtures',
    ];

    private function createEntityManager(?array $paths = null, string $connectionName = 'default', ?array $params = null): EntityManagerInterface
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration($paths ?? $this->fixturesPath, true);
        $configuration->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));

        if (\PHP_VERSION_ID >= 80_400 && method_exists($configuration, 'enableNativeLazyObjects')) {
            // @phpstan-ignore-next-line
            $configuration->enableNativeLazyObjects(true);
        }

        $connection = $this->getConnection($connectionName, $params);

        $em = new EntityManager($connection, $configuration);
        $evm = $em->getEventManager();

        // Attach SoftDeleteableListener to the EventManager
        $evm->addEventListener(Events::onFlush, new SoftDeleteableListener());

        return $em;
    }
}
