---
description: "Frequent mistakes when using auditor 5.x: where the Doctrine provider lives, decoding audit entries, LifecycleEvent payload keys, renamed fields and masked values."
---

# Common mistakes

> **Pitfalls met when writing code against auditor 5.x, and the correct form**

## Looking for the Doctrine provider in the core

Since auditor 5.0, `damienharper/auditor` contains no Doctrine code. `DH\Auditor\Provider\Doctrine\…`
classes come from `damienharper/auditor-doctrine-provider`; Symfony applications install
`damienharper/auditor-bundle`, which requires it. See [Which packages do I need?](getting-started/which-packages.md).

## Mixing major versions

`auditor-bundle` 8.x goes with auditor 5.x and `auditor-doctrine-provider` 2.x; `auditor-bundle` 7.x
with auditor 4.x and `auditor-doctrine-provider` 1.x. Code written for one line does not run on the
other: the audit table format and several entry fields changed in 5.0 ([upgrade guide](upgrade/v5.md)).

## Decoding `extraData` twice

```php
// ❌ $entry->extraData is already an array (or null)
$data = json_decode($entry->extraData, true);

// ✅
$data = $entry->extraData ?? [];
```

The same holds for `$entry->blame`, already decoded, and `getDiffs()`, which returns an array.

## Reading an `action` key from a `LifecycleEvent`

The payload of a `LifecycleEvent` carries the operation in `type`, as a string:

```php
// ❌ there is no 'action' key
$operation = $event->getPayload()['action'];

// ✅
$operation = $event->getPayload()['type']; // 'insert', 'update', 'remove', 'associate', 'dissociate'
```

## Comparing `Entry::$type` with an enum case

`$entry->type` is a plain string. Compare it with the enum value or the string constant:

```php
use DH\Auditor\Model\TransactionType;

// ❌ always false: a string is never identical to an enum case
$entry->type === TransactionType::Update;

// ✅
$entry->type === TransactionType::Update->value;
$entry->type === TransactionType::UPDATE;
```

## Using fields removed in 5.0

| Removed (4.x and earlier)                         | Use instead (5.x)                                              |
|---------------------------------------------------|----------------------------------------------------------------|
| `$entry->transactionHash`                         | `$entry->transactionId` (a ULID)                               |
| payload keys `blame_user`, `blame_user_fqdn`, `blame_user_firewall`, `ip` | payload key `blame` (JSON: `username`, `user_fqdn`, `user_firewall`, `ip`) |
| `DH\Auditor\Transaction\NeedsConversionToAuditableType` | `DH\Auditor\Provider\Doctrine\Transaction\NeedsConversionToAuditableType` (in `auditor-doctrine-provider`) |
| `DH\Auditor\Provider\Doctrine\Auditing\Attribute\{Auditable,Ignore,Security}` (deprecated 4.x aliases) | `DH\Auditor\Attribute\{Auditable,Ignore,Security}` |

## Expecting DQL or raw SQL to be audited

The Doctrine provider audits the changes flushed through the Doctrine ORM unit of work. Bulk DQL
`UPDATE`/`DELETE` queries and raw SQL statements bypass it and are not recorded.

## Hiding a secret with `#[Ignore]`, or leaking a masked one

`#[Ignore]` drops the field from the audit: its changes are not recorded at all. To record that a sensitive field
changed without storing its values, use `#[Mask]` (`"***"`, or an HMAC with `MaskStrategy::KeyedHash`, which needs the
provider's mask secret). Only the values in the diffs are masked: the label of the entity (its `__toString()` or a
label resolver) is stored in clear in every entry, so never build it from a masked field.

## Using DoctrineAuditBundle names

`dh_doctrine_audit`, `DH\DoctrineAuditBundle\…` and annotations such as
`DH\DoctrineAuditBundle\Annotation\Auditable` belong to the former DoctrineAuditBundle. See
[Coming from DoctrineAuditBundle](getting-started/coming-from-doctrine-audit-bundle.md).
