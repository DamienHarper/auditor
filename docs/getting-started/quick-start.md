# Quick Start Guide

> **Set up the auditor core and connect a provider**

`auditor` is a framework-agnostic audit library. It handles the orchestration — event
dispatching, user attribution, security — while **providers** handle the actual
storage and query layer.

## 1️⃣ Create the Auditor instance

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
    // Return the current authenticated user, or null
    return new User((string) $currentUser->getId(), $currentUser->getUsername());
});

// Optional: provide IP + firewall context
$configuration->setSecurityProvider(function (): array {
    return [$request->getClientIp(), 'main'];
});

$auditor = new Auditor($configuration, new EventDispatcher());
```

## 2️⃣ Register a provider

A provider connects auditor to a specific storage technology.
Register it after creating the `Auditor` instance.

```php
$auditor->registerProvider($provider);
```

From this point all changes detected by the provider are dispatched through the
auditor's event system and persisted.

## Available providers

| Provider | Package | Storage |
|----------|---------|---------|
| DoctrineProvider | [auditor-doctrine-provider](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/) | Doctrine ORM / DBAL |

For a complete setup walkthrough including entity configuration, schema creation, and
reading audit entries, follow your provider's quick start:

→ **[DoctrineProvider Quick Start](https://damienharper.github.io/auditor-docs/auditor-doctrine-provider/getting-started/quick-start)**

---

## What's Next?

- ⚙️ [Configuration Reference](../configuration/index.md) — Global options (user provider, security, role checker)
- 🔌 [Providers](../providers/doctrine/index.md) — Available providers
- 🔍 [Querying Audits](../querying/index.md) — Reading audit entries
