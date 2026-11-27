# Writing a custom auditor provider (5.x)

A provider plugs auditor into a technology. It can **audit** (detect changes and dispatch `LifecycleEvent`s),
**store** (persist each `LifecycleEvent`), or both. Different providers can share the roles, e.g. the Doctrine
provider detects changes and a custom provider stores entries in Elasticsearch.

Published guide: https://damienharper.github.io/auditor-docs/auditor/providers/custom-provider

## Contracts

| Type | FQCN | Notes |
|---|---|---|
| Provider contract | `DH\Auditor\Provider\ProviderInterface` | Implement through `AbstractProvider` |
| Base provider | `DH\Auditor\Provider\AbstractProvider` | You implement `supportsStorage()`, `supportsAuditing()`, `persist()` |
| Provider configuration | `DH\Auditor\Provider\ConfigurationInterface` | Empty marker interface |
| Service base | `DH\Auditor\Provider\Service\AbstractService` | Constructor takes the service name; `getName()` |
| Service markers | `DH\Auditor\Provider\Service\AuditingServiceInterface`, `StorageServiceInterface` | Both extend `ServiceInterface` (`getName(): string`) |
| Event | `DH\Auditor\Event\LifecycleEvent` | `getPayload()`, `setPayload()`, `$entity` |
| Operation types | `DH\Auditor\Model\TransactionType` | Enum cases and string constants |

`AbstractProvider` provides `setAuditor()`, `getAuditor()` (throws `ProviderException` before registration),
`isRegistered()`, `getConfiguration()` (returns the protected `$configuration`, which you must set),
`registerStorageService()` / `registerAuditingService()` (throw `ProviderException` when the provider does not
support that role or the service name is already taken) and the matching getters.

## Minimal storage provider

```php
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\ConfigurationInterface;
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Provider\Service\StorageServiceInterface;

final class AcmeConfiguration implements ConfigurationInterface
{
    public function __construct(public readonly AcmeBackend $backend) {}
}

final class AcmeStorageService extends AbstractService implements StorageServiceInterface {}

final class AcmeProvider extends AbstractProvider
{
    public function __construct(private readonly AcmeConfiguration $config)
    {
        $this->configuration = $config;
        $this->registerStorageService(new AcmeStorageService('default'));
    }

    public function supportsStorage(): bool { return true; }

    public function supportsAuditing(): bool { return false; }

    public function persist(LifecycleEvent $event): void
    {
        $payload = $event->getPayload();

        $this->config->backend->write([
            'type' => $payload['type'],                    // 'insert', 'update', ... (no 'action' key)
            'object_id' => $payload['object_id'],
            'transaction_id' => $payload['transaction_id'], // ULID
            'diffs' => json_decode($payload['diffs'], true, 512, JSON_THROW_ON_ERROR),
            'blame_id' => $payload['blame_id'],
            'blame' => null !== $payload['blame'] ? json_decode($payload['blame'], true, 512, JSON_THROW_ON_ERROR) : null,
            'extra_data' => $payload['extra_data'],        // JSON string or null
            'created_at' => $payload['created_at'],
        ]);
    }
}

$auditor->registerProvider(new AcmeProvider(new AcmeConfiguration($backend)));
```

`Auditor::registerProvider()` throws `ProviderException` when the provider supports neither role, and keys
providers by class (`getProvider(AcmeProvider::class)`).

## Payload

Keys the core requires (constructing a `LifecycleEvent` or calling `setPayload()` without them throws
`DH\Auditor\Exception\InvalidArgumentException`; `extra_data`, `blame` and `schema_version` are defaulted to null,
null and 2 when missing):

| Key | Content |
|---|---|
| `type` | `'insert'`, `'update'`, `'remove'`, `'associate'`, `'dissociate'` |
| `object_id` | identifier of the audited object, as a string |
| `discriminator` | inheritance discriminator or null |
| `schema_version` | `2` |
| `transaction_id` | ULID shared by the entries of one flush |
| `diffs` | JSON string: `source` + `changes` (+ `deep_diffs`), or `source` + `target` + `is_owning_side` + `join_table` for associations |
| `extra_data` | JSON string or null |
| `blame_id` | user identifier or null |
| `blame` | JSON string (`username`, `user_fqdn`, `user_firewall`, `ip`, optional `impersonator` and `via`) or null |
| `created_at` | creation date; the Doctrine provider sends a `Y-m-d H:i:s.u` string in the auditor timezone |

The Doctrine provider also sends `entity` (FQCN), `table` (audit table name) and `roots` (audit roots) when
relevant. Do not rely on them in a provider meant to work with other auditing providers.

## Auditing providers

An auditing provider detects changes in its technology and dispatches one event per entry:

```php
$this->getAuditor()->getEventDispatcher()->dispatch(new LifecycleEvent($payload, $entity));
```

It should honour `$this->getAuditor()->getConfiguration()->enabled` and `$this->getAuditor()->isAuditingEnabled($this)`
(otherwise `disableAuditing()` has no effect), take the user of the current blame context
(`getBlameContextStack()->current()?->user`) before the one of `getUserProvider()`, read the
`[ip, firewall]` list from `getSecurityProvider()`, extra data from `getExtraDataProvider()` (an `?array` to
JSON-encode) and the current blame context from `getBlameContextStack()->current()` (`toArray()` gives the `via`
value). It reads entity configuration from the attributes in `DH\Auditor\Attribute\`. `DH\Auditor\Model\Transaction`
and the DTOs in `DH\Auditor\Event\Dto\` are available to collect the changes of one unit of work, and
`DH\Auditor\Transaction\TransactionProcessorInterface` / `TransactionManagerInterface` /
`TransactionHydratorInterface` describe the processing steps.

## Errors

Throw any `\Exception` from `persist()` when the entry cannot be written. `AuditEventSubscriber` still hands the
entry to the other storage providers and throws a `ProviderException` (message listing every failure,
`getPrevious()` = first failure) only when none of them persisted it. That exception reaches the code that
dispatched the event (with Doctrine, the flush fails and the transaction is rolled back).

## Symfony and long-running processes

- With auditor-bundle, tag the provider service `dh_auditor.provider`: the bundle calls `registerProvider()`.
- Implement `Symfony\Contracts\Service\ResetInterface` to clear per-request state in workers.

## Testing `persist()`

```php
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Model\TransactionType;

$event = new LifecycleEvent([
    'type' => TransactionType::INSERT,
    'object_id' => '42',
    'discriminator' => null,
    'schema_version' => 2,
    'transaction_id' => '01JABCDEFGHJKMNPQRSTVWXYZ0',
    'diffs' => '{"source":{"id":"42","class":"App\\\\Entity\\\\Post","label":"Post#42","table":"post"},"changes":{}}',
    'extra_data' => null,
    'blame_id' => null,
    'blame' => null,
    'created_at' => '2026-01-01 12:00:00.000000',
]);
$provider->persist($event);
```
