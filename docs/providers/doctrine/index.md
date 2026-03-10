# DoctrineProvider

DoctrineProvider adds Doctrine ORM support to `auditor`. It automatically tracks insertions,
updates, deletions and many-to-many association changes, and persists audit entries in
dedicated audit tables.

It also provides a **Reader** API to query and filter audit entries directly from your
application.

> [!WARNING]
> The built-in `DoctrineProvider` (namespace `DH\Auditor\Provider\Doctrine\`) is **deprecated**
> since auditor 4.1 and will be removed in v5.0.
>
> Use the standalone **[auditor-doctrine-provider](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/)** package instead — it is a drop-in replacement
> with the same namespace and feature set.

## What it supports

- MySQL, MariaDB, PostgreSQL, SQLite
- Multiple Entity Managers (via `storage_mapper`)
- PHP Attributes (`#[Auditable]`, `#[Ignore]`, `#[Security]`) or programmatic config
- Soft-delete detection (via gedmo/doctrine-extensions)
- Schema management (create/update audit tables)
- Reader + filter system for querying audit entries
