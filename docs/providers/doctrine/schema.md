# Schema Management

This guide covers how to create and manage audit tables in your database.

## Overview

For each audited entity, auditor creates a corresponding audit table to store the change history. The `SchemaManager` class handles all schema operations.

## Audit Table Structure

Each audit table has the following structure:

```sql
CREATE TABLE users_audit (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type                VARCHAR(10)   NOT NULL,
    object_id           VARCHAR(255)  NOT NULL,
    discriminator       VARCHAR(255)  NULL,
    transaction_hash    VARCHAR(40)   NULL,
    diffs               JSON          NULL,
    blame_id            VARCHAR(255)  NULL,
    blame_user          VARCHAR(255)  NULL,
    blame_user_fqdn     VARCHAR(255)  NULL,
    blame_user_firewall VARCHAR(100)  NULL,
    ip                  VARCHAR(45)   NULL,
    created_at          DATETIME(6)   NOT NULL,
    
    INDEX idx_type_xxx           (type),
    INDEX idx_object_id_xxx      (object_id),
    INDEX idx_discriminator_xxx  (discriminator),
    INDEX idx_transaction_xxx    (transaction_hash),
    INDEX idx_blame_id_xxx       (blame_id),
    INDEX idx_created_at_xxx     (created_at)
);
```

### Column Details

| Column                | Type          | Description                                       |
|-----------------------|---------------|---------------------------------------------------|
| `id`                  | INT (PK)      | Auto-increment primary key                        |
| `type`                | VARCHAR(10)   | Action type: insert, update, remove, associate, dissociate |
| `object_id`           | VARCHAR(255)  | The ID of the audited entity                      |
| `discriminator`       | VARCHAR(255)  | Entity class for single-table inheritance         |
| `transaction_hash`    | VARCHAR(40)   | Groups changes from the same flush()              |
| `diffs`               | JSON/TEXT     | JSON-encoded change data                          |
| `blame_id`            | VARCHAR(255)  | User identifier who made the change               |
| `blame_user`          | VARCHAR(255)  | Username/display name                             |
| `blame_user_fqdn`     | VARCHAR(255)  | Full class name of the user object                |
| `blame_user_firewall` | VARCHAR(100)  | Symfony firewall name                             |
| `ip`                  | VARCHAR(45)   | Client IP address (IPv4 or IPv6)                  |
| `created_at`          | DATETIME      | When the audit entry was created                  |

## Using SchemaManager

### Creating the Schema Manager

```php
<?php

use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;

$schemaManager = new SchemaManager($provider);
```

### Getting SQL Statements

Preview the SQL that would be executed:

```php
// Get all SQL statements needed to create/update audit tables
$sqls = $schemaManager->getUpdateAuditSchemaSql();

// Returns: ['storage_name' => ['CREATE TABLE...', 'CREATE INDEX...', ...], ...]
foreach ($sqls as $storageName => $queries) {
    echo "Storage: $storageName\n";
    foreach ($queries as $sql) {
        echo "  $sql\n";
    }
}
```

### Updating the Schema

Execute the SQL statements:

```php
// Update all audit tables
$schemaManager->updateAuditSchema();

// With progress callback
$schemaManager->updateAuditSchema(null, function (array $progress) {
    echo sprintf("Progress: %d/%d\n", $progress['current'], $progress['total']);
});

// With custom SQL (preview first, then execute)
$sqls = $schemaManager->getUpdateAuditSchemaSql();
// ... review SQLs ...
$schemaManager->updateAuditSchema($sqls);
```

## Console Commands

When using auditor-bundle, two commands are available:

### Update Schema Command

Creates or updates audit tables:

```bash
# Preview SQL statements (no changes)
php bin/console audit:schema:update --dump-sql

# Execute the changes
php bin/console audit:schema:update --force

# Both: show SQL and execute
php bin/console audit:schema:update --dump-sql --force
```

### Clean Audit Logs Command

Removes old audit entries:

```bash
# Remove audits older than 12 months (default)
php bin/console audit:clean

# Remove audits older than 6 months
php bin/console audit:clean P6M

# Remove audits older than 30 days
php bin/console audit:clean P30D

# Remove audits before a specific date
php bin/console audit:clean --date=2024-01-01

# Preview only (dry run)
php bin/console audit:clean --dry-run

# Skip confirmation
php bin/console audit:clean --no-confirm

# Show SQL statements
php bin/console audit:clean --dump-sql

# Include/exclude specific entities
php bin/console audit:clean --include=App\\Entity\\Post
php bin/console audit:clean --exclude=App\\Entity\\User
```

## Table Naming

### Default Naming

By default, audit tables are named: `{entity_table}_audit`

| Entity Table  | Audit Table        |
|---------------|--------------------|
| `users`       | `users_audit`      |
| `blog_posts`  | `blog_posts_audit` |

### Custom Prefix/Suffix

```php
use DH\Auditor\Provider\Doctrine\Configuration;

// Prefix only: audit_users
$config = new Configuration([
    'table_prefix' => 'audit_',
    'table_suffix' => '',
]);

// Suffix only: users_history
$config = new Configuration([
    'table_prefix' => '',
    'table_suffix' => '_history',
]);

// Both: audit_users_log
$config = new Configuration([
    'table_prefix' => 'audit_',
    'table_suffix' => '_log',
]);
```

### Schema/Namespace Support

For databases with schema support (PostgreSQL):

```php
// Entity in schema: myschema.users
// Audit table: myschema.users_audit
```

## Schema Changes

### Adding New Audited Entity

When you add `#[Auditable]` to a new entity:

1. Run `audit:schema:update --dump-sql` to preview
2. Run `audit:schema:update --force` to create the table

### Removing Audited Entity

When you remove `#[Auditable]` from an entity:

- The audit table is **not** automatically deleted
- Historical data is preserved
- Manually drop the table if needed

### Modifying Entity Fields

Adding or removing fields from an entity:

- **No schema changes needed** - Diffs are stored as JSON
- New fields will appear in future audits
- Removed fields won't appear in new audits
- Historical audits retain their original data

## Programmatic Schema Operations

### Create a Single Audit Table

```php
use Doctrine\DBAL\Schema\Schema;

// Create audit table for a specific entity
$schema = $schemaManager->createAuditTable(User::class);

// Or with an existing schema
$existingSchema = $connection->createSchemaManager()->introspectSchema();
$schema = $schemaManager->createAuditTable(User::class, $existingSchema);
```

### Update a Single Audit Table

```php
// Ensure an existing audit table has the correct structure
$schema = $schemaManager->updateAuditTable(User::class);
```

### Get Auditable Tables

```php
// Get all auditable entity tables for an EntityManager
$tables = $schemaManager->getAuditableTableNames($entityManager);
// Returns: ['App\Entity\User' => 'users', 'App\Entity\Post' => 'posts', ...]
```

### Collect Auditable Entities

```php
// Group auditable entities by storage service
$repository = $schemaManager->collectAuditableEntities();
// Returns: ['storage_name' => ['App\Entity\User' => 'users', ...], ...]
```

## Database-Specific Notes

### MySQL/MariaDB

- JSON column type used for `diffs`
- InnoDB engine recommended for transactions
- Consider ROW_FORMAT=DYNAMIC for large diffs

### PostgreSQL

- Native JSON/JSONB support
- Schema (namespace) support
- Consider using JSONB for better performance

### SQLite

- `diffs` stored as TEXT (no native JSON)
- Useful for development/testing
- Not recommended for production with high audit volume

## Performance Considerations

1. **Index usage** - All common query columns are indexed
2. **JSON storage** - Use native JSON types when available
3. **Partitioning** - Consider table partitioning for very large audit logs
4. **Archiving** - Use `audit:clean` or implement custom archiving
5. **Separate database** - Consider storing audits in a dedicated database

## Troubleshooting

### "Table already exists"

The schema manager checks for existing tables before creating. If you get conflicts:

1. Drop the audit table manually
2. Run `audit:schema:update --force`

### Column Type Mismatch

If column types don't match (e.g., TEXT vs JSON):

1. Back up your data
2. Run `audit:schema:update --force` to update column types

### Missing Indices

Run `audit:schema:update --force` to add missing indices.

## Next Steps

- [Querying Audits](../../querying/index.md)
- [Console Commands](../../commands/index.md)
- [Multi-Database Setup](multi-database.md)
