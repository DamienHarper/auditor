# auditor [![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=auditor,%20the%20missing%20audit%20log%20library.&url=https://github.com/DamienHarper/auditor&hashtags=auditor)

[![Latest Stable Version](https://poser.pugx.org/damienharper/auditor/v/stable)](https://packagist.org/packages/damienharper/auditor)
[![Latest Unstable Version](https://poser.pugx.org/damienharper/auditor/v/unstable)](https://packagist.org/packages/damienharper/auditor)
[![auditor 5.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-5.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-5.x.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=master)
[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/master/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/master)
[![License](https://poser.pugx.org/damienharper/auditor/license)](https://packagist.org/packages/damienharper/auditor)
[![Total Downloads](https://poser.pugx.org/damienharper/auditor/downloads)](https://packagist.org/packages/damienharper/auditor)
[![Monthly Downloads](https://poser.pugx.org/damienharper/auditor/d/monthly)](https://packagist.org/packages/damienharper/auditor)
[![Daily Downloads](https://poser.pugx.org/damienharper/auditor/d/daily)](https://packagist.org/packages/damienharper/auditor)

The purpose of `auditor` is to provide an easy and standardized way to collect audit logs:
who changed what, when, and how. It is the core of the auditor family: it defines the contracts
and the audit entry model, and providers do the change tracking and the storage.


## Which package do I need?
| Your application                     | Install                                                   | Brings                                              |
|:-------------------------------------|:----------------------------------------------------------|:----------------------------------------------------|
| Symfony with Doctrine ORM            | `composer require damienharper/auditor-bundle`            | auditor + Doctrine provider + configuration + viewer |
| Doctrine ORM without Symfony         | `composer require damienharper/auditor-doctrine-provider` | auditor + Doctrine provider                         |
| Another persistence layer            | `composer require damienharper/auditor`                   | the core only: [write your own provider](docs/providers/custom-provider.md) |

| Line | auditor | auditor-doctrine-provider | auditor-bundle | Status |
|:-----|:--------|:--------------------------|:---------------|:-------|
| Next | 5.x     | 2.x                       | 8.x            | Active development |
| Latest stable | 4.x  | 1.x                       | 7.x            | Maintained |

Since auditor 5.0 the Doctrine provider is no longer part of this package: it lives in
`auditor-doctrine-provider` (in 4.x it is still bundled, deprecated). `auditor-bundle` was formerly
known as `DoctrineAuditBundle`. See the [upgrade guides](https://damienharper.github.io/auditor-docs/auditor/upgrade).


## Using an AI coding agent?
Each package ships an [Agent Skill](https://agentskills.io) in its `skills/` folder (`vendor/damienharper/*/skills/`
once installed) describing its API for the installed version. With Claude Code:
```
/plugin marketplace add DamienHarper/auditor
/plugin install auditor-bundle@auditor
```
The documentation is also available as [llms.txt](https://damienharper.github.io/auditor-docs/llms.txt).


## Architecture
This library is architected around two concepts:
- Auditing services responsible for collecting audit events
- Storage services responsible for persisting audit traces

Those two kind of services are offered by Providers.


## Providers
The Doctrine ORM provider lives in its own package,
[`auditor-doctrine-provider`](https://github.com/DamienHarper/auditor-doctrine-provider). It creates audit logs for
all `Doctrine ORM` database related changes:

- inserts, updates and removals, including their diffs and relation field diffs;
- many-to-many relation changes, association and dissociation actions;
- if available, the user responsible for these changes, their IP address and how the change was made (blame context);
- audit entries are written within the same database transaction as the changes,
so that even if something fails the global state remains clean.

It supports MySQL, MariaDB, PostgreSQL and SQLite. Symfony applications use
[`auditor-bundle`](https://github.com/DamienHarper/auditor-bundle), which also ships a web viewer.

You can also [build your own provider](docs/providers/custom-provider.md).


## Official Documentation
`auditor` official documentation can be found [here](https://damienharper.github.io/auditor-docs/) or locally in the [docs/](docs/) folder.


## Version Information
| Version | Status                      | Requirements                                                        | Badges                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
|:--------|:----------------------------|:--------------------------------------------------------------------|:------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 5.x     | Active development :rocket: | PHP >= 8.4, Symfony >= 8.1                                          | [![auditor 5.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-5.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-5.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=master) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/master/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/master) |
| 4.x     | Bug and security fixes      | PHP >= 8.4, Symfony >= 8.1, Doctrine DBAL >= 4.0, Doctrine ORM >= 3.2 | [![auditor 4.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-4.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-4.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=4.x)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=4.x) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/4.x/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/4.x) |
| 3.x     | End of Life                 | PHP >= 8.2, Symfony >= 5.4                                          | [![auditor 3.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-3.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-3.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=3.x)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=3.x) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/3.x/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/3.x)             |
| 2.x     | EOL                         | PHP >= 7.4, Symfony >= 4.4                                          | [![auditor 2.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-2.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-2.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=2.x)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=2.x) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/master/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/2.x)          |
| 1.x     | EOL                         | PHP >= 7.2, Symfony >= 3.4                                          | [![auditor 1.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-1.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-1.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=1.x)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=1.x) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/1.x/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/1.x)             |

Changelog is available in the [releases](https://github.com/DamienHarper/auditor/releases), and the upgrade guides in [UPGRADE-5.0.md](UPGRADE-5.0.md) and the [documentation](https://damienharper.github.io/auditor-docs/auditor/upgrade).


## Contributing
`auditor` is an open source project. Contributions made by the community are welcome. 
Send us your ideas, code reviews, pull requests and feature requests to help us improve this project.

Do not forget to provide unit tests when contributing to this project. 
To do so, follow instructions in this dedicated [README](tests/README.md)


## Credits
- Thanks to [all contributors](https://github.com/DamienHarper/auditor/graphs/contributors)
- This library initially took some inspiration from [data-dog/audit-bundle](https://github.com/DATA-DOG/DataDogAuditBundle.git) and 
[simplethings/entity-audit-bundle](https://github.com/simplethings/EntityAuditBundle.git)
- Special thanks to [JetBrains](https://www.jetbrains.com/?from=auditor) for their *Licenses for Open Source Development*

## License
`auditor` is free to use and is licensed under the [MIT license](http://www.opensource.org/licenses/mit-license.php)
