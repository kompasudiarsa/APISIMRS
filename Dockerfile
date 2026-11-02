FROM php:8.2-cli-bookworm AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev \
    && docker-php-ext-install mbstring zip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.2-fpm-bookworm AS php

ENV ACCEPT_EULA=Y

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl gnupg2 ca-certificates apt-transport-https libpq-dev libonig-dev unixodbc-dev \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends msodbcsql18 \
    && pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && docker-php-ext-install mbstring opcache pdo pdo_pgsql \
    && apt-mark manual libpq5 \
    && apt-get purge -y --auto-remove libonig-dev libpq-dev unixodbc-dev gnupg2 apt-transport-https \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/apisimrs-entrypoint
COPY docker/php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf

RUN chmod +x /usr/local/bin/apisimrs-entrypoint \
    && mkdir -p \
        storage/app \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    CACHE_DRIVER=file \
    SESSION_DRIVER=file \
    QUEUE_CONNECTION=sync

ENTRYPOINT ["apisimrs-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.27-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
EXPOSE 80
