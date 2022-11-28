<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Event;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

class TableSchemaSubscriber implements EventSubscriber
{
    private DoctrineProvider $provider;

    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $schemaManager = new SchemaManager($this->provider);
            $storageService = $this->provider->getStorageServiceForEntity($classMetadata->getName());

            \assert($storageService instanceof StorageService);
            $platform = $storageService->getEntityManager()->getConnection()->getDatabasePlatform();
            if (!$platform->supportsSchemas()) {
                $classMetadata->setPrimaryTable([
                    'name' => $schemaManager->resolveTableName($classMetadata->getTableName(), $classMetadata->getSchemaName() ?? '', $platform),
                    'schema' => '',
                ]);
            }
        }
    }

    /**
     * @return array<string>
     */
    public function getSubscribedEvents(): array
    {
        return ['loadClassMetadata'];
    }
}
