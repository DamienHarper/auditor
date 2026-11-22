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
    public function __construct(
        Configuration $configuration,
        EventDispatcherInterface $dispatcher,
        bool $registerAuditEventSubscriber = true,
    );
    
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

By default, the constructor attaches `AuditEventSubscriber` to `$dispatcher` (priority
`AuditEventSubscriber::PRIORITY`, i.e. `-1_000_000`). Pass `false` as third argument when the
subscriber is declared as a listener of the dispatcher instead, e.g. in a Symfony container: since
Symfony 8.2, the compiled event dispatcher deprecates `addListener()` at runtime.

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

    public function setExtraDataProvider(callable $extraDataProvider): self;
    public function getExtraDataProvider(): ?callable;

    public function getBlameContextStack(): BlameContextStack;
}
```

### BlameContext and BlameContextStack

The context a change is made in, written in the `via` key of the `blame` JSON. See
[Blame Context](../configuration/blame-context.md).

```php
namespace DH\Auditor\Blame;

final readonly class BlameContext
{
    public function __construct(
        public string $type,                  // e.g. 'import', 'console', 'messenger'
        public ?string $label = null,
        public array $data = [],              // JSON encodable
        public ?UserInterface $user = null,   // user to blame instead of the user provider's
    );

    public function toArray(): array;         // value of the `via` key
}

final class BlameContextStack
{
    public function push(BlameContext $context): void;
    public function pop(): ?BlameContext;
    public function current(): ?BlameContext;  // top of the stack, the one written
    public function run(BlameContext $context, callable $callback): mixed;
    public function isEmpty(): bool;
    public function reset(): void;
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
    public ?array $via;                       // virtual: blame['via'] (blame context)
    public ?array $extraData;                 // virtual: decoded extra_data JSON
    public ?\DateTimeImmutable $createdAt;    // virtual: maps to created_at column

    // Methods
    public function getDiffs(): array;             // field changes (schema v2: changes envelope)
    public function getDiffSource(): ?array;       // entity metadata (schema v2 only)
    public function getDiffTarget(): ?array;       // association target (schema v2 only)
    public function isSnapshot(): bool;            // insert written by audit:snapshot (schema v2 only)
    public function isDeepDiff(string $field): bool;   // the field's changeset is a JSON deep diff
    public static function isDiffLeaf(array $node): bool; // leaf ({old}, {new}, {old, new}) of a deep diff
    public function toArray(): array;              // flat array used by exports (same keys for v1 and v2)
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
class Auditable
{
    public function __construct(
        public bool $enabled = true,
        public ?string $maxAge = null,     // ISO 8601 duration, e.g. 'P1Y'
        public ?int $maxEntries = null,    // keep the N most recent entries
    );

    public function getRetentionPolicy(): RetentionPolicy;
}
```

`maxAge` and `maxEntries` define an optional retention policy, applied by providers that support pruning
(`audit:clean` with the Doctrine provider). Invalid values throw `DH\Auditor\Exception\InvalidArgumentException`
when the attribute is instantiated.

### RetentionPolicy

```php
namespace DH\Auditor\Model;

final readonly class RetentionPolicy
{
    public function __construct(public ?string $maxAge = null, public ?int $maxEntries = null);

    public function isEmpty(): bool;

    // Date before which entries are eligible for deletion, null when no max age is set
    public function getCutoff(\DateTimeImmutable $now): ?\DateTimeImmutable;
}
```

Validation rules: `maxAge` must be an ISO 8601 duration greater than zero (`P0D` is rejected), `maxEntries` must be
a positive integer.

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
