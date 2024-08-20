# Default values
php ?= 8.3
sf ?= 7.1
db ?= sqlite
args ?= "--colors=always --no-coverage"

# Allowed PHP and Symfony version combinations
# (PHP_VERSION;SYMFONY_VERSION)
valid_combinations = \
    8.2;5.4 \
    8.2;6.4 \
    8.2;7.1 \
    8.3;6.4 \
    8.3;7.1

current_combination = $(php);$(sf)

# Set the DATABASE_URL based on the selected database
ifeq ($(db),mysql)
  DATABASE_URL = "mysql://auditor:password@mysql:3306/auditor?charset=utf8mb4"
else ifeq ($(db),pgsql)
  DATABASE_URL = "pgsql://auditor:password@pgsql:5432/auditor?charset=utf8"
else ifeq ($(db),sqlite)
  DATABASE_URL = "sqlite:///:memory:"
else
  $(error Unknown database type: $(db))
endif

# Validate PHP and Symfony version matrix
validate_matrix:
	@if ! echo "$(valid_combinations)" | grep -q "$(current_combination)"; then \
		echo "Error: Invalid combination of PHP and Symfony versions: php=$(php), sf=$(sf)"; \
		echo "Allowed combinations are:"; \
		echo "(PHP_VERSION;SYMFONY_VERSION)"; \
		echo "$(valid_combinations)" | tr ' ' '\n'; \
		exit 1; \
	fi

# Help target
.PHONY: help
help:
	@echo "Usage: make tests [php=<php_version>] [sf=<symfony_version>] [db=<database>] [args=<phpunit_args>]"
	@echo ""
	@echo "Options:"
	@echo "  php      PHP version to use (default: $(php))"
	@echo "  sf       Symfony version to use (default: $(sf))"
	@echo "  db       Database type (sqlite, mysql, pgsql; default: $(db))"
	@echo "  args     PHPUnit arguments (default: $(args))"

# Run tests target
.PHONY: tests
tests: validate_matrix
	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) DATABASE_URL=$(DATABASE_URL) docker compose -f compose.yaml run --rm --remove-orphans php-cli composer install && vendor/bin/phpunit $(args)

# Clean up Docker containers, networks, and volumes
#.PHONY: clean
#clean:
#	PHP_VERSION=$(php) SYMFONY_VERSION=$(sf) DATABASE_URL=$(DATABASE_URL) \
#	docker compose -f ./docker/compose.yaml -f ./docker/compose.$(db).yaml down
