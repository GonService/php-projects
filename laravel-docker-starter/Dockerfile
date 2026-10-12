# syntax=docker/dockerfile:1.7
# Multi-stage build: Composer dependencies -> PHP-FPM runtime ("app") and Nginx ("web").

ARG PHP_VERSION=8.3

# ---- Composer dependencies (no dev packages) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- PHP-FPM runtime ----
FROM php:${PHP_VERSION}-fpm-alpine AS app
WORKDIR /var/www/html

# hadolint ignore=DL3018,SC2086
RUN apk add --no-cache fcgi icu-libs libzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql opcache intl zip bcmath \
 && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=6 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---- Nginx serving public assets and proxying PHP to "app" ----
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public
HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=6 \
  CMD wget -q -O /dev/null http://127.0.0.1/healthz || exit 1
