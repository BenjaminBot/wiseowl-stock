FROM composer:2 AS composer

FROM php:8.4-cli-bookworm AS development

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libpq-dev libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install -j$(nproc) bcmath gd intl mbstring pdo_pgsql zip pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
