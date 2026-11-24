FROM php:8.3-fpm

# System libs for PHP extensions + git/unzip for Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Xdebug for code coverage in tests (enable with XDEBUG_MODE=coverage)
RUN pecl install xdebug && docker-php-ext-enable xdebug
ENV XDEBUG_MODE=off

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
