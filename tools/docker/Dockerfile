ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli AS auditor

WORKDIR /app

# install PHP extensions
#  - pdo_mysql
#  - pdo_pgsql
#  - xdebug
#  - blackfire (probe)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer pdo_mysql pdo_pgsql xdebug blackfire

CMD [ "php" ]

# Blackfire CLI stage — adds the `blackfire` CLI tool on top of the auditor image
FROM auditor AS auditor-blackfire
COPY --from=blackfire/blackfire:2 /usr/local/bin/blackfire /usr/local/bin/blackfire
