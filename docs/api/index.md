---
description: "API reference of the auditor core: Auditor, Configuration, Entry, attributes, masking, history classes, LifecycleEvent, provider interfaces and exceptions."
---

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

`disableStorage()` stops `AuditEventSubscriber` from passing audit entries to that provider's
`persist()`; `disableAuditing()` tells the provider to stop producing entries (the Doctrine provider
checks `isAuditingEnabled()`, a custom auditing provider must do the same). Both refuse to switch off
the last enabled provider of their role, and disabling a provider that is already disabled does nothing.
The six methods throw an `InvalidArgumentException` for a provider that was not registered with
`registerProvider()`.

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
    
    public private(set) bool $enabled;
    public readonly string $timezone;

    public function enable(): self;
    public function disable(): self;
    
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
> See the [auditor-doctrine-provider](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider)
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
    public ?array $impersonator;              // virtual: blame['impersonator'] (switch user)
    public ?array $via;                       // virtual: blame['via'] (blame context)
    public ?array $extraData;                 // virtual: decoded extra_data JSON
    public ?\DateTimeImmutable $createdAt;    // virtual: maps to created_at column
    public ?string $entity;                   // virtual: FQCN, set by readers of several tables
    public ?string $rootPath;                 // virtual: audit root path, set in the history of a root

    // Methods
    public function getDiffs(): array;             // field changes (schema v2: changes envelope)
    public function getDiffSource(): ?array;       // entity metadata (schema v2 only)
    public function getDiffTarget(): ?array;       // association target (schema v2 only)
    public function isSnapshot(): bool;            // insert written by audit:snapshot (schema v2 only)
    public function isDeepDiff(string $field): bool;   // the field's changeset is a JSON deep diff
    public static function isDiffLeaf(array $node): bool; // leaf ({old}, {new}, {old, new}) of a deep diff
    public function toArray(): array;              // flat array used by exports (same keys for v1 and v2)
    public function getSortKey(): string;          // total order across tables (date, transaction, entity, id)
    public function getExtraData(): ?array;
    public function getBlame(): ?array;
}
```

### User

```php
namespace DH\Auditor\User;

interface UserInterface
{
    public ?string $identifier { get; }
    public ?string $username { get; }
    public ?string $fqdn { get; }                 // class of the user (user_fqdn)
    public ?UserInterface $impersonator { get; }  // user who impersonates this one
}

final class User implements UserInterface
{
    public ?string $identifier { get => $this->id; }   // the $id given to the constructor

    public function __construct(
        ?string $id = null,
        public readonly ?string $username = null,
        public readonly ?string $fqdn = null,
        public readonly ?UserInterface $impersonator = null,
    );
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

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Ignore
{
    public function __construct(public ?string $field = null);
}
```

On a property, the property is the ignored field: leave `field` out. On a class, `field` names the
ignored field, and the attribute can be repeated. This is the form to use on models that do not declare
a property per column, such as Eloquent models:

```php
use DH\Auditor\Attribute\Auditable;
use DH\Auditor\Attribute\Ignore;

#[Auditable]
#[Ignore(field: 'password')]
#[Ignore(field: 'remember_token')]
class User extends Model {}
```

### Security

```php
namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Security
{
    public const string VIEW_SCOPE = 'view';
    
    public function __construct(public array $view);
}
```

### AuditRoot

```php
namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class AuditRoot
{
    public function __construct(public string $path); // e.g. 'property' or 'visit.property'

    public function getSegments(): array;
}
```

Indexes the audit entries of the entity under a root entity. See [Histories](../history.md).

### DiffLabel

```php
namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class DiffLabel
{
    public function __construct(public string $resolver, public ?string $field = null);
}
```

Annotates a field with a resolver service that will translate the raw stored value into a
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

As with `#[Ignore]`, the attribute can also be put on the class, repeated, with `field` naming the field:
`#[DiffLabel(resolver: StatusLabelResolver::class, field: 'status')]`.

### Mask

```php
namespace DH\Auditor\Attribute;

use DH\Auditor\Masking\MaskStrategy;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Mask
{
    public function __construct(public MaskStrategy $strategy = MaskStrategy::Hidden, public ?string $field = null);
}
```

Records that a field changed without storing its values. Unlike `#[Ignore]`, the change still shows in the history.

| Strategy                   | Stored value                                                                    |
|----------------------------|---------------------------------------------------------------------------------|
| `MaskStrategy::Hidden`     | `"***"` (`Masker::HIDDEN_VALUE`)                                                 |
| `MaskStrategy::KeyedHash`  | HMAC-SHA256 of the value, keyed with the mask secret of the provider             |

A null value stays null. With `KeyedHash`, equal values give equal hashes, so the entries of one value can be found
without storing it; changing the secret changes the hashes recorded from then on.

```php
use DH\Auditor\Attribute\Auditable;
use DH\Auditor\Attribute\Mask;
use DH\Auditor\Masking\MaskStrategy;

#[Auditable]
class User
{
    #[Mask(strategy: MaskStrategy::KeyedHash)]
    private string $email;

    #[Mask]
    private string $pin;
}
```

As with `#[Ignore]`, the attribute can also be put on the class, repeated, with `field` naming the field:
`#[Mask(field: 'email')]`.

> [!WARNING]
> Only the values of the field in the diffs are masked. A value written elsewhere is not, in particular the label of
> the entity (its `__toString()` or a label resolver), recorded in the `source` of every entry.

Providers replace the values through `DH\Auditor\Masking\Masker`:

```php
namespace DH\Auditor\Masking;

final readonly class Masker
{
    public const string HIDDEN_VALUE = '***';

    public function __construct(?string $secret = null);

    // null stays null; a string is hashed as is, any other value as its JSON encoding
    public function mask(MaskStrategy $strategy, mixed $value): ?string;
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

### EntityLabelResolverInterface and FieldLabelResolverInterface

```php
namespace DH\Auditor\Contract;

interface EntityLabelResolverInterface
{
    public function __invoke(object $entity): ?string; // label of "source" / "target", null = default
}

interface FieldLabelResolverInterface
{
    public function resolve(string $entity, string $field): string;
}
```

## 🕰️ Histories

```php
namespace DH\Auditor\History;

final readonly class HistoryGrouper
{
    public function __construct(?callable $key = null, ChangeExtractor $extractor = new ChangeExtractor());
    public static function byTransaction(iterable $entries): array; // list<HistoryItem>
    public static function transactionKey(Entry $entry): string;
    public function groupsByTransaction(): bool;                    // no key of its own: one item per transaction
    public function keyOf(Entry $entry): string;
    public function group(iterable $entries): array;               // list<HistoryItem>
}

final class HistoryItem
{
    public readonly string $key;
    public readonly array $entries;            // list<Entry>, most recent first
    public \DateTimeImmutable $createdAt;
    public int|string|null $userId;
    public ?string $username;
    public ?string $userFqdn;
    public ?array $impersonator;               // user who impersonated the author, if any
    public BlameOrigin $origin;
    public ?array $via;
    public function getChanges(): array;       // list<Change>, chronological
    public function getMainChange(): ?Change;
    public function getTransactionIds(): array;
    public function getEntities(): array;
}

final readonly class ChangeExtractor
{
    public function __construct(array $fields = []); // entity FQCN => ordered list of fields to keep
    public function extract(Entry $entry): array;    // list<Change>
    public function extractAll(iterable $entries): array; // without mirror association entries
    public static function entityOf(Entry $entry): string;
}

final readonly class Change
{
    public ChangeKind $kind;
    public string $entity;
    public string $objectId;
    public ?string $objectLabel;
    public ?string $field;
    public mixed $old;
    public mixed $new;
    public ?string $oldLabel;
    public ?string $newLabel;
    public Entry $entry;
    public array $labels;                         // field => label, for Created, Snapshot and Deleted changes
    public function getValue(string $field): mixed;   // value of a field of a Created, Snapshot or Deleted change
    public function getLabel(string $field): ?string;
    public function getTargetClass(): ?string;
    public function getTargetId(): ?string;
}

enum ChangeKind: string
{
    case Created = 'created';
    case Snapshot = 'snapshot';
    case Deleted = 'deleted';
    case Set = 'set';
    case Changed = 'changed';
    case Cleared = 'cleared';
    case Added = 'added';
    case Removed = 'removed';
    case Json = 'json';
    public function isEntityLevel(): bool;        // Created, Snapshot or Deleted
}
enum BlameOrigin: string
{
    case Http = 'http';
    case Console = 'console';
    case Messenger = 'messenger';
    case System = 'system';
}
```

See [Histories](../history.md).

## 📣 Events

### LifecycleEvent

```php
namespace DH\Auditor\Event;

final class LifecycleEvent extends AuditEvent
{
    public function __construct(array $payload, public readonly ?object $entity = null); // the audited entity, when known
    
    public function setPayload(array $payload): self;
    public function getPayload(): array;
}
```

## ⚠️ Exceptions

### Exception Classes

```php
namespace DH\Auditor\Exception;

final class AccessDeniedException extends \Exception {}
final class InvalidArgumentException extends \Exception {}
final class MappingException extends \Exception {}
final class ProviderException extends \Exception {}
```

---

## Next Steps

- 🚀 [Getting Started Guide](../getting-started/quick-start.md)
- 🗄️ [DoctrineProvider](../providers/doctrine/index.md)
- 🔍 [Querying Audits](../querying/index.md)
