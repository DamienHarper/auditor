---
description: Record the client IP address and firewall name of each audit entry with a security provider callable that returns a two-value list, in Symfony or plain PHP.
---

# Security Provider Configuration

> **Capture contextual security information for each audit event**

The security provider captures additional contextual information about each audit event: the client IP address and
the firewall name.

## 🔍 Overview

When an audit entry is created, the security provider supplies:

- **the client IP address**, stored under the `ip` key of the `blame` JSON column;
- **the firewall name**, stored under the `user_firewall` key of the `blame` JSON column.

The class of the user (`user_fqdn`) is not supplied by the security provider: it is the `fqdn` of the user returned by
the [user provider](user-provider.md).

## 🚀 Setting Up a Security Provider

The security provider is a callable (or a `DH\Auditor\Security\SecurityProviderInterface` implementation) that
returns a **list of two values**: the client IP address, then the firewall name. Either may be `null`.

### Basic Example

```php
<?php

use DH\Auditor\Configuration;

$configuration = new Configuration(['enabled' => true]);

$configuration->setSecurityProvider(function (): array {
    return [
        $_SERVER['REMOTE_ADDR'] ?? null, // client IP
        null,                            // firewall
    ];
});
```

### Complete Symfony Example

```php
<?php

use DH\Auditor\Security\SecurityProviderInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SecurityProvider implements SecurityProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private FirewallMap $firewallMap,
    ) {}

    public function __invoke(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [null, null];
        }

        return [
            $request->getClientIp(),
            $this->firewallMap->getFirewallConfig($request)?->getName(),
        ];
    }
}

$configuration->setSecurityProvider($securityProvider);
```

> [!NOTE]
> auditor-bundle ships such a security provider and configures it for you.

## 📋 Return Value Structure

The security provider must return a list (positional array) of two values:

| Position | Type           | Description                                     |
|----------|----------------|-------------------------------------------------|
| `0`      | `string\|null` | The IP address of the client making the request |
| `1`      | `string\|null` | The name of the security firewall used          |

> [!WARNING]
> Return a list, not an associative array: the values are read by position, so keys such as `client_ip` are not
> recognized and the IP address and firewall are stored as `null`.

## 🌐 Handling Different Contexts

### Behind a Proxy

> [!TIP]
> When behind a load balancer or reverse proxy, ensure trusted proxies are configured in Symfony. The
> `getClientIp()` method then handles `X-Forwarded-For` automatically.

### CLI Commands and Workers

There is no request in a console command or a Messenger worker: return `[null, null]`, or a value of your choice:

```php
$configuration->setSecurityProvider(function () use ($requestStack): array {
    $request = $requestStack->getCurrentRequest();

    if (null === $request) {
        return [null, null];      // console command, worker…
    }

    return [$request->getClientIp(), str_starts_with($request->getPathInfo(), '/api') ? 'api' : 'main'];
});
```

To record *how* a change was made outside a web request (a command, an import, a message), use a
[blame context](blame-context.md) rather than a fake IP address or firewall.

## 🔍 Accessing Security Info from Audit Entries

When reading audit entries:

```php
<?php

$reader = new Reader($provider);
$audits = $reader->createQuery(Post::class)->execute();

foreach ($audits as $entry) {
    echo "IP Address: " . $entry->ip;
    echo "User Class: " . $entry->userFqdn;
    echo "Firewall: " . $entry->userFirewall;
}
```

## 🌐 IP Address Format

The IP address is stored as a string in the `blame` JSON column, IPv4 (`192.168.1.1`) or IPv6
(`2001:0db8:85a3:0000:0000:8a2e:0370:7334`).

## ✅ Best Practices

1. **Always handle a null request** - The request may not be available (CLI, async jobs)
2. **Configure trusted proxies** - When behind a reverse proxy
3. **Always return two values** - `[$ip, $firewall]`, each possibly `null`
4. **Consider privacy regulations** - IP logging may be subject to GDPR or similar laws

> [!NOTE]
> Remember to comply with privacy regulations when storing IP addresses. Consider implementing data retention policies.

---

## Related

- 👤 [User Provider Configuration](user-provider.md)
- 🛡️ [Role Checker Configuration](role-checker.md)
- 🧭 [Blame Context](blame-context.md)
