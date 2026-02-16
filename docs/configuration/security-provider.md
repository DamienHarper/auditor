# Security Provider Configuration

> **Capture contextual security information for each audit event**

The security provider captures additional contextual information about each audit event, such as the client IP address and firewall name.

## 🔍 Overview

When an audit entry is created, the security provider can supply:

- **ip** - The client's IP address
- **blame_user_fqdn** - The fully qualified class name of the user
- **blame_user_firewall** - The Symfony firewall name (if applicable)

## 🚀 Setting Up a Security Provider

### Basic Example

```php
<?php

use DH\Auditor\Configuration;

$configuration = new Configuration(['enabled' => true]);

$configuration->setSecurityProvider(function (): array {
    return [
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_fqdn' => null,
        'user_firewall' => null,
    ];
});
```

### Complete Symfony Example

```php
<?php

use DH\Auditor\Configuration;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;

$configuration->setSecurityProvider(
    function () use ($requestStack, $tokenStorage, $firewallMap): array {
        $request = $requestStack->getCurrentRequest();
        $token = $tokenStorage->getToken();
        
        return [
            'client_ip' => $request?->getClientIp(),
            'user_fqdn' => $token?->getUser()?::class,
            'user_firewall' => $this->getFirewallName($request, $firewallMap),
        ];
    }
);

// Helper method to get firewall name
function getFirewallName(?Request $request, FirewallMap $firewallMap): ?string
{
    if (null === $request) {
        return null;
    }
    
    $firewallConfig = $firewallMap->getFirewallConfig($request);
    
    return $firewallConfig?->getName();
}
```

## 📋 Return Value Structure

The security provider must return an array with these keys:

| Key              | Type          | Description                                      |
|------------------|---------------|--------------------------------------------------|
| `client_ip`      | `string\|null`| The IP address of the client making the request  |
| `user_fqdn`      | `string\|null`| The fully qualified class name of the user object|
| `user_firewall`  | `string\|null`| The name of the security firewall used           |

## 🌐 Handling Different Contexts

### Web Requests

```php
$configuration->setSecurityProvider(function () use ($requestStack): array {
    $request = $requestStack->getCurrentRequest();
    
    return [
        'client_ip' => $request?->getClientIp(),
        'user_fqdn' => $this->security->getUser()?::class,
        'user_firewall' => $this->getFirewallName($request),
    ];
});
```

### Behind a Proxy

> [!TIP]
> When behind a load balancer or reverse proxy, ensure trusted proxies are configured in Symfony. The `getClientIp()` method will automatically handle `X-Forwarded-For`.

```php
$configuration->setSecurityProvider(function () use ($requestStack): array {
    $request = $requestStack->getCurrentRequest();
    
    // Ensure trusted proxies are configured in Symfony
    // The getClientIp() method will automatically handle X-Forwarded-For
    $clientIp = $request?->getClientIp();
    
    return [
        'client_ip' => $clientIp,
        'user_fqdn' => null,
        'user_firewall' => null,
    ];
});
```

### CLI Commands

```php
$configuration->setSecurityProvider(function (): array {
    if (PHP_SAPI === 'cli') {
        return [
            'client_ip' => gethostbyname(gethostname()) ?: '127.0.0.1',
            'user_fqdn' => 'CLI',
            'user_firewall' => null,
        ];
    }
    
    // Regular web handling...
    return [
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_fqdn' => null,
        'user_firewall' => null,
    ];
});
```

### API Requests

```php
$configuration->setSecurityProvider(function () use ($requestStack): array {
    $request = $requestStack->getCurrentRequest();
    
    // Check if this is an API request
    $isApi = str_starts_with($request?->getPathInfo() ?? '', '/api');
    
    return [
        'client_ip' => $request?->getClientIp(),
        'user_fqdn' => $this->security->getUser()?::class,
        'user_firewall' => $isApi ? 'api' : 'main',
    ];
});
```

## 🔍 Accessing Security Info from Audit Entries

When reading audit entries:

```php
<?php

$reader = new Reader($provider);
$audits = $reader->createQuery(Post::class)->execute();

foreach ($audits as $entry) {
    echo "IP Address: " . $entry->getIp();
    echo "User Class: " . $entry->getUserFqdn();
    echo "Firewall: " . $entry->getUserFirewall();
}
```

## 🌐 IP Address Format

The `ip` column supports both IPv4 and IPv6 addresses (max 45 characters):

- IPv4: `192.168.1.1`
- IPv6: `2001:0db8:85a3:0000:0000:8a2e:0370:7334`

## ✅ Best Practices

1. **Always handle null request** - The request may not be available (CLI, async jobs)
2. **Configure trusted proxies** - When behind a reverse proxy
3. **Return consistent data types** - Always return an array with all three keys
4. **Consider privacy regulations** - IP logging may be subject to GDPR or similar laws

> [!NOTE]
> Remember to comply with privacy regulations when storing IP addresses. Consider implementing data retention policies.

---

## Related

- 👤 [User Provider Configuration](user-provider.md)
- 🛡️ [Role Checker Configuration](role-checker.md)
