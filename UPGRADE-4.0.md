# UPGRADE FROM 3.x to 4.0

This document summarizes the backward incompatible changes introduced in auditor 4.0.

For a complete upgrade guide with step-by-step instructions, see the [full documentation](docs/upgrade/v4.md).

## Requirements Changes

| Requirement   | 3.x     | 4.0     |
|---------------|---------|---------|
| PHP           | >= 8.2  | >= 8.4  |
| Symfony       | >= 5.4  | >= 8.0  |
| Doctrine DBAL | >= 3.2  | >= 4.0  |
| Doctrine ORM  | >= 2.13 | >= 3.2  |
| PHPUnit       | >= 11.0 | >= 12.0 |

## Breaking Changes

### PHP 8.4+ Modernization

| Change | Before (3.x) | After (4.0) |
|--------|--------------|-------------|
| Transaction types | `Transaction::INSERT` | `TransactionType::INSERT` |
| Entry access | `$entry->getType()` | `$entry->type` |
| User access | `$user->getIdentifier()` | `$user->identifier` |
| Configuration | `$config->isEnabled()` | `$config->enabled` |
| Namespace | `...\Annotation\*` | `...\Attribute\*` |
| Loader | `AnnotationLoader` | `AttributeLoader` |
| Subscribers | `EventSubscriberInterface` | `#[AsEventListener]` |
| Commands | `setName()` / `setDescription()` | `#[AsCommand]` |

### Removed Methods (DoctrineHelper)

| Removed Method                            | Replacement                          |
|-------------------------------------------|--------------------------------------|
| `DoctrineHelper::createSchemaManager()`   | `$connection->createSchemaManager()` |
| `DoctrineHelper::introspectSchema()`      | `$schemaManager->introspectSchema()` |
| `DoctrineHelper::getMigrateToSql()`       | See [full guide](docs/upgrade/v4.md) |

### DoctrineHelper::getRealClassName()

Now only handles `__CG__` proxies. With PHP 8.4+, Doctrine ORM uses native lazy objects.

### Composer Scripts

The `setup5`, `setup6`, `setup7` scripts have been replaced by a unified `setup` script:

```bash
composer setup
```

## Quick Migration

```bash
# 1. Update dependencies
composer require php:^8.4 symfony/framework-bundle:^8.0 \
    doctrine/dbal:^4.0 doctrine/orm:^3.2 \
    damienharper/auditor:^4.0

# 2. Update audit schema (if using auditor-bundle)
bin/console audit:schema:update --dump-sql
```

## Need Help?

- [Full upgrade documentation](docs/upgrade/v4.md)
- [GitHub Issues](https://github.com/DamienHarper/auditor/issues)
