# auditor

> **The missing audit log library for PHP**

[![Latest Stable Version](https://poser.pugx.org/damienharper/auditor/v/stable)](https://packagist.org/packages/damienharper/auditor)
[![License](https://poser.pugx.org/damienharper/auditor/license)](https://packagist.org/packages/damienharper/auditor)
[![Total Downloads](https://poser.pugx.org/damienharper/auditor/downloads)](https://packagist.org/packages/damienharper/auditor)

## What is auditor?

**auditor** is a PHP library that provides an easy and standardized way to collect audit logs. It is designed to track changes made to your entities and persist them as audit trails.

### Key Features

- 📝 **Automatic change tracking** - Captures inserts, updates, and deletes automatically
- 🔗 **Relationship tracking** - Records many-to-many associations and dissociations
- 👤 **User attribution** - Records who made the changes and their IP address
- 🔒 **Transactional integrity** - Audit entries are inserted within the same database transaction
- 🎯 **Flexible configuration** - Choose which entities and fields to audit
- 🔐 **Security controls** - Define who can view audit logs
- 🗄️ **Multi-database support** - Store audits in separate databases if needed

## Architecture Overview

The library is architected around two core concepts:

1. **Auditing Services** - Responsible for collecting audit events when changes occur
2. **Storage Services** - Responsible for persisting audit traces to the database

These services are provided by **Providers**. The library ships with a default provider for Doctrine ORM.

```
                    ┌───────────────────────────────────────┐
                    │            Your Application           │
                    └───────────────────┬───────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                    AUDITOR                                      │
│  ┌───────────────────────────────────────────────────────────────────────────┐  │
│  │                             Configuration                                 │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐   │  │
│  │  │   enabled   │  │  timezone   │  │ userProvider│  │ securityProvider│   │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────────┘   │  │
│  │  ┌─────────────┐                                                          │  │
│  │  │ roleChecker │                                                          │  │
│  │  └─────────────┘                                                          │  │
│  └───────────────────────────────────────────────────────────────────────────┘  │
│                                                                                 │
│  ┌───────────────────────────────────────────────────────────────────────────┐  │
│  │                            DoctrineProvider                               │  │
│  │                                                                           │  │
│  │     ┌─────────────────────────┐       ┌─────────────────────────┐         │  │
│  │     │    AuditingService(s)   │       │    StorageService(s)    │         │  │
│  │     │  ┌───────────────────┐  │       │  ┌───────────────────┐  │         │  │
│  │     │  │  EntityManager A  │  │       │  │  EntityManager X  │  │         │  │
│  │     │  │   (source data)   │  │       │  │  (audit storage)  │  │         │  │
│  │     │  └───────────────────┘  │       │  └───────────────────┘  │         │  │
│  │     └───────────┬─────────────┘       └─────────────┬───────────┘         │  │
│  │                 │                                   │                     │  │
│  │                 │    ┌─────────────────────────┐    │                     │  │
│  │                 └───►│  TransactionProcessor   │◄───┘                     │  │
│  │                      │  ┌───────────────────┐  │                          │  │
│  │                      │  │ • Track inserts   │  │                          │  │
│  │                      │  │ • Track updates   │  │                          │  │
│  │                      │  │ • Track deletes   │  │                          │  │
│  │                      │  │ • Track relations │  │                          │  │
│  │                      │  └───────────────────┘  │                          │  │
│  │                      └────────────┬────────────┘                          │  │
│  │                                   │                                       │  │
│  └───────────────────────────────────┼───────────────────────────────────────┘  │
│                                      │                                          │
│  ┌───────────────────────────────────┼───────────────────────────────────────┐  │
│  │                EventDispatcher    │                                       │  │
│  │                      ┌────────────▼────────────┐                          │  │
│  │                      │     LifecycleEvent      │                          │  │
│  │                      └────────────┬────────────┘                          │  │
│  └───────────────────────────────────┼───────────────────────────────────────┘  │
└──────────────────────────────────────┼──────────────────────────────────────────┘
                                       │
                                       ▼
                      ┌────────────────────────────────┐
                      │        Audit Tables            │
                      │  ┌──────────────────────────┐  │
                      │  │  • users_audit           │  │
                      │  │  • posts_audit           │  │
                      │  │  • orders_audit          │  │
                      │  │  • ...                   │  │
                      │  └──────────────────────────┘  │
                      └────────────────────────────────┘
```

### Data Flow

1. **Entity Change** → Your application modifies an entity via Doctrine
2. **Detection** → `AuditingService` detects the change through Doctrine events
3. **Processing** → `TransactionProcessor` computes diffs and prepares audit data
4. **Event** → A `LifecycleEvent` is dispatched with the audit payload
5. **Persistence** → `StorageService` persists the audit entry to the database

## Supported Databases

The DoctrineProvider supports the following RDBMS:

| Database   | Support Level |
|------------|---------------|
| MySQL      | ✅ Full       |
| MariaDB    | ✅ Full       |
| PostgreSQL | ✅ Full       |
| SQLite     | ✅ Full       |

> **Note:** The DoctrineProvider should work with any database supported by Doctrine, though only the above are actively tested.

## Version Compatibility

| Version | Status                     | Requirements                                                          |
|---------|----------------------------|-----------------------------------------------------------------------|
| 4.x     | Active development 🚀      | PHP >= 8.4, Symfony >= 8.0, Doctrine DBAL >= 4.0, Doctrine ORM >= 3.2 |
| 3.x     | Active support             | PHP >= 8.2, Symfony >= 5.4                                            |
| 2.x     | End of Life                | PHP >= 7.4, Symfony >= 4.4                                            |
| 1.x     | End of Life                | PHP >= 7.2, Symfony >= 3.4                                            |

## Quick Links

- [Installation Guide](getting-started/installation.md)
- [Quick Start](getting-started/quick-start.md)
- [Configuration Reference](configuration/index.md)
- [DoctrineProvider](providers/doctrine/index.md)
- [Querying Audits](querying/index.md)
- [Extra Data](extra-data.md)
- [API Reference](api/index.md)

## Related Projects

- **[auditor-bundle](https://github.com/DamienHarper/auditor-bundle)** - Symfony bundle for seamless integration

## License

This library is released under the [MIT License](https://opensource.org/licenses/MIT).
