---
name: auditor
description: "Use when writing or reviewing PHP code that depends on damienharper/auditor 5.x (the audit log core) — marking entities with #[Auditable], #[Ignore], #[Security], #[DiffLabel] or #[AuditRoot], reading Entry objects, listening to LifecycleEvent, adding extra data or blame contexts, building histories with HistoryGrouper and ChangeExtractor, or writing a custom provider. For the 5.x line only (schema v2); 4.x APIs differ."
---

# auditor 5.x (damienharper/auditor)

`damienharper/auditor` is the provider-agnostic core of an audit log library for PHP (namespace `DH\Auditor`).
It holds the configuration (user, security, role checker, extra data, blame context), the attributes, the
`LifecycleEvent` bus, the `Entry` read model and the history helpers. It does **not** detect changes, store entries
or query them: **providers** do.

- **No Doctrine code in the core since 5.0.** `DH\Auditor\Provider\Doctrine\*` (DoctrineProvider, Reader, Query,
  filters, schema manager, `audit:*` commands) lives in `damienharper/auditor-doctrine-provider`, same namespace.
- **Symfony apps** install `damienharper/auditor-bundle`: it wires the Auditor, the Doctrine provider, the user,
  security and role checker services, the viewer and the commands. Configure through the bundle, not by hand.
- Plain PHP with Doctrine ORM: core + `auditor-doctrine-provider`, wired manually.

## Packages and versions

| Package | 5.x line works with | Role |
|---|---|---|
| `damienharper/auditor` | 5.x | Core: contracts, attributes, `Entry`, events, histories |
| `damienharper/auditor-doctrine-provider` | 2.x | Doctrine ORM change detection, audit tables, `Reader`, `audit:*` commands |
| `damienharper/auditor-bundle` | 8.x | Symfony integration and viewer |

Requirements of 5.x: PHP >= 8.4, Symfony components >= 8.1. 4.x is maintained for bug and security fixes only.
Check `composer.lock` before assuming which line the project uses: 4.x has no `transactionId`, no `blame` array,
no histories.

```bash
composer require damienharper/auditor-bundle                      # Symfony
composer require damienharper/auditor damienharper/auditor-doctrine-provider  # plain PHP + Doctrine ORM
```

## Attributes (`DH\Auditor\Attribute\`)

```php
use DH\Auditor\Attribute\Auditable;
use DH\Auditor\Attribute\AuditRoot;
use DH\Auditor\Attribute\DiffLabel;
use DH\Auditor\Attribute\Ignore;
use DH\Auditor\Attribute\Security;

#[Auditable(maxAge: 'P1Y')]                    // not repeatable; plain #[Auditable] = no retention policy
#[Security(view: ['ROLE_ADMIN'])]              // roles allowed to read this entity's audits (scope 'view')
#[AuditRoot('order')]                          // entries also belong to the history of $this->order
class OrderLine
{
    #[Ignore]                                  // never written in diffs
    private string $internalNote;

    #[DiffLabel(resolver: StatusLabelResolver::class)]  // stores {"value": 2, "label": "Shipped"}
    private int $status;
}
```

- `#[Auditable(enabled: bool = true, maxAge: ?string = null, maxEntries: ?int = null)]`, class only. Invalid
  retention values throw `DH\Auditor\Exception\InvalidArgumentException`. Retention is applied by providers that
  prune (`audit:clean` in the Doctrine provider).
- `#[Ignore(field: ?string = null)]` and `#[DiffLabel(resolver: string, field: ?string = null)]`: on a property,
  omit `field`; on a class, `field` names the field and the attribute is repeatable (models without one property per
  column, e.g. Eloquent).
- `#[Security(view: array)]`, class only. It only declares roles: a **role checker** enforces them (the bundle's
  does; standalone, write one, see below). `Security::VIEW_SCOPE === 'view'`.
- `#[AuditRoot(path: string)]`, class only, repeatable. `path` is association properties joined by dots
  (`'property'`, `'visit.property'`), validated on instantiation. A null value on the path is skipped. Indexing is
  not transitive. The provider indexes the entries; rebuilding the index of existing entries is a provider command.
- A `#[DiffLabel]` resolver implements `DH\Auditor\Contract\DiffLabelResolverInterface`
  (`__invoke(mixed $value): ?string`, null = no label). It runs during the flush: never flush or write to the
  audited entity manager from it.

## Core configuration (plain PHP)

```php
use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\User\User;
use Symfony\Component\EventDispatcher\EventDispatcher;

$configuration = new Configuration([
    'enabled' => true, 'timezone' => 'UTC',
    'user_provider' => null, 'security_provider' => null, 'role_checker' => null, 'extra_data_provider' => null,
]);
$configuration->setUserProvider(fn (): ?User => $current ? new User((string) $current->getId(), $current->getEmail()) : null);
$configuration->setSecurityProvider(fn (): array => [$clientIp, $firewallName]); // a LIST [ip, firewall], each ?string
$configuration->setRoleChecker(fn (string $entity, string $scope): bool => true); // false: readers throw AccessDeniedException
$configuration->setExtraDataProvider(fn (): ?array => ['request_id' => $requestId]);

$auditor = new Auditor($configuration, new EventDispatcher());
$auditor->registerProvider($provider); // e.g. a DoctrineProvider from auditor-doctrine-provider
```

- `$configuration->enabled` (read) / `enable()` / `disable()`; `$configuration->timezone`.
- User provider returns `?DH\Auditor\User\UserInterface` (`?string $identifier`, `?string $username`); `User` is
  the built-in value object. Its class is recorded as `user_fqdn`. Return null when nobody is authenticated.
- No role checker (null) means everyone can read every audit.

## Blame context: record *how* a change was made

```php
use DH\Auditor\Blame\BlameContext;

$auditor->getConfiguration()->getBlameContextStack()->run(
    new BlameContext('import', 'Import batch #42', ['file' => 'products.csv']), // type, ?label, data, ?user
    fn () => $entityManager->flush(),
);
```

Written in `blame.via` (`['type' => ..., 'label' => ..., 'data' => ...]`). Only the innermost context is written.
Prefer `run()` over `push()`/`pop()`; call `reset()` between messages in long-running workers (the bundle does).
`BlameContext::$user` blames another user than the user provider's. Contexts of type `console` and `messenger`
drive `BlameOrigin`.

## Reading entries: `DH\Auditor\Model\Entry`

Readers come from the provider (Doctrine: `DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader`,
`createQuery()`, `createRootQuery()`). They return `Entry` objects:

```php
$entry->id;              // ?int
$entry->schemaVersion;   // 2 for current rows, 1 for legacy rows not migrated
$entry->type;            // plain string: 'insert' | 'update' | 'remove' | 'associate' | 'dissociate'
$entry->objectId;        // string
$entry->discriminator;   // ?string
$entry->transactionId;   // ?string, a ULID (falls back on legacy transaction_hash)
$entry->userId;          // int|string|null (blame_id)
$entry->blame;           // ?array decoded: username, user_fqdn, user_firewall, ip, via
$entry->username; $entry->userFqdn; $entry->userFirewall; $entry->ip; // shortcuts into $entry->blame
$entry->via;             // ?array blame context
$entry->extraData;       // ?array, ALREADY decoded: never json_decode() it
$entry->createdAt;       // ?DateTimeImmutable
$entry->entity;          // ?string FQCN, set by readers mixing several entities
$entry->rootPath;        // ?string, set when read in the history of an audit root

$entry->getDiffs();      // v2 update/insert/remove: ['field' => ['old' => x, 'new' => y], ...]
                         // v2 associate/dissociate: whole diff (source, target, is_owning_side, join_table)
$entry->getDiffSource(); // ?array ['id', 'class', 'label', 'table'] (null for v1)
$entry->getDiffTarget(); // ?array target of associate/dissociate (null otherwise or v1)
$entry->isSnapshot();    // insert written by a snapshot of an existing entity
$entry->isDeepDiff($f);  // getDiffs()[$f] is a JSON deep diff tree, not an {old, new} pair
Entry::isDiffLeaf($node);// leaf of a deep diff ({old}, {new} or {old, new})
$entry->toArray();       // flat export array (blame_user, blame_via, ...), same keys for v1 and v2
$entry->getSortKey();    // string sorting entries of several tables (strcmp)
```

All properties are read-only. A `#[DiffLabel]` value is stored as `['value' => ..., 'label' => ...]`; an
association value as a summary (`class`, `id`, `label`, ...). Use `ChangeExtractor` instead of parsing diffs by hand.

## `LifecycleEvent` (one per audit entry)

`DH\Auditor\Event\LifecycleEvent`: `getPayload(): array`, `setPayload(array): self`, `$event->entity` (the audited
object, or null). The core dispatches it to `AuditEventSubscriber` (priority `-1_000_000`), which calls `persist()`
on every storage provider. Any listener with a higher priority can change the payload first.

Payload keys required by the core (schema v2): `type`, `object_id`, `discriminator`, `schema_version` (2),
`transaction_id`, `diffs` (JSON string), `extra_data` (JSON string or null), `blame_id`, `blame` (JSON string or
null), `created_at`. The Doctrine provider adds `entity` (FQCN), `table` (audit table) and, for entities with audit
roots, `roots`. There is **no `action` key**: test `$payload['type']`, e.g. against `TransactionType::UPDATE`
(`DH\Auditor\Model\TransactionType`: backed enum `Insert`, `Update`, `Remove`, `Associate`, `Dissociate` plus
string constants `INSERT`, `UPDATE`, ...). The 4.x keys `transaction_hash`, `blame_user`, `blame_user_fqdn`,
`blame_user_firewall` and `ip` are gone.

## Extra data

Two ways, combinable; the listener runs after the provider callable:

```php
$auditor->getConfiguration()->setExtraDataProvider(fn (): ?array => ['route' => $route]); // once per flush, returns ?array

#[AsEventListener(event: LifecycleEvent::class, priority: 10)]
final class OrderExtraData
{
    public function __invoke(LifecycleEvent $event): void
    {
        if (!$event->entity instanceof Order) {
            return;
        }
        $payload = $event->getPayload();
        $existing = null !== $payload['extra_data'] ? json_decode($payload['extra_data'], true, 512, JSON_THROW_ON_ERROR) : [];
        $payload['extra_data'] = json_encode([...$existing, 'channel' => $event->entity->getChannel()], JSON_THROW_ON_ERROR);
        $event->setPayload($payload);
    }
}
```

In the payload, `extra_data` is a JSON **string** (or null), never an array. Read it back with `$entry->extraData`.
The listener runs inside the flush: read freely, never write through the audited entity manager.

## Histories (`DH\Auditor\History\`)

```php
use DH\Auditor\History\ChangeExtractor;
use DH\Auditor\History\HistoryGrouper;
use DH\Auditor\Model\Entry;

$items = HistoryGrouper::byTransaction($entries);        // entries most recent first, as readers return them
$items = new HistoryGrouper(
    static fn (Entry $e): ?string => $e->extraData['request_id'] ?? null, // custom key, null = transaction id
    new ChangeExtractor([Order::class => ['status', 'total', 'lines']]),  // optional: fields kept, in order
)->group($entries);

foreach ($items as $item) {          // HistoryItem
    $item->createdAt; $item->username; $item->userId; $item->origin; $item->via; $item->entries;
    foreach ($item->getChanges() as $change) {   // list<Change>, chronological, mirror associations removed
        $change->kind;               // ChangeKind: Created, Snapshot, Deleted, Set, Changed, Cleared, Added, Removed, Json
        $change->field; $change->old; $change->new; $change->oldLabel; $change->newLabel;
        $change->getValue('status'); $change->getLabel('status');
    }
    $item->getMainChange(); $item->getTransactionIds(); $item->getEntities();
}
```

- `$grouper->groupsByTransaction()`: true when the grouper has no key of its own, so it makes one item per
  transaction; a reader can then read a page of N items as a page of N transactions (whole actions). With a key of
  its own, an item may span several transactions: page by entries instead.

- `BlameOrigin::fromEntry($entry)`: `Console` / `Messenger` (blame context type), else `Http` when the blame has
  a firewall, else `System`. The context wins over the firewall.
- For a `Json` change, `$change->new` is the deep diff tree, not the full value: test `$change->kind` before
  printing.
- Labels for end users: `DH\Auditor\Contract\FieldLabelResolverInterface::resolve(string $entity, string $field)`
  (default `RawFieldLabelResolver`), `EntityLabelResolverInterface::__invoke(object $entity): ?string` (label
  written in `source` / `target` at write time).

## Writing a custom provider

Extend `DH\Auditor\Provider\AbstractProvider`, implement `supportsStorage()`, `supportsAuditing()` and
`persist(LifecycleEvent $event)`, set `$this->configuration` (a `ConfigurationInterface`), and register named
services (`AbstractService` + `StorageServiceInterface` / `AuditingServiceInterface`). Details, payload handling,
error semantics and a test: [references/custom-provider.md](references/custom-provider.md).

## Common mistakes

- Importing `DH\Auditor\Provider\Doctrine\...` without requiring `damienharper/auditor-doctrine-provider` (2.x).
- Wiring the Auditor by hand in a Symfony app instead of configuring `auditor-bundle`.
- `json_decode($entry->extraData)` or `json_decode($entry->blame)`: both are already arrays.
- Reading `$payload['action']`, `$entry->transactionHash`, or payload keys `blame_user` / `ip` (4.x).
- Setting `$payload['extra_data']` (or `blame`, `diffs`) to an array instead of a JSON string.
- Returning `['ip' => ..., 'firewall' => ...]` from the security provider: it must be a list `[$ip, $firewall]`.
- Expecting `#[Security]` to deny access with no role checker configured (null role checker = everyone reads).
- Pushing a `BlameContext` without popping it (use `run()`), or not calling `reset()` in workers.
- Flushing or writing to the audited entity manager from a `LifecycleEvent` listener or a `#[DiffLabel]` resolver.
- Treating `getDiffs()` of an `associate`/`dissociate` entry as field changes, or a deep diff as `{old, new}`:
  check `$entry->type` and `isDeepDiff()`, or use `ChangeExtractor`.
- Assuming `getDiffSource()` / `getDiffTarget()` are non-null: they are null for schema v1 rows.
- Calling `json_decode()` on `$entry->getDiffs()` (already an array) or sorting entries of several tables by
  `id` (use `getSortKey()`).

## Documentation

- Overview: https://damienharper.github.io/auditor-docs/auditor
- Installation: https://damienharper.github.io/auditor-docs/auditor/getting-started/installation
- Quick start: https://damienharper.github.io/auditor-docs/auditor/getting-started/quick-start
- Configuration: https://damienharper.github.io/auditor-docs/auditor/configuration (also `/user-provider`,
  `/security-provider`, `/role-checker`, `/blame-context`)
- Extra data: https://damienharper.github.io/auditor-docs/auditor/extra-data
- Histories: https://damienharper.github.io/auditor-docs/auditor/history
- Custom provider: https://damienharper.github.io/auditor-docs/auditor/providers/custom-provider
- API reference: https://damienharper.github.io/auditor-docs/auditor/api
- Upgrade 4.x to 5.0 (schema v2, payload changes): https://damienharper.github.io/auditor-docs/auditor/upgrade/v5
  (source: `docs/upgrade/v5.md`)
- Doctrine provider (reader, filters, commands): https://damienharper.github.io/auditor-docs/auditor-doctrine-provider
