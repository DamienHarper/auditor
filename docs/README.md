# auditor Documentation

This directory contains the documentation for the **auditor** library.

## Documentation Architecture

The documentation follows a multi-repository approach:

| Repository        | Content                              | Format          |
|-------------------|--------------------------------------|-----------------|
| `auditor`         | Library documentation (this folder)  | Markdown        |
| `auditor-bundle`  | Symfony bundle documentation         | Markdown        |
| `auditor-docs`    | Aggregated documentation site        | Docusaurus      |

Documentation written here is automatically synchronized to `auditor-docs` and published via GitHub Pages.

## Structure

```
docs/
├── index.md                        # Introduction & Overview
├── contributing.md                 # Contribution guide
├── getting-started/
│   ├── installation.md             # Installation guide
│   └── quick-start.md              # Quick start tutorial
├── configuration/
│   ├── index.md                    # Configuration overview
│   ├── user-provider.md            # User provider setup
│   ├── security-provider.md        # Security provider setup
│   └── role-checker.md             # Role checker setup
├── providers/
│   └── doctrine/
│       ├── index.md                # DoctrineProvider overview
│       ├── configuration.md        # Provider configuration
│       ├── attributes.md           # PHP 8 attributes reference
│       ├── services.md             # Auditing & storage services
│       ├── schema.md               # Schema management
│       └── multi-database.md       # Multi-database setup
├── querying/
│   ├── index.md                    # Querying overview
│   ├── filters.md                  # Filters reference
│   └── entry.md                    # Entry model reference
├── commands/
│   └── index.md                    # Console commands
├── api/
│   └── index.md                    # API reference
├── upgrade/
│   ├── index.md                    # Upgrade overview
│   ├── v4.md                       # v3.x → v4.x migration
│   └── v3.md                       # v2.x → v3.x migration
└── README.md                       # This file
```

## Writing Guidelines

### Markdown Standards

- Use **clean Markdown** without framework-specific syntax
- Use standard relative links: `[Configuration](configuration/index.md)`
- Use fenced code blocks with language identifiers:
  ````markdown
  ```php
  $auditor = new Auditor($configuration, $dispatcher);
  ```
  ````

### File Naming

- Use lowercase with hyphens: `quick-start.md`, `multi-database.md`
- Use `index.md` for section landing pages
- Files are sorted alphabetically in the sidebar (use prefixes like `01-` if order matters)

### Code Examples

- Keep examples concise and focused
- Include necessary `use` statements
- Test all code examples before committing
- Use realistic variable names

### Internal Links

Link to other documentation pages using relative paths:

```markdown
See the [Configuration Guide](configuration/index.md) for more details.
See [User Provider](../configuration/user-provider.md) for setup instructions.
```

## Synchronization

When changes are pushed to the `master` branch:

1. A GitHub Action triggers `auditor-docs` repository
2. `auditor-docs` pulls the latest documentation
3. Docusaurus builds the aggregated site
4. The site is deployed to GitHub Pages


## Local Preview

To preview documentation locally, you have several options:

### Option 1: Markdown Preview (IDE)

Most IDEs (PhpStorm, VS Code) have built-in Markdown preview.

### Option 2: Local Docusaurus (Full Preview)

Clone `auditor-docs` and run locally:

```bash
git clone https://github.com/DamienHarper/auditor-docs.git
cd auditor-docs

# Copy this docs folder
cp -r /path/to/auditor/docs/* docs/auditor/

npm install
npm run start
```

## Contributing

1. Edit the Markdown files in this directory
2. Follow the writing guidelines above
3. Test links and code examples
4. Submit a pull request

Documentation contributions follow the same PR process as code contributions. See [contributing.md](contributing.md) for details.

## License

This documentation is part of the auditor project and is released under the [MIT License](../LICENSE).
