# DoctrineProvider Configuration

> **All configuration options for the DoctrineProvider**

This page covers all configuration options for the DoctrineProvider.

## ⚙️ Configuration Options

```php
<?php

use DH\Auditor\Provider\Doctrine\Configuration;

$configuration = new Configuration([
    'table_prefix' => '',
    'table_suffix' => '_audit',
    'ignored_columns' => [],
    'entities' => [],
    'viewer' => true,
    'storage_mapper' => null,
    'utf8_convert' => false,
]);
```

### Options Reference

| Option            | Type                | Default       | Description                                      |
|-------------------|---------------------|---------------|--------------------------------------------------|
| `table_prefix`    | `string`            | `''`          | Prefix for audit table names                     |
| `table_suffix`    | `string`            | `'_audit'`    | Suffix for audit table names                     |
| `ignored_columns` | `array`             | `[]`          | Columns to ignore globally across all entities   |
| `entities`        | `array`             | `[]`          | Entity-specific configuration                    |
| `viewer`          | `bool\|array`       | `true`        | Enable/configure the audit viewer                |
| `storage_mapper`  | `callable\|null`    | `null`        | Callback to route audits to storage services     |
| `utf8_convert`    | `bool`              | `false`       | Re-encode diff strings to UTF-8 before persisting. Enable only if your data may contain non-UTF-8 strings (legacy encodings). |

## 📛 Table Naming

Audit tables are named based on the original entity table name with optional prefix and suffix:

```
[table_prefix] + [original_table_name] + [table_suffix]
```

### Examples

```php
// Default: users → users_audit
$configuration = new Configuration([
    'table_suffix' => '_audit',
]);

// Custom prefix: users → audit_users
$configuration = new Configuration([
    'table_prefix' => 'audit_',
    'table_suffix' => '',
]);

// Both: users → audit_users_log
$configuration = new Configuration([
    'table_prefix' => 'audit_',
    'table_suffix' => '_log',
]);
```

## 🚫 Ignored Columns

### Global Ignored Columns

Columns that should never be audited across all entities:

```php
$configuration = new Configuration([
    'ignored_columns' => [
        'createdAt',
        'updatedAt',
        'deletedAt',  // Useful with soft delete
    ],
]);
```

### Per-Entity Ignored Columns

Override ignored columns for specific entities:

```php
$configuration = new Configuration([
    'entities' => [
        App\Entity\User::class => [
            'ignored_columns' => ['password', 'salt', 'resetToken'],
        ],
    ],
]);
```

## 🏷️ Entity Configuration

Configure entities programmatically instead of (or in addition to) using attributes:

```php
$configuration = new Configuration([
    'entities' => [
        App\Entity\User::class => [
            'enabled' => true,
            'ignored_columns' => ['password'],
            'roles' => [
                'view' => ['ROLE_ADMIN'],  // Who can view these audits
            ],
        ],
        App\Entity\Post::class => [
            'enabled' => true,
        ],
        App\Entity\Comment::class => [
            'enabled' => false,  // Disabled, even if has #[Auditable]
        ],
    ],
]);
```

### Entity Options

| Option            | Type       | Default | Description                                  |
|-------------------|------------|---------|----------------------------------------------|
| `enabled`         | `bool`     | `true`  | Whether auditing is enabled for this entity  |
| `ignored_columns` | `array`    | `[]`    | Columns to ignore for this entity            |
| `roles`           | `array`    | `null`  | View permissions (`['view' => ['ROLE_X']]`)  |

## 👁️ Viewer Configuration

The `viewer` option controls the built-in audit viewer (when using auditor-bundle):

```php
// Enable with defaults
$configuration = new Configuration([
    'viewer' => true,
]);

// Disable
$configuration = new Configuration([
    'viewer' => false,
]);

// Custom page size
$configuration = new Configuration([
    'viewer' => [
        'enabled' => true,
        'page_size' => 25,  // Default is 50
    ],
]);
```

## 🔡 UTF-8 Conversion

By default, auditor **does not** re-encode diff values to UTF-8. Since DBAL 4 requires PHP 8.4+ and modern database connections are always UTF-8, this conversion is unnecessary for most applications.

If your application stores data from legacy sources that may contain non-UTF-8 byte sequences, enable this option:

```php
$configuration = new Configuration([
    'utf8_convert' => true,
]);
```

> [!WARNING]
> Enabling `utf8_convert` traverses the entire diff array recursively on every audit entry and may have a noticeable performance impact on entities with large changesets.

> [!NOTE]
> **Upgrading from a version that predates this option?** The conversion was previously always applied. If your data relies on this behavior, set `'utf8_convert' => true` to restore it.

## 🗄️ Storage Mapper

When using multiple storage databases, the storage mapper determines where to store audits for each entity.

> [!NOTE]
> See [Multi-Database Configuration](multi-database.md) for detailed information.

```php
$configuration = new Configuration([
    'storage_mapper' => function (string $entity, array $storageServices) {
        // Route User audits to a dedicated storage
        if ($entity === App\Entity\User::class) {
            return $storageServices['users_audit_storage'];
        }
        
        // Default storage for everything else
        return $storageServices['default'];
    },
]);
```

## 🔄 Runtime Configuration

### Enable/Disable Auditing for an Entity

```php
// Disable auditing for User
$configuration->disableAuditFor(App\Entity\User::class);

// Re-enable auditing for User
$configuration->enableAuditFor(App\Entity\User::class);
```

### Enable/Disable Viewer

```php
// Disable the viewer
$configuration->disableViewer();

// Enable the viewer
$configuration->enableViewer();

// Check if viewer is enabled
if ($configuration->isViewerEnabled()) {
    // ...
}
```

### Change Viewer Page Size

```php
$configuration->setViewerPageSize(100);
$pageSize = $configuration->getViewerPageSize();
```

### Set Storage Mapper

```php
$configuration->setStorageMapper(function (string $entity, array $services) {
    // Custom routing logic
    return $services['default'];
});
```

## 🔍 Checking Audit Status

The provider offers methods to check entity audit status:

```php
// Check if an entity class is configured for auditing
$provider->isAuditable(App\Entity\User::class);  // bool

// Check if an entity is currently being audited (respects enabled flag)
$provider->isAudited(App\Entity\User::class);  // bool

// Check if a specific field is being audited
$provider->isAuditedField(App\Entity\User::class, 'email');  // bool
$provider->isAuditedField(App\Entity\User::class, 'password');  // false (if ignored)
```

## 🔀 Configuration Merging

> [!TIP]
> Configuration from attributes and programmatic config are merged intelligently.

1. Attributes on entities are loaded first
2. Programmatic configuration overrides attribute settings
3. Global ignored columns are combined with entity-specific ones

```php
// Given this entity with attributes
#[Auditable]
#[Security(view: ['ROLE_USER'])]
class User
{
    #[Ignore]
    private string $password;
}

// And this programmatic config
$configuration = new Configuration([
    'ignored_columns' => ['updatedAt'],
    'entities' => [
        User::class => [
            'ignored_columns' => ['salt'],
            'roles' => ['view' => ['ROLE_ADMIN']],  // Overrides attribute
        ],
    ],
]);

// Final configuration for User:
// - ignored_columns: ['password', 'salt', 'updatedAt']
// - roles: ['view' => ['ROLE_ADMIN']]
```

## 📄 Complete Example

```php
<?php

use DH\Auditor\Provider\Doctrine\Configuration;
use App\Entity\{User, Post, Comment, Order, Payment};

$configuration = new Configuration([
    'table_prefix' => '',
    'table_suffix' => '_audit',
    
    'ignored_columns' => [
        'createdAt',
        'updatedAt',
    ],
    
    'entities' => [
        User::class => [
            'enabled' => true,
            'ignored_columns' => ['password', 'salt', 'resetToken'],
            'roles' => ['view' => ['ROLE_ADMIN']],
        ],
        Post::class => [
            'enabled' => true,
        ],
        Comment::class => [
            'enabled' => true,
            'roles' => ['view' => ['ROLE_MODERATOR', 'ROLE_ADMIN']],
        ],
        Order::class => [
            'enabled' => true,
        ],
        Payment::class => [
            'enabled' => true,
            'roles' => ['view' => ['ROLE_ACCOUNTANT', 'ROLE_ADMIN']],
        ],
    ],
    
    'viewer' => [
        'enabled' => true,
        'page_size' => 50,
    ],

    'storage_mapper' => null,  // Single database, no mapper needed

    'utf8_convert' => false,   // Set to true only for legacy non-UTF-8 data sources
]);
```

---

## Next Steps

- 🏷️ [Attributes Reference](attributes.md)
- 🗄️ [Multi-Database Setup](multi-database.md)
- 🛠️ [Schema Management](schema.md)
