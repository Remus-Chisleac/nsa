FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer update --no-dev --no-interaction --optimize-autoloader --ignore-platform-reqs

FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor /var/www/html/vendor
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers

WORKDIR /var/www/html
COPY config/ ./config/
COPY src/ ./src/
COPY bin/ ./bin/
COPY public/ ./public/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
