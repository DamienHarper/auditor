<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Event;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Exception;

class CreateSchemaListener implements EventSubscriber
{
    /**
     * @var DoctrineProvider
     */
    private $provider;

    /**
     * @var array
     */
    private $tablesForEntityManager = [];

    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();

        // check inheritance type and returns if unsupported
        if (!\in_array($metadata->inheritanceType, [
            ClassMetadataInfo::INHERITANCE_TYPE_NONE,
            ClassMetadataInfo::INHERITANCE_TYPE_JOINED,
            ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE,
        ], true)) {
            throw new Exception(sprintf('Inheritance type "%s" is not yet supported', $metadata->inheritanceType));
        }

//        // check reader and manager entity managers and returns if different
//        if ($this->reader->getEntityManager() !== $this->transactionManager->getConfiguration()->getEntityManager()) {
//            return;
//        }

        // check if entity or its children are audited
        if (!$this->provider->isAuditable($metadata->name)) {
            $audited = false;
            if (
                $metadata->rootEntityName === $metadata->name
                && ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE === $metadata->inheritanceType
            ) {
                foreach ($metadata->subClasses as $subClass) {
                    if ($this->provider->isAuditable($subClass)) {
                        $audited = true;
                    }
                }
            }
            if (!$audited) {
                return;
            }
        }

        $storageService = $this->provider->getStorageServiceForEntity($metadata->name);
        \assert($storageService instanceof StorageService);     // helps PHPStan

        // execute schema updates directly if entity manager has no metadata.
        // doctrine:schema:update will exit early, as no mapping is configured.
        $metadatas = $storageService->getEntityManager()->getMetadataFactory()->getAllMetadata();
        if (empty($metadatas)) {
            $connection = $storageService->getEntityManager()->getConnection();
            $storageSchemaManager = $connection->getSchemaManager();
            $fromSchema = $storageSchemaManager->createSchema();
            $sqls = [];

            $updater = new SchemaManager($this->provider);
            $toSchema = $updater->createAuditTable($metadata->name, $eventArgs->getClassTable(), clone $fromSchema);
            $sqls[$storageService->getName()] = $fromSchema->getMigrateToSql($toSchema, $storageSchemaManager->getDatabasePlatform());
            $updater->updateAuditSchema($sqls);
        } else {
            $id = spl_object_hash($storageService->getEntityManager());
            $this->tablesForEntityManager[$id][] = [$metadata->name, $eventArgs->getClassTable()];
        }
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        $id = spl_object_hash($eventArgs->getEntityManager());
        if (!isset($this->tablesForEntityManager[$id])) {
            return;
        }

        $updater = new SchemaManager($this->provider);
        foreach ($this->tablesForEntityManager[$id] as $data) {
            $updater->createAuditTable($data[0], $data[1], $eventArgs->getSchema());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            ToolEvents::postGenerateSchemaTable,
            ToolEvents::postGenerateSchema,
        ];
    }
}
