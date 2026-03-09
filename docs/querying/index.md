# Querying Audits

> **This section covers provider-specific querying APIs.**

Querying audit logs is handled by each provider independently. The `auditor` core library
does not provide a query API — it only defines the audit flow and storage contracts.

## DoctrineProvider

If you are using **DoctrineProvider** (whether the built-in deprecated version or the
standalone `auditor-doctrine-provider` package), the full Reader and Query API documentation
is available in the provider's dedicated docs:

- [Querying Audits →](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/querying/)

The DoctrineProvider exposes a powerful `Reader` class with:
- `createQuery(Entity::class, $options)` — paginated queries with filters
- `DateRangeFilter`, `RangeFilter`, `SimpleFilter`, `NullFilter` — composable filter system
- `paginate()` — structured pagination result
- `getAuditsByTransactionHash()` — cross-entity transaction lookup

## Other providers

Refer to your provider's own documentation for its querying capabilities.
