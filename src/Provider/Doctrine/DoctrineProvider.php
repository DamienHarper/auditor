<?php

namespace DH\Auditor\Provider\Doctrine;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\ConfigurationInterface;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\ProviderInterface;
use DH\Auditor\Provider\Service\AuditingServiceInterface;
use DH\Auditor\Provider\Service\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class DoctrineProvider extends AbstractProvider
{
    /**
     * @var TransactionManager
     */
    private $transactionManager;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
        $this->transactionManager = new TransactionManager($this);

        \assert($this->configuration instanceof Configuration);    // helps PHPStan
        $this->configuration->setProvider($this);
    }

    public function registerAuditingService(AuditingServiceInterface $service): ProviderInterface
    {
        parent::registerAuditingService($service);

        \assert($service instanceof AuditingService);    // helps PHPStan
        $entityManager = $service->getEntityManager();
        $evm = $entityManager->getEventManager();

        // Register subscribers
        $evm->addEventSubscriber(new CreateSchemaListener($this));
        $evm->addEventSubscriber(new DoctrineSubscriber($this->transactionManager));

        return $this;
    }

    public function isStorageMapperRequired(): bool
    {
        return \count($this->getStorageServices()) > 1;
    }

    public function getAuditingServiceForEntity(string $entity): AuditingServiceInterface
    {
        foreach ($this->auditingServices as $name => $service) {
            \assert($service instanceof AuditingService);   // helps PHPStan

            try {
                // entity is managed by the entity manager of this service
                $service->getEntityManager()->getClassMetadata($entity)->getTableName();

                return $service;
            } catch (Exception $e) {
            }
        }

        throw new InvalidArgumentException(sprintf('Auditing service not found for "%s".', $entity));
    }

    public function getStorageServiceForEntity(string $entity): StorageServiceInterface
    {
        $this->checkStorageMapper();

        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        $storageMapper = $this->configuration->getStorageMapper();

        if (null === $storageMapper || 1 === \count($this->getStorageServices())) {
            // No mapper and only 1 storage entity manager
            return array_values($this->getStorageServices())[0];
        }

        return $storageMapper($entity, $this->getStorageServices());
    }

    public function persist(LifecycleEvent $event): void
    {
        $payload = $event->getPayload();
        $auditTable = $payload['table'];
        $entity = $payload['entity'];
        unset($payload['table'], $payload['entity']);

        $fields = array_combine(array_keys($payload), array_map(function ($x) {return ":{$x}"; }, array_keys($payload)));
        \assert(\is_array($fields));    // helps PHPStan

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $auditTable,
            implode(', ', array_keys($fields)),
            implode(', ', array_values($fields))
        );

        /** @var StorageService $storageService */
        $storageService = $this->getStorageServiceForEntity($entity);
        $statement = $storageService->getEntityManager()->getConnection()->prepare($query);

        foreach ($payload as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();
    }

    /**
     * Returns true if $entity is auditable.
     *
     * @param object|string $entity
     */
    public function isAuditable($entity): bool
    {
        $class = DoctrineHelper::getRealClassName($entity);
        // is $entity part of audited entities?
        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        if (!\array_key_exists($class, $this->configuration->getEntities())) {
            // no => $entity is not audited
            return false;
        }

        return true;
    }

    /**
     * Returns true if $entity is audited.
     *
     * @param object|string $entity
     */
    public function isAudited($entity): bool
    {
        if (!$this->auditor->getConfiguration()->isEnabled()) {
            return false;
        }

        /** @var Configuration $configuration */
        $configuration = $this->configuration;
        $class = DoctrineHelper::getRealClassName($entity);

        // is $entity part of audited entities?
        if (!\array_key_exists($class, $configuration->getEntities())) {
            // no => $entity is not audited
            return false;
        }

        $entityOptions = $configuration->getEntities()[$class];

        if (null === $entityOptions) {
            // no option defined => $entity is audited
            return true;
        }

        if (isset($entityOptions['enabled'])) {
            return (bool) $entityOptions['enabled'];
        }

        return true;
    }

    /**
     * Returns true if $field is audited.
     *
     * @param object|string $entity
     */
    public function isAuditedField($entity, string $field): bool
    {
        // is $field is part of globally ignored columns?
        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        if (\in_array($field, $this->configuration->getIgnoredColumns(), true)) {
            // yes => $field is not audited
            return false;
        }

        // is $entity audited?
        if (!$this->isAudited($entity)) {
            // no => $field is not audited
            return false;
        }

        $class = DoctrineHelper::getRealClassName($entity);
        $entityOptions = $this->configuration->getEntities()[$class];

        if (null === $entityOptions) {
            // no option defined => $field is audited
            return true;
        }

        // are columns excluded and is field part of them?
        if (isset($entityOptions['ignored_columns'])
            && \in_array($field, $entityOptions['ignored_columns'], true)) {
            // yes => $field is not audited
            return false;
        }

        return true;
    }

    public function supportsStorage(): bool
    {
        return true;
    }

    public function supportsAuditing(): bool
    {
        return true;
    }

    public function setStorageMapper(callable $storageMapper): void
    {
        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        $this->configuration->setStorageMapper($storageMapper);
    }

    public function loadAnnotations(EntityManagerInterface $entityManager, ?array $entities = null): self
    {
        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        $annotationLoader = new AnnotationLoader($entityManager);
        $this->configuration->setEntities(array_merge(
            null === $entities ? $this->configuration->getEntities() : $entities,
            $annotationLoader->load()
        ));

        return $this;
    }

    private function checkStorageMapper(): self
    {
        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        if (null === $this->configuration->getStorageMapper() && $this->isStorageMapperRequired()) {
            throw new ProviderException('You must provide a mapper function to map audits to storage.');
        }

//        if (null === $this->getStorageMapper() && 1 === count($this->getStorageServices())) {
//            // No mapper and only 1 storage entity manager
//            return array_values($this->storageServices)[0];
//        }

        return $this;
    }
}
