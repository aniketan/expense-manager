# syntax=docker/dockerfile:1

# ---- Frontend assets -------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- Application -----------------------------------------------------------
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite \
    && sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf laravel servername

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Install PHP dependencies first so this layer is cached across code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/expense-manager-entrypoint
RUN chmod +x /usr/local/bin/expense-manager-entrypoint

EXPOSE 80
ENTRYPOINT ["expense-manager-entrypoint"]
CMD ["apache2-foreground"]
