# Filters Reference

> **Narrow down audit query results with powerful filters**

Filters allow you to narrow down audit query results. This page documents all available filter types.

## 📋 Overview

| Filter            | Purpose                              | Example Use Case                    |
|-------------------|--------------------------------------|-------------------------------------|
| `SimpleFilter`    | Exact value matching                 | Filter by type, user, entity ID     |
| `DateRangeFilter` | Date/time range                      | Audits from last week               |
| `RangeFilter`     | Numeric range                        | Audits with ID >= 1000              |
| `NullFilter`      | NULL value matching                  | Audits by anonymous users           |
| `JsonFilter`      | JSON column content                  | Filter by extra_data values         |

## 🔌 Filter Interface

All filters implement `FilterInterface`:

```php
namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

interface FilterInterface
{
    public function getName(): string;
    
    public function getSQL(): array;
}
```

## 🎯 SimpleFilter

The most common filter for exact value matching.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
```

### Constructor

```php
public function __construct(
    private readonly string $name,   // Column name
    private mixed $value             // Value or array of values
)
```

### Single Value

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;

$query = $reader->createQuery(User::class, ['page_size' => null]);

// Filter by type
$query->addFilter(new SimpleFilter(Query::TYPE, 'update'));

// Filter by object ID
$query->addFilter(new SimpleFilter(Query::OBJECT_ID, '123'));

// Filter by user ID
$query->addFilter(new SimpleFilter(Query::USER_ID, 42));

// Filter by transaction hash
$query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, 'abc123...'));
```

### Multiple Values (OR)

When providing an array, it generates an `IN` clause:

```php
// type IN ('insert', 'update')
$query->addFilter(new SimpleFilter(Query::TYPE, ['insert', 'update']));

// object_id IN ('1', '2', '3')
$query->addFilter(new SimpleFilter(Query::OBJECT_ID, ['1', '2', '3']));

// blame_id IN (10, 20, 30)
$query->addFilter(new SimpleFilter(Query::USER_ID, [10, 20, 30]));
```

### Generated SQL

```php
$filter = new SimpleFilter('type', 'update');
$sql = $filter->getSQL();
// Returns: ['sql' => 'type = :type', 'params' => ['type' => 'update']]

$filter = new SimpleFilter('type', ['insert', 'update']);
$sql = $filter->getSQL();
// Returns: ['sql' => 'type IN (:type)', 'params' => ['type' => ['insert', 'update']]]
```

### Methods

```php
$filter = new SimpleFilter(Query::TYPE, 'update');

$filter->getName();   // 'type'
$filter->getValue();  // 'update' or ['insert', 'update']
```

## 📅 DateRangeFilter

Filter by date/time range.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
```

### Constructor

```php
public function __construct(
    private string $name,                    // Column name
    ?\DateTimeInterface $minValue,           // Lower bound (inclusive)
    ?\DateTimeInterface $maxValue = null     // Upper bound (inclusive)
)
```

### Both Bounds

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;

$query = $reader->createQuery(User::class, ['page_size' => null]);

// Audits between two dates
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('2024-01-01'),
    new \DateTime('2024-12-31')
));
```

### Lower Bound Only

```php
// Audits since January 1st
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('2024-01-01'),
    null
));
```

### Upper Bound Only

```php
// Audits before July 1st
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    null,
    new \DateTime('2024-07-01')
));
```

### Common Patterns

```php
// Last 24 hours
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('-24 hours'),
    new \DateTime('now')
));

// Last 7 days
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('-7 days'),
    new \DateTime('now')
));

// This month
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('first day of this month'),
    new \DateTime('last day of this month')
));

// Specific business hours
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('2024-01-15 09:00:00'),
    new \DateTime('2024-01-15 17:00:00')
));
```

### Generated SQL

```php
$filter = new DateRangeFilter(
    'created_at',
    new \DateTime('2024-01-01'),
    new \DateTime('2024-12-31')
);
$sql = $filter->getSQL();
// Returns:
// [
//     'sql' => 'created_at >= :min_created_at AND created_at <= :max_created_at',
//     'params' => [
//         'min_created_at' => '2024-01-01 00:00:00',
//         'max_created_at' => '2024-12-31 00:00:00'
//     ]
// ]
```

### Validation

> [!WARNING]
> The filter validates that `minValue` is not after `maxValue`. Providing an invalid range will throw an `InvalidArgumentException`.

```php
// This throws InvalidArgumentException
new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('2024-12-31'),  // Later date as min
    new \DateTime('2024-01-01')   // Earlier date as max
);
// Error: Max bound has to be later than min bound.
```

### Methods

```php
$filter = new DateRangeFilter(Query::CREATED_AT, $min, $max);

$filter->getName();      // 'created_at'
$filter->getMinValue();  // DateTimeInterface|null
$filter->getMaxValue();  // DateTimeInterface|null
```

## 🔢 RangeFilter

Filter by numeric range.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;
```

### Constructor

```php
public function __construct(
    private string $name,    // Column name
    mixed $minValue,         // Lower bound (inclusive)
    mixed $maxValue = null   // Upper bound (inclusive)
)
```

### Both Bounds

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;

$query = $reader->createQuery(User::class, ['page_size' => null]);

// Audits with ID between 100 and 200
$query->addFilter(new RangeFilter(Query::ID, 100, 200));
```

### Lower Bound Only

```php
// Audits with ID >= 1000
$query->addFilter(new RangeFilter(Query::ID, 1000, null));
```

### Upper Bound Only

```php
// Audits with ID <= 500
$query->addFilter(new RangeFilter(Query::ID, null, 500));
```

### Validation

> [!IMPORTANT]
> At least one bound must be provided. Providing neither will throw an `InvalidArgumentException`.

```php
// This throws InvalidArgumentException
new RangeFilter(Query::ID, null, null);
// Error: You must provide at least one of the two range bounds.
```

### Generated SQL

```php
$filter = new RangeFilter('id', 100, 200);
$sql = $filter->getSQL();
// Returns:
// [
//     'sql' => 'id >= :min_id AND id <= :max_id',
//     'params' => ['min_id' => 100, 'max_id' => 200]
// ]
```

### Methods

```php
$filter = new RangeFilter(Query::ID, 100, 200);

$filter->getName();      // 'id'
$filter->getMinValue();  // 100
$filter->getMaxValue();  // 200
```

## 🚫 NullFilter

Filter for NULL values.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\NullFilter;
```

### Constructor

```php
public function __construct(
    private readonly string $name   // Column name
)
```

### Usage

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\NullFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;

$query = $reader->createQuery(User::class, ['page_size' => null]);

// Filter audits made by anonymous users (no blame_id)
$query->addFilter(new NullFilter(Query::USER_ID));

// Filter audits without discriminator
$query->addFilter(new NullFilter(Query::DISCRIMINATOR));
```

### Generated SQL

```php
$filter = new NullFilter('blame_id');
$sql = $filter->getSQL();
// Returns: ['sql' => 'blame_id IS NULL', 'params' => []]
```

### Methods

```php
$filter = new NullFilter(Query::USER_ID);

$filter->getName();  // 'blame_id'
```

## 🔀 Combining Filters

> [!TIP]
> You can add multiple filters to a query. Filters are combined with AND.

```php
$query = $reader->createQuery(User::class, ['page_size' => null]);

// Type is 'update' AND user is 42 AND in date range
$query->addFilter(new SimpleFilter(Query::TYPE, 'update'));
$query->addFilter(new SimpleFilter(Query::USER_ID, 42));
$query->addFilter(new DateRangeFilter(
    Query::CREATED_AT,
    new \DateTime('-7 days'),
    new \DateTime('now')
));
```

### Same Column Multiple Filters

Multiple filters on the same column are merged:

```php
// These are equivalent:
$query->addFilter(new SimpleFilter(Query::TYPE, 'insert'));
$query->addFilter(new SimpleFilter(Query::TYPE, 'update'));

// Becomes: type IN ('insert', 'update')

// Same as:
$query->addFilter(new SimpleFilter(Query::TYPE, ['insert', 'update']));
```

## 📋 Supported Filter Columns

The following columns can be filtered:

| Column             | Constant                     | Description                    |
|--------------------|------------------------------|--------------------------------|
| `id`               | `Query::ID`                  | Audit entry primary key        |
| `type`             | `Query::TYPE`                | Action type                    |
| `object_id`        | `Query::OBJECT_ID`           | Audited entity ID              |
| `discriminator`    | `Query::DISCRIMINATOR`       | Entity class for inheritance   |
| `transaction_hash` | `Query::TRANSACTION_HASH`    | Transaction identifier         |
| `blame_id`         | `Query::USER_ID`             | User ID who made the change    |
| `created_at`       | `Query::CREATED_AT`          | When the audit was created     |
| `json`             | `Query::JSON`                | JSON column content (extra_data) |

## 🔎 JsonFilter

Filter by JSON column content. Generates platform-specific SQL for optimal performance.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\JsonFilter;
```

### Constructor

```php
public function __construct(
    private readonly string $column,    // JSON column name (e.g., 'extra_data')
    private readonly string $path,      // JSON path (e.g., 'department' or 'user.role')
    private readonly mixed $value,      // Value to match
    private readonly string $operator = '=',  // Comparison operator
    private readonly bool $strict = false     // Throw exception if JSON not supported
)
```

### Basic Usage

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\JsonFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;

$query = $reader->createQuery(User::class, ['page_size' => null]);

// Filter by exact value
$query->addFilter(new JsonFilter('extra_data', 'department', 'IT'));

$entries = $query->execute();
```

### Supported Operators

```php
// Exact match (default)
new JsonFilter('extra_data', 'department', 'IT');
new JsonFilter('extra_data', 'department', 'IT', '=');

// Not equal
new JsonFilter('extra_data', 'department', 'IT', '!=');
new JsonFilter('extra_data', 'department', 'IT', '<>');

// Pattern matching
new JsonFilter('extra_data', 'department', 'IT%', 'LIKE');
new JsonFilter('extra_data', 'department', '%temp%', 'NOT LIKE');

// Multiple values
new JsonFilter('extra_data', 'status', ['active', 'pending'], 'IN');
new JsonFilter('extra_data', 'status', ['deleted', 'archived'], 'NOT IN');

// NULL checking
new JsonFilter('extra_data', 'deleted_by', null, 'IS NULL');
new JsonFilter('extra_data', 'department', null, 'IS NOT NULL');
```

### Nested JSON Paths

```php
// Access nested values using dot notation
$query->addFilter(new JsonFilter('extra_data', 'user.role', 'admin'));
$query->addFilter(new JsonFilter('extra_data', 'metadata.source.ip', '192.168.1.1'));
```

### Database Support

| Database   | Minimum Version | JSON Function |
|------------|-----------------|---------------|
| MySQL      | 5.7.0           | `JSON_UNQUOTE(JSON_EXTRACT())` |
| MariaDB    | 10.2.3          | `JSON_UNQUOTE(JSON_EXTRACT())` |
| PostgreSQL | 9.4.0           | `->>` operator |
| SQLite     | 3.38.0          | `json_extract()` |

### Fallback Behavior

When the database doesn't support JSON functions, the filter falls back to `LIKE` pattern matching:

```php
// On unsupported database, this:
new JsonFilter('extra_data', 'department', 'IT');

// Becomes approximately:
// WHERE extra_data LIKE '%"department":"IT"%'
```

> [!WARNING]
> The LIKE fallback may produce inaccurate results (false positives). A `E_USER_WARNING` is triggered when this occurs.

### Strict Mode

Enable strict mode to throw an exception instead of falling back to LIKE:

```php
// Throws InvalidArgumentException if JSON is not supported
$filter = new JsonFilter('extra_data', 'department', 'IT', '=', strict: true);
```

### Limitations

> [!NOTE]
> Only **scalar value extraction** is supported in this version. Array/object comparisons (e.g., `JSON_CONTAINS`) are not yet implemented.

### Generated SQL Examples

```php
// MySQL/MariaDB
$filter = new JsonFilter('extra_data', 'department', 'IT');
// SQL: JSON_UNQUOTE(JSON_EXTRACT(extra_data, '$.department')) = :json_department

// PostgreSQL
// SQL: extra_data->>'department' = :json_department

// SQLite
// SQL: json_extract(extra_data, '$.department') = :json_department
```

## 🧩 Custom Filter Example

> [!NOTE]
> You can create custom filters by implementing `FilterInterface`. For NULL value filtering, use the built-in `NullFilter` class.

```php
<?php

namespace App\Audit\Filter;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\FilterInterface;

final class NotNullFilter implements FilterInterface
{
    public function __construct(
        private readonly string $name
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getSQL(): array
    {
        return [
            'sql' => sprintf('%s IS NOT NULL', $this->name),
            'params' => [],
        ];
    }
}

// Usage
$query->addFilter(new NotNullFilter('blame_id'));
```

---

## Next Steps

- 🔍 [Querying Overview](index.md)
- 📦 [Entry Model Reference](entry.md)
