# auditor [![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=auditor,%20the%20missing%20audit%20log%20library.&url=https://github.com/DamienHarper/auditor&hashtags=auditor)

[![Latest Stable Version](https://poser.pugx.org/damienharper/auditor/v/stable)](https://packagist.org/packages/damienharper/auditor)
[![Latest Unstable Version](https://poser.pugx.org/damienharper/auditor/v/unstable)](https://packagist.org/packages/damienharper/auditor)
[![auditor 3.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-3.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-3.x.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=master)
[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/master/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/master)
[![License](https://poser.pugx.org/damienharper/auditor/license)](https://packagist.org/packages/damienharper/auditor)
[![Total Downloads](https://poser.pugx.org/damienharper/auditor/downloads)](https://packagist.org/packages/damienharper/auditor)
[![Monthly Downloads](https://poser.pugx.org/damienharper/auditor/d/monthly)](https://packagist.org/packages/damienharper/auditor)
[![Daily Downloads](https://poser.pugx.org/damienharper/auditor/d/daily)](https://packagist.org/packages/damienharper/auditor)

The purpose of `auditor` is to provide an easy and standardized way to collect audit logs.


## Architecture
This library is architected around two concepts:
- Auditing services responsible for collecting audit events
- Storage services responsible for persisting audit traces

Those two kind of services are offered by Providers.


## Default provider
A default provider is included with this library: the `DoctrineProvider`

`DoctrineProvider` offers both auditing services and sorage services.
It creates audit logs for all `Doctrine ORM` database related changes:

- inserts and updates including their diffs and relation field diffs.
- many to many relation changes, association and dissociation actions.
- if available, the user responsible for these changes and his IP address are recorded. 
- audit entries are inserted within the same transaction during **flush** event 
so that even if something fails the global state remains clean.

`DoctrineProvider` supports following RDBMS
* MySQL
* MariaDB
* PostgreSQL
* SQLite

`DoctrineProvider` should work with **any other** database supported by `Doctrine`. 
Though, we can only really support the ones we can test with [GitHub Actions](https://github.com/features/actions).

Basically you can track any change of any entity from audit logs.

**NOTE:** `DoctrineProvider` cannot track DQL or direct SQL statements executions.


## Official Documentation
`auditor` official documentation can be found [here](https://damienharper.github.io/auditor-docs/).


## Version Information
| Version | Status                                         | Requirements               | Badges                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
|:--------|:-----------------------------------------------|:---------------------------|:------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 3.x     | Active development :rocket: (Not yet released) | PHP >= 8.2, Symfony >= 5.4 | [![auditor 3.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-3.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-3.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=master) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/master/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/master) |
| 2.x     | Active support                                 | PHP >= 7.4, Symfony >= 4.4 | [![auditor 2.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-2.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-2.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=2.x)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=2.x) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/master/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/2.x)          |
| 1.x     | EOL                                            | PHP >= 7.2, Symfony >= 3.4 | [![auditor 1.x CI](https://github.com/DamienHarper/auditor/actions/workflows/ci-1.x.yml/badge.svg)](https://github.com/DamienHarper/auditor/actions/workflows/ci-1.x.yml) <br/>[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DamienHarper/auditor/badges/quality-score.png?b=1.x)](https://scrutinizer-ci.com/g/DamienHarper/auditor/?branch=1.x) <br/>[![codecov](https://codecov.io/gh/DamienHarper/auditor/branch/1.x/graph/badge.svg)](https://app.codecov.io/gh/DamienHarper/auditor/branch/1.x)             |

Changelog is available [here](https://damienharper.github.io/auditor-docs/docs/auditor/release-notes.html)


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
