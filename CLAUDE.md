# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

`auditor` is a provider-agnostic PHP library (namespace `DH\Auditor`) that provides the core infrastructure for audit logging. It defines the contracts (`ProviderInterface`, `AuditingServiceInterface`, `StorageServiceInterface`) and core models (`Entry`, `TransactionType`, `LifecycleEvent`) that concrete provider packages implement. Active development targets **5.x** (PHP ≥ 8.4, Symfony ≥ 8.1).

The Doctrine ORM provider lives in the separate [`damienharper/auditor-doctrine-provider`](https://github.com/DamienHarper/auditor-doctrine-provider) package.

## auditor family

| Package | Repository | Default branch | Role |
|---|---|---|---|
| `damienharper/auditor` | `DamienHarper/auditor` | `master` | Core: contracts, models, events |
| `damienharper/auditor-doctrine-provider` | `DamienHarper/auditor-doctrine-provider` | `main` | Doctrine ORM provider: change detection, storage, reader, schema |
| `damienharper/auditor-bundle` | `DamienHarper/auditor-bundle` | `master` | Symfony bundle: configuration, viewer, export |

- Dependency order: core → provider → bundle. Downstream packages require the upstream ones at `@dev`, so their CI runs against the upstream default branch.
- A change spanning several packages lands as one PR per repository, in that order; a downstream PR goes green once the upstream one is merged.
- Each repository has its own `CLAUDE.md`, `Makefile` (`wt-*` worktree targets, Docker matrix) and `composer agent-*` scripts.

## Commands

### Run tests locally
```bash
composer test                          # all tests, no coverage
composer test:coverage                 # with Xdebug coverage
composer testdox                       # testdox format
vendor/bin/phpunit --filter=AuditorTest # single test class
```

### Code quality
```bash
composer cs-fix      # fix style (php-cs-fixer)
composer cs-check    # dry-run style check
composer phpstan     # static analysis (level max)
composer qa          # rector + cs-fix + phpstan
```

### Agent commands (token-efficient, silent output)
When running as an AI agent, use these variants instead — they suppress progress bars and
verbose output while still surfacing errors:
```bash
composer agent-cs-fix      # php-cs-fixer --quiet
composer agent-phpstan     # phpstan --no-progress
composer agent-rector      # rector --no-progress-bar
composer agent-test        # phpunit --no-progress
composer agent-qa          # agent-rector + agent-cs-fix + agent-phpstan
```

### Tool dependency updates
```bash
composer update-tools  # updates php-cs-fixer, phpstan, rector independently
```

### Docker matrix (Makefile)
```bash
make tests php=8.5 sf=8.2               # PHPUnit in Docker (core tests use no database)
make tests args='--filter=EntryTest'
make qa                                 # rector + cs-fix + phpstan in Docker
make help                               # all targets and options
```

### Git worktrees (Makefile)
Parallel work happens in worktrees under `.claude/worktrees/<name>/` (git-ignored), each with its
own `vendor/` and `tools/`. The `wt-*` targets run the `composer agent-*` scripts with the host
PHP; `wt=<name>` can be omitted when `make` runs from inside the worktree.
```bash
make wt-create branch=feature/my-feature   # new branch from the freshly fetched origin/HEAD, then install
make wt-qa wt=feature-my-feature           # composer agent-qa
make wt-test wt=feature-my-feature args='--filter=EntryTest'
make wt-clean wt=feature-my-feature confirm=yes   # refused while the worktree has uncommitted changes
```
Other targets: `wt-init`, `wt-list`, `wt-exec args=<cmd>`, `wt-phpstan`. The Docker targets also
work from a worktree (`make -C .claude/worktrees/<name> tests`).

## Architecture

### Core design

The library provides the event bus and contracts; providers implement the actual auditing and storage logic.

```
Auditor (src/Auditor.php)
  └─ registers ProviderInterface implementations
       └─ (e.g. DoctrineProvider from auditor-doctrine-provider)
```

`Auditor` wires a single `AuditEventSubscriber` (priority -1,000,000) onto Symfony's `EventDispatcherInterface` to catch `LifecycleEvent` and fan it out to registered providers.

### Key classes

| Class | Path | Purpose |
|---|---|---|
| `Auditor` | `src/Auditor.php` | Main entry point; manages providers |
| `ProviderInterface` | `src/Provider/ProviderInterface.php` | Contract all providers must implement |
| `AbstractProvider` | `src/Provider/AbstractProvider.php` | Base provider implementation |
| `Entry` | `src/Model/Entry.php` | Hydrated audit log row (read model) |
| `TransactionType` | `src/Model/TransactionType.php` | Backed string enum for operation types |
| `LifecycleEvent` | `src/Event/LifecycleEvent.php` | Event dispatched per audit entry |
| `AuditEventSubscriber` | `src/EventSubscriber/AuditEventSubscriber.php` | Fans `LifecycleEvent` out to providers |

### PHP Attributes for entity configuration

Attributes live in `src/Attribute/`:
- `#[Auditable]` — marks an entity class as auditable
- `#[Ignore]` — marks a field to skip
- `#[Security]` — restricts who can view audit entries for an entity
- `#[DiffLabel]` — readable value of a field in the diffs
- `#[Mask]` — change of a field recorded without its values (`"***"` or a keyed HMAC, `Mask::apply()`)
- `#[AuditRoot('path')]` — repeatable: the entries of the entity belong to the history of the entity at the end of the path (providers index them)

### Histories (`src/History/`)

`HistoryGrouper` groups entries into `HistoryItem`s (one per transaction, or a custom key), `ChangeExtractor` turns entries into `Change`s (`ChangeKind`), `BlameOrigin` tells a web request from a command, a worker or the system. `Entry::$entity`, `Entry::$rootPath` and `Entry::getSortKey()` support readers that merge several audit tables. See `docs/history.md`.

### Provider contracts

`src/Provider/Service/` defines the service interfaces. Both are empty marker interfaces extending `ServiceInterface` (`getName()` only; `AbstractService` implements it):
- `AuditingServiceInterface` — marks a service a provider registers to detect changes (`registerAuditingService()`); the detection logic and the dispatch of `LifecycleEvent`s live in the provider
- `StorageServiceInterface` — marks a service a provider registers to store entries (`registerStorageService()`); persistence goes through `ProviderInterface::persist()`

## Testing conventions

### Test structure
- Tests mirror `src/` structure under `tests/`
- Core tests cover `Auditor`, `Configuration`, `LifecycleEvent`, `Entry`, `TransactionType`, and the event subscriber

### `Entry` model gotchas
- `$entry->type` is a plain `string` (`'insert'`, `'update'`, etc.)
- `$entry->schemaVersion` is `2` for entries written in the current format, `1` for legacy rows
- `$entry->extraData` is a virtual property that returns `?array` (already `json_decode`'d) — do not call `json_decode()` on it
- `$entry->objectId`, `$entry->transactionId` (a ULID in schema v2), `$entry->userId` are virtual properties backed by snake_case private fields
- `$entry->blame` is the decoded `blame` JSON column (`username`, `user_fqdn`, `user_firewall`, `ip`); `$entry->username`, `$entry->userFqdn`, `$entry->userFirewall` and `$entry->ip` are shortcuts to it
- There is no `transactionHash` any more: a legacy `transaction_hash` column is read into `transactionId` by `Entry::fromArray()`
- `getDiffs()` returns the `changes` of a v2 diff (`field => ['old' => …, 'new' => …]`), or the whole diff of an association/dissociation (no `changes` key); legacy entries return the raw diff. `getDiffSource()` / `getDiffTarget()` read the `source` / `target` blocks; `isDeepDiff()` and `isDiffLeaf()` tell JSON deep diffs from field diffs

### `LifecycleEvent` payload keys (schema v2)
- Core keys, required by `AuditEvent` (an `InvalidArgumentException` otherwise): `type` (string operation), `object_id`, `discriminator`, `schema_version` (`2`), `transaction_id` (ULID), `diffs` (JSON string), `extra_data`, `blame_id`, `blame` (JSON string: `username`, `user_fqdn`, `user_firewall`, `ip`, plus `via` when a blame context is set), `created_at` (`Y-m-d H:i:s.u` string). `normalizePayload()` defaults `extra_data` and `blame` to `null` and `schema_version` to `2` when missing; the other keys must be given.
- Keys added by the Doctrine provider (`TransactionProcessor`), not checked by the core: `entity` (FQCN), `table` (audit table name), and `roots` when the entity declares `#[AuditRoot]`. `DoctrineProvider::persist()` strips them before writing the row.
- No `action` key at the `LifecycleEvent` level — use `$payload['type']`.
- The v1 keys `transaction_hash`, `blame_user`, `blame_user_fqdn`, `blame_user_firewall` and `ip` are gone: the blame context lives in `blame`.
- `diffs` envelope: `source` (summary of the audited entity) and `changes`, plus `deep_diffs` (fields whose changeset is a JSON deep diff) when relevant; association/dissociation diffs carry `source`, `target`, `is_owning_side` and `join_table` instead of `changes`.

## Tooling details

- **php-cs-fixer**, **phpstan**, and **rector** each have their own isolated Composer project under `tools/`. Never run them directly via `vendor/bin/` — use the `tools/` paths or the `composer` scripts.
- PHPStan runs at level max; new code must type-hint everything. Current suppressions for PHPStan 2.x stricter checks are tracked in `phpstan.neon`.
- The `rector.php` config controls automated refactoring rules.
