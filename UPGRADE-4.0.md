# UPGRADE FROM 3.x to 4.0

This document describes the backward incompatible changes introduced in auditor 4.0 and how to adapt your code accordingly.

## Requirements Changes

### PHP Version
- **Minimum PHP version is now 8.2** (same as 3.x)

### Symfony Version
- **Minimum Symfony version is now 8.0** (was 5.4 in 3.x)
- Support for Symfony 5.4, 6.4, and 7.x has been dropped

### Doctrine Versions
- **Doctrine DBAL**: minimum version is now **4.0** (was 3.2 in 3.x)
- **Doctrine ORM**: minimum version is now **3.2** (was 2.13 in 3.x)

### PHPUnit Version
- **PHPUnit**: minimum version is now **12.0** (was 11.0 in 3.x)

## Removed Methods

### `DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper`

The following methods have been removed as they were compatibility shims for older Doctrine DBAL versions:

| Removed Method | Replacement |
|----------------|-------------|
| `DoctrineHelper::createSchemaManager($connection)` | `$connection->createSchemaManager()` |
| `DoctrineHelper::introspectSchema($schemaManager)` | `$schemaManager->introspectSchema()` |
| `DoctrineHelper::getMigrateToSql($connection, $fromSchema, $toSchema)` | See example below |

#### Migration example for `getMigrateToSql`:

**Before (3.x):**
```php
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;

$sqls = DoctrineHelper::getMigrateToSql($connection, $fromSchema, $toSchema);
```

**After (4.0):**
```php
use Doctrine\DBAL\Schema\Comparator;

$platform = $connection->getDatabasePlatform();
$sqls = $platform->getAlterSchemaSQL(
    (new Comparator($platform))->compareSchemas($fromSchema, $toSchema)
);
```

### `DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper::getRealClassName()`

This method now only handles `__CG__` proxies (Doctrine Common Gateway marker). With PHP 8.4+, Doctrine ORM uses native lazy objects instead of proxy classes.

## Internal Changes

These changes are internal and should not affect most users:

### `DoctrineHelper::setPrimaryKey()`
Now uses `PrimaryKeyConstraint` directly without fallback to deprecated `setPrimaryKey()` method.

### `DoctrineHelper::getReflectionPropertyValue()`
Now uses `getPropertyAccessor()` directly without fallback to deprecated `getReflectionProperty()` method.

### `PlatformHelper::getServerVersion()`
Simplified to use `getNativeConnection()` directly. The `getWrappedConnection()` fallback has been removed.

## Composer Scripts

The following composer scripts have been removed:
- `setup5` (Symfony 5.4)
- `setup6` (Symfony 6.4)
- `setup7` (Symfony 7.x)

Use the new unified `setup` script instead:
```bash
composer setup
```

## Migration Steps

1. **Update your PHP version** to 8.2 or higher
2. **Update your Symfony dependencies** to 8.0 or higher
3. **Update Doctrine dependencies**:
   - `doctrine/dbal` to ^4.0
   - `doctrine/orm` to ^3.2
4. **Update your code** to replace any removed method calls (see above)
5. **Run your test suite** to ensure everything works correctly

## Need Help?

If you encounter any issues during the upgrade, please:
1. Check the [official documentation](https://damienharper.github.io/auditor-docs/)
2. Open an issue on [GitHub](https://github.com/DamienHarper/auditor/issues)
