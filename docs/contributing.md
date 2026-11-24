---
description: "Contribute to the auditor library: development setup, running PHPUnit locally or in the Docker matrix, code quality tools, bug reports and documentation fixes."
---

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

#### Working in a Git worktree (optional)

To work on several branches in parallel, the `Makefile` creates [Git worktrees](https://git-scm.com/docs/git-worktree) in `.claude/worktrees/<name>/` (git-ignored). A new branch starts from the freshly fetched default branch. Each worktree gets its own `vendor/` and `tools/` dependencies, and the `wt-*` targets run the Composer scripts with your local PHP.

```bash
# Create a worktree for a new branch and install its dependencies
make wt-create branch=feature/my-new-feature

# QA and tests in that worktree (wt= can be omitted when make runs from inside it)
make wt-qa wt=feature-my-new-feature
make wt-test wt=feature-my-new-feature args='--filter=EntryTest'

# The Docker targets also work from a worktree
make -C .claude/worktrees/feature-my-new-feature tests

# Remove the worktree once its branch is merged (refused while it has uncommitted changes)
make wt-clean wt=feature-my-new-feature confirm=yes
```

Run `make help` for the other targets (`wt-init`, `wt-list`, `wt-exec`, `wt-phpstan`).

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

The project includes a `Makefile` that allows you to test against different combinations of PHP and Symfony versions using Docker containers.

> [!NOTE]
> The core library has no database code: its tests do not use one. Database-specific behaviour is tested in
> [auditor-doctrine-provider](https://github.com/DamienHarper/auditor-doctrine-provider), whose Makefile has a `db`
> option.

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
| `rector`  | Run Rector to apply automated refactorings                     |
| `qa`      | Run Rector, PHP-CS-Fixer and PHPStan, in this order            |
| `help`    | Display available commands and options                         |

**Options:**

| Option | Values                                | Default  | Description              |
|--------|---------------------------------------|----------|--------------------------|
| `php`  | `8.4`, `8.5`, `8.6`                   | `8.4`    | PHP version              |
| `sf`   | `8.1`, `8.2`                          | `8.1`    | Symfony version          |
| `args` | Any PHPUnit/tool arguments            | (varies) | Additional arguments     |

**Valid PHP/Symfony Combinations:**

| PHP Version | Symfony Versions |
|-------------|------------------|
| 8.4         | 8.1, 8.2         |
| 8.5         | 8.1, 8.2         |
| 8.6         | 8.1, 8.2         |

**Examples:**

```bash
# Show all available commands and options
make help

# Run tests with defaults (PHP 8.4, Symfony 8.1)
make tests

# Run tests with specific PHP version
make tests php=8.5

# Full specification
make tests php=8.5 sf=8.2

# Run specific test class
make tests args='--filter=EntryTest'

# Run tests with coverage
make tests args='--coverage-html=coverage'
```

**How It Works:**

The Makefile uses Docker Compose to spin up a container with the specified PHP version. The configuration files are
located in `tools/docker/`.

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
# Run Rector, PHP-CS-Fixer and PHPStan, in this order
make qa

# Run Rector alone
make rector

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

1. Ensure all tests pass
2. Run code quality tools (`make cs-fix`, `make phpstan`)
3. Update documentation if needed
4. Add a changelog entry if significant
5. Submit the pull request
6. Respond to review feedback

### 🤖 Continuous Integration (CI)

When you submit a Pull Request, GitHub Actions will automatically run:

- **PHPUnit tests** across the full matrix:
  - PHP versions: 8.4, 8.5, 8.6
  - Symfony versions: 8.1, 8.2
- **PHP-CS-Fixer** for code style validation
- **PHPStan** for static analysis
- **Code coverage** report

> [!WARNING]
> Your PR must pass all CI checks before it can be merged. If a check fails, review the logs to identify and fix the issue.

### ✏️ Writing Tests

Tests are **highly encouraged** and often required for new features or bug fixes:

- Place tests in the `tests/` directory, mirroring the `src/` structure
- Use meaningful test method names that describe the behavior being tested
- Include both positive and negative test cases
- Test edge cases and error conditions

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
