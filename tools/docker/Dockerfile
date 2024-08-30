ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli AS auditor

WORKDIR /app

# install PHP extensions
#  - pdo_mysql
#  - pdo_pgsql
#  - xdebug
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer pdo_mysql pdo_pgsql xdebug

CMD [ "php" ]
