<?php

namespace DH\Auditor\Provider\Doctrine;

use DH\Auditor\Provider\ConfigurationInterface;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Configuration implements ConfigurationInterface
{
    /**
     * @var DoctrineProvider
     */
    private $provider;

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
    private $extraFields = [];

    /**
     * @var array
     */
    private $extraIndices = [];

    /**
     * @var array
     */
    private $ignoredColumns;

    /**
     * @var null|array
     */
    private $entities;

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
     * @var array
     */
    private $annotationLoaded = [];

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

        if (isset($config['extra_fields']) && !empty($config['extra_fields'])) {
            // use field names as array keys for easier lookup
            foreach ($config['extra_fields'] as $fieldName => $fieldOptions) {
                $this->extraFields[$fieldName] = $fieldOptions;
            }
        }

        if (isset($config['extra_indices']) && !empty($config['extra_indices'])) {
            // use index names as array keys for easier lookup
            foreach ($config['extra_indices'] as $indexName => $indexOptions) {
                $this->extraIndices[$indexName] = $indexOptions;
            }
        }

        $this->storageServices = $config['storage_services'];
        $this->auditingServices = $config['auditing_services'];
        $this->isViewerEnabled = $config['viewer'];
        $this->storageMapper = $config['storage_mapper'];
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
                'extra_fields' => [],
                'extra_indices' => [],
                'storage_services' => [],
                'auditing_services' => [],
                'viewer' => true,
                'storage_mapper' => null,
            ])
            ->setAllowedTypes('table_prefix', 'string')
            ->setAllowedTypes('table_suffix', 'string')
            ->setAllowedTypes('ignored_columns', 'array')
            ->setAllowedTypes('entities', 'array')
            ->setAllowedTypes('extra_fields', 'array')
            ->setAllowedTypes('extra_indices', 'array')
            ->setAllowedTypes('storage_services', 'array')
            ->setAllowedTypes('auditing_services', 'array')
            ->setAllowedTypes('viewer', 'bool')
            ->setAllowedTypes('storage_mapper', ['null', 'string', 'callable'])
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

    public function getExtraFields(): array
    {
        return $this->extraFields;
    }

    public function getAllFields(): array
    {
        return array_merge(
            SchemaHelper::getAuditTableColumns(),
            $this->extraFields
        );
    }

    /**
     * @param array<string, mixed> $extraFields
     *
     * @return $this
     */
    public function setExtraFields(array $extraFields): self
    {
        $this->extraFields = $extraFields;

        return $this;
    }

    public function getExtraIndices(): array
    {
        return $this->extraIndices;
    }

    public function prepareExtraIndices(string $tablename): array
    {
        $indices = [];
        foreach ($this->extraIndices as $extraIndexField => $extraIndexOptions) {
            $indices[$extraIndexField] = [
                'type' => $extraIndexOptions['type'] ?? 'index',
                'name' => sprintf('%s_%s_idx', $extraIndexOptions['name_prefix'] ?? $extraIndexField, md5($tablename)),
            ];
        }

        return $indices;
    }

    public function getAllIndices(string $tablename): array
    {
        return array_merge(
            SchemaHelper::getAuditTableIndices($tablename),
            $this->prepareExtraIndices($tablename)
        );
    }

    /**
     * @param array<string, mixed> $extraIndices
     *
     * @return $this
     */
    public function setExtraIndices(array $extraIndices): self
    {
        $this->extraIndices = $extraIndices;

        return $this;
    }

    /**
     * Get the value of entities.
     */
    public function getEntities(): array
    {
        if (null !== $this->provider) {
            /** @var AuditingService[] $auditingServices */
            $auditingServices = $this->provider->getAuditingServices();
            foreach ($auditingServices as $auditingService) {
                // do not load annotations if they're already loaded
                if (!isset($this->annotationLoaded[$auditingService->getName()]) || !$this->annotationLoaded[$auditingService->getName()]) {
                    $this->provider->loadAnnotations($auditingService->getEntityManager(), null === $this->entities ? [] : $this->entities);
                    $this->annotationLoaded[$auditingService->getName()] = true;
                }
            }
        }

        return null === $this->entities ? [] : $this->entities;
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

    public function getProvider(): DoctrineProvider
    {
        return $this->provider;
    }

    public function setProvider(DoctrineProvider $provider): void
    {
        $this->provider = $provider;
    }
}
