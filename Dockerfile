FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-cli-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libonig-dev libsqlite3-dev unzip git curl \
    && docker-php-ext-install pdo_pgsql pdo_mysql pdo_sqlite mbstring pcntl sockets zip bcmath \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer dump-autoload --optimize && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh
COPY docker/php.ini /usr/local/etc/php/conf.d/taskflow.ini
ENTRYPOINT ["sh", "/var/www/html/docker/entrypoint.sh"]
CMD ["app"]
