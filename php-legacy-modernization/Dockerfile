# syntax=docker/dockerfile:1.7
# Image of the refactored app only (after/). The legacy example is excluded by .dockerignore.
FROM php:8.3-apache

ENV APP_DB=/var/www/app/var/app.sqlite

RUN docker-php-ext-install opcache \
 && a2enmod headers \
 && echo 'Listen 8080' > /etc/apache2/ports.conf

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/app
COPY --chown=www-data:www-data after/ after/
COPY --chown=www-data:www-data database/ database/
COPY --chown=www-data:www-data bin/ bin/
RUN mkdir -p var && chown www-data:www-data var && chmod +x bin/setup-db

USER www-data
VOLUME ["/var/www/app/var"]
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
  CMD curl -fsS http://127.0.0.1:8080/healthz || exit 1
