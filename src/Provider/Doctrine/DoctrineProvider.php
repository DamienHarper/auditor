<?php

namespace DH\Auditor\Provider\Doctrine;

use Closure;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Audit\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Audit\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Transaction\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

class DoctrineProvider extends AbstractProvider
{
    public const STORAGE_ONLY = 1;
    public const AUDITING_ONLY = 2;
    public const BOTH = 3;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var EntityManagerInterface[]
     */
    private $storageEntityManagers = [];

    /**
     * @var EntityManagerInterface[]
     */
    private $auditingEntityManagers = [];

    /**
     * @var TransactionManager
     */
    private $transactionManager;

    /**
     * @var Closure
     */
    private $mapper;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->transactionManager = new TransactionManager($this);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Registers an entity manager.
     *
     * @param EntityManagerInterface $entityManager The entity manager to register
     * @param int                    $scope         Scope of the provided entity manager: storage only, auditing only, both (default)
     * @param string                 $name          Name of the entity manager (metadata namespace by default)
     *
     * @throws ProviderException
     *
     * @return DoctrineProvider
     */
    public function registerEntityManager(EntityManagerInterface $entityManager, int $scope = self::BOTH, string $name = 'default'): self
    {
        switch ($scope) {
            case self::STORAGE_ONLY:
                $this->registerStorageEntityManager($entityManager, $name);

                break;
            case self::AUDITING_ONLY:
                $this->registerAuditingEntityManager($entityManager, $name);

                break;
            case self::BOTH:
                $this->registerStorageEntityManager($entityManager, $name);
                $this->registerAuditingEntityManager($entityManager, $name);

                break;
        }

        return $this;
    }

    public function setMappingClosure(Closure $mapper): self
    {
        $this->mapper = $mapper;

        return $this;
    }

    public function getMappingClosure(): ?Closure
    {
        return $this->mapper;
    }

    public function getEntityManagerForEntity(string $entity): EntityManagerInterface
    {
        $this->checkMapper();

        if (null === $this->mapper && 1 === count($this->getStorageEntityManagers())) {
            // No mapper and only 1 storage entity manager
            return array_values($this->storageEntityManagers)[0];
        }

        return $this->mapper->call($this, $entity, $this->getStorageEntityManagers());
    }

    public function persist(LifecycleEvent $event): void
    {
//dump(__METHOD__);
        $payload = $event->getPayload();
        $auditTable = $payload['table'];
        $entity = $payload['entity'];
        unset($payload['table'], $payload['entity']);

        $fields = [
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

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $auditTable,
            implode(', ', array_keys($fields)),
            implode(', ', array_values($fields))
        );

        $entityManager = $this->getEntityManagerForEntity($entity);
        $statement = $entityManager->getConnection()->prepare($query);

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
//dump(__METHOD__.'('.$class.'): '.(\array_key_exists($class, $this->configuration->getEntities())?'true':'false'));

        // is $entity part of audited entities?
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

        $class = DoctrineHelper::getRealClassName($entity);

        // is $entity part of audited entities?
        if (!\array_key_exists($class, $this->configuration->getEntities())) {
            // no => $entity is not audited
            return false;
        }

        $entityOptions = $this->configuration->getEntities()[$class];

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
        if (isset($entityOptions['ignored_columns']) &&
            \in_array($field, $entityOptions['ignored_columns'], true)) {
            // yes => $field is not audited
            return false;
        }

        return true;
    }

    /**
     * Get the value of storageEntityManagers.
     *
     * @return EntityManagerInterface[]
     */
    public function getStorageEntityManagers(): array
    {
        return $this->storageEntityManagers;
    }

    /**
     * Get the value of auditingEntityManagers.
     *
     * @return EntityManagerInterface[]
     */
    public function getAuditingEntityManagers(): array
    {
        return $this->auditingEntityManagers;
    }

    public function supportsStorage(): bool
    {
        return true;
    }

    public function supportsAuditing(): bool
    {
        return true;
    }

    public function registerStorageEntityManager(EntityManagerInterface $entityManager, string $name): self
    {
        if (\array_key_exists($name, $this->storageEntityManagers)) {
            throw new ProviderException(sprintf('A provider named "%s" is already registered.', $name));
        }
        $this->storageEntityManagers[$name] = $entityManager;

        $evm = $entityManager->getEventManager();
        $evm->addEventSubscriber(new CreateSchemaListener($this));

        return $this;
    }

    public function registerAuditingEntityManager(EntityManagerInterface $entityManager, string $name): self
    {
        if (\array_key_exists($name, $this->auditingEntityManagers)) {
            throw new ProviderException(sprintf('A provider named "%s" is already registered.', $name));
        }
        $this->auditingEntityManagers[$name] = $entityManager;

        $evm = $entityManager->getEventManager();
        $evm->addEventSubscriber(new DoctrineSubscriber($this->transactionManager));
        $evm->addEventSubscriber(new SoftDeleteableListener());

        $this->loadAnnotations($entityManager);

        return $this;
    }

    public function isMapperRequired(): bool
    {
        return \count($this->storageEntityManagers) > 1;
    }

    private function loadAnnotations(EntityManagerInterface $entityManager): self
    {
        $annotationLoader = new AnnotationLoader($entityManager);
        $this->configuration->setEntities(array_merge(
            $this->configuration->getEntities(),
            $annotationLoader->load()
        ));

        return $this;
    }

    private function checkMapper(): self
    {
        if (null === $this->mapper && $this->isMapperRequired()) {
            throw new ProviderException('You must provide a mapper function to map audits to storage.');
        }

        return $this;
    }
}
