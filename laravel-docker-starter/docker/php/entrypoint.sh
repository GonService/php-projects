#!/bin/sh
# Container entrypoint: optional migrations, Laravel caches, then the main process.
set -eu

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force --no-interaction
fi

if [ "${APP_ENV:-production}" != "local" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec "$@"
