# Running Tests

This document explains how to run the test suite for the auditor library.

## Quick Start

```bash
# Run tests with your local PHP
composer test

# Run tests with coverage
composer test:coverage

# Run tests with testdox output
composer testdox
```

## Testing with Docker (Recommended)

The project includes a `Makefile` that lets you test across different PHP versions, Symfony versions, and databases using Docker.

### Prerequisites

- Docker
- Docker Compose
- Make

### Available Commands

```bash
# Show help
make help

# Run tests (defaults: PHP 8.4, Symfony 8.0, SQLite)
make tests

# Run tests with specific database
make tests db=mysql
make tests db=pgsql
make tests db=mariadb

# Run tests with specific PHP version
make tests php=8.5

# Run a specific test
make tests args='--filter=ReaderTest'
```

### Supported Matrix

| Option | Values                                |
|--------|---------------------------------------|
| `php`  | `8.4`, `8.5`                          |
| `sf`   | `8.0`                                 |
| `db`   | `sqlite`, `mysql`, `pgsql`, `mariadb` |

### Testing Full Matrix

Before submitting a PR, test against multiple databases:

```bash
make tests db=sqlite
make tests db=mysql
make tests db=pgsql
make tests db=mariadb
```

## Writing Tests

- Place tests in `tests/` mirroring the `src/` structure
- Use meaningful test method names
- Include positive and negative test cases
- Ensure tests pass on all supported databases

See [docs/contributing.md](../docs/contributing.md) for detailed guidelines.
