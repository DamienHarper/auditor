# API Reference

> **Complete API documentation for all public classes and interfaces**

This section provides detailed API documentation for all public classes and interfaces.

## 📚 Core Classes

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

## 🔌 Provider Interfaces

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

## 🔌 DoctrineProvider

> [!WARNING]
> All DoctrineProvider classes (`DoctrineProvider`, `Configuration`, `Reader`, `Query`, filters,
> `SchemaManager`, `AuditingService`, `StorageService`) were **removed** from auditor core in v5.0.
> See the [auditor-doctrine-provider](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/)
> documentation for the current API reference.

## 📦 Models

### Entry

```php
namespace DH\Auditor\Model;

final class Entry
{
    // Factory
    public static function fromArray(array $row): self;

    // Read-only properties (PHP 8.4 property hooks)
    public private(set) ?int $id;
    public private(set) int $schemaVersion;   // 1 = legacy, 2 = current
    public private(set) string $type;         // 'insert', 'update', 'remove', 'associate', 'dissociate'
    public string $objectId;                  // virtual: maps to object_id column
    public private(set) ?string $discriminator;
    public ?string $transactionId;            // virtual: ULID (v2) or SHA-1 fallback (v1)
    public int|string|null $userId;           // virtual: maps to blame_id column
    public ?array $blame;                     // virtual: decoded blame JSON
    public ?string $username;                 // virtual: blame['username']
    public ?string $userFqdn;                 // virtual: blame['user_fqdn']
    public ?string $userFirewall;             // virtual: blame['user_firewall']
    public ?string $ip;                       // virtual: blame['ip']
    public ?array $extraData;                 // virtual: decoded extra_data JSON
    public ?\DateTimeImmutable $createdAt;    // virtual: maps to created_at column

    // Methods
    public function getDiffs(): array;             // field changes (schema v2: changes envelope)
    public function getDiffSource(): ?array;       // entity metadata (schema v2 only)
    public function getDiffTarget(): ?array;       // association target (schema v2 only)
    public function getExtraData(): ?array;
    public function getBlame(): ?array;
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

## 🏷️ Attributes

### Auditable

```php
namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Auditable
{
    public function __construct(public bool $enabled = true);
}
```

### Ignore

```php
namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Ignore {}
```

### Security

```php
namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Security
{
    public const string VIEW_SCOPE = 'view';
    
    public function __construct(public array $view);
}
```

### DiffLabel

```php
namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class DiffLabel
{
    public function __construct(public string $resolver);
}
```

Annotates a property with a resolver service that will translate the raw stored value into a
human-readable label. The `resolver` argument must be the FQCN of a service that implements
`DiffLabelResolverInterface`.

The label is stored alongside the raw value in the JSON diff as `{"value": x, "label": "y"}`,
consistent with how relation fields already store `{id, label, class, table}`.

**Example:**

```php
use DH\Auditor\Attribute\Auditable;
use DH\Auditor\Attribute\DiffLabel;

#[Auditable]
class Order
{
    #[DiffLabel(resolver: StatusLabelResolver::class)]
    private int $status;
}
```

## 📋 Contracts

### DiffLabelResolverInterface

```php
namespace DH\Auditor\Contract;

interface DiffLabelResolverInterface
{
    /**
     * Resolves a raw audit diff value to a human-readable label.
     *
     * Called at write-time (during flush). Implementations MUST NOT flush the
     * same EntityManager or rely on an open transaction. A separate read-only
     * connection or a pure in-memory lookup is safe.
     *
     * Return null to fall back to storing the plain raw value without a label.
     */
    public function __invoke(mixed $value): ?string;
}
```

**Example implementation:**

```php
use DH\Auditor\Contract\DiffLabelResolverInterface;

final class StatusLabelResolver implements DiffLabelResolverInterface
{
    private const LABELS = [
        1 => 'Pending',
        2 => 'Processing',
        3 => 'Shipped',
        4 => 'Delivered',
    ];

    public function __invoke(mixed $value): ?string
    {
        return self::LABELS[(int) $value] ?? null;
    }
}
```

## 📣 Events

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

## ⚠️ Exceptions

### Exception Classes

```php
namespace DH\Auditor\Exception;

class AccessDeniedException extends \Exception {}
class InvalidArgumentException extends \Exception {}
class MappingException extends \Exception {}
class ProviderException extends \Exception {}
```

---

## Next Steps

- 🚀 [Getting Started Guide](../getting-started/quick-start.md)
- 🗄️ [DoctrineProvider](../providers/doctrine/index.md)
- 🔍 [Querying Audits](../querying/index.md)
