---
description: Record who made each change with a user provider callable returning a User or a custom UserInterface, and read the blamed user back from audit entries.
---

# User Provider Configuration

> **Identify who made changes to audited entities**

The user provider is responsible for identifying who made changes to audited entities.

## 🔍 Overview

When an audit entry is created, auditor can record:

- **blame_id** - The unique identifier of the user
- **username** - The username or display name (stored in the `blame` JSON column)

This information comes from the user provider callback.

## 🚀 Setting Up a User Provider

### Basic Example

```php
<?php

use DH\Auditor\Configuration;
use DH\Auditor\User\User;

$configuration = new Configuration([
    'enabled' => true,
]);

$configuration->setUserProvider(function (): ?User {
    // Get the currently authenticated user from your application
    $currentUser = $this->getAuthenticatedUser();
    
    if (null === $currentUser) {
        return null;  // No user authenticated
    }
    
    return new User(
        (string) $currentUser->getId(),    // User ID (stored as blame_id)
        $currentUser->getUsername(),        // Username (stored in blame JSON column)
        $currentUser::class,                // Class of the user (user_fqdn key of the blame JSON column)
    );
});
```

### Symfony Security Integration

```php
<?php

use DH\Auditor\User\User;
use Symfony\Bundle\SecurityBundle\Security;

// In a service or controller
$configuration->setUserProvider(function () use ($security): ?User {
    $user = $security->getUser();
    
    if (null === $user) {
        return null;
    }
    
    return new User(
        $user->getUserIdentifier(),
        $user->getUserIdentifier(),
        $user::class,
    );
});
```

### With User Entity ID

If your user has a database ID:

```php
<?php

use App\Entity\User as AppUser;
use DH\Auditor\User\User;

$configuration->setUserProvider(function () use ($security, $entityManager): ?User {
    $user = $security->getUser();
    
    if (null === $user) {
        return null;
    }
    
    // Get the user ID from the database entity
    $userId = $entityManager
        ->getRepository(AppUser::class)
        ->findOneBy(['email' => $user->getUserIdentifier()])
        ?->getId();
    
    return new User(
        (string) ($userId ?? $user->getUserIdentifier()),
        $user->getUserIdentifier()
    );
});
```

## 📦 The User Class

The built-in `User` class is a final value object. `UserInterface` declares four read-only properties (PHP 8.4
property hooks), read by the providers:

```php
<?php

namespace DH\Auditor\User;

interface UserInterface
{
    public ?string $identifier { get; }   // stored as blame_id

    public ?string $username { get; }     // stored in the blame JSON column

    public ?string $fqdn { get; }         // class of the user, user_fqdn key of the blame JSON column

    public ?UserInterface $impersonator { get; } // impersonator key of the blame JSON column
}

final class User implements UserInterface
{
    public ?string $identifier {
        get => $this->id;
    }

    public function __construct(
        private readonly ?string $id = null,
        public readonly ?string $username = null,
        public readonly ?string $fqdn = null,
        public readonly ?UserInterface $impersonator = null,
    ) {}
}
```

`fqdn` is the class of the user in your application (such as `App\Entity\User`), recorded as `user_fqdn`: it tells
users of different classes apart when they share an id. It is null when the user provider does not supply it.

## 🎭 Impersonation

When a user impersonates another one (switch user), return the impersonated user and pass the impersonator: the
changes are blamed on the impersonated user, and the impersonator is recorded in the `impersonator` key of the
blame JSON column.

```php
<?php

return new User(
    (string) $impersonated->getId(),
    $impersonated->getUserIdentifier(),
    $impersonated::class,
    new User((string) $impersonator->getId(), $impersonator->getUserIdentifier(), $impersonator::class),
);
```

```json
{
  "username": "alice",
  "user_fqdn": "App\\Entity\\Customer",
  "user_firewall": "main",
  "ip": "10.0.0.1",
  "impersonator": {"id": "1", "username": "admin", "fqdn": "App\\Entity\\Admin"}
}
```

The key is written only when an impersonator is set; `$entry->impersonator` returns it (null otherwise).

## 🧩 Custom User Implementation

You can create your own user class by implementing `UserInterface`:

```php
<?php

namespace App\Audit;

use DH\Auditor\User\UserInterface;

final readonly class CustomUser implements UserInterface
{
    public ?string $identifier;
    public ?string $username;
    public ?string $fqdn;
    public ?UserInterface $impersonator;

    public function __construct(string $id, string $name, public string $email)
    {
        $this->identifier = $id;
        $this->username = sprintf('%s (%s)', $name, $email);
        $this->fqdn = \App\Entity\User::class;
        $this->impersonator = null;
    }
}
```

A readonly property satisfies a `{ get; }` property of an interface.

## 🌐 API/CLI Context

> [!TIP]
> For API requests, consider identifying the API client rather than leaving the user empty. For CLI commands,
> return `null` (no fake user) and record how the change was made with a [blame context](blame-context.md),
> such as `new BlameContext('console', 'app:import-products')`.

```php
<?php

$configuration->setUserProvider(function (): ?User {
    // CLI context: no user to blame, the command is recorded by a blame context
    if (PHP_SAPI === 'cli') {
        return null;
    }
    
    // Check for API token
    $apiToken = $_SERVER['HTTP_X_API_TOKEN'] ?? null;
    if ($apiToken) {
        $apiUser = $this->apiUserRepository->findByToken($apiToken);
        if ($apiUser) {
            return new User(
                (string) $apiUser->getId(),
                sprintf('API: %s', $apiUser->getName())
            );
        }
    }
    
    // Regular web user
    $user = $this->security->getUser();
    if ($user) {
        return new User(
            $user->getUserIdentifier(),
            $user->getUserIdentifier()
        );
    }
    
    return null;
});
```

## 🔍 Accessing User Info from Audit Entries

When reading audit entries, you can access the recorded user information:

```php
<?php

$reader = new Reader($provider);
$query = $reader->createQuery(Post::class, ['object_id' => 123]);
$audits = $query->execute();

foreach ($audits as $entry) {
    echo "User ID: " . $entry->userId;    // blame_id column
    echo "Username: " . $entry->username; // blame JSON → username key
    echo "User FQDN: " . $entry->userFqdn; // blame JSON → user_fqdn key
    echo "Impersonator: " . ($entry->impersonator['username'] ?? '-'); // blame JSON → impersonator key
}
```

## ✅ Best Practices

1. **Always return `null` when no user is available** - Don't create fake users
2. **Use meaningful identifiers** - Use the actual user ID, not just the username
3. **Include useful context in the username** - Consider including role or context info
4. **Handle all authentication methods** - Consider API tokens, CLI, impersonation, etc.

> [!IMPORTANT]
> Always return `null` when there's no authenticated user. Don't create placeholder users as this can lead to confusing audit trails.

---

## Related

- 🧭 [Blame Context](blame-context.md): record how a change was made, or blame another user for a unit of work
- 🔐 [Security Provider Configuration](security-provider.md)
- 🛡️ [Role Checker Configuration](role-checker.md)
