# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm@sha256:1b6bb4f3d32dfbcd1e159d7bd47caa8852507f61666db7731d73f34acec781b6 AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig5 libsqlite3-0 libonig-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_sqlite opcache \
    && apt-get purge -y --auto-remove libonig-dev libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-site-inspection.ini

FROM php-base AS dependencies
RUN apt-get update && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2@sha256:4ed71c38c6d788eb4e4e3747b5332f5b40d5b5a6fcadd5062a1a70986a118e0a /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY backend/ ./
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && php artisan package:discover --ansi \
    && composer check-platform-reqs --no-dev

FROM php-base AS php
WORKDIR /var/www/backend
COPY --from=dependencies /app ./
COPY docker/entrypoint.sh /usr/local/bin/site-inspection-entrypoint
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-site-inspection.conf
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/\r$//' /usr/local/bin/site-inspection-entrypoint \
    && chmod 0755 /usr/local/bin/site-inspection-entrypoint
ENTRYPOINT ["site-inspection-entrypoint"]
CMD ["php-fpm"]

FROM node:24-bookworm-slim@sha256:c86ecead9e55a5210ac1727de38a4ca840bf03dcaf34dc9749addbf383b1920c AS frontend-build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ENV VITE_API_BASE_URL=/api
RUN npm run build

FROM nginx:stable-bookworm@sha256:552e7481ca93ffccd046aa658dbbed22caefbc09c66fa7cd247cbb90b8a5c609 AS nginx
COPY --from=frontend-build /app/dist /var/www/frontend
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/fastcgi.conf /etc/nginx/site-inspection-fastcgi.conf
