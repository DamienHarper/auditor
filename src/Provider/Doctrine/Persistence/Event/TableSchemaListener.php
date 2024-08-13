<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Event;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

final readonly class TableSchemaListener
{
    public function __construct(private DoctrineProvider $provider)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        if (!$classMetadata->isEmbeddedClass && (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName)) {
            $schemaManager = new SchemaManager($this->provider);
            $storageService = $this->provider->getStorageServiceForEntity($classMetadata->getName());

            \assert($storageService instanceof StorageService);
            $platform = $storageService->getEntityManager()->getConnection()->getDatabasePlatform();
            if ($platform instanceof AbstractPlatform && !$platform->supportsSchemas()) {
                $classMetadata->setPrimaryTable([
                    'name' => $schemaManager->resolveTableName($classMetadata->getTableName(), $classMetadata->getSchemaName() ?? '', $platform),
                    'schema' => '',
                ]);
            }
        }
    }
}
