<?php

declare(strict_types=1);

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
use DH\Auditor\Provider\Doctrine\Persistence\Event\TableSchemaSubscriber;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\ProviderInterface;
use DH\Auditor\Provider\Service\AuditingServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ToolEvents;
use Exception;

/**
 * @see \DH\Auditor\Tests\Provider\Doctrine\DoctrineProviderTest
 */
final class DoctrineProvider extends AbstractProvider
{
    /**
     * @var array<string, string>
     */
    private const FIELDS = [
        'type' => ':type',
        'object_id' => ':object_id',
        'discriminator' => ':discriminator',
        'transaction_hash' => ':transaction_hash',
        'diffs' => ':diffs',
        'blame_id' => ':blame_id',
        'blame_user' => ':blame_user',
        'blame_user_fqdn' => ':blame_user_fqdn',
        'blame_user_firewall' => ':blame_user_firewall',
        'ip' => ':ip',
        'created_at' => ':created_at',
    ];

    private TransactionManager $transactionManager;

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
        $evm->addEventListener([Events::loadClassMetadata], new TableSchemaSubscriber($this));
        $evm->addEventListener([ToolEvents::postGenerateSchemaTable], new CreateSchemaListener($this));
        $evm->addEventSubscriber(new DoctrineSubscriber($this->transactionManager));

        return $this;
    }

    public function isStorageMapperRequired(): bool
    {
        return \count($this->getStorageServices()) > 1;
    }

    public function getAuditingServiceForEntity(string $entity): AuditingService
    {
        foreach ($this->auditingServices as $service) {
            \assert($service instanceof AuditingService);   // helps PHPStan

            try {
                // entity is managed by the entity manager of this service
                $service->getEntityManager()->getClassMetadata($entity)->getTableName();

                return $service;
            } catch (Exception) {
            }
        }

        throw new InvalidArgumentException(sprintf('Auditing service not found for "%s".', $entity));
    }

    public function getStorageServiceForEntity(string $entity): StorageService
    {
        $this->checkStorageMapper();

        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        $storageMapper = $this->configuration->getStorageMapper();

        if (null === $storageMapper || 1 === \count($this->getStorageServices())) {
            // No mapper and only 1 storage entity manager
            /** @var array<StorageService> $services */
            $services = $this->getStorageServices();

            return array_values($services)[0];
        }

        if (\is_string($storageMapper) && class_exists($storageMapper)) {
            $storageMapper = new $storageMapper();
        }

        \assert(\is_callable($storageMapper));   // helps PHPStan

        return $storageMapper($entity, $this->getStorageServices());
    }

    public function persist(LifecycleEvent $event): void
    {
        $payload = $event->getPayload();
        $auditTable = $payload['table'];
        $entity = $payload['entity'];
        unset($payload['table'], $payload['entity']);

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $auditTable,
            implode(', ', array_keys(self::FIELDS)),
            implode(', ', array_values(self::FIELDS))
        );

        /** @var StorageService $storageService */
        $storageService = $this->getStorageServiceForEntity($entity);
        $statement = $storageService->getEntityManager()->getConnection()->prepare($query);

        foreach ($payload as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->executeStatement();

        // let's get the last inserted ID from the database so other providers can use that info
        $payload = $event->getPayload();
        $payload['id'] = (int) $storageService->getEntityManager()->getConnection()->lastInsertId();
        $event->setPayload($payload);
    }

    /**
     * Returns true if $entity is auditable.
     */
    public function isAuditable(object|string $entity): bool
    {
        $class = DoctrineHelper::getRealClassName($entity);
        // is $entity part of audited entities?
        \assert($this->configuration instanceof Configuration);   // helps PHPStan

        // no => $entity is not audited
        return \array_key_exists($class, $this->configuration->getEntities());
    }

    /**
     * Returns true if $entity is audited.
     */
    public function isAudited(object|string $entity): bool
    {
        \assert($this->auditor instanceof \DH\Auditor\Auditor);
        if (!$this->auditor->getConfiguration()->isEnabled()) {
            return false;
        }

        /** @var Configuration $configuration */
        $configuration = $this->configuration;
        $class = DoctrineHelper::getRealClassName($entity);

        // is $entity part of audited entities?
        $entities = $configuration->getEntities();
        if (!\array_key_exists($class, $entities)) {
            // no => $entity is not audited
            return false;
        }

        $entityOptions = $entities[$class];

        if (isset($entityOptions['enabled'])) {
            return (bool) $entityOptions['enabled'];
        }

        return true;
    }

    /**
     * Returns true if $field is audited.
     */
    public function isAuditedField(object|string $entity, string $field): bool
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

        // are columns excluded and is field part of them?
        // yes => $field is not audited
        return !(isset($entityOptions['ignored_columns'])
            && \in_array($field, $entityOptions['ignored_columns'], true));
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

    public function loadAnnotations(EntityManagerInterface $entityManager, array $entities): self
    {
        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        $ormConfiguration = $entityManager->getConfiguration();
        $metadataCache = $ormConfiguration->getMetadataCache();

        $annotationLoader = new AnnotationLoader($entityManager);

        if ($metadataCache instanceof \Psr\Cache\CacheItemPoolInterface) {
            $item = $metadataCache->getItem('__DH_ANNOTATIONS__');
            if (!$item->isHit() || !\is_array($annotationEntities = $item->get())) {
                $annotationEntities = $annotationLoader->load();
                $item->set($annotationEntities);
                $metadataCache->save($item);
            }
        } else {
            $annotationEntities = $annotationLoader->load();
        }

        $this->configuration->setEntities(array_merge($entities, $annotationEntities));

        return $this;
    }

    private function checkStorageMapper(): self
    {
        \assert($this->configuration instanceof Configuration);   // helps PHPStan
        if (null === $this->configuration->getStorageMapper() && $this->isStorageMapperRequired()) {
            throw new ProviderException('You must provide a mapper callback to map audits to storage.');
        }

        return $this;
    }
}
