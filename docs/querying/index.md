# Querying Audits

This guide covers how to read and query audit entries using the Reader and Query APIs.

## Overview

The DoctrineProvider includes a powerful querying system:

- **Reader** - Factory for creating queries and utilities
- **Query** - Builds and executes audit queries
- **Filters** - Filter results by various criteria
- **Entry** - Represents a single audit log entry

## The Reader Class

### Creating a Reader

```php
<?php

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;

$reader = new Reader($doctrineProvider);
```

### Creating a Query

```php
// Simple query for all audits of an entity
$query = $reader->createQuery(User::class);
$audits = $query->execute();

// Query with options
$query = $reader->createQuery(User::class, [
    'object_id' => 123,        // Specific entity ID
    'type' => 'update',        // Action type
    'page' => 1,               // Pagination
    'page_size' => 20,
]);
```

### Query Options

| Option             | Type                    | Default | Description                               |
|--------------------|-------------------------|---------|-------------------------------------------|
| `object_id`        | `int\|string\|array`    | `null`  | Filter by entity ID(s)                    |
| `type`             | `string\|array`         | `null`  | Filter by action type(s)                  |
| `blame_id`         | `int\|string\|array`    | `null`  | Filter by user ID(s) who made changes     |
| `user_id`          | `int\|string\|array`    | `null`  | Alias for `blame_id`                      |
| `transaction_hash` | `string\|array`         | `null`  | Filter by transaction hash(es)            |
| `page`             | `int\|null`             | `1`     | Page number (1-based)                     |
| `page_size`        | `int\|null`             | `50`    | Results per page                          |
| `strict`           | `bool`                  | `true`  | Use discriminator for inheritance         |

## Basic Queries

### Get All Audits for an Entity

```php
$query = $reader->createQuery(User::class);
$audits = $query->execute();

foreach ($audits as $entry) {
    echo sprintf(
        "[%s] %s on User #%s by %s\n",
        $entry->getCreatedAt()->format('Y-m-d H:i:s'),
        $entry->getType(),
        $entry->getObjectId(),
        $entry->getUsername() ?? 'unknown'
    );
}
```

### Get Audits for a Specific Entity

```php
$query = $reader->createQuery(User::class, [
    'object_id' => 123,
]);
$audits = $query->execute();
```

### Get Audits by Type

```php
// Only updates
$query = $reader->createQuery(User::class, [
    'type' => 'update',
]);

// Multiple types
$query = $reader->createQuery(User::class, [
    'type' => ['insert', 'update'],
]);
```

### Get Audits by User

```php
// Changes made by a specific user
$query = $reader->createQuery(User::class, [
    'blame_id' => 42,
]);

// Changes by multiple users
$query = $reader->createQuery(User::class, [
    'blame_id' => [42, 43, 44],
]);
```

### Get Audits by Transaction

```php
// All changes from a single transaction
$query = $reader->createQuery(User::class, [
    'transaction_hash' => 'abc123def456...',
]);

// Or across all entities
$audits = $reader->getAuditsByTransactionHash('abc123def456...');
// Returns: ['App\Entity\User' => [...], 'App\Entity\Post' => [...]]
```

## The Query Class

For more control, work directly with the Query object:

```php
$query = $reader->createQuery(User::class);

// Add custom ordering
$query->resetOrderBy();
$query->addOrderBy(Query::CREATED_AT, 'ASC');
$query->addOrderBy(Query::ID, 'ASC');

// Add custom limit
$query->limit(100, 0);  // limit, offset

$audits = $query->execute();
```

### Query Constants

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;

Query::TYPE              // 'type'
Query::OBJECT_ID         // 'object_id'
Query::DISCRIMINATOR     // 'discriminator'
Query::TRANSACTION_HASH  // 'transaction_hash'
Query::USER_ID           // 'blame_id'
Query::CREATED_AT        // 'created_at'
Query::ID                // 'id'
```

## Filters

### SimpleFilter

For exact value matching:

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;

$query = $reader->createQuery(User::class);

// Single value
$query->addFilter(new SimpleFilter(Query::TYPE, 'update'));

// Multiple values (OR condition)
$query->addFilter(new SimpleFilter(Query::TYPE, ['insert', 'update']));
```

### DateRangeFilter

For filtering by date range:

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;

$query = $reader->createQuery(User::class, ['page_size' => null]);

// Audits from the last 30 days
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('-30 days'),
    new \DateTime('now')
));

// Audits since a specific date
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('2024-01-01'),
    null  // No upper bound
));

// Audits until a specific date
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    null,  // No lower bound
    new \DateTime('2024-06-30')
));
```

### RangeFilter

For numeric ranges:

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;

$query = $reader->createQuery(User::class, ['page_size' => null]);

// Audits for IDs between 100 and 200
$query->addFilter(new RangeFilter(Query::ID, 100, 200));

// Audits with ID >= 500
$query->addFilter(new RangeFilter(Query::ID, 500, null));
```

## Pagination

### Using Query Options

```php
$query = $reader->createQuery(User::class, [
    'page' => 1,
    'page_size' => 20,
]);
$audits = $query->execute();
```

### Using the Paginate Method

```php
$query = $reader->createQuery(User::class);
$result = $reader->paginate($query, $page = 1, $pageSize = 20);

// Result structure
[
    'results' => ArrayIterator,      // The audit entries
    'currentPage' => 1,
    'hasPreviousPage' => false,
    'hasNextPage' => true,
    'previousPage' => null,
    'nextPage' => 2,
    'numPages' => 5,
    'haveToPaginate' => true,
    'numResults' => 100,
    'pageSize' => 20,
]
```

### Disable Pagination

```php
// Get all results without pagination
$query = $reader->createQuery(User::class, [
    'page' => null,
    'page_size' => null,
]);
$allAudits = $query->execute();
```

## Counting Results

```php
$query = $reader->createQuery(User::class);
$count = $query->count();
```

## The Entry Model

Each audit result is an `Entry` object:

```php
/** @var Entry $entry */
foreach ($audits as $entry) {
    // Basic info
    $entry->getId();              // int - Audit entry ID
    $entry->getType();            // string - insert, update, remove, etc.
    $entry->getObjectId();        // string - Entity ID
    $entry->getCreatedAt();       // DateTimeImmutable
    
    // Transaction
    $entry->getTransactionHash(); // string|null
    $entry->getDiscriminator();   // string|null - For inheritance
    
    // Changes
    $entry->getDiffs();           // array - The actual changes
    $entry->getDiffs(true);       // array - Including @source metadata
    
    // User attribution
    $entry->getUserId();          // int|string|null - blame_id
    $entry->getUsername();        // string|null - blame_user
    $entry->getUserFqdn();        // string|null - User class
    $entry->getUserFirewall();    // string|null - Firewall name
    $entry->getIp();              // string|null - Client IP
}
```

## Reading Diffs

The `getDiffs()` method returns the changes:

### Insert Diffs

```php
// For insert operations:
[
    'email' => [
        'new' => 'john@example.com',
        'old' => null,
    ],
    'name' => [
        'new' => 'John Doe',
        'old' => null,
    ],
]
```

### Update Diffs

```php
// For update operations:
[
    'email' => [
        'new' => 'jane@example.com',
        'old' => 'john@example.com',
    ],
]
```

### Association Diffs

```php
// For associate/dissociate operations:
[
    'source' => [
        'class' => 'App\\Entity\\Post',
        'id' => 1,
        'label' => 'My Post',
        'field' => 'tags',
    ],
    'target' => [
        'class' => 'App\\Entity\\Tag',
        'id' => 5,
        'label' => 'PHP',
        'field' => 'posts',
    ],
    'is_owning_side' => true,
    'table' => 'post_tag',  // Join table
]
```

## Utility Methods

### Get Entity Table Names

```php
// Get the source entity table name
$tableName = $reader->getEntityTableName(User::class);
// Returns: 'users'

// Get the audit table name
$auditTableName = $reader->getEntityAuditTableName(User::class);
// Returns: 'users_audit'
```

## Error Handling

### AccessDeniedException

Thrown when the role checker denies access:

```php
use DH\Auditor\Exception\AccessDeniedException;

try {
    $query = $reader->createQuery(SensitiveEntity::class);
    $audits = $query->execute();
} catch (AccessDeniedException $e) {
    // Handle access denied
}
```

### InvalidArgumentException

Thrown when querying non-auditable entities:

```php
use DH\Auditor\Exception\InvalidArgumentException;

try {
    $query = $reader->createQuery(NonAuditableEntity::class);
} catch (InvalidArgumentException $e) {
    // Entity App\Entity\NonAuditableEntity is not auditable.
}
```

## Complete Example

```php
<?php

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;

$reader = new Reader($provider);

// Build a complex query
$query = $reader->createQuery(User::class, [
    'page' => null,
    'page_size' => null,
]);

// Add filters
$query->addFilter(new SimpleFilter(Query::TYPE, ['insert', 'update']));
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('-7 days'),
    new \DateTime('now')
));

// Custom ordering
$query->resetOrderBy();
$query->addOrderBy(Query::CREATED_AT, 'DESC');

// Limit results
$query->limit(100);

// Execute and process
$audits = $query->execute();

foreach ($audits as $entry) {
    $diffs = $entry->getDiffs();
    
    echo sprintf(
        "[%s] %s #%s by %s from %s\n",
        $entry->getCreatedAt()->format('Y-m-d H:i:s'),
        $entry->getType(),
        $entry->getObjectId(),
        $entry->getUsername() ?? 'system',
        $entry->getIp() ?? 'unknown'
    );
    
    foreach ($diffs as $field => $change) {
        echo sprintf("  - %s: %s → %s\n",
            $field,
            json_encode($change['old'] ?? null),
            json_encode($change['new'] ?? null)
        );
    }
}
```

## Next Steps

- [Filters Reference](filters.md)
- [Entry Model Reference](entry.md)
- [Security & Access Control](../configuration/role-checker.md)
