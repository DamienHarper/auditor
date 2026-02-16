# User Provider Configuration

> **Identify who made changes to audited entities**

The user provider is responsible for identifying who made changes to audited entities.

## 🔍 Overview

When an audit entry is created, auditor can record:

- **blame_id** - The unique identifier of the user
- **blame_user** - The username or display name

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
        $currentUser->getUsername()         // Username (stored as blame_user)
    );
});
```

### Symfony Security Integration

```php
<?php

use DH\Auditor\User\User;
use Symfony\Component\Security\Core\Security;

// In a service or controller
$configuration->setUserProvider(function () use ($security): ?User {
    $user = $security->getUser();
    
    if (null === $user) {
        return null;
    }
    
    return new User(
        $user->getUserIdentifier(),
        $user->getUserIdentifier()
    );
});
```

### With User Entity ID

If your user has a database ID:

```php
<?php

use DH\Auditor\User\User;

$configuration->setUserProvider(function () use ($security, $entityManager): ?User {
    $user = $security->getUser();
    
    if (null === $user) {
        return null;
    }
    
    // Get the user ID from the database entity
    $userId = $entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => $user->getUserIdentifier()])
        ?->getId();
    
    return new User(
        (string) ($userId ?? $user->getUserIdentifier()),
        $user->getUserIdentifier()
    );
});
```

## 📦 The User Class

The built-in `User` class is a simple value object:

```php
<?php

namespace DH\Auditor\User;

class User implements UserInterface
{
    public function __construct(
        private ?string $identifier,
        private ?string $username
    ) {}

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
```

## 🧩 Custom User Implementation

You can create your own user class by implementing `UserInterface`:

```php
<?php

namespace App\Audit;

use DH\Auditor\User\UserInterface;

class CustomUser implements UserInterface
{
    public function __construct(
        private string $id,
        private string $username,
        private string $email,
        private array $roles
    ) {}

    public function getIdentifier(): ?string
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return sprintf('%s (%s)', $this->username, $this->email);
    }
    
    // Additional methods for your needs
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getRoles(): array
    {
        return $this->roles;
    }
}
```

## 🌐 API/CLI Context

> [!TIP]
> For API requests or CLI commands, consider identifying the context (API token, CLI user) rather than leaving it empty.

```php
<?php

$configuration->setUserProvider(function (): ?User {
    // Check for CLI context
    if (PHP_SAPI === 'cli') {
        return new User('CLI', 'System (CLI)');
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
    echo "User ID: " . $entry->getUserId();       // blame_id
    echo "Username: " . $entry->getUsername();    // blame_user
    echo "User FQDN: " . $entry->getUserFqdn();   // blame_user_fqdn
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

- 🔐 [Security Provider Configuration](security-provider.md)
- 🛡️ [Role Checker Configuration](role-checker.md)
