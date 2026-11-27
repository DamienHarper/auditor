---
description: "Build business histories from audit log entries with #[AuditRoot], HistoryGrouper, ChangeExtractor, BlameOrigin and label resolvers, across audited entities."
---

# Histories

> **Show the history of a business object, not of one table**

An application rarely shows the history of one entity alone. The history of a property listing also covers its
features, its publications and its visits; the history of a ticket covers its attachments and its links. auditor
5.0 gives the building blocks for that:

- [`#[AuditRoot]`](#-audit-roots) declares that the audit entries of an entity belong to the history of another one;
- [`HistoryGrouper`](#-grouping-entries-into-actions) turns entries into actions (one database transaction, or any
  key you choose) with their author and origin;
- [`ChangeExtractor`](#-reading-changes) turns entries into a list of `Change`, whatever the format of their diffs;
- [`BlameOrigin`](#-blame-origin) tells a change made in a web request from one made by a command or a worker;
- [`FieldLabelResolverInterface` and `EntityLabelResolverInterface`](#-labels) give labels meant for end users.

Reading the history of a root is done by the provider: see the
[Doctrine provider documentation](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/querying/history).

## 🌳 Audit roots

```php
use DH\Auditor\Attribute\Auditable;
use DH\Auditor\Attribute\AuditRoot;

#[Auditable]
#[AuditRoot('property')]
class PropertyFeature
{
    #[ORM\ManyToOne(inversedBy: 'features')]
    private Property $property;
}

#[Auditable]
#[AuditRoot('property')]
#[AuditRoot('buyer')]          // repeatable: a bid belongs to the history of the property and of the buyer
class Bid { /* … */ }

#[Auditable]
#[AuditRoot('visit.property')] // a path of association properties
class VisitAttendee { /* … */ }
```

When an audited entity declares roots, the provider records, next to each audit entry, an index row "this entry
belongs to Property#42 through `property`". The audit tables do not change. What the index brings:

- the history of a root is one read, whatever the number of satellite tables;
- a deleted satellite keeps its history in the history of its root (it is indexed while it still knows its root);
- an entity can belong to several roots, and a root can itself be the satellite of another root. Indexing is not
  transitive: the satellites of `Visit` are only in the history of `Property` when they declare the path
  `visit.property` too;
- the path that led to the root (`$entry->rootPath`) tells on which side of a relation the root is, such as the
  source or the target ticket of a link between two tickets.

A path whose value is null is skipped: an attachment can declare `ticket`, `subject` and `initiative` roots and be
indexed under the one that is set. The path is validated when the attribute is instantiated (property names
separated by dots).

What the index cannot see: rows deleted by the database itself (`ON DELETE CASCADE`), which never go through the
ORM, and entries written before the roots were declared (providers offer a command to rebuild the index from the
existing entries).

## 🧩 Grouping entries into actions

```php
use DH\Auditor\History\HistoryGrouper;

foreach (HistoryGrouper::byTransaction($entries) as $item) {
    $item->createdAt;       // date of the most recent entry
    $item->username;        // author, null for a change made without a user
    $item->userId;          // a user id without a username is a user whose name was not recorded
    $item->origin;          // BlameOrigin::Http, Console, Messenger or System
    $item->via;             // blame context, if any
    $item->impersonator;    // user who impersonated the author (switch user), if any
    $item->getChanges();    // list<Change>, in chronological order
    $item->getMainChange(); // the creation, snapshot or deletion if any, otherwise the first change
    $item->entries;         // raw Entry objects, most recent first, for technical details
}
```

Entries are grouped by transaction id by default. Any callable can provide another key, for instance an id that the
application writes in `extra_data` to group the transactions of one request; a null key falls back to the
transaction id:

```php
use DH\Auditor\History\HistoryGrouper;
use DH\Auditor\Model\Entry;

$grouper = new HistoryGrouper(static fn (Entry $entry): ?string => $entry->extraData['request_id'] ?? null);
$items = $grouper->group($entries);
```

Pass the entries most recent first, as readers return them: items keep the order of their first entry.

`groupsByTransaction()` tells whether a grouper has no key of its own, and so makes one item per transaction: a
reader can then read a page of N items as a page of N transactions.

## 🔍 Reading changes

`ChangeExtractor` reads schema v1 and v2 entries alike and returns `DH\Auditor\History\Change` objects:

| `ChangeKind` | Entry | `field` | `old` / `new` |
|---|---|---|---|
| `Created`, `Snapshot` | insert (snapshot: written by a snapshot command) | null | `new`: field => raw value (a JSON field is rebuilt from its deep diff); `labels`: field => label of the values written with one |
| `Deleted` | remove | null | `old`: field => raw value when the provider recorded the removed state, otherwise `[]`; `labels` as above |
| `Set`, `Changed`, `Cleared` | update | the field | raw values; `oldLabel` / `newLabel` hold the association label or the `#[DiffLabel]` label |
| `Json` | update of a JSON deep diff | the field | `new`: the deep diff tree |
| `Added`, `Removed` | associate / dissociate | the collection | the element summary (`class`, `id`, `label`…); its label is kept once the element is deleted |

`$change->getValue($field)` and `$change->getLabel($field)` read one field whatever the kind: the value held by a
creation, snapshot or deletion, or the value after a field change. For a `Json` change, `getValue()` returns the deep
diff tree (only the changed keys, with their old and new values), not the whole JSON value: test `$change->kind`
before printing it.

`extractAll()` drops the mirror entries of bidirectional associations (the same change audited on both sides) and
keeps the first one read. An optional field list per entity keeps only those fields, in that order:

```php
use DH\Auditor\History\ChangeExtractor;
use DH\Auditor\History\HistoryGrouper;

$extractor = new ChangeExtractor([
    Property::class => ['status', 'price', 'surface', 'features'],
]);
$items = new HistoryGrouper(extractor: $extractor)->group($entries);
```

## 🧭 Blame origin

`BlameOrigin::fromEntry($entry)` tells where a change came from:

| Origin | Rule |
|---|---|
| `Console` | blame context of type `console` |
| `Messenger` | blame context of type `messenger` |
| `Http` | otherwise, the blame holds a firewall (an authenticated web request) |
| `System` | anything else |

The blame context wins over the firewall: a message handled on behalf of a user carries that user, but the change
was made by the worker. Providers can filter on the origin (see their documentation).

## 🏷️ Labels

- `DH\Auditor\Contract\FieldLabelResolverInterface::resolve(string $entity, string $field): string` gives the label
  of a field. `RawFieldLabelResolver` returns the field name; integrations provide translated labels.
- `DH\Auditor\Contract\EntityLabelResolverInterface` (`__invoke(object $entity): ?string`) builds, at write time,
  the label of an entity written in the `source` and `target` blocks of the diffs. Return null to keep the default
  label (the string representation of the entity, otherwise `Class#id`). Like a `#[DiffLabel]` resolver, it runs
  during the flush and must not flush.

## ↕️ Sorting entries of several tables

`Entry::getSortKey()` returns a string that sorts entries of several tables in a total order: creation date (UTC,
to the microsecond), transaction id, entity, entry id, separated by spaces. Compare keys with `strcmp()`, and build the same kind of key for
your own events (comments, reminders…) to mix them with audit entries.

`Entry::$entity` (FQCN) and `Entry::$rootPath` are set by readers that return entries of several entities.
