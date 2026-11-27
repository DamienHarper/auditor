---
description: "Map the former DoctrineAuditBundle (dh_doctrine_audit, DH\\DoctrineAuditBundle) to auditor and auditor-bundle: packages, namespaces, configuration keys and classes."
---

# Coming from DoctrineAuditBundle

> **DoctrineAuditBundle became auditor + auditor-bundle: here is what each old name maps to**

`damienharper/doctrine-audit-bundle` (3.x and earlier) was split when `auditor-bundle` 4.0 came out:
the auditing logic moved to the framework-agnostic `auditor` library, and the bundle became a thin
Symfony integration named `auditor-bundle`. Since auditor 5.0 the Doctrine ORM part is a third
package, `auditor-doctrine-provider`. Code or answers that use the names on the left target a
version that has been unsupported for years.

## 📦 Packages

| DoctrineAuditBundle                     | Today                                                                         |
|-----------------------------------------|-------------------------------------------------------------------------------|
| `damienharper/doctrine-audit-bundle`    | `damienharper/auditor-bundle` (requires `auditor` and `auditor-doctrine-provider`) |
| `DH\DoctrineAuditBundle\DHDoctrineAuditBundle` | `DH\AuditorBundle\DHAuditorBundle`                                     |
| namespace `DH\DoctrineAuditBundle\…`    | `DH\AuditorBundle\…` (bundle), `DH\Auditor\…` (core), `DH\Auditor\Provider\Doctrine\…` (provider) |

## ⚙️ Configuration

The root key changed from `dh_doctrine_audit` to `dh_auditor`, and the Doctrine options moved under
`providers.doctrine`:

| `dh_doctrine_audit` (3.x)            | `dh_auditor` (today)                                           |
|--------------------------------------|----------------------------------------------------------------|
| `enabled`                            | `enabled`                                                      |
| `timezone`                           | `timezone`                                                     |
| `table_prefix`, `table_suffix`       | `providers.doctrine.table_prefix`, `providers.doctrine.table_suffix` |
| `ignored_columns`                    | `providers.doctrine.ignored_columns`                           |
| `entities.<FQCN>.ignored_columns`, `.enabled` | `providers.doctrine.entities.<FQCN>.ignored_columns`, `.enabled` |
| `entities.<FQCN>.roles.view` | `providers.doctrine.entities.<FQCN>.roles.view` |
| `storage_entity_manager`             | `providers.doctrine.storage_services` (and `storage_mapper`)   |
| `enabled_viewer`                     | `providers.doctrine.viewer`                                    |

The full reference is in the [auditor-bundle configuration](https://damienharper.github.io/auditor-docs/auditor-bundle/configuration).

## 🏷️ Annotations and classes

| DoctrineAuditBundle                                   | Today                                                         |
|-------------------------------------------------------|---------------------------------------------------------------|
| `DH\DoctrineAuditBundle\Annotation\Auditable` (annotation) | `#[Auditable]` (`DH\Auditor\Attribute\Auditable`), a PHP attribute |
| `DH\DoctrineAuditBundle\Annotation\Ignore` (annotation) | `#[Ignore]` (`DH\Auditor\Attribute\Ignore`)                    |
| `DH\DoctrineAuditBundle\Annotation\Security` (annotation) | `#[Security]` (`DH\Auditor\Attribute\Security`)                |
| `DH\DoctrineAuditBundle\Reader\AuditReader`           | `DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader`       |
| `DH\DoctrineAuditBundle\Reader\AuditEntry`            | `DH\Auditor\Model\Entry`                                       |
| `audit:schema:update` command                         | `audit:schema:update`, plus `audit:schema:migrate` for the audit table format |
| `audit:clean` command                                 | `audit:clean`                                                  |

Annotations are not supported any more: use the PHP attributes.

## 🗄️ Audit tables

Audit tables created by DoctrineAuditBundle predate several format changes, the latest being the
schema version 2 of auditor 5.0 (converted by `audit:schema:migrate`). Upgrade one major at a time.
No single guide covers the whole path. The steps that exist: the
[auditor-bundle 6.x upgrade notes](https://damienharper.github.io/auditor-docs/auditor-bundle/6.x/upgrading) and
[auditor 3.x upgrade notes](https://damienharper.github.io/auditor-docs/auditor/3.x/upgrading) (from DoctrineAuditBundle
and the early majors), then the guides below for 4.x and later. Back up the audit tables first.

- [auditor upgrade guides](../upgrade/index.md)
- [auditor-bundle upgrade guides](https://damienharper.github.io/auditor-docs/auditor-bundle/upgrade)
- [auditor-doctrine-provider upgrade guides](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/upgrade)
