---
description: "Reference of the audit console commands shipped by auditor-doctrine-provider: schema update and migration, cleaning, snapshots, export and root indexing."
---

# Console Commands

> **Manage audit data via the command line**

> [!NOTE]
> These commands are provided by **[auditor-doctrine-provider](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider)** (not auditor core).
> For programmatic usage and standalone registration, see [Schema Management](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/providers/doctrine/schema).

When using auditor-bundle or the standalone `auditor-doctrine-provider`, these console commands are available:

## 📋 Available Commands

| Command                  | Description                                          |
|--------------------------|------------------------------------------------------|
| `audit:schema:update`    | Create or update audit tables to the current schema  |
| `audit:schema:migrate`   | Migrate legacy v1 audit tables to schema v2          |
| `audit:clean`            | Remove old audit entries                             |
| `audit:snapshot`         | Record the current state of existing entities        |
| `audit:export`           | Export audit entries as NDJSON, JSON or CSV          |
| `audit:roots:rebuild`    | Index existing entries under their audit roots       |

## 🛠️ `audit:schema:update`

Creates new audit tables and updates existing ones to match the current schema.

### Usage

```bash
php bin/console audit:schema:update [options]
```

### Options

| Option       | Short | Description                                          |
|--------------|-------|------------------------------------------------------|
| `--dump-sql` |       | Show SQL statements without executing them           |
| `--force`    | `-f`  | Execute the SQL statements                           |

### Examples

```bash
# Preview the SQL that would be executed
php bin/console audit:schema:update --dump-sql

# Output:
# The following SQL statements will be executed:
#
#     CREATE TABLE users_audit (...);
#     CREATE INDEX idx_type_xxx ON users_audit (type);
#     ...

# Execute the changes
php bin/console audit:schema:update --force

# Both: show SQL and execute
php bin/console audit:schema:update --dump-sql --force
```

### When to Run

Run this command:

- After adding `#[Auditable]` to a new entity
- After updating auditor to a new version
- During deployment to ensure schema is up-to-date

> [!TIP]
> The command is safe to run multiple times. Existing tables are updated (not recreated), existing data is preserved, and only necessary changes are applied.

### Exit Codes

| Code | Meaning                                          |
|------|--------------------------------------------------|
| 0    | Success (including "nothing to update")          |
| 1    | Error or missing required options                |

## 🔄 `audit:schema:migrate`

Migrates legacy v1 audit tables to schema v2 (ULID `transaction_id`, unified `blame` JSON column).
Safe to run multiple times; skips already-migrated tables.

> [!CAUTION]
> `audit:schema:update` refuses to run while any audit table still has the legacy v1 schema.
> Always run `audit:schema:migrate --force` first when upgrading from auditor-bundle 7.x or earlier.

### Usage

```bash
php bin/console audit:schema:migrate [options]
```

### Options

| Option                       | Description                                                  |
|------------------------------|--------------------------------------------------------------|
| `--dump-sql`                 | Show SQL statements without executing them                   |
| `--force`, `-f`              | Execute the migration                                        |
| `--convert-all`              | Convert transaction hashes and diffs in one pass (recommended) |
| `--convert-transaction-hash` | Convert SHA-1 `transaction_hash` values to ULID format       |
| `--convert-diffs`            | Convert legacy diffs JSON to the v2 envelope format          |
| `--limit=N`                  | Convert `N` transactions or rows only, keep the legacy columns (resumable), print an estimate of the remaining time |
| `--drop-transaction-hash`    | ⚠️ Drop `transaction_hash` **without** converting it: legacy rows lose their transaction grouping for good |

### Examples

```bash
# Preview the DDL that would be executed
php bin/console audit:schema:migrate --dump-sql

# Full migration in one pass (recommended)
php bin/console audit:schema:migrate --force --convert-all

# Estimate the duration on a copy of the production data
php bin/console audit:schema:migrate --force --convert-all --limit=10000
```

---

## 🧹 `audit:clean`

Removes audit entries older than a specified retention period.

### Usage

```bash
php bin/console audit:clean [options] [keep]
```

### Arguments

| Argument | Default | Description                                           |
|----------|---------|-------------------------------------------------------|
| `keep`   | `P12M`  | Retention period as ISO 8601 duration                 |

### Options

| Option         | Short | Description                                        |
|----------------|-------|----------------------------------------------------|
| `--no-confirm` |       | Skip confirmation prompt                           |
| `--dry-run`    |       | Show what would be deleted without executing       |
| `--dump-sql`   |       | Show the SQL statements                            |
| `--date`       | `-d`  | Clean audits before a specific date                |
| `--exclude`    | `-x`  | Exclude specific entities (can be used multiple times) |
| `--include`    | `-i`  | Include only specific entities (can be used multiple times) |

### Retention Period Format

The `keep` argument uses ISO 8601 duration format:

| Format  | Meaning              |
|---------|----------------------|
| `P12M`  | 12 months            |
| `P1Y`   | 1 year               |
| `P6M`   | 6 months             |
| `P30D`  | 30 days              |
| `P7D`   | 7 days               |
| `P1D`   | 1 day                |
| `PT12H` | 12 hours             |

### Per-Entity Retention Policies

An entity can define its own retention policy, either with `#[Auditable]` or in the bundle configuration
(see the `auditor-bundle` documentation):

```php
use DH\Auditor\Attribute\Auditable;

#[Auditable(maxAge: 'P7Y')]               // keep 7 years of history
class Payment {}

#[Auditable(maxEntries: 10_000)]          // keep the 10,000 most recent entries
class Session {}

#[Auditable(maxAge: 'P1Y', maxEntries: 500)]
class User {}
```

- `maxAge` is an ISO 8601 duration greater than zero; `maxEntries` is a positive integer. Invalid values make the
  attribute fail as soon as it is loaded.
- For an entity with a `maxAge`, that value **replaces** the global cutoff (`keep` argument or `--date` option),
  whether it is shorter or longer.
- Entities without a `maxAge` keep using the global cutoff, exactly as before.
- `maxEntries` applies on top of the age rule: an entry is deleted when it is older than the cutoff **or** not among
  the `maxEntries` most recent entries of the entity (most recent first, ties broken by insertion order).
- With single-table inheritance, the classes of a hierarchy share one audit table: each rule only deletes the
  entries of its own class (`discriminator` column). Entries without a discriminator are left untouched.
- Every policy is validated before anything is deleted: one invalid policy makes the command fail without
  deleting anything.
- Cutoffs are computed in the auditor timezone (the one audit entries are written in).

The policies in use are listed before the confirmation prompt:

```
Per-entity retention policies (they override the global cutoff for these entities):
 --------------------- --------- -------------
  Entity                Max age   Max entries
 --------------------- --------- -------------
  App\Entity\Payment    P7Y       -
  App\Entity\Session    -         10000
 --------------------- --------- -------------
```

### Examples

#### Basic Cleanup

```bash
# Keep last 12 months (default)
php bin/console audit:clean

# Keep last 6 months
php bin/console audit:clean P6M

# Keep last 30 days
php bin/console audit:clean P30D
```

#### Preview Mode

```bash
# See what would be deleted without actually deleting
php bin/console audit:clean P6M --dry-run

# See the SQL statements
php bin/console audit:clean P6M --dump-sql
```

#### Non-Interactive

```bash
# Skip confirmation (useful for cron jobs)
php bin/console audit:clean P12M --no-confirm
```

#### Custom Date

```bash
# Delete audits before a specific date
php bin/console audit:clean --date=2024-01-01

# Combine with no-confirm for scripts
php bin/console audit:clean --date=2023-12-31 --no-confirm
```

#### Entity Filtering

```bash
# Exclude specific entities from cleanup
php bin/console audit:clean -x App\\Entity\\User -x App\\Entity\\Payment

# Include only specific entities
php bin/console audit:clean -i App\\Entity\\Log -i App\\Entity\\Session

# Long form
php bin/console audit:clean --exclude=App\\Entity\\User --include=App\\Entity\\Post
```

### Output

```
You are about to clean audits created before 2024-01-15 14:30:45: 12 classes involved.
 Do you want to proceed? (yes/no) [no]:
 > yes

Starting...
Cleaning audit tables... (users_audit)
 12/12 [============================] 100%
Cleaning audit tables... (done)

 [OK] Success.
```

### Scheduling

For automated cleanup, add to your crontab:

```bash
# Clean up audits older than 12 months, daily at 2 AM
0 2 * * * /path/to/php /path/to/bin/console audit:clean P12M --no-confirm >> /var/log/audit-clean.log 2>&1
```

With `auditor-bundle`, pruning can also be scheduled with Symfony Scheduler (opt-in, see the bundle configuration).
Unlike `audit:clean`, a scheduled run only applies explicit retention policies: entities without a policy are left
untouched unless a default max age is configured.

> [!TIP]
> Use Symfony Messenger Scheduler if available for more robust scheduling.

### Exit Codes

| Code | Meaning                        |
|------|--------------------------------|
| 0    | Success or cancelled by user   |
| 1    | Error                          |

## 📸 `audit:snapshot`

Records the current state of existing entities in their audit tables. Run it once auditing is enabled on a
database that already holds data: the history of each entity then starts with its state at that time
instead of its first change.

### Usage

```bash
php bin/console audit:snapshot [options] [--] [<entities>...]
```

### Arguments

| Argument   | Description                                      |
|------------|--------------------------------------------------|
| `entities` | Audited entities to snapshot (FQCN), or `--all`  |

### Options

| Option         | Short | Default | Description                                                     |
|----------------|-------|---------|-----------------------------------------------------------------|
| `--all`        | `-a`  |         | Snapshot every audited entity                                   |
| `--batch-size` | `-b`  | `500`   | Entities read and written per database transaction              |
| `--force`      | `-f`  |         | Also snapshot entities that already have an audit history       |
| `--dry-run`    |       |         | Count the entities to snapshot, write nothing                   |

### What is written

One `insert` entry per entity, whose diffs carry `"snapshot": true` (`Entry::isSnapshot()` returns `true`):
every audited field with `old: null` and its current value as `new`, as for an insertion. Its `created_at`
is the date of the snapshot and its blame is the console user.

Entities that already have an audit entry are skipped, so the command can be run again safely.
To-many associations are not snapshotted, and enabled Doctrine filters (e.g. soft-delete) apply.

### Examples

```bash
# Preview: how many entities would be snapshotted
php bin/console audit:snapshot --all --dry-run

# Snapshot two entities
php bin/console audit:snapshot 'App\Entity\Post' 'App\Entity\Author'

# Snapshot everything with bigger batches
php bin/console audit:snapshot --all --batch-size=2000
```

### Exit Codes

| Code | Meaning                                         |
|------|-------------------------------------------------|
| 0    | Success (or already running)                    |
| 1    | Error (e.g. entity not audited)                 |
| 2    | Invalid arguments                               |

## 📤 `audit:export`

Exports audit entries as NDJSON (default, recommended for large exports), JSON or CSV, streamed to the standard
output or to a file. Each line or row is an `Entry::toArray()`.

```bash
php bin/console audit:export [options] [entity]
```

| Option        | Description                                              |
|---------------|----------------------------------------------------------|
| `entity`      | Entity FQCN to export, all auditable entities by default |
| `--id`        | Filter by object ID                                      |
| `--from`      | Entries created on or after this date (ISO 8601)         |
| `--to`        | Entries created on or before this date (ISO 8601)        |
| `--blame-id`  | Filter by user ID                                        |
| `--format`    | `ndjson` (default), `json` or `csv`                      |
| `--anonymize` | Mask the user and IP fields                              |
| `--output`    | Write to this file instead of the standard output        |

```bash
php bin/console audit:export App\\Entity\\Post --format=csv --from=2026-01-01 --output=posts.csv
```

---

## 🌳 `audit:roots:rebuild`

Indexes the existing audit entries under their audit roots (`#[AuditRoot]`), after declaring a new root or after
migrating audit tables to schema v2. Only missing index rows are written, so the command can be run again.

```bash
php bin/console audit:roots:rebuild [--entity=FQCN ...] [--dry-run]
```

See [Histories](../history.md) and the
[Doctrine provider history page](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/querying/history#rebuilding-the-index).

---

## 🔒 Command Locking

These commands use Symfony's Lock component to prevent concurrent execution:

```
The command is already running in another process.
```

> [!NOTE]
> This ensures data integrity when running commands in parallel or from cron jobs.

## ✅ Best Practices

1. **Always preview first** - Use `--dump-sql` and `--dry-run` before executing
2. **Backup before cleanup** - Especially for large deletions
3. **Schedule regular cleanups** - Prevent unlimited growth
4. **Monitor disk space** - Audit tables can grow quickly
5. **Consider retention policies** - Different entities may need different retention
6. **Test in staging** - Verify commands work as expected before production

## 🔧 Troubleshooting

### "The command is already running"

> [!WARNING]
> Wait for the other instance to finish, or check for stuck processes.

### Schema update has no changes

> [!NOTE]
> This means your audit tables are already up-to-date.

### Permission denied

> [!IMPORTANT]
> Ensure the database user has CREATE TABLE and ALTER TABLE permissions.

### Timeout during large cleanup

For very large tables, consider:
- Running during off-peak hours
- Increasing PHP timeout
- Breaking into smaller date ranges
