<?php

namespace DH\Auditor\Provider\Doctrine;

use DH\Auditor\Provider\ConfigurationInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string
     */
    private $tablePrefix;

    /**
     * @var string
     */
    private $tableSuffix;

    /**
     * @var array
     */
    private $ignoredColumns;

    /**
     * @var array
     */
    private $entities = [];

    /**
     * @var array
     */
    private $storageServices = [];

    /**
     * @var array
     */
    private $auditingServices = [];

    /**
     * @var bool
     */
    private $isViewerEnabled;

    /**
     * @var callable
     */
    private $storageMapper;

    /**
     * @var callable
     */
    private $userProvider;

    /**
     * @var callable
     */
    private $roleChecker;

    /**
     * @var callable
     */
    private $securityProvider;

    public function __construct(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $config = $resolver->resolve($options);

        $this->tablePrefix = $config['table_prefix'];
        $this->tableSuffix = $config['table_suffix'];
        $this->ignoredColumns = $config['ignored_columns'];

        if (isset($config['entities']) && !empty($config['entities'])) {
            // use entity names as array keys for easier lookup
            foreach ($config['entities'] as $auditedEntity => $entityOptions) {
                $this->entities[$auditedEntity] = $entityOptions;
            }
        }

        $this->storageServices = $config['storage_services'];
        $this->auditingServices = $config['auditing_services'];
        $this->storageMapper = $config['storage_mapper'];
        $this->roleChecker = $config['role_checker'];
        $this->userProvider = $config['user_provider'];
        $this->securityProvider = $config['security_provider'];
        $this->isViewerEnabled = $config['viewer'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // https://symfony.com/doc/current/components/options_resolver.html
        $resolver
            ->setDefaults([
                'table_prefix' => '',
                'table_suffix' => '_audit',
                'ignored_columns' => [],
                'entities' => [],
                'storage_services' => [],
                'auditing_services' => [],
                'storage_mapper' => null,
                'role_checker' => null,
                'user_provider' => null,
                'security_provider' => null,
                'viewer' => true,
            ])
            ->setAllowedTypes('table_prefix', 'string')
            ->setAllowedTypes('table_suffix', 'string')
            ->setAllowedTypes('ignored_columns', 'array')
            ->setAllowedTypes('entities', 'array')
            ->setAllowedTypes('storage_services', 'array')
            ->setAllowedTypes('auditing_services', 'array')
            ->setAllowedTypes('storage_mapper', ['null', 'string', 'callable'])
            ->setAllowedTypes('role_checker', ['null', 'string', 'callable'])
            ->setAllowedTypes('user_provider', ['null', 'string', 'callable'])
            ->setAllowedTypes('security_provider', ['null', 'string', 'callable'])
            ->setAllowedTypes('viewer', 'bool')
        ;
    }

    /**
     * Set the value of entities.
     *
     * This method completely overrides entities configuration
     * including annotation configuration
     *
     * @param array<int|string, mixed> $entities
     */
    public function setEntities(array $entities): self
    {
        $this->entities = $entities;

        return $this;
    }

    /**
     * enable audit Controller and its routing.
     *
     * @return $this
     */
    public function enableViewer(): self
    {
        $this->isViewerEnabled = true;

        return $this;
    }

    /**
     * disable audit Controller and its routing.
     *
     * @return $this
     */
    public function disableViewer(): self
    {
        $this->isViewerEnabled = false;

        return $this;
    }

    /**
     * Get enabled flag.
     */
    public function isViewerEnabled(): bool
    {
        return $this->isViewerEnabled;
    }

    /**
     * Get the value of tablePrefix.
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Get the value of tableSuffix.
     */
    public function getTableSuffix(): string
    {
        return $this->tableSuffix;
    }

    /**
     * Get the value of excludedColumns.
     *
     * @return array<string>
     */
    public function getIgnoredColumns(): array
    {
        return $this->ignoredColumns;
    }

    /**
     * Get the value of entities.
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * Enables auditing for a specific entity.
     *
     * @param string $entity Entity class name
     *
     * @return $this
     */
    public function enableAuditFor(string $entity): self
    {
        if (isset($this->getEntities()[$entity])) {
            $this->entities[$entity]['enabled'] = true;
        }

        return $this;
    }

    /**
     * Disables auditing for a specific entity.
     *
     * @param string $entity Entity class name
     *
     * @return $this
     */
    public function disableAuditFor(string $entity): self
    {
        if (isset($this->getEntities()[$entity])) {
            $this->entities[$entity]['enabled'] = false;
        }

        return $this;
    }

    public function setStorageMapper(callable $mapper): self
    {
        $this->storageMapper = $mapper;

        return $this;
    }

    public function getStorageMapper(): ?callable
    {
        return $this->storageMapper;
    }

    public function setUserProvider(callable $userProvider): self
    {
        $this->userProvider = $userProvider;

        return $this;
    }

    public function getUserProvider(): ?callable
    {
        return $this->userProvider;
    }

    public function setRoleChecker(callable $roleChecker): self
    {
        $this->roleChecker = $roleChecker;

        return $this;
    }

    public function getRoleChecker(): ?callable
    {
        return $this->roleChecker;
    }

    public function setSecurityProvider(callable $securityProvider): self
    {
        $this->securityProvider = $securityProvider;

        return $this;
    }

    public function getSecurityProvider(): ?callable
    {
        return $this->securityProvider;
    }
}
