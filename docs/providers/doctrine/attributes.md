# Attributes Reference

Auditor uses PHP 8 attributes to configure entity auditing. This page documents all available attributes.

## Overview

| Attribute       | Target   | Description                               |
|-----------------|----------|-------------------------------------------|
| `#[Auditable]`  | Class    | Marks an entity for auditing              |
| `#[Ignore]`     | Property | Excludes a property from auditing         |
| `#[Security]`   | Class    | Defines who can view entity audits        |

## #[Auditable]

Marks a Doctrine entity as auditable.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;
```

### Usage

```php
<?php

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Auditable]
class Post
{
    // Entity will be audited
}
```

### Parameters

| Parameter  | Type   | Default | Description                        |
|------------|--------|---------|------------------------------------|
| `enabled`  | `bool` | `true`  | Whether auditing is enabled        |

### Examples

```php
// Auditing enabled (default)
#[Auditable]
class Post {}

// Explicitly enabled
#[Auditable(enabled: true)]
class Post {}

// Disabled by default (can be enabled at runtime)
#[Auditable(enabled: false)]
class DraftPost {}
```

### Enabling/Disabling at Runtime

```php
// Get the provider configuration
$configuration = $provider->getConfiguration();

// Disable auditing for an entity
$configuration->disableAuditFor(Post::class);

// Re-enable auditing
$configuration->enableAuditFor(Post::class);
```

## #[Ignore]

Excludes a property from being tracked in audits.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Ignore;
```

### Usage

```php
<?php

use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Auditable;
use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Ignore;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Auditable]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 255)]
    #[Ignore]  // Password changes won't be logged
    private string $password;

    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]  // Tokens shouldn't be in audit logs
    private ?string $resetToken = null;
}
```

### Parameters

The `#[Ignore]` attribute has no parameters.

### Common Use Cases

```php
#[ORM\Entity]
#[Auditable]
class User
{
    // Security-sensitive fields
    #[Ignore]
    private string $password;
    
    #[Ignore]
    private ?string $salt = null;
    
    #[Ignore]
    private ?string $resetToken = null;
    
    #[Ignore]
    private ?string $confirmationToken = null;
    
    // Technical/metadata fields
    #[Ignore]
    private ?int $loginCount = null;
    
    #[Ignore]
    private ?\DateTimeInterface $lastLogin = null;
    
    // Frequently changing but unimportant fields
    #[Ignore]
    private ?\DateTimeInterface $lastActivity = null;
}
```

### Inheritance

The `#[Ignore]` attribute is inherited. Properties marked in a parent class will also be ignored in child classes:

```php
#[ORM\Entity]
#[Auditable]
#[ORM\InheritanceType('SINGLE_TABLE')]
class BaseEntity
{
    #[Ignore]
    private ?\DateTimeInterface $updatedAt = null;
}

#[ORM\Entity]
class Post extends BaseEntity
{
    // updatedAt is also ignored here
}
```

## #[Security]

Defines which roles are allowed to view audits for an entity.

### Namespace

```php
use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Security;
```

### Usage

```php
<?php

use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Auditable;
use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Security;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Auditable]
#[Security(view: ['ROLE_ADMIN'])]
class User
{
    // Only users with ROLE_ADMIN can view User audits
}
```

### Parameters

| Parameter | Type            | Required | Description                              |
|-----------|-----------------|----------|------------------------------------------|
| `view`    | `array<string>` | Yes      | Array of roles allowed to view audits    |

### Examples

```php
// Single role
#[Security(view: ['ROLE_ADMIN'])]
class User {}

// Multiple roles (any of them grants access)
#[Security(view: ['ROLE_ADMIN', 'ROLE_AUDITOR', 'ROLE_COMPLIANCE'])]
class Order {}

// Different entities, different access levels
#[Security(view: ['ROLE_USER'])]  // Less restricted
class Comment {}

#[Security(view: ['ROLE_SUPER_ADMIN'])]  // Most restricted
class Payment {}
```

### How It Works

When you query audits using the `Reader`, it checks the role checker callback against the entity's security configuration:

1. Reader creates a query for an entity
2. Role checker is invoked with the entity class and scope
3. If the role checker returns `false`, an `AccessDeniedException` is thrown

```php
// In your role checker configuration
$configuration->setRoleChecker(function (string $entity, string $scope) use ($security, $provider): bool {
    $entities = $provider->getConfiguration()->getEntities();
    
    // Check if entity has security roles defined
    if (isset($entities[$entity]['roles'][$scope])) {
        $requiredRoles = $entities[$entity]['roles'][$scope];
        
        // User must have at least one of the required roles
        foreach ($requiredRoles as $role) {
            if ($security->isGranted($role)) {
                return true;
            }
        }
        
        return false;
    }
    
    // No security defined, allow access (or deny, depending on your policy)
    return true;
});
```

## Complete Example

```php
<?php

namespace App\Entity;

use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Auditable;
use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Ignore;
use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Security;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[Auditable]
#[Security(view: ['ROLE_ADMIN', 'ROLE_USER_AUDITOR'])]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    #[Ignore]  // Don't audit password changes (security)
    private string $password;

    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]  // Don't audit tokens
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Ignore]  // Frequent updates, low audit value
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Ignore]  // Metadata, usually audited separately
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Ignore]  // Metadata
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private Collection $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters and setters...
}
```

## Combining with Programmatic Configuration

Attributes can be combined with or overridden by programmatic configuration:

```php
use DH\Auditor\Provider\Doctrine\Configuration;

$configuration = new Configuration([
    'entities' => [
        // Override the Security attribute
        User::class => [
            'roles' => ['view' => ['ROLE_SUPER_ADMIN']],  // More restrictive
        ],
        
        // Add additional ignored columns
        Post::class => [
            'ignored_columns' => ['viewCount'],  // Added to attribute-defined ignores
        ],
        
        // Completely disable auditing despite #[Auditable]
        DraftPost::class => [
            'enabled' => false,
        ],
    ],
]);
```

## Best Practices

1. **Always ignore sensitive data** - Passwords, tokens, secrets
2. **Use Security attribute for sensitive entities** - Users, payments, etc.
3. **Consider ignoring metadata fields** - `createdAt`, `updatedAt`, `deletedAt`
4. **Apply principle of least privilege** - Start restrictive, relax as needed
5. **Use descriptive role names** - `ROLE_AUDIT_VIEWER` instead of generic `ROLE_USER`

## Next Steps

- [Configuration Reference](configuration.md)
- [Querying Audits](../../querying/index.md)
- [Role Checker Configuration](../../configuration/role-checker.md)
