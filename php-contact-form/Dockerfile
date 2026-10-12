# syntax=docker/dockerfile:1.7
# Single image: Apache + mod_php 8.3, running as www-data on port 8080, SQLite in a volume.
FROM php:8.3-apache

ENV CONTACT_DB=/var/www/html/var/contact.sqlite

# pdo_sqlite and mbstring are already compiled into the official image; only enable OPcache and headers.
RUN docker-php-ext-install opcache \
 && a2enmod headers \
 && echo 'Listen 8080' > /etc/apache2/ports.conf

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
RUN mkdir -p var && chown www-data:www-data var && chmod +x bin/contact

USER www-data
VOLUME ["/var/www/html/var"]
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
  CMD curl -fsS http://127.0.0.1:8080/healthz || exit 1
