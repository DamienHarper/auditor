# Default values
php ?= 8.3
sf ?= 7.1
db ?= sqlite
args ?= --colors=always --no-coverage

# Allowed PHP and Symfony version combinations
# (PHP_VERSION;SYMFONY_VERSION)
valid_combinations = \
    8.2;5.4 \
    8.2;6.4 \
    8.2;7.1 \
    8.3;6.4 \
    8.3;7.1

current_combination = $(php);$(sf)

# list of config files to provide to docker compose
compose_files = -f tools/docker/compose.yaml

# Set the DATABASE_URL and dedicated compose file based on the selected database
ifeq ($(db),mysql)
  DATABASE_URL = "mysql://auditor:password@127.0.0.1:3360/auditor?serverVersion=8&charset=utf8mb4"
  compose_files := $(compose_files) -f tools/docker/compose.mysql.yaml
else ifeq ($(db),pgsql)
  DATABASE_URL = "pgsql://postgres:password@127.0.0.1:5432/auditor?serverVersion=15&charset=utf8"
  compose_files := $(compose_files) -f tools/docker/compose.pgsql.yaml
else ifeq ($(db),mariadb)
  DATABASE_URL = "mysql://auditor:password@127.0.0.1:3366/auditor?serverVersion=8&charset=utf8mb4"
  compose_files := $(compose_files) -f tools/docker/compose.mariadb.yaml
else ifeq ($(db),sqlite)
  DATABASE_URL = "sqlite:///:memory:"
else
  $(error Unknown database type: $(db))
endif

# Help target
.PHONY: help
help:
	@echo "Usage: make <target> [php=<php_version>] [sf=<symfony_version>] [db=<database>] [args=<phpunit_args>]"
	@echo ""
	@echo "Targets:"
	@echo "  tests	  Run tests"
	@echo "  cs-fix   Run PHP-CS-Fixer"
	@echo "  phpstan  Run PHPStan"
	@echo ""
	@echo "Options:"
	@echo "  php      PHP version to use (default: $(php))"
	@echo "  sf       Symfony version to use (default: $(sf))"
	@echo "  db       Database type (sqlite, mysql, pgsql, mariadb; default: $(db))"
	@echo "  args     PHPUnit arguments (default: $(args))"

# Run tests target
.PHONY: tests
tests: validate_matrix
	rm -f composer.lock
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer global config --no-plugins allow-plugins.symfony/flex true"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer global require --no-progress --no-scripts --no-plugins symfony/flex --quiet"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) DATABASE_URL=$(DATABASE_URL) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer install --quiet"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) DATABASE_URL=$(DATABASE_URL) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli vendor/bin/phpunit $(args)"
	rm -f composer.lock

# Run phpstan target
.PHONY: phpstan
phpstan: validate_matrix
	rm -f composer.lock
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer update --working-dir=tools/phpstan --no-interaction --quiet"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer global config --no-plugins allow-plugins.symfony/flex true"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer global require --no-progress --no-scripts --no-plugins symfony/flex --quiet"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer install --no-interaction --quiet"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli tools/phpstan/vendor/bin/phpstan --memory-limit=1G --ansi analyse src"
	rm -f composer.lock

# Run phpstan target
.PHONY: cs-fix
cs-fix: validate_matrix
	rm -f composer.lock
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer update --quiet --working-dir=tools/php-cs-fixer"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer global config --no-plugins allow-plugins.symfony/flex true"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer global require --no-progress --no-scripts --no-plugins symfony/flex --quiet"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli composer install --quiet"
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) sh -c "docker compose $(compose_files) run --rm --remove-orphans php-cli tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --using-cache=no --verbose --ansi"
	rm -f composer.lock

# Validate PHP and Symfony version matrix
validate_matrix:
	@if ! echo "$(valid_combinations)" | grep -q "$(current_combination)"; then \
		echo "Error: Invalid combination of PHP and Symfony versions: php=$(php), sf=$(sf)"; \
		echo "Allowed combinations are:"; \
		echo "(PHP_VERSION;SYMFONY_VERSION)"; \
		echo "$(valid_combinations)" | tr ' ' '\n'; \
		exit 1; \
	fi
