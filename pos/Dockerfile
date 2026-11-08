FROM node:24.22.1-slim AS assets

WORKDIR /app
COPY package.json package-lock.json .npmrc vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

FROM serversideup/php:8.5.12-fpm-nginx

# The runtime image ships without dev dependencies. CI builds the test image with COMPOSER_FLAGS="".
ARG COMPOSER_FLAGS="--no-dev"

USER root
RUN install-php-extensions pdo_pgsql
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader $COMPOSER_FLAGS

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# .env lives on a volume so that app, worker and scheduler share one APP_KEY.
RUN composer dump-autoload --optimize $COMPOSER_FLAGS \
    && mkdir -p storage/app/env \
    && ln -sf storage/app/env/.env .env
