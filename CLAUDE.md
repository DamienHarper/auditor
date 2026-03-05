# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

`auditor` is a PHP library (namespace `DH\Auditor`) that provides audit logging for Doctrine ORM. It tracks inserts, updates, deletes, and many-to-many association changes. Active development is on **4.x** (PHP ≥ 8.4, Symfony ≥ 8.0, Doctrine DBAL ≥ 4.0, Doctrine ORM ≥ 3.2).

## Commands

### Run tests locally (SQLite, no Docker)
```bash
composer test                          # all tests, no coverage
composer test:coverage                 # with Xdebug coverage
composer testdox                       # testdox format
vendor/bin/phpunit --filter=ReaderTest # single test class
vendor/bin/phpunit --filter=testMethodName # single test method
```

### Run tests via Docker (recommended for CI parity)
```bash
make tests                             # defaults: PHP 8.4, Symfony 8.0, SQLite
make tests db=mysql                    # or pgsql, mariadb
make tests php=8.5
make tests args='--filter=ReaderTest'
```

### Code quality
```bash
composer cs-fix      # fix style (php-cs-fixer)
composer cs-check    # dry-run style check
composer phpstan     # static analysis (level max)
composer qa          # rector + cs-fix + phpstan
```

### Benchmarks
```bash
composer bench                         # run benchmarks
BENCH_N=200 make bench                 # with 200 entities
make bench args='--tag=before'         # store baseline
make bench args='--ref=before'         # compare to baseline
```

### Tool dependency updates
```bash
composer update-tools  # updates php-cs-fixer, phpstan, rector independently
```

## Architecture

### Core design

The library separates two responsibilities:
- **Auditing services** — hook into Doctrine ORM's `onFlush` event to detect changes
- **Storage services** — persist the captured audit entries to the database

Both are provided by **Providers** (`ProviderInterface`). The only built-in provider is `DoctrineProvider`, which implements both roles.

```
Auditor (src/Auditor.php)
  └─ registers ProviderInterface implementations
       └─ DoctrineProvider (src/Provider/Doctrine/DoctrineProvider.php)
            ├─ AuditingService  → EntityManager used to listen for changes
            ├─ StorageService   → EntityManager used to write audit rows
            └─ TransactionManager → TransactionProcessor → dispatches LifecycleEvent
```

`Auditor` wires a single `AuditEventSubscriber` (priority -1,000,000) onto Symfony's `EventDispatcherInterface` to catch `LifecycleEvent` and fan it out to storage providers.

### Audit flow

1. `DoctrineSubscriber::onFlush()` is called by Doctrine's event system
2. `TransactionManager::populate()` collects changesets from the UoW
3. An `AuditorMiddleware`/`AuditorDriver` DBAL middleware queues a flusher callback
4. After the DB transaction commits, the flusher calls `TransactionManager::process()`
5. `TransactionProcessor` dispatches one `LifecycleEvent` per change
6. `DoctrineProvider::persist()` executes a prepared INSERT into the audit table

### Key classes

| Class | Path | Purpose |
|---|---|---|
| `Auditor` | `src/Auditor.php` | Main entry point; manages providers |
| `DoctrineProvider` | `src/Provider/Doctrine/DoctrineProvider.php` | Auditing + storage provider |
| `Configuration` | `src/Provider/Doctrine/Configuration.php` | Provider config (table prefix/suffix, entities, viewer, mapper) |
| `Entry` | `src/Model/Entry.php` | Hydrated audit log row (read model) |
| `TransactionType` | `src/Model/TransactionType.php` | Backed string enum for operation types |
| `Reader` | `src/Provider/Doctrine/Persistence/Reader/Reader.php` | Query audit logs |
| `SchemaManager` | `src/Provider/Doctrine/Persistence/Schema/SchemaManager.php` | Create/update audit tables |

### Audit table naming

Default: `{table_prefix}{entity_table}_audit{table_suffix}`. Configured via `table_prefix` and `table_suffix` options on `Configuration` (default suffix = `_audit`, prefix = `''`).

### PHP Attributes for entity configuration

Three attributes live in `src/Attribute/` (canonical namespace since 4.x):
- `#[Auditable]` — marks an entity class as auditable
- `#[Ignore]` — marks a field to skip
- `#[Security]` — restricts who can view audit entries for an entity

The old `src/Provider/Doctrine/Auditing/Attribute/` classes still exist but are **deprecated** — they simply extend the core ones.

Entities can also be configured programmatically via `Configuration::setEntities()`.

### Multi-EM support

When using multiple Entity Managers, a `storage_mapper` callable must be configured on `DoctrineProvider` to route audit rows to the correct storage EM.

## Testing conventions

### Test structure
- Tests mirror `src/` structure under `tests/`
- All test fixtures (entity classes used only in tests) live in `tests/Provider/Doctrine/Fixtures/`
- Issue-specific fixtures are in subdirectories named `Issue{N}/`

### Schema setup traits (critical pattern)

Integration tests use a layered trait system:

```
SchemaSetupTrait         ← base: creates provider, schemas, seeds data
  └─ BlogSchemaSetupTrait ← configures Blog entities (Author/Post/Comment/Tag)
  └─ DefaultSchemaSetupTrait ← configures a minimal default set
```

`SchemaSetupTrait::setUp()` calls in order:
1. `createAndInitDoctrineProvider()` — instantiates `DoctrineProvider`
2. `configureEntities()` — registers audited entities (override this, not `setUp()`)
3. `setupEntitySchemas()` — creates ORM tables
4. `setupAuditSchemas()` — creates audit tables (based on what `configureEntities()` set)
5. `setupEntities()` — seeds data

**Critical**: Override `configureEntities()` (not `setUp()`) to declare which entities are audited. If a test class defines its own `setUp()`, the trait's `setUp()` is bypassed and audit tables are never created.

### `Entry` model gotchas
- `$entry->type` is a plain `string` (`'insert'`, `'update'`, etc.)
- `$entry->extraData` is a virtual property that returns `?array` (already `json_decode`'d) — do not call `json_decode()` on it
- `$entry->objectId`, `$entry->transactionHash`, `$entry->userId`, `$entry->username` etc. are virtual properties backed by snake_case private fields

### `LifecycleEvent` payload keys
`entity` (FQCN), `table`, `type` (string operation), `object_id`, `discriminator`, `transaction_hash`, `diffs`, `extra_data`, `blame_id`, `blame_user`, `blame_user_fqdn`, `blame_user_firewall`, `ip`, `created_at`. No `action` key at the `LifecycleEvent` level — use `$payload['type']`.

## Tooling details

- **php-cs-fixer**, **phpstan**, and **rector** each have their own isolated Composer project under `tools/`. Never run them directly via `vendor/bin/` — use the `tools/` paths or the `composer` scripts.
- PHPStan runs at level max; new code must type-hint everything. Current suppressions for PHPStan 2.x stricter checks are tracked in `phpstan.neon`.
- The `rector.php` config controls automated refactoring rules.
