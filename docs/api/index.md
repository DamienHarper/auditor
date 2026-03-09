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
> `SchemaManager`, `AuditingService`, `StorageService`) are **deprecated** in auditor core
> (removed in v5.0). See the [auditor-doctrine-provider](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/)
> documentation for the current API reference.

## 📦 Models

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
