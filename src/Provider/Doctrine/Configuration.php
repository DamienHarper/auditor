<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine;

use DH\Auditor\Provider\ConfigurationInterface;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Tests\Provider\Doctrine\ConfigurationTest;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see ConfigurationTest
 */
final class Configuration implements ConfigurationInterface
{
    private ?DoctrineProvider $provider = null;

    private readonly string $tablePrefix;

    private readonly string $tableSuffix;

    /**
     * @var array<string>
     */
    private array $ignoredColumns = [];

    private ?array $entities = null;

    private bool $isViewerEnabled;

    private int $viewerPageSize;

    private readonly bool $utf8Convert;

    private bool $initialized = false;

    /**
     * @var null|callable
     */
    private $storageMapper;

    private array $attributeLoaded = [];

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

        $this->isViewerEnabled = self::isViewerEnabledInConfig($config['viewer']);
        $this->viewerPageSize = self::getViewerPageSizeFromConfig($config['viewer']);
        $this->utf8Convert = $config['utf8_convert'];
        $this->storageMapper = $config['storage_mapper'];
    }

    public static function isViewerEnabledInConfig(mixed $config): bool
    {
        if (\is_array($config)) {
            if (!\array_key_exists('enabled', $config) || !\is_bool($config['enabled'])) {
                return false;
            }

            return $config['enabled'];
        }

        // "viewer" is disabled by default
        return \is_bool($config) && $config;
    }

    public static function getViewerPageSizeFromConfig(mixed $config): int
    {
        if (\is_array($config)) {
            if (!\array_key_exists('page_size', $config) || !\is_int($config['page_size'])) {
                return Reader::PAGE_SIZE;
            }

            return abs($config['page_size']);
        }

        return \is_int($config) ? abs($config) : Reader::PAGE_SIZE;
    }

    /**
     * Set the value of entities.
     *
     * This method completely overrides entities configuration
     * including attribute configuration
     *
     * @param array<int|string, mixed> $entities
     */
    public function setEntities(array $entities): self
    {
        $this->entities = $entities;
        $this->initialized = false;

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

    public function setViewerPageSize(int $pageSize): self
    {
        $this->viewerPageSize = abs($pageSize);

        return $this;
    }

    public function getViewerPageSize(): int
    {
        return $this->viewerPageSize;
    }

    /**
     * Get enabled flag.
     */
    public function isViewerEnabled(): bool
    {
        return $this->isViewerEnabled;
    }

    public function isUtf8ConvertEnabled(): bool
    {
        return $this->utf8Convert;
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
        if ($this->initialized && null !== $this->entities) {
            return $this->entities;
        }

        if ($this->provider instanceof DoctrineProvider) {
            $schemaManager = new SchemaManager($this->provider);

            /** @var array<AuditingService> $auditingServices */
            $auditingServices = $this->provider->getAuditingServices();
            foreach ($auditingServices as $auditingService) {
                $entityManager = $auditingService->getEntityManager();
                $platform = $entityManager->getConnection()->getDatabasePlatform();

                // do not load attributes if they're already loaded
                if (!isset($this->attributeLoaded[$auditingService->getName()]) || !$this->attributeLoaded[$auditingService->getName()]) {
                    $this->provider->loadAttributes($entityManager, $this->entities ?? []);
                    $this->attributeLoaded[$auditingService->getName()] = true;
                }

                \assert(null !== $this->entities);
                foreach (array_keys($this->entities) as $entity) {
                    $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
                    $entityTableName = $meta->getTableName();
                    $namespaceName = $meta->getSchemaName() ?? '';

                    $computedTableName = $schemaManager->resolveTableName($entityTableName, $namespaceName);
                    $this->entities[$entity]['table_schema'] = $namespaceName;
                    $this->entities[$entity]['table_name'] = $entityTableName;
                    // $this->entities[$entity]['computed_table_name'] = $entityTableName;
                    $this->entities[$entity]['computed_table_name'] = $computedTableName;
                    $this->entities[$entity]['audit_table_schema'] = $namespaceName;
                    $this->entities[$entity]['audit_table_name'] = $schemaManager->computeAuditTablename($entityTableName, $this);
                    // $this->entities[$entity]['computed_audit_table_name'] = $schemaManager->computeAuditTablename($this->entities[$entity], $this, $platform);
                    $this->entities[$entity]['computed_audit_table_name'] = $schemaManager->computeAuditTablename(
                        $computedTableName,
                        $this
                    );
                }
            }

            $this->initialized = true;
        }

        return $this->entities ?? [];
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

    /**
     * @return null|callable|string
     */
    public function getStorageMapper(): mixed
    {
        return $this->storageMapper;
    }

    public function getProvider(): ?DoctrineProvider
    {
        return $this->provider;
    }

    public function setProvider(DoctrineProvider $provider): void
    {
        $this->provider = $provider;
        $this->initialized = false;
    }

    private function configureOptions(OptionsResolver $resolver): void
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
                'viewer' => true,
                'storage_mapper' => null,
                'utf8_convert' => false,
            ])
            ->setAllowedTypes('table_prefix', 'string')
            ->setAllowedTypes('table_suffix', 'string')
            ->setAllowedTypes('ignored_columns', 'array')
            ->setAllowedTypes('entities', 'array')
            ->setAllowedTypes('storage_services', 'array')
            ->setAllowedTypes('auditing_services', 'array')
            ->setAllowedTypes('viewer', ['bool', 'array'])
            ->setAllowedTypes('storage_mapper', ['null', 'string', 'callable'])
            ->setAllowedTypes('utf8_convert', 'bool')
        ;
    }
}
