# Console Commands

> **Manage audit data via the command line**

When using the auditor-bundle or standalone, the library provides console commands for managing audit data.

## 📋 Available Commands

| Command                 | Description                            |
|-------------------------|----------------------------------------|
| `audit:schema:update`   | Create or update audit table schemas   |
| `audit:clean`           | Remove old audit entries               |

## 🛠️ audit:schema:update

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

## 🧹 audit:clean

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

> [!TIP]
> Use Symfony Messenger Scheduler if available for more robust scheduling.

### Exit Codes

| Code | Meaning                        |
|------|--------------------------------|
| 0    | Success or cancelled by user   |
| 1    | Error                          |

## 💻 Programmatic Usage

Both commands can be used programmatically:

### Schema Update

```php
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;

$schemaManager = new SchemaManager($provider);

// Get SQL
$sqls = $schemaManager->getUpdateAuditSchemaSql();

// Execute
$schemaManager->updateAuditSchema();

// With progress callback
$schemaManager->updateAuditSchema(null, function (array $progress) {
    // Handle progress
});
```

### Cleaning Audits

```php
use Doctrine\DBAL\Connection;

/** @var Connection $connection */
$connection = $storageService->getEntityManager()->getConnection();

$queryBuilder = $connection->createQueryBuilder();
$queryBuilder
    ->delete('users_audit')
    ->where('created_at < :until')
    ->setParameter('until', (new \DateTime('-12 months'))->format('Y-m-d H:i:s'))
;

$deleted = $queryBuilder->executeStatement();
```

## 🔒 Command Locking

Both commands use Symfony's Lock component to prevent concurrent execution:

```
The command is already running in another process.
```

> [!NOTE]
> This ensures data integrity when running commands in parallel or from cron jobs.

## 📦 Registering Commands (Standalone)

When not using auditor-bundle, register commands manually:

```php
use DH\Auditor\Provider\Doctrine\Persistence\Command\CleanAuditLogsCommand;
use DH\Auditor\Provider\Doctrine\Persistence\Command\UpdateSchemaCommand;
use Symfony\Component\Console\Application;

$application = new Application();

// Schema update command
$updateCommand = new UpdateSchemaCommand();
$updateCommand->setAuditor($auditor);
$application->add($updateCommand);

// Clean command
$cleanCommand = new CleanAuditLogsCommand();
$cleanCommand->setAuditor($auditor);
$application->add($cleanCommand);

$application->run();
```

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
