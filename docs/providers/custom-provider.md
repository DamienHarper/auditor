---
id: custom-provider
title: Building a Custom Provider
---
# Building a Custom Provider

This guide covers everything you need to create a provider for `auditor` — from the minimal
contract to a distributable Composer package.

## What is a provider?

A **provider** is the bridge between `auditor`'s change-detection pipeline and your storage
backend. A single provider can implement one or both of these responsibilities:

| Role | Interface | Responsibility |
|------|-----------|----------------|
| **Auditing** | `AuditingServiceInterface` | Hook into the ORM/framework to detect entity changes |
| **Storage** | `StorageServiceInterface` | Persist the captured `LifecycleEvent` to your backend |

Both roles are handled by registering named **services** inside the provider.

---

## Provider contract

### `ProviderInterface`

Every provider must implement `DH\Auditor\Provider\ProviderInterface`:

```php
interface ProviderInterface
{
    public function setAuditor(Auditor $auditor): self;
    public function getAuditor(): Auditor;
    public function getConfiguration(): ConfigurationInterface;
    public function isRegistered(): bool;

    public function registerStorageService(StorageServiceInterface $service): self;
    public function registerAuditingService(AuditingServiceInterface $service): self;

    public function getStorageServices(): array;   // StorageServiceInterface[]
    public function getAuditingServices(): array;  // AuditingServiceInterface[]

    public function supportsStorage(): bool;
    public function supportsAuditing(): bool;

    public function persist(LifecycleEvent $event): void;
}
```

### `AbstractProvider` — use this instead of implementing from scratch

`DH\Auditor\Provider\AbstractProvider` already implements everything except three methods:

```php
abstract class AbstractProvider implements ProviderInterface
{
    // You must implement:
    public function supportsStorage(): bool;
    public function supportsAuditing(): bool;
    public function persist(LifecycleEvent $event): void;
}
```

It handles `setAuditor()`, `getAuditor()`, `isRegistered()`, service registration/deduplication,
and `getConfiguration()` (via the protected `$configuration` property).

### `ConfigurationInterface`

Your provider's configuration class must implement `DH\Auditor\Provider\ConfigurationInterface`
(it is an empty marker interface — add whatever options your provider needs):

```php
use DH\Auditor\Provider\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function __construct(
        private readonly string $tableName = 'audit_log',
        private readonly bool $enabled = true,
    ) {}

    public function getTableName(): string { return $this->tableName; }
    public function isEnabled(): bool { return $this->enabled; }
}
```

---

## Service contract

Services are lightweight named objects that tell `auditor` what a provider is capable of.
They only need to implement `getName(): string` (via `ServiceInterface`).

```
ServiceInterface
  ├─ AuditingServiceInterface   (marker: provider can detect changes)
  └─ StorageServiceInterface    (marker: provider can store audit entries)
```

Extend `AbstractService` to avoid boilerplate:

```php
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Provider\Service\AuditingServiceInterface;
use DH\Auditor\Provider\Service\StorageServiceInterface;

// A service that hooks into your ORM
final class MyAuditingService extends AbstractService implements AuditingServiceInterface
{
    public function __construct(string $name, private readonly MyOrmConnection $connection)
    {
        parent::__construct($name);
    }
}

// A service that writes to your storage backend
final class MyStorageService extends AbstractService implements StorageServiceInterface
{
    public function __construct(string $name, private readonly MyStorageBackend $backend)
    {
        parent::__construct($name);
    }
}
```

> [!NOTE]
> Service names must be unique **within a provider**. The name is just a human-readable
> identifier (e.g. `'default'`). It is used as the array key in `getStorageServices()` /
> `getAuditingServices()`.

---

## The `persist()` method

`persist()` is called by `AuditEventSubscriber` for every `LifecycleEvent` dispatched by
auditor. This is where you write the audit entry to your backend.

### `LifecycleEvent` payload

```php
public function persist(LifecycleEvent $event): void
{
    $payload = $event->getPayload();
    // $event->entity  → the original entity object (may be null)
}
```

The `$payload` array always contains these keys:

| Key | Type | Description |
|-----|------|-------------|
| `type` | `string` | Operation: `'insert'`, `'update'`, `'remove'`, `'associate'`, `'dissociate'` |
| `object_id` | `string` | Stringified primary key of the entity |
| `discriminator` | `?string` | Doctrine inheritance discriminator (or `null`) |
| `transaction_hash` | `?string` | Groups all changes in a single flush |
| `diffs` | `string` | JSON-encoded field-level changes |
| `extra_data` | `?string` | Optional JSON metadata (enriched by event listeners) |
| `blame_id` | `int\|string\|null` | Authenticated user identifier |
| `blame_user` | `?string` | Authenticated username |
| `blame_user_fqdn` | `?string` | User class FQCN |
| `blame_user_firewall` | `?string` | Symfony firewall name |
| `ip` | `?string` | Client IP address |
| `created_at` | `DateTimeImmutable` | Timestamp of the change |

Providers built on Doctrine ORM also add:

| Key | Type | Description |
|-----|------|-------------|
| `entity` | `string` | FQCN of the audited entity |
| `table` | `string` | Resolved audit table name |

> [!IMPORTANT]
> Use `$payload['type']` (a plain string) to check the operation type, **not** `$payload['action']`.
> The `TransactionType` enum provides constants if you need comparisons:
> `TransactionType::INSERT`, `TransactionType::UPDATE`, etc.

---

## Minimal provider example

```php
namespace Acme\AuditProvider;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;

final class AcmeProvider extends AbstractProvider
{
    public function __construct(private readonly Configuration $config)
    {
        $this->configuration = $config;

        // Register your services in the constructor
        $this->registerStorageService(new AcmeStorageService('default', $config));
    }

    public function supportsStorage(): bool
    {
        return true;
    }

    public function supportsAuditing(): bool
    {
        // This provider only handles storage, not auditing.
        // Another provider (e.g. DoctrineProvider) handles change detection.
        return false;
    }

    public function persist(LifecycleEvent $event): void
    {
        $payload = $event->getPayload();

        // Write to your backend — file, remote API, time-series DB, etc.
        $this->config->getBackend()->write([
            'operation'  => $payload['type'],
            'entity_id'  => $payload['object_id'],
            'changes'    => json_decode($payload['diffs'], true),
            'created_at' => $payload['created_at']->format(\DateTimeInterface::ATOM),
        ]);
    }
}
```

Register it with `Auditor`:

```php
$auditor->registerProvider(new AcmeProvider(new Configuration($backend)));
```

---

## Splitting auditing and storage across providers

You can mix providers freely. A common pattern is to use **DoctrineProvider for auditing**
(change detection) and a **custom provider for storage** (e.g. writing to Elasticsearch):

```php
// DoctrineProvider handles change detection
$doctrineProvider = new DoctrineProvider($doctrineConfig);
$doctrineProvider->registerAuditingService(new AuditingService('default', $entityManager));
$auditor->registerProvider($doctrineProvider);

// Your custom provider handles persistence only
$elasticProvider = new ElasticProvider(new ElasticConfiguration($client));
$auditor->registerProvider($elasticProvider);
```

`auditor` requires **at least one provider** that supports auditing **and** at least one that
supports storage. The two roles can be fulfilled by the same provider or by separate ones.

---

## Long-running processes (workers)

If your application runs in a long-lived process (Symfony Messenger workers, ReactPHP, etc.),
implement Symfony's `ResetInterface` to clear any cached state between messages:

```php
use Symfony\Contracts\Service\ResetInterface;

final class AcmeProvider extends AbstractProvider implements ResetInterface
{
    public function reset(): void
    {
        // Clear prepared statements, connection references, internal caches, etc.
    }
}
```

---

## Packaging your provider

Publishing your provider as a standalone Composer package lets the community use it without
modifying `auditor`'s core.

### Recommended package structure

```
acme/auditor-acme-provider/
├─ src/
│   ├─ AcmeProvider.php
│   ├─ Configuration.php
│   ├─ Service/
│   │   ├─ AuditingService.php   (if applicable)
│   │   └─ StorageService.php
│   └─ DependencyInjection/      (Symfony bundle integration, optional)
│       ├─ AcmeExtension.php
│       └─ Configuration.php
├─ tests/
├─ composer.json
├─ README.md
└─ LICENSE
```

### `composer.json` requirements

```json
{
    "name": "acme/auditor-acme-provider",
    "description": "ACME storage provider for auditor",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.4",
        "damienharper/auditor": "^4.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\AuditProvider\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Acme\\AuditProvider\\Tests\\": "tests/"
        }
    }
}
```

> [!NOTE]
> Do **not** list `damienharper/auditor` under `"replace"` or `"conflict"`. Your package is
> a **consumer** of the core library, not a replacement for it.

### Naming convention

Follow the pattern `{vendor}/auditor-{technology}-provider` (e.g.
`damienharper/auditor-doctrine-provider`, `acme/auditor-elasticsearch-provider`). This makes
the package discoverable and its purpose immediately obvious.

### Packagist keywords

Add these keywords to `composer.json` to improve discoverability:

```json
"keywords": ["audit", "audit-log", "auditor", "provider", "acme"]
```

---

## Optional: Symfony bundle integration

If your provider targets Symfony applications, ship a bundle that wires everything into the
container automatically.

```php
namespace Acme\AuditProvider\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class AcmeAuditExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->register(AcmeProvider::class)
            ->setArguments([new Reference('acme.audit.configuration')])
            ->addTag('auditor.provider');
    }
}
```

The `auditor.provider` tag tells `auditor-bundle` to call
`$auditor->registerProvider($provider)` automatically.

---

## Testing your provider

Test your `persist()` implementation by dispatching a `LifecycleEvent` directly, without
needing a real ORM flush:

```php
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Model\TransactionType;
use PHPUnit\Framework\TestCase;

final class AcmeProviderTest extends TestCase
{
    public function testPersistWritesToBackend(): void
    {
        $backend = $this->createMock(AcmeBackend::class);
        $backend->expects($this->once())->method('write');

        $provider = new AcmeProvider(new Configuration($backend));

        $event = new LifecycleEvent([
            'type'                => TransactionType::INSERT,
            'object_id'           => '42',
            'discriminator'       => null,
            'transaction_hash'    => 'abc123',
            'diffs'               => '{}',
            'extra_data'          => null,
            'blame_id'            => null,
            'blame_user'          => null,
            'blame_user_fqdn'     => null,
            'blame_user_firewall' => null,
            'ip'                  => '127.0.0.1',
            'created_at'          => new \DateTimeImmutable(),
        ]);

        $provider->persist($event);
    }
}
```

---

## Quick reference: interfaces and classes

| Class / Interface | Namespace | Purpose |
|---|---|---|
| `ProviderInterface` | `DH\Auditor\Provider` | Full provider contract |
| `AbstractProvider` | `DH\Auditor\Provider` | Boilerplate base — extend this |
| `ConfigurationInterface` | `DH\Auditor\Provider` | Marker for provider config classes |
| `ServiceInterface` | `DH\Auditor\Provider\Service` | Base service marker |
| `AuditingServiceInterface` | `DH\Auditor\Provider\Service` | Marks a service as change-detector |
| `StorageServiceInterface` | `DH\Auditor\Provider\Service` | Marks a service as storage writer |
| `AbstractService` | `DH\Auditor\Provider\Service` | Boilerplate base for services |
| `LifecycleEvent` | `DH\Auditor\Event` | Event dispatched per audit entry |
| `TransactionType` | `DH\Auditor\Model` | Backed enum of operation types |
