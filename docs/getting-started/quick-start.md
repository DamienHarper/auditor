# Quick Start Guide

> **Set up the auditor core and connect a provider**

`auditor` is a framework-agnostic audit library. It handles orchestration — event
dispatching, user attribution, security — while **providers** handle storage and querying.

```mermaid
flowchart LR
    A["1️⃣ Configure\nAuditor"] --> B["2️⃣ Register\na Provider"]
    B --> C["3️⃣ Mark entities\nas Auditable"]
    C --> D["🎉 Done!"]
```

## 1️⃣ Configure the Auditor

The `Auditor` class is the central registry. Configure it once at bootstrap time.

```php
<?php

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\User\User;
use Symfony\Component\EventDispatcher\EventDispatcher;

$configuration = new Configuration([
    'enabled'  => true,
    'timezone' => 'UTC',
]);

// Optional: tell auditor who is making changes
$configuration->setUserProvider(function (): ?User {
    return new User((string) $currentUser->getId(), $currentUser->getUsername());
});

// Optional: provide IP + firewall context
$configuration->setSecurityProvider(function (): array {
    return [$request->getClientIp(), 'main'];
});

$auditor = new Auditor($configuration, new EventDispatcher());
```

## 2️⃣ Register a Provider

A provider connects auditor to a specific storage technology. Each provider has its own
setup (services, schema, etc.) — refer to your provider's documentation.

```php
$auditor->registerProvider($provider);
```

| Provider | Package | Storage |
|----------|---------|---------|
| DoctrineProvider | [auditor-doctrine-provider](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/) | Doctrine ORM / DBAL |

## 3️⃣ Mark Entities as Auditable

Use the `#[Auditable]` attribute on any class you want tracked. `#[Ignore]` excludes
individual fields; `#[Security]` restricts who can view the audit entries.

```php
<?php

namespace App\Entity;

use DH\Auditor\Attribute\Auditable;
use DH\Auditor\Attribute\Ignore;
use DH\Auditor\Attribute\Security;

#[Auditable]
#[Security(view: ['ROLE_ADMIN'])]
class User
{
    private string $email;

    #[Ignore]
    private string $password;  // this field will never appear in audit diffs
}
```

The attributes are part of `auditor` core (`DH\Auditor\Attribute\`) and work with any
provider. How a provider discovers and registers auditable entities is provider-specific —
see your provider's documentation for details.

## 🎉 Done!

From this point, every flush that touches an auditable entity will produce an audit entry.
Refer to your provider's quick start for schema setup and reading audit entries back:

→ **[DoctrineProvider Quick Start](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/getting-started/quick-start)**

---

## What's Next?

- ⚙️ [Configuration Reference](../configuration/index.md) — Global options (user provider, security, role checker)
- 🏷️ [Attributes Reference](../providers/doctrine/attributes.md) — `#[Auditable]`, `#[Ignore]`, `#[Security]`
- 🔌 [Providers](../providers/doctrine/index.md) — Available providers
- 🔍 [Querying Audits](../querying/index.md) — Reading audit entries
