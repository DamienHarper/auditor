# Entry Model Reference

The `Entry` class represents a single audit log entry. This page documents all its properties and methods.

## Overview

```php
namespace DH\Auditor\Model;

final class Entry
{
    // Created from database row
    public static function fromArray(array $row): self;
    
    // Accessors for all properties
    public function getId(): ?int;
    public function getType(): string;
    public function getObjectId(): string;
    // ... etc
}
```

## Creating Entries

Entries are typically created by the Reader when executing queries:

```php
$reader = new Reader($provider);
$query = $reader->createQuery(User::class);
$entries = $query->execute();  // Returns Entry[]
```

You can also create entries manually (useful for testing):

```php
use DH\Auditor\Model\Entry;

$entry = Entry::fromArray([
    'id' => 1,
    'type' => 'update',
    'object_id' => '123',
    'discriminator' => null,
    'transaction_hash' => 'abc123',
    'diffs' => '{"name":{"old":"John","new":"Jane"}}',
    'extra_data' => '{"department":"IT","role":"admin"}', // or null
    'blame_id' => '42',
    'blame_user' => 'admin',
    'blame_user_fqdn' => 'App\\Entity\\User',
    'blame_user_firewall' => 'main',
    'ip' => '192.168.1.1',
    'created_at' => new \DateTimeImmutable(),
]);
```

## Properties & Methods

### getId()

```php
public function getId(): ?int
```

Returns the unique identifier of the audit entry (auto-increment primary key).

```php
$entryId = $entry->getId();
// Returns: 12345
```

### getType()

```php
public function getType(): string
```

Returns the type of action that was audited.

```php
$type = $entry->getType();
// Returns: 'insert', 'update', 'remove', 'associate', or 'dissociate'
```

| Type          | Description                                    |
|---------------|------------------------------------------------|
| `insert`      | A new entity was created                       |
| `update`      | An existing entity was modified                |
| `remove`      | An entity was deleted                          |
| `associate`   | A many-to-many relationship was created        |
| `dissociate`  | A many-to-many relationship was removed        |

### getObjectId()

```php
public function getObjectId(): string
```

Returns the ID of the audited entity.

```php
$entityId = $entry->getObjectId();
// Returns: '123' (always string)
```

### getDiscriminator()

```php
public function getDiscriminator(): ?string
```

Returns the entity class name for single-table inheritance entities.

```php
$discriminator = $entry->getDiscriminator();
// Returns: 'App\Entity\Admin' or null
```

This is used when auditing entities with `SINGLE_TABLE` inheritance to identify the actual entity class.

### getTransactionHash()

```php
public function getTransactionHash(): ?string
```

Returns the transaction hash that groups related changes.

```php
$hash = $entry->getTransactionHash();
// Returns: 'a1b2c3d4e5f6...' (40 character hash)
```

All changes made in a single `EntityManager::flush()` share the same transaction hash.

### getDiffs()

```php
public function getDiffs(bool $includeMetadata = false): array
```

Returns the actual changes recorded for this audit entry.

```php
// Basic usage
$diffs = $entry->getDiffs();

// Include @source metadata
$diffs = $entry->getDiffs(true);
```

#### Insert Diffs

```php
// When type = 'insert'
[
    'email' => [
        'new' => 'john@example.com',
        'old' => null,
    ],
    'name' => [
        'new' => 'John Doe',
        'old' => null,
    ],
    'roles' => [
        'new' => ['ROLE_USER'],
        'old' => null,
    ],
]
```

#### Update Diffs

```php
// When type = 'update'
[
    'name' => [
        'new' => 'Jane Doe',
        'old' => 'John Doe',
    ],
    'email' => [
        'new' => 'jane@example.com',
        'old' => 'john@example.com',
    ],
]
```

#### Remove Diffs

```php
// When type = 'remove'
// Contains a summary of the deleted entity
[
    'id' => 123,
    'label' => 'John Doe',  // Generated from entity's __toString or ID
    'class' => 'App\\Entity\\User',
]
```

#### Association Diffs

```php
// When type = 'associate' or 'dissociate'
[
    'source' => [
        'class' => 'App\\Entity\\Post',
        'id' => 1,
        'label' => 'My Blog Post',
        'field' => 'tags',
    ],
    'target' => [
        'class' => 'App\\Entity\\Tag',
        'id' => 5,
        'label' => 'PHP',
        'field' => 'posts',
    ],
    'is_owning_side' => true,
    'table' => 'post_tag',  // The join table
]
```

#### With Metadata

```php
$diffs = $entry->getDiffs(true);
// Includes @source key with additional metadata
[
    '@source' => [
        // Source information
    ],
    'name' => [
        'new' => 'Jane',
        'old' => 'John',
    ],
]
```

### getExtraData()

```php
public function getExtraData(): ?array
```

Returns any supplementary data attached to this audit entry, or `null` if none was set.

```php
$extraData = $entry->getExtraData();
// Returns: ['department' => 'IT', 'role' => 'admin'] or null

// Also available as a virtual property
$extraData = $entry->extraData;
```

Extra data is populated via a `LifecycleEvent` listener. See the [Extra Data guide](../extra-data.md) for setup instructions and examples.

### getUserId()

```php
public function getUserId(): int|string|null
```

Returns the identifier of the user who made the change.

```php
$userId = $entry->getUserId();
// Returns: 42 or '42' or 'user-uuid-123' or null
```

Returns `null` if no user was identified (e.g., system process, CLI command without user context).

### getUsername()

```php
public function getUsername(): ?string
```

Returns the username/display name of the user.

```php
$username = $entry->getUsername();
// Returns: 'admin' or 'John Doe' or null
```

### getUserFqdn()

```php
public function getUserFqdn(): ?string
```

Returns the fully qualified class name of the user object.

```php
$userClass = $entry->getUserFqdn();
// Returns: 'App\Entity\User' or null
```

### getUserFirewall()

```php
public function getUserFirewall(): ?string
```

Returns the Symfony security firewall name.

```php
$firewall = $entry->getUserFirewall();
// Returns: 'main' or 'api' or null
```

### getIp()

```php
public function getIp(): ?string
```

Returns the client IP address.

```php
$ip = $entry->getIp();
// Returns: '192.168.1.1' or '2001:db8::1' (IPv6) or null
```

### getCreatedAt()

```php
public function getCreatedAt(): ?\DateTimeImmutable
```

Returns when the audit entry was created.

```php
$createdAt = $entry->getCreatedAt();
// Returns: DateTimeImmutable

echo $createdAt->format('Y-m-d H:i:s');
// Output: 2024-01-15 14:30:45
```

The timestamp uses the timezone configured in the Auditor configuration.

## Working with Entries

### Display Audit Log

```php
$entries = $query->execute();

foreach ($entries as $entry) {
    printf(
        "[%s] %s %s #%s by %s (%s)\n",
        $entry->getCreatedAt()->format('Y-m-d H:i:s'),
        ucfirst($entry->getType()),
        $entry->getDiscriminator() ?? 'Entity',
        $entry->getObjectId(),
        $entry->getUsername() ?? 'System',
        $entry->getIp() ?? 'N/A'
    );
    
    foreach ($entry->getDiffs() as $field => $change) {
        if (isset($change['old'], $change['new'])) {
            printf(
                "  - %s: %s → %s\n",
                $field,
                json_encode($change['old']),
                json_encode($change['new'])
            );
        }
    }
    echo "\n";
}
```

### Check Change Type

```php
if ($entry->getType() === 'update') {
    $changes = $entry->getDiffs();
    
    // Check if specific field changed
    if (isset($changes['email'])) {
        echo sprintf(
            "Email changed from %s to %s\n",
            $changes['email']['old'],
            $changes['email']['new']
        );
    }
}
```

### Group by Transaction

```php
$entries = $query->execute();
$grouped = [];

foreach ($entries as $entry) {
    $hash = $entry->getTransactionHash();
    if (!isset($grouped[$hash])) {
        $grouped[$hash] = [];
    }
    $grouped[$hash][] = $entry;
}

// Now $grouped contains all changes per transaction
foreach ($grouped as $hash => $transactionEntries) {
    echo "Transaction: $hash\n";
    foreach ($transactionEntries as $entry) {
        echo "  - " . $entry->getType() . " on #" . $entry->getObjectId() . "\n";
    }
}
```

### Filter Changes by Field

```php
// Find all entries where 'status' field changed
$statusChanges = array_filter($entries, function (Entry $entry) {
    return isset($entry->getDiffs()['status']);
});

foreach ($statusChanges as $entry) {
    $diffs = $entry->getDiffs();
    echo sprintf(
        "Status changed from '%s' to '%s' at %s\n",
        $diffs['status']['old'],
        $diffs['status']['new'],
        $entry->getCreatedAt()->format('Y-m-d H:i:s')
    );
}
```

### Build Timeline

```php
// Build a timeline of changes for an entity
$query = $reader->createQuery(User::class, [
    'object_id' => 123,
    'page_size' => null,
]);
$query->resetOrderBy();
$query->addOrderBy(Query::CREATED_AT, 'ASC');

$timeline = [];
foreach ($query->execute() as $entry) {
    $timeline[] = [
        'date' => $entry->getCreatedAt(),
        'type' => $entry->getType(),
        'user' => $entry->getUsername(),
        'changes' => $entry->getDiffs(),
    ];
}
```

## JSON Serialization

The diffs are stored as JSON and automatically decoded:

```php
// Diffs are returned as arrays, not JSON strings
$diffs = $entry->getDiffs();
// This is already an array, no need to json_decode()

// To get JSON for API response:
$json = json_encode([
    'id' => $entry->getId(),
    'type' => $entry->getType(),
    'object_id' => $entry->getObjectId(),
    'diffs' => $entry->getDiffs(),
    'extra_data' => $entry->getExtraData(),
    'created_at' => $entry->getCreatedAt()?->format('c'),
    'user' => $entry->getUsername(),
]);
```

## Next Steps

- [Extra Data Guide](../extra-data.md)
- [Querying Overview](index.md)
- [Filters Reference](filters.md)
