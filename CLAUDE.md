# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

`auditor` is a provider-agnostic PHP library (namespace `DH\Auditor`) that provides the core infrastructure for audit logging. It defines the contracts (`ProviderInterface`, `AuditingServiceInterface`, `StorageServiceInterface`) and core models (`Entry`, `TransactionType`, `LifecycleEvent`) that concrete provider packages implement. Active development targets **5.x** (PHP ≥ 8.4, Symfony ≥ 8.0).

The Doctrine ORM provider lives in the separate [`damienharper/auditor-doctrine-provider`](https://github.com/DamienHarper/auditor-doctrine-provider) package.

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

### Tool dependency updates
```bash
composer update-tools  # updates php-cs-fixer, phpstan, rector independently
```

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

Three attributes live in `src/Attribute/`:
- `#[Auditable]` — marks an entity class as auditable
- `#[Ignore]` — marks a field to skip
- `#[Security]` — restricts who can view audit entries for an entity

### Provider contracts

`src/Provider/Service/` defines abstract service interfaces:
- `AuditingServiceInterface` — detects changes and produces `LifecycleEvent`s
- `StorageServiceInterface` — persists audit entries

## Testing conventions

### Test structure
- Tests mirror `src/` structure under `tests/`
- Core tests cover `Auditor`, `Configuration`, `LifecycleEvent`, `Entry`, `TransactionType`, and the event subscriber

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
