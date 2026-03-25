# UPGRADE FROM 4.x to 5.0

This document summarizes the backward incompatible changes introduced in auditor 5.0.

## Removal of the embedded DoctrineProvider

The `DH\Auditor\Provider\Doctrine` namespace has been **removed** from the `damienharper/auditor` package.
All Doctrine ORM functionality now lives exclusively in the dedicated
[`damienharper/auditor-doctrine-provider`](https://github.com/DamienHarper/auditor-doctrine-provider) package.

### Who is affected

- **Symfony users (auditor-bundle)**: no action required. The bundle already depends on
  `damienharper/auditor-doctrine-provider` and all class FQCNs remain identical.
- **Manual integration users**: run the migration command below.

### Migration

```bash
composer require damienharper/auditor-doctrine-provider:^2.0
```

No other code changes are needed — the namespace (`DH\Auditor\Provider\Doctrine\*`) is identical in the
external package.

## `NeedsConversionToAuditableType` moved

The interface `NeedsConversionToAuditableType` has moved from the core package to the doctrine-provider package.

| 4.x | 5.0 |
|-----|-----|
| `DH\Auditor\Transaction\NeedsConversionToAuditableType` | `DH\Auditor\Provider\Doctrine\Transaction\NeedsConversionToAuditableType` |

Update any custom Doctrine type that implements this interface:

```php
// Before (4.x)
use DH\Auditor\Transaction\NeedsConversionToAuditableType;

// After (5.0)
use DH\Auditor\Provider\Doctrine\Transaction\NeedsConversionToAuditableType;
```

## Quick migration

```bash
# 1. Install the external doctrine provider
composer require damienharper/auditor-doctrine-provider:^2.0

# 2. Update auditor itself
composer require damienharper/auditor:^5.0

# 3. If you implement NeedsConversionToAuditableType, update the import (see above)
```

## Need Help?

- [GitHub Issues](https://github.com/DamienHarper/auditor/issues)
