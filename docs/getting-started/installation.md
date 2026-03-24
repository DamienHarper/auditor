# Installation

> **Install auditor using Composer**

This guide covers the installation of the auditor library using Composer.

## 📋 Requirements

### Version 5.x (Current)

| Requirement    | Version  |
|----------------|----------|
| PHP            | >= 8.4   |
| Symfony        | >= 8.0   |

Doctrine ORM support requires the separate [`auditor-doctrine-provider`](https://github.com/DamienHarper/auditor-doctrine-provider) package.

### Previous Versions

| Version | PHP     | Symfony   | Doctrine DBAL | Doctrine ORM |
|---------|---------|-----------|---------------|--------------|
| 4.x     | >= 8.4  | >= 8.0    | >= 4.0        | >= 3.2       |
| 3.x     | >= 8.2  | >= 5.4    | >= 3.2        | >= 2.13      |
| 2.x     | >= 7.4  | >= 4.4    | -             | -            |
| 1.x     | >= 7.2  | >= 3.4    | -             | -            |

## 📦 Install via Composer

Open a terminal in your project directory and run:

```bash
composer require damienharper/auditor
```

This will install the latest stable version compatible with your PHP and dependency requirements.

### Installing a Specific Version

To install a specific version:

```bash
# Install the latest 5.x version
composer require damienharper/auditor:^5.0

# Install the latest 4.x version
composer require damienharper/auditor:^4.0
```

### Installing with Doctrine ORM support

```bash
composer require damienharper/auditor damienharper/auditor-doctrine-provider
```

## 🎵 Symfony Integration

For Symfony applications, we recommend using the **auditor-bundle** which provides:

- ✅ Automatic service configuration
- ✅ Web interface for browsing audits
- ✅ Console commands
- ✅ Twig extensions

```bash
composer require damienharper/auditor-bundle
```

> [!TIP]
> See the [auditor-bundle documentation](https://github.com/DamienHarper/auditor-bundle) for more details.

## 🔧 Standalone Usage

The library can be used without Symfony. You'll need to manually configure the Auditor and register providers.

See the [Quick Start Guide](quick-start.md) for a complete setup example.

## 📚 Dependencies

The `auditor` core library automatically installs the following dependencies:

| Package                      | Purpose                        |
|------------------------------|--------------------------------|
| `symfony/event-dispatcher`   | Event handling                 |
| `symfony/options-resolver`   | Configuration handling         |

Provider packages bring their own additional dependencies. For example, `auditor-doctrine-provider` adds:

| Package                      | Purpose                        |
|------------------------------|--------------------------------|
| `doctrine/dbal`              | Database abstraction layer     |
| `doctrine/orm`               | Object-Relational Mapping      |
| `symfony/cache`              | Metadata caching               |
| `symfony/lock`               | Command locking                |

---

## Next Steps

- 🚀 [Quick Start Guide](quick-start.md) - Set up auditing in your project
- ⚙️ [Configuration](../configuration/index.md) - Configure auditor for your needs
- 🗄️ [DoctrineProvider](../providers/doctrine/index.md) - Learn about the Doctrine provider
