# Role Checker Configuration

The role checker controls access to audit logs, allowing you to restrict who can view audits for specific entities.

## Overview

The role checker is a callback that is invoked when someone attempts to read audit entries. It determines whether the current user has permission to view the requested audits.

If access is denied, an `AccessDeniedException` is thrown.

## Setting Up a Role Checker

### Basic Example

```php
<?php

use DH\Auditor\Configuration;

$configuration = new Configuration(['enabled' => true]);

$configuration->setRoleChecker(function (string $entity, string $scope): bool {
    // $entity - The fully qualified class name of the entity being audited
    // $scope  - The action being performed (e.g., 'view')
    
    // Return true to allow access, false to deny
    return true;
});
```

### Symfony Security Integration

```php
<?php

use App\Entity\User;
use App\Entity\Payment;
use Symfony\Component\Security\Core\Security;

$configuration->setRoleChecker(function (string $entity, string $scope) use ($security): bool {
    // Admins can view all audits
    if ($security->isGranted('ROLE_ADMIN')) {
        return true;
    }
    
    // Managers can view most audits
    if ($security->isGranted('ROLE_MANAGER')) {
        // But not User audits (sensitive)
        return $entity !== User::class;
    }
    
    // Accountants can only view Payment audits
    if ($security->isGranted('ROLE_ACCOUNTANT')) {
        return $entity === Payment::class;
    }
    
    // Deny all other users
    return false;
});
```

## Parameters

The role checker receives two parameters:

| Parameter | Type     | Description                                      |
|-----------|----------|--------------------------------------------------|
| `$entity` | `string` | The FQCN of the entity being queried             |
| `$scope`  | `string` | The action scope (currently only `'view'`)       |

### Available Scopes

| Scope  | Constant                     | Description                |
|--------|------------------------------|----------------------------|
| `view` | `Security::VIEW_SCOPE`       | Viewing audit entries      |

## Using Entity-Level Security Attributes

You can also define view permissions directly on entities using the `#[Security]` attribute:

```php
<?php

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Security;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Auditable]
#[Security(view: ['ROLE_ADMIN', 'ROLE_AUDIT_VIEWER'])]
class SensitiveEntity
{
    // ...
}
```

The role checker should then verify these roles:

```php
<?php

$configuration->setRoleChecker(function (string $entity, string $scope) use ($security, $provider): bool {
    // Get entity configuration (including security roles from attributes)
    $configuration = $provider->getConfiguration();
    $entities = $configuration->getEntities();
    
    if (!isset($entities[$entity]['roles'][$scope])) {
        // No specific roles defined, allow access
        return true;
    }
    
    $requiredRoles = $entities[$entity]['roles'][$scope];
    
    // Check if user has any of the required roles
    foreach ($requiredRoles as $role) {
        if ($security->isGranted($role)) {
            return true;
        }
    }
    
    return false;
});
```

## Complex Authorization Logic

### Per-Entity Custom Rules

```php
<?php

$configuration->setRoleChecker(function (string $entity, string $scope) use ($security, $em): bool {
    $user = $security->getUser();
    
    if (null === $user) {
        return false;
    }
    
    // Check ownership-based access for certain entities
    switch ($entity) {
        case Order::class:
            // Users can view their own orders' audits
            // Admins can view all
            if ($security->isGranted('ROLE_ADMIN')) {
                return true;
            }
            
            // This would need additional context about which order is being queried
            return $security->isGranted('ROLE_ORDER_AUDITOR');
            
        case User::class:
            // Only super admins can view user audits
            return $security->isGranted('ROLE_SUPER_ADMIN');
            
        default:
            // Default: allow managers and above
            return $security->isGranted('ROLE_MANAGER');
    }
});
```

### Time-Based Restrictions

```php
<?php

$configuration->setRoleChecker(function (string $entity, string $scope) use ($security): bool {
    // Only allow audit viewing during business hours for non-admins
    if (!$security->isGranted('ROLE_ADMIN')) {
        $hour = (int) date('H');
        if ($hour < 9 || $hour > 17) {
            return false;
        }
    }
    
    return $security->isGranted('ROLE_AUDITOR');
});
```

## Error Handling

When the role checker returns `false`, an `AccessDeniedException` is thrown:

```php
<?php

use DH\Auditor\Exception\AccessDeniedException;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;

$reader = new Reader($provider);

try {
    $query = $reader->createQuery(SensitiveEntity::class);
    $audits = $query->execute();
} catch (AccessDeniedException $e) {
    // Handle access denied
    // $e->getMessage() contains details about the denial
}
```

## No Role Checker

If no role checker is configured (`null`), all users have access to all audit logs:

```php
// This allows everyone to view all audits
$configuration = new Configuration([
    'role_checker' => null,  // No access control
]);
```

## Best Practices

1. **Always implement a role checker in production** - Don't leave audits open to everyone
2. **Log access attempts** - Consider logging who accesses audit data
3. **Use the principle of least privilege** - Only grant access where necessary
4. **Consider data sensitivity** - Some entities may contain sensitive information
5. **Test your rules** - Ensure your authorization logic works as expected

## Related

- [Security Attribute](../providers/doctrine/attributes.md#security)
- [User Provider Configuration](user-provider.md)
- [Security Provider Configuration](security-provider.md)
