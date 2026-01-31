#!/usr/bin/env bash
set -e

cd /var/www/html

echo "Ensuring storage permissions"
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R 775 storage bootstrap/cache

echo "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader

echo "Caching configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache  

if [[ "${RUN_MIGRATIONS:-true}" == "true" ]]; then
  echo "Running migrations"
  php artisan migrate --force
fi

exec /usr/bin/supervisord -n
