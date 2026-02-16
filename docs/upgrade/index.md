# Upgrade Guide

> **Navigate between major versions of auditor**

This guide helps you upgrade between major versions of auditor.

## 📚 Upgrade Paths

- [⬆️ Upgrading to 4.x from 3.x](v4.md)
- [⬆️ Upgrading to 3.x from 2.x](v3.md)

## 📋 Version Support

| Version | Status                      | PHP       | Symfony   |
|:--------|:----------------------------|:----------|:----------|
| 4.x     | Active development 🚀       | >= 8.4    | >= 8.0    |
| 3.x     | Active support              | >= 8.2    | >= 5.4    |
| 2.x     | End of Life                 | >= 7.4    | >= 4.4    |
| 1.x     | End of Life                 | >= 7.2    | >= 3.4    |

## ✅ General Upgrade Tips

> [!IMPORTANT]
> Always follow these steps when upgrading:

1. **Read the full changelog** before upgrading
2. **Backup your database** including audit tables
3. **Test in staging** before production
4. **Update dependencies first** - Symfony, Doctrine, PHP
5. **Run tests** after upgrading
6. **Check for deprecations** before the major version

## 🔍 Checking Your Version

```bash
composer show damienharper/auditor
```

## 📦 Updating

```bash
# Update to latest 4.x
composer require damienharper/auditor:^4.0

# Update to latest 3.x (if on 3.x)
composer require damienharper/auditor:^3.0
```
