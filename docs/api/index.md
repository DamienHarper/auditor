# API Reference

This section provides detailed API documentation for all public classes and interfaces.

## Core Classes

### Auditor

The main entry point for the library.

```php
namespace DH\Auditor;

final class Auditor
{
    public function __construct(Configuration $configuration, EventDispatcherInterface $dispatcher);
    
    public function getEventDispatcher(): EventDispatcherInterface;
    public function getConfiguration(): Configuration;
    
    // Provider management
    public function getProviders(): array;
    public function getProvider(string $name): ProviderInterface;
    public function hasProvider(string $name): bool;
    public function registerProvider(ProviderInterface $provider): self;
    
    // Storage control
    public function enableStorage(ProviderInterface $provider): self;
    public function disableStorage(ProviderInterface $provider): self;
    public function isStorageEnabled(ProviderInterface $provider): bool;
    
    // Auditing control
    public function enableAuditing(ProviderInterface $provider): self;
    public function disableAuditing(ProviderInterface $provider): self;
    public function isAuditingEnabled(ProviderInterface $provider): bool;
}
```

### Configuration

Global auditor configuration.

```php
namespace DH\Auditor;

final class Configuration
{
    public function __construct(array $options);
    
    public function enable(): self;
    public function disable(): self;
    public function isEnabled(): bool;
    
    public function getTimezone(): string;
    
    public function setUserProvider(callable $userProvider): self;
    public function getUserProvider(): ?callable;
    
    public function setSecurityProvider(callable $securityProvider): self;
    public function getSecurityProvider(): ?callable;
    
    public function setRoleChecker(callable $roleChecker): self;
    public function getRoleChecker(): ?callable;
}
```

## Provider Interfaces

### ProviderInterface

```php
namespace DH\Auditor\Provider;

interface ProviderInterface
{
    public function setAuditor(Auditor $auditor): self;
    public function getAuditor(): Auditor;
    public function getConfiguration(): ConfigurationInterface;
    public function isRegistered(): bool;
    
    public function registerStorageService(StorageServiceInterface $service): self;
    public function registerAuditingService(AuditingServiceInterface $service): self;
    
    public function persist(LifecycleEvent $event): void;
    
    public function getStorageServices(): array;
    public function getAuditingServices(): array;
    
    public function supportsStorage(): bool;
    public function supportsAuditing(): bool;
}
```

### ConfigurationInterface

```php
namespace DH\Auditor\Provider;

interface ConfigurationInterface
{
    // Marker interface
}
```

## DoctrineProvider

### DoctrineProvider

```php
namespace DH\Auditor\Provider\Doctrine;

final class DoctrineProvider extends AbstractProvider
{
    public function __construct(ConfigurationInterface $configuration);
    
    public function getTransactionManager(): TransactionManager;
    
    public function isStorageMapperRequired(): bool;
    
    public function getAuditingServiceForEntity(string $entity): AuditingService;
    public function getStorageServiceForEntity(string $entity): StorageService;
    
    public function isAuditable(object|string $entity): bool;
    public function isAudited(object|string $entity): bool;
    public function isAuditedField(object|string $entity, string $field): bool;
    
    public function setStorageMapper(callable $storageMapper): void;
    
    public function loadAnnotations(EntityManagerInterface $em, array $entities): self;
}
```

### DoctrineProvider Configuration

```php
namespace DH\Auditor\Provider\Doctrine;

final class Configuration implements ConfigurationInterface
{
    public function __construct(array $options);
    
    // Entities
    public function setEntities(array $entities): self;
    public function getEntities(): array;
    public function enableAuditFor(string $entity): self;
    public function disableAuditFor(string $entity): self;
    
    // Viewer
    public function enableViewer(): self;
    public function disableViewer(): self;
    public function isViewerEnabled(): bool;
    public function setViewerPageSize(int $pageSize): self;
    public function getViewerPageSize(): int;
    
    // Table naming
    public function getTablePrefix(): string;
    public function getTableSuffix(): string;
    
    // Ignored columns
    public function getIgnoredColumns(): array;
    
    // Storage mapper
    public function setStorageMapper(callable $mapper): self;
    public function getStorageMapper(): mixed;
    
    // Provider reference
    public function getProvider(): ?DoctrineProvider;
    public function setProvider(DoctrineProvider $provider): void;
}
```

## Reader & Query

### Reader

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Reader;

final readonly class Reader implements ReaderInterface
{
    public const int PAGE_SIZE = 50;
    
    public function __construct(DoctrineProvider $provider);
    
    public function getProvider(): DoctrineProvider;
    
    public function createQuery(string $entity, array $options = []): Query;
    
    public function getAuditsByTransactionHash(string $transactionHash): array;
    
    public function paginate(Query $query, int $page = 1, ?int $pageSize = null): array;
    
    public function getEntityTableName(string $entity): string;
    public function getEntityAuditTableName(string $entity): string;
}
```

### Query

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Reader;

final class Query implements QueryInterface
{
    public const string TYPE = 'type';
    public const string CREATED_AT = 'created_at';
    public const string TRANSACTION_HASH = 'transaction_hash';
    public const string OBJECT_ID = 'object_id';
    public const string USER_ID = 'blame_id';
    public const string ID = 'id';
    public const string DISCRIMINATOR = 'discriminator';
    
    public function __construct(string $table, Connection $connection, string $timezone);
    
    public function execute(): array;
    public function count(): int;
    
    public function addFilter(FilterInterface $filter): self;
    public function addOrderBy(string $field, string $direction = 'DESC'): self;
    public function resetOrderBy(): self;
    public function limit(int $limit, int $offset = 0): self;
    
    public function getSupportedFilters(): array;
    public function getFilters(): array;
    public function getOrderBy(): array;
    public function getLimit(): array;
}
```

## Filters

### FilterInterface

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

interface FilterInterface
{
    public function getName(): string;
    public function getSQL(): array;
}
```

### SimpleFilter

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

final class SimpleFilter implements FilterInterface
{
    public function __construct(string $name, mixed $value);
    
    public function getName(): string;
    public function getValue(): mixed;
    public function getSQL(): array;
}
```

### DateRangeFilter

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

final readonly class DateRangeFilter implements FilterInterface
{
    public function __construct(
        string $name,
        ?\DateTimeInterface $minValue,
        ?\DateTimeInterface $maxValue = null
    );
    
    public function getName(): string;
    public function getMinValue(): ?\DateTimeInterface;
    public function getMaxValue(): ?\DateTimeInterface;
    public function getSQL(): array;
}
```

### RangeFilter

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

final readonly class RangeFilter implements FilterInterface
{
    public function __construct(string $name, mixed $minValue, mixed $maxValue = null);
    
    public function getName(): string;
    public function getMinValue(): mixed;
    public function getMaxValue(): mixed;
    public function getSQL(): array;
}
```

## Models

### Entry

```php
namespace DH\Auditor\Model;

final class Entry
{
    public static function fromArray(array $row): self;
    
    public function getId(): ?int;
    public function getType(): string;
    public function getObjectId(): string;
    public function getDiscriminator(): ?string;
    public function getTransactionHash(): ?string;
    public function getDiffs(bool $includeMetadata = false): array;
    public function getUserId(): int|string|null;
    public function getUsername(): ?string;
    public function getUserFqdn(): ?string;
    public function getUserFirewall(): ?string;
    public function getIp(): ?string;
    public function getCreatedAt(): ?\DateTimeImmutable;
}
```

### User

```php
namespace DH\Auditor\User;

interface UserInterface
{
    public function getIdentifier(): ?string;
    public function getUsername(): ?string;
}

class User implements UserInterface
{
    public function __construct(?string $identifier, ?string $username);
    
    public function getIdentifier(): ?string;
    public function getUsername(): ?string;
}
```

## Attributes

### Auditable

```php
namespace DH\Auditor\Provider\Doctrine\Auditing\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Auditable
{
    public function __construct(public bool $enabled = true);
}
```

### Ignore

```php
namespace DH\Auditor\Provider\Doctrine\Auditing\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Ignore {}
```

### Security

```php
namespace DH\Auditor\Provider\Doctrine\Auditing\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Security
{
    public const string VIEW_SCOPE = 'view';
    
    public function __construct(public array $view);
}
```

## Schema Management

### SchemaManager

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Schema;

final readonly class SchemaManager
{
    public function __construct(DoctrineProvider $provider);
    
    public function updateAuditSchema(?array $sqls = null, ?callable $callback = null): void;
    
    public function getAuditableTableNames(EntityManagerInterface $em): array;
    public function collectAuditableEntities(): array;
    public function getUpdateAuditSchemaSql(): array;
    
    public function createAuditTable(string $entity, ?Schema $schema = null): Schema;
    public function updateAuditTable(string $entity, ?Schema $schema = null): Schema;
    
    public function resolveTableName(string $tableName, string $namespaceName, AbstractPlatform $platform): string;
    public function resolveAuditTableName(string $entity, Configuration $config, AbstractPlatform $platform): ?string;
    public function computeAuditTablename(string $entityTableName, Configuration $config): ?string;
}
```

## Events

### LifecycleEvent

```php
namespace DH\Auditor\Event;

final class LifecycleEvent extends AuditEvent
{
    public function __construct(array $payload);
    
    public function setPayload(array $payload): self;
    public function getPayload(): array;
}
```

## Exceptions

### Exception Classes

```php
namespace DH\Auditor\Exception;

class AccessDeniedException extends \Exception {}
class InvalidArgumentException extends \Exception {}
class MappingException extends \Exception {}
class ProviderException extends \Exception {}
```

## Services

### AuditingService

```php
namespace DH\Auditor\Provider\Doctrine\Service;

final class AuditingService extends DoctrineService implements AuditingServiceInterface
{
    public function __construct(string $name, EntityManagerInterface $entityManager);
    
    public function getName(): string;
    public function getEntityManager(): EntityManagerInterface;
}
```

### StorageService

```php
namespace DH\Auditor\Provider\Doctrine\Service;

final class StorageService extends DoctrineService implements StorageServiceInterface
{
    public function __construct(string $name, EntityManagerInterface $entityManager);
    
    public function getName(): string;
    public function getEntityManager(): EntityManagerInterface;
}
```

## Next Steps

- [Getting Started Guide](../getting-started/quick-start.md)
- [DoctrineProvider Reference](../providers/doctrine/index.md)
- [Querying Audits](../querying/index.md)
