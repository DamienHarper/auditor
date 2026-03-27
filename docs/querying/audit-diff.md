# Reading Audit Diffs

`Entry::getDiffs()` returns an `AuditDiffCollection` — a typed, iterable value object that
exposes the changes recorded for a single audit entry.

## Dispatching on operation type

The collection carries a `DiffKind` discriminant that tells you how to interpret it:

```php
use DH\Auditor\Model\DiffKind;

$collection = $entry->getDiffs();

match ($collection->kind) {
    DiffKind::FieldChanges => handleFieldChanges($collection),
    DiffKind::EntityRemoval => handleRemoval($collection->requireEntitySnapshot()),
    DiffKind::Associate,
    DiffKind::Dissociate   => handleRelation($collection->requireRelationDescriptor()),
};
```

Predicate helpers are also available on `DiffKind` for simple branching:

```php
if ($collection->kind->hasFieldDiffs()) { ... }
if ($collection->kind->isRelation())    { ... }
if ($collection->kind->isRemoval())     { ... }
```

## INSERT and UPDATE — FieldChanges

Iterate the collection to access individual field changes as `AuditDiff` objects:

```php
foreach ($entry->getDiffs() as $field => $diff) {
    echo $field;            // e.g. "email"
    echo $diff->new;        // new value
    echo $diff->old;        // old value (null when hasOld is false)
    echo $diff->hasOld;     // false for INSERT, true for UPDATE
}
```

Check for a specific field:

```php
$diffs = $entry->getDiffs();

if ($diffs->has('email')) {
    $diff = $diffs->get('email'); // ?AuditDiff
}

$fields = $diffs->fields(); // list<string> of changed field names
```

### DiffLabel-enriched values

When a [`DiffLabelResolver`](../providers/) enriches a field, the raw value becomes a
`['label' => string, 'value' => mixed]` wrapper. `AuditDiff` transparently unwraps it:

```php
$diff->isEnriched;     // true when old/new is an enriched shape
$diff->newRawValue;    // unwrapped scalar value (e.g. the foreign key integer)
$diff->newLabel;       // human-readable label (e.g. "Electronics")
$diff->oldRawValue;    // same for old value
$diff->oldLabel;
```

## REMOVE — EntityRemoval

REMOVE entries carry an entity snapshot instead of field-level diffs:

```php
$snapshot = $entry->getDiffs()->requireEntitySnapshot();

echo $snapshot->class; // FQCN of the deleted entity
echo $snapshot->id;    // primary key value (int|string)
echo $snapshot->label; // string representation (__toString or "FQCN#id")
echo $snapshot->table; // database table name
```

## ASSOCIATE / DISSOCIATE — Relation

Relation entries carry a descriptor with source and target endpoints:

```php
$rel = $entry->getDiffs()->requireRelationDescriptor();

echo $rel->isOwningSide;       // bool
echo $rel->pivotTable;         // ?string — non-null for ManyToMany join tables
echo $rel->source->class;      // FQCN of the source entity
echo $rel->source->field;      // relation field name on source
echo $rel->source->id;         // primary key value (int|string)
echo $rel->target->class;      // FQCN of the target entity
```

`DiffKind::Associate` and `DiffKind::Dissociate` are separate cases, letting you
distinguish a link operation from an unlink in your `match` expression.

### Non-standard primary keys

When an entity uses a non-`id` primary key (e.g. a UUID field), `RelationEndpoint`
stores the actual column name in `$pkName`:

```php
$endpoint->pkName; // e.g. "uuid", or null for the conventional "id"
$endpoint->id;     // the actual PK value, regardless of column name
```

## Including metadata

Pass `$includeMetadata = true` to expose the internal `@source` key that providers
attach during diff computation:

```php
$rawWithMeta = $entry->getDiffs(includeMetadata: true);
```

This is rarely needed outside of debugging or provider development.
