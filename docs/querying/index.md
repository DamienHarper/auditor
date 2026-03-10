# Querying Audits

Querying audit entries is **provider-specific**: each provider implements its own reader and
filter system adapted to its underlying storage technology.

The `auditor` core does not define a query API — it only orchestrates providers and dispatches
audit events.

## What providers typically offer

Providers that implement a reader expose at minimum:

- A **Reader** — factory for queries, pagination, cross-entity transaction lookups
- A **Query** builder — filters, ordering, limit/offset
- A set of **Filters** — narrow results by field value, date range, numeric range, JSON content, etc.

## DoctrineProvider

The **auditor-doctrine-provider** package ships a full Reader and filter system backed by
Doctrine DBAL.

→ **[Querying Audits (auditor-doctrine-provider)](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/querying/)**

Highlights:

| Feature | Class |
|---------|-------|
| Reader factory + pagination | `Reader` |
| Query builder | `Query` |
| Exact value match | `SimpleFilter` |
| Date range | `DateRangeFilter` |
| Numeric range | `RangeFilter` |
| NULL check | `NullFilter` |
| JSON column content | `JsonFilter` |

## Other providers

Refer to your provider's own documentation for its querying capabilities.
