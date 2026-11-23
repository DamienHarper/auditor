# UPGRADE FROM 4.x to 5.0

This document summarizes the backward incompatible changes introduced in auditor 5.0.

## Removal of the embedded DoctrineProvider

The `DH\Auditor\Provider\Doctrine` namespace has been **removed** from the `damienharper/auditor` package.
All Doctrine ORM functionality now lives exclusively in the dedicated
[`damienharper/auditor-doctrine-provider`](https://github.com/DamienHarper/auditor-doctrine-provider) package.

### Who is affected

- **Symfony users (auditor-bundle)**: no action required. The bundle already depends on
  `damienharper/auditor-doctrine-provider` and all class FQCNs remain identical.
- **Manual integration users**: run the migration command below.

### Migration

```bash
composer require damienharper/auditor-doctrine-provider:^2.0
```

No other code changes are needed — the namespace (`DH\Auditor\Provider\Doctrine\*`) is identical in the
external package.

## `NeedsConversionToAuditableType` moved

The interface `NeedsConversionToAuditableType` has moved from the core package to the doctrine-provider package.

| 4.x | 5.0 |
|-----|-----|
| `DH\Auditor\Transaction\NeedsConversionToAuditableType` | `DH\Auditor\Provider\Doctrine\Transaction\NeedsConversionToAuditableType` |

Update any custom Doctrine type that implements this interface:

```php
// Before (4.x)
use DH\Auditor\Transaction\NeedsConversionToAuditableType;

// After (5.0)
use DH\Auditor\Provider\Doctrine\Transaction\NeedsConversionToAuditableType;
```

## Requirements

| Requirement   | 4.x    | 5.0    |
|---------------|--------|--------|
| PHP           | >= 8.4 | >= 8.4 |
| Symfony       | >= 8.1 | >= 8.1 |
| `symfony/uid` | -      | ^8.1 (new, installed with auditor) |
| Doctrine DBAL / ORM | required | via `auditor-doctrine-provider` 2.0 (DBAL >= 4.6, ORM >= 3.7) |

## Audit format: schema v2

auditor 5.0 introduces a new audit format. **Existing audit tables must be migrated** with
`audit:schema:migrate --force --convert-all` (auditor-doctrine-provider 2.0), then `audit:schema:update --force`.
The migration has no rollback: back up the audit tables and follow the
[auditor-doctrine-provider 2.0 upgrade guide](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/upgrade/v2).

### `Entry`

- `$entry->transactionHash` is **removed**: use `$entry->transactionId` (a ULID, `CHAR(26)`). For rows not migrated
  yet, `Entry::fromArray()` reads the legacy `transaction_hash` into `transactionId`.
- The blame columns are merged into one `blame` JSON column: `$entry->blame` returns it, and `$entry->username`,
  `userFqdn`, `userFirewall` and `ip` keep working.
- `getDiffs()` returns the `changes` of a schema v2 entry (legacy entries are unchanged).
- New: `$entry->schemaVersion`, `getDiffSource()`, `getDiffTarget()`.

### `LifecycleEvent` payload

Custom providers reading `$event->getPayload()` must be updated:

| 4.x | 5.0 |
|-----|-----|
| `transaction_hash` | `transaction_id` (ULID) |
| `blame_user`, `blame_user_fqdn`, `blame_user_firewall`, `ip` | `blame`: JSON string with the `username`, `user_fqdn`, `user_firewall` and `ip` keys (and `via` when a blame context is set) |
| *(new)* | `schema_version` (`2`) |

`diffs` uses an envelope: `source` (summary of the audited entity) and `changes`; association entries carry `source`,
`target`, `is_owning_side` and `join_table`.

## Persistence failures are no longer silent

`AuditEventSubscriber` used to swallow every exception thrown by `ProviderInterface::persist()`.
It now throws a `DH\Auditor\Exception\ProviderException` when no storage provider could persist
the audit entry (the message lists each provider's error, `getPrevious()` returns the first one).
With the Doctrine provider, the flush fails and the database transaction is rolled back.
A failure is still ignored when another storage provider persisted the entry.

## New: blame contexts

A blame context records *how* a change was made (an import, a console command, a Messenger message…) in the
`via` key of the `blame` JSON, next to the user. It is additive: nothing changes until a context is pushed.

```php
use DH\Auditor\Blame\BlameContext;

$auditor->getConfiguration()->getBlameContextStack()->run(
    new BlameContext('import', 'Import batch #42'),
    fn () => $entityManager->flush(),
);

$entry->via; // ['type' => 'import', 'label' => 'Import batch #42']
```

A context may also carry the user to blame instead of the user provider's one. `Entry::toArray()` exports it
as `blame_via`. See [Blame Context](docs/configuration/blame-context.md).

## New: audit roots and histories

Additive. `#[AuditRoot('property')]` (repeatable, a path of association properties) indexes the audit entries of an
entity under a root, so that providers can read the history of a business object across tables.
`DH\Auditor\History\HistoryGrouper` groups entries into actions (by transaction or by any key), with their author,
`BlameOrigin` (http, console, messenger, system) and a list of `Change` that does not depend on the diff format.
`Entry` gains `$entity`, `$rootPath` and `getSortKey()`. See [Histories](docs/history.md).

## Quick migration

```bash
# 1. Install the external doctrine provider
composer require damienharper/auditor-doctrine-provider:^2.0

# 2. Update auditor itself
composer require damienharper/auditor:^5.0

# 3. If you implement NeedsConversionToAuditableType, update the import (see above)

# 4. Migrate the audit tables (back up first, see the provider upgrade guide)
bin/console audit:schema:migrate --force --convert-all
bin/console audit:schema:update --force
```

The complete guide is in [docs/upgrade/v5.md](docs/upgrade/v5.md).

## Need Help?

- [GitHub Issues](https://github.com/DamienHarper/auditor/issues)
