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

The project includes a `Makefile` that lets you test across different PHP and Symfony versions using Docker. The
core library has no database code: its tests do not use one.

### Prerequisites

- Docker
- Docker Compose
- Make

### Available Commands

```bash
# Show help
make help

# Run tests (defaults: PHP 8.4, Symfony 8.1)
make tests

# Run tests with specific PHP and Symfony versions
make tests php=8.5 sf=8.2

# Run a specific test
make tests args='--filter=EntryTest'
```

### Supported Matrix

| Option | Values              |
|--------|---------------------|
| `php`  | `8.4`, `8.5`, `8.6` |
| `sf`   | `8.1`, `8.2`        |

## Writing Tests

- Place tests in `tests/` mirroring the `src/` structure
- Use meaningful test method names
- Include positive and negative test cases

See [docs/contributing.md](../docs/contributing.md) for detailed guidelines.
