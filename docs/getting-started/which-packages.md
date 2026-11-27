---
description: "Choose between auditor, auditor-doctrine-provider and auditor-bundle, and pick the versions that work together for your PHP or Symfony application."
---

# Which packages do I need?

> **auditor is a family of three packages: pick the one that matches your application**

## 🧭 Pick a package

| Your application                         | Install                                                   | You get                                                        |
|------------------------------------------|-----------------------------------------------------------|----------------------------------------------------------------|
| Symfony with Doctrine ORM                | `composer require damienharper/auditor-bundle`            | auditor + Doctrine provider + configuration, commands, viewer  |
| Doctrine ORM without Symfony             | `composer require damienharper/auditor-doctrine-provider` | auditor + Doctrine provider, wired by hand                     |
| Another persistence layer or a custom store | `composer require damienharper/auditor`                | the core only: [write your own provider](../providers/custom-provider.md) |

`auditor-bundle` requires `auditor-doctrine-provider`, which requires `auditor`: installing the bundle installs the other two.

| Package                                   | Role                                                                 |
|-------------------------------------------|----------------------------------------------------------------------|
| `damienharper/auditor`                    | Core: contracts, audit entry model (`Entry`), attributes, histories, events |
| `damienharper/auditor-doctrine-provider`  | Doctrine ORM change detection, audit tables, schema tools, reader    |
| `damienharper/auditor-bundle`             | Symfony integration: `dh_auditor` configuration, console commands, audit viewer |

## 🔢 Versions that work together

| auditor | auditor-doctrine-provider | auditor-bundle | PHP    | Symfony | Doctrine ORM | Status                |
|---------|---------------------------|----------------|--------|---------|--------------|-----------------------|
| 5.x     | 2.x                       | 8.x            | >= 8.4 | >= 8.1  | >= 3.7       | Active development    |
| 4.x     | 1.x                       | 7.x            | >= 8.4 | >= 8.1  | >= 3.2       | Bug and security fixes |
| 3.x     | —                         | 6.x            | >= 8.2 | >= 5.4  |              | End of life           |

Do not mix lines: `auditor-bundle` 8.x needs auditor 5.x and `auditor-doctrine-provider` 2.x,
`auditor-bundle` 7.x needs auditor 4.x and `auditor-doctrine-provider` 1.x.

## ⚠️ Where the Doctrine provider lives

- **auditor 5.x**: the Doctrine provider is **not** part of `damienharper/auditor` any more. Install
  `damienharper/auditor-doctrine-provider` (or `auditor-bundle`, which requires it). The classes keep
  the `DH\Auditor\Provider\Doctrine` namespace.
- **auditor 4.x**: a copy of the Doctrine provider is still bundled in `damienharper/auditor`, deprecated
  since 4.1. `auditor-doctrine-provider` 1.x is the drop-in replacement.
- **auditor 3.x and earlier**: the Doctrine provider is bundled in `damienharper/auditor`.

## 🕰️ Older names

`auditor-bundle` was formerly known as `DoctrineAuditBundle` (`damienharper/doctrine-audit-bundle`).
See [Coming from DoctrineAuditBundle](coming-from-doctrine-audit-bundle.md).

## Next Steps

- [Installation](installation.md)
- [Quick Start](quick-start.md)
- [Common mistakes](../common-mistakes.md)
- [Upgrade guides](../upgrade/index.md)
