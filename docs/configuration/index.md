---
description: Reference of the auditor Configuration options (enabled, timezone, user provider, security provider, role checker, extra data provider) and how to set them.
---

# Configuration Reference

> **All configuration options available in auditor**

This section covers all configuration options available in auditor.

## ⚙️ Auditor Configuration

The main `Configuration` class accepts the following options:

```php
<?php

use DH\Auditor\Configuration;

$configuration = new Configuration([
    'enabled' => true,
    'timezone' => 'UTC',
    'user_provider' => null,
    'security_provider' => null,
    'role_checker' => null,
    'extra_data_provider' => null,
]);
```

### Options Reference

| Option              | Type              | Default | Description                                         |
|---------------------|-------------------|---------|-----------------------------------------------------|
| `enabled`           | `bool`            | `true`  | Enable or disable auditing globally                 |
| `timezone`          | `string`          | `'UTC'` | Timezone used for audit timestamps                  |
| `user_provider`     | `callable\|null`  | `null`  | Callback that returns the current user              |
| `security_provider` | `callable\|null`  | `null`  | Callback that returns security context info         |
| `role_checker`      | `callable\|null`  | `null`  | Callback that checks if user can view entity audits |
| `extra_data_provider` | `callable\|null` | `null` | Callback that returns the `extra_data` of the entries of a flush (see [Extra Data](../extra-data.md)) |

## 🔧 Configuration Methods

### Enable/Disable Auditing

```php
// Disable auditing
$configuration->disable();

// Re-enable auditing
$configuration->enable();

// Check if auditing is enabled
if ($configuration->enabled) {
    // ...
}
```

### Get Timezone

```php
$timezone = $configuration->timezone;
```

## 👤 User Provider

The user provider is a callable that returns information about the current user. This is used to record who made each change.

```php
use DH\Auditor\User\User;

$configuration->setUserProvider(function (): ?User {
    // Return null if no user is authenticated
    $currentUser = $this->security->getUser();
    
    if (null === $currentUser) {
        return null;
    }
    
    return new User(
        (string) $currentUser->getId(),  // User identifier
        $currentUser->getUsername(),      // Username for display
        $currentUser::class,              // Class of the user (user_fqdn)
    );
});
```

The `User` class implements `UserInterface`:

```php
namespace DH\Auditor\User;

interface UserInterface
{
    public ?string $identifier { get; }
    public ?string $username { get; }
    public ?string $fqdn { get; }
    public ?UserInterface $impersonator { get; }
}
```

See [User Provider Configuration](user-provider.md) for more details. To record *how* a change was made (an
import, a command, a message…), see [Blame Context](blame-context.md).

## 🔐 Security Provider

The security provider returns the client IP address and the firewall name, as a list of two values:

```php
$configuration->setSecurityProvider(function (): array {
    return [
        $_SERVER['REMOTE_ADDR'] ?? null, // client IP
        $this->getFirewallName(),        // firewall name
    ];
});
```

See [Security Provider Configuration](security-provider.md) for more details.

## 🛡️ Role Checker

The role checker determines whether a user can view audits for a specific entity:

```php
$configuration->setRoleChecker(function (string $entity, string $scope): bool {
    // $entity is the FQCN of the audited entity
    // $scope is the action (e.g., 'view')
    
    // Example: Only admins can view User audits
    if ($entity === User::class) {
        return $this->security->isGranted('ROLE_ADMIN');
    }
    
    // Allow everyone to view other audits
    return true;
});
```

See [Role Checker Configuration](role-checker.md) for more details.

## 🗄️ DoctrineProvider Configuration

The DoctrineProvider has its own configuration. See [DoctrineProvider Configuration](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/providers/doctrine/configuration) for details.

## 📝 Configuration Summary

```php
<?php

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\User\User;
use Symfony\Component\EventDispatcher\EventDispatcher;

$configuration = new Configuration([
    'enabled' => true,
    'timezone' => 'Europe/Paris',
]);

// Set user provider
$configuration->setUserProvider(function (): ?User {
    $user = $securityContext->getUser();
    if (null === $user) {
        return null;
    }
    return new User((string) $user->getId(), $user->getUsername(), $user::class);
});

// Set security provider
$configuration->setSecurityProvider(function (): array {
    return [$_SERVER['REMOTE_ADDR'] ?? null, null];
});

// Set role checker
$configuration->setRoleChecker(function (string $entity, string $scope): bool {
    return $this->authorizationChecker->isGranted('ROLE_AUDIT_VIEWER');
});

$auditor = new Auditor($configuration, new EventDispatcher());
```

---

## Next Steps

- 👤 [User Provider Configuration](user-provider.md)
- 🧭 [Blame Context](blame-context.md)
- 🔐 [Security Provider Configuration](security-provider.md)
- 🛡️ [Role Checker Configuration](role-checker.md)
- 🗄️ [DoctrineProvider Configuration](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/providers/doctrine/configuration)
