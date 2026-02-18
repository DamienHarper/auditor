# Contributing

> **Thank you for your interest in contributing to auditor!**

## 🤝 Ways to Contribute

- 🐛 **Report bugs** - Submit issues on GitHub
- 💡 **Suggest features** - Open a discussion or issue
- 📖 **Improve documentation** - Fix typos, add examples, clarify explanations
- 🔧 **Submit code** - Fix bugs or implement new features
- ⭐ **Star the project** - Show your support

## 💻 Code Contributions

All code contributions are made via **Pull Requests (PR)**. Direct commits to the `master` branch are not allowed.

### 🚀 Development Setup

1. Fork the repository on GitHub
2. Clone your fork locally:

```bash
git clone https://github.com/YOUR_USERNAME/auditor.git
cd auditor
```

3. Install dependencies:

```bash
composer install
```

4. Create a branch for your changes:

```bash
git checkout -b feature/my-new-feature
```

### 🧪 Running Tests

#### Quick Tests (Local PHP)

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run tests with testdox output
composer testdox
```

#### 🐳 Testing with Docker (Recommended)

The project includes a `Makefile` that allows you to test against different combinations of PHP versions, Symfony versions, and databases using Docker containers.

> [!TIP]
> This ensures your code works across all supported environments before submitting a PR.

**Prerequisites:**
- Docker
- Docker Compose
- Make

**Available Make Targets:**

| Target    | Description                                                    |
|-----------|----------------------------------------------------------------|
| `tests`   | Run the test suite using PHPUnit                               |
| `cs-fix`  | Run PHP-CS-Fixer to fix coding standards                       |
| `phpstan` | Run PHPStan for static code analysis                           |
| `bench`   | Run PHPBench benchmarks (statistical, reproducible)            |
| `profile` | Profile with Blackfire (requires `BLACKFIRE_*` env vars)       |
| `help`    | Display available commands and options                         |

**Options:**

| Option | Values                                | Default  | Description              |
|--------|---------------------------------------|----------|--------------------------|
| `php`  | `8.4`, `8.5`                          | `8.4`    | PHP version              |
| `sf`   | `8.0`                                 | `8.0`    | Symfony version          |
| `db`   | `sqlite`, `mysql`, `pgsql`, `mariadb` | `sqlite` | Database type            |
| `args` | Any PHPUnit/tool arguments            | (varies) | Additional arguments     |

**Valid PHP/Symfony Combinations:**

| PHP Version | Symfony Versions |
|-------------|------------------|
| 8.4         | 8.0              |
| 8.5         | 8.0              |

**Examples:**

```bash
# Show all available commands and options
make help

# Run tests with defaults (PHP 8.4, Symfony 8.0, SQLite)
make tests

# Run tests with specific PHP version
make tests php=8.5

# Run tests with MySQL
make tests db=mysql

# Run tests with PostgreSQL
make tests db=pgsql

# Run tests with MariaDB
make tests db=mariadb

# Full specification
make tests php=8.4 sf=8.0 db=mysql

# Run specific test class
make tests args='--filter=ReaderTest'

# Run tests with coverage
make tests args='--coverage-html=coverage'
```

**Testing the Full Matrix:**

> [!IMPORTANT]
> Before submitting a pull request, it's recommended to test against multiple database types.

```bash
# Test all databases with PHP 8.4
make tests php=8.4 db=sqlite
make tests php=8.4 db=mysql
make tests php=8.4 db=pgsql
make tests php=8.4 db=mariadb
```

**How It Works:**

The Makefile uses Docker Compose to spin up containers with the specified PHP version and database. The configuration files are located in `tools/docker/`:

```
tools/docker/
├── compose.yaml             # Base configuration
├── compose.mysql.yaml       # MySQL service
├── compose.pgsql.yaml       # PostgreSQL service
├── compose.mariadb.yaml     # MariaDB service
├── compose.blackfire.yaml   # Blackfire agent sidecar (profiling)
└── Dockerfile               # PHP CLI image (+ auditor-blackfire stage)
```

### ⚡ Benchmarking

The project ships with a **PHPBench** benchmark suite and a **Blackfire** profiling script to measure the performance impact of changes on the Doctrine flush pipeline (audit entry generation + persistence).

Benchmark files live in `benchmarks/`:

```
benchmarks/
├── AuditBench.php   # PHPBench suite — 6 statistical scenarios
└── profile.php      # Standalone Blackfire profiling script
```

#### Composer Scripts (Recommended)

The quickest way to run benchmarks locally. All scripts honour the `BENCH_N` environment variable (default: `50`) to control the number of entities per flush.

| Script | Description |
|--------|-------------|
| `composer bench` | Run all benchmarks (aggregate report) |
| `composer bench:baseline` | Store a baseline tagged `before` |
| `composer bench:compare` | Compare current code against the `before` baseline |
| `composer bench:html` | Generate `benchmark-report.html` vs baseline |
| `composer profile` | Run the Blackfire profiling script (dry run without the CLI) |

```bash
# Simple run
BENCH_N=1000 composer bench

# Take a baseline (on master)
BENCH_N=1000 XDEBUG_MODE=off composer bench:baseline

# Compare (on your branch)
BENCH_N=1000 XDEBUG_MODE=off composer bench:compare

# Generate HTML artifact
BENCH_N=1000 XDEBUG_MODE=off composer bench:html
# → benchmark-report.html
```

> [!TIP]
> Pass `XDEBUG_MODE=off` to disable the xdebug overhead and get production-representative timings.

Extra PHPBench arguments can be appended after `--`:

```bash
# Custom tag
BENCH_N=1000 composer bench -- --tag=my_tag --store
BENCH_N=1000 composer bench -- --ref=my_tag --progress=plain

# Run a single scenario
BENCH_N=1000 composer bench -- --filter=benchInsert
```

#### Running Benchmarks via Docker

Use `make bench` to run against a specific PHP version inside the same containerised environment used for the test suite. Results are comparable across machines and CI runs.

```bash
# Run with defaults (N=50, PHP 8.4)
make bench

# Run with 1 000 entities, xdebug disabled
BENCH_N=1000 XDEBUG_MODE=off make bench

# Run on PHP 8.5
BENCH_N=1000 make bench php=8.5

# Pass extra PHPBench arguments
make bench args='--filter=benchInsert'
```

#### Before/After Comparison

This workflow quantifies the **performance impact** of a branch against `master`.

PHPBench stores runs as XML files in `.phpbench/` (gitignored). `--tag` names a stored run; `--ref` replays it alongside the current run. Both branches must use the **same `BENCH_N`** and the **same environment** (same PHP version, same xdebug mode) for a valid comparison.

**Via Composer (local PHP, recommended):**

```bash
# 1. On master — store the baseline
git checkout master
BENCH_N=1000 XDEBUG_MODE=off composer bench:baseline

# 2. On your branch — compare
git checkout my-branch
BENCH_N=1000 XDEBUG_MODE=off composer bench:compare
```

**Via Docker (reproducible environment):**

```bash
git checkout master
BENCH_N=1000 XDEBUG_MODE=off make bench php=8.4 args='--tag=before --store'

git checkout my-branch
BENCH_N=1000 XDEBUG_MODE=off make bench php=8.4 args='--ref=before --progress=plain'
```

With `--ref=before`, each subject line in the progress output shows the inline comparison:

```
benchInsert    I5 [Mo850μs (actual) vs. Mo1.2ms (before)] -29.17% (±1.8%)
benchUpdate    I5 [Mo9.2ms (actual) vs. Mo12.1ms (before)] -23.97% (±2.1%)
...
```

A **negative percentage** means the current branch is faster. The result is statistically meaningful when `|gain| > rstdev`.

#### Capturing Results for a PR Description

```bash
BENCH_N=1000 XDEBUG_MODE=off composer bench:compare 2>&1 \
    | grep -E "bench[A-Z]" > benchmark-summary.txt
```

Paste the content of `benchmark-summary.txt` into the PR body inside a fenced code block.

#### Blackfire Profiling (Flame Graphs)

For deep-dive profiling (call graph, function-level hotspots), use the Blackfire script.

**Prerequisites:** A [Blackfire.io](https://blackfire.io) account (free tier works). Set the following environment variables:

```bash
export BLACKFIRE_SERVER_ID=<your-server-id>
export BLACKFIRE_SERVER_TOKEN=<your-server-token>
export BLACKFIRE_CLIENT_ID=<your-client-id>
export BLACKFIRE_CLIENT_TOKEN=<your-client-token>
```

**Dry run (no Blackfire account needed):**

```bash
BENCH_N=100 composer profile
```

**Full Blackfire profile:**

```bash
# With the Blackfire CLI installed locally
BENCH_N=100 blackfire run php benchmarks/profile.php

# Via Docker (Blackfire agent sidecar)
BENCH_N=100 make profile
```

The `make profile` target spins up a Blackfire agent container alongside php-cli using `compose.blackfire.yaml`. The resulting profile URL is printed in the terminal and opens in your Blackfire dashboard.

#### Benchmark Scenarios

| Subject | What is measured |
|---------|-----------------|
| `benchInsert` | N Author inserts → N audit `INSERT` entries |
| `benchUpdate` | N Post updates (title + body + created_at) → N audit `UPDATE` entries |
| `benchRemove` | N Author removals → N audit `REMOVE` entries |
| `benchAssociate` | N Post→Tag ManyToMany links → N audit `ASSOCIATE` entries |
| `benchDissociate` | N Post→Tag ManyToMany unlinks → N audit `DISSOCIATE` entries |
| `benchMixed` | Realistic flush: N/2 inserts + N/4 updates (2 fields) + N/4 removes |

All scenarios use an **in-memory SQLite database** so results are environment-independent. The `BENCH_N` env var (default `50`) controls the number of entities per flush.

### 🧹 Code Quality

Before submitting, ensure your code passes all quality checks.

#### Using Composer (Local)

```bash
# Run all QA tools
composer qa

# Individual tools:
composer cs-check    # Check code style
composer cs-fix      # Fix code style
composer phpstan     # Static analysis
composer rector      # Automated refactoring suggestions
```

#### Using Make (Docker)

```bash
# Run PHP-CS-Fixer
make cs-fix

# Run PHPStan
make phpstan

# With specific PHP version
make phpstan php=8.5

# With custom arguments
make phpstan args='analyse src --level=9'
make cs-fix args='fix --dry-run'
```

### 📝 Commit Messages

Write clear, concise commit messages:

- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit the first line to 72 characters
- Reference issues and pull requests when relevant

Good examples:
- `Add support for composite primary keys`
- `Fix Reader pagination when page_size is null`
- `Update documentation for v4 migration`

### 🔄 Pull Request Process

1. Ensure all tests pass (ideally on multiple databases)
2. Run code quality tools (`make cs-fix`, `make phpstan`)
3. Update documentation if needed
4. Add a changelog entry if significant
5. Submit the pull request
6. Respond to review feedback

### 🤖 Continuous Integration (CI)

When you submit a Pull Request, GitHub Actions will automatically run:

- **PHPUnit tests** across the full matrix:
  - PHP versions: 8.4, 8.5
  - Symfony versions: 8.0
  - Databases: SQLite, MySQL, PostgreSQL, MariaDB
- **PHP-CS-Fixer** for code style validation
- **PHPStan** for static analysis
- **Code coverage** report

> [!WARNING]
> Your PR must pass all CI checks before it can be merged. If a check fails, review the logs to identify and fix the issue.

> [!TIP]
> Run `make tests db=mysql` and `make tests db=pgsql` locally before pushing to catch database-specific issues early.

### ✏️ Writing Tests

Tests are **highly encouraged** and often required for new features or bug fixes:

- Place tests in the `tests/` directory, mirroring the `src/` structure
- Use meaningful test method names that describe the behavior being tested
- Include both positive and negative test cases
- Test edge cases and error conditions
- For database-related features, ensure tests work with all supported databases

**Test Structure Example:**

```php
namespace DH\Auditor\Tests\Provider\Doctrine;

use PHPUnit\Framework\TestCase;

final class MyFeatureTest extends TestCase
{
    public function testFeatureWorksAsExpected(): void
    {
        // Arrange
        // Act
        // Assert
    }

    public function testFeatureThrowsExceptionOnInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // ...
    }
}
```

**Running Your Tests:**

```bash
# Run only your new tests
make tests args='--filter=MyFeatureTest'

# Run with coverage to ensure good test coverage
composer test:coverage
```

## 🐛 Reporting Bugs

When reporting bugs, please include:

1. **auditor version** - `composer show damienharper/auditor`
2. **PHP version** - `php -v`
3. **Symfony version** - `composer show symfony/framework-bundle`
4. **Database** - MySQL, PostgreSQL, SQLite, MariaDB, etc.
5. **Steps to reproduce** - Minimal code example
6. **Expected behavior** - What should happen
7. **Actual behavior** - What actually happens
8. **Error messages** - Full stack trace if available

## 💡 Feature Requests

For feature requests:

1. Check existing issues to avoid duplicates
2. Describe the use case
3. Explain why existing features don't meet your needs
4. Suggest a possible implementation if you have ideas

## 📖 Documentation Contributions

Documentation lives in the `docs/` directory and uses Markdown.

### Adding a New Page

1. Create a new `.md` file in the appropriate directory
2. Add frontmatter if needed
3. Update `sidebars.js` to include the new page
4. Add links from related pages

### Style Guide

- Use clear, simple language
- Include code examples
- Add internal links to related content
- Use tables for structured data
- Test all code examples

## 💬 Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Provide constructive feedback
- Focus on what is best for the community

## ❓ Questions?

- Open a [GitHub Discussion](https://github.com/DamienHarper/auditor/discussions)
- Check existing issues and discussions first
- Be patient - maintainers are volunteers

## 📜 License

By contributing, you agree that your contributions will be licensed under the MIT License.
