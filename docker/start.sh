#!/usr/bin/env sh
set -eu
cd /var/www/html

: "${APP_KEY:?Set APP_KEY in Render; preserve the current key when moving services}"
: "${JWT_SECRET:?Set JWT_SECRET in Render; preserve the current secret}"
if [ "${DB_CONNECTION:-sqlite}" = libsql ]; then
    : "${TURSO_DATABASE_URL:?Set the remote Turso database URL}"
    : "${TURSO_AUTH_TOKEN:?Set the Turso database auth token}"
fi

if [ "${DB_CONNECTION:-sqlite}" = sqlite ]; then
    export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    case "$DB_DATABASE" in
        /*) ;;
        *) echo 'SQLite DB_DATABASE must be an absolute file path' >&2; exit 1;;
    esac
    sqlite_dir=$(dirname "$DB_DATABASE")
    mkdir -p "$sqlite_dir"
    if [ ! -f "$DB_DATABASE" ]; then touch "$DB_DATABASE"; fi
    chown www-data:www-data "$sqlite_dir" "$DB_DATABASE"
    chmod u+rwX "$sqlite_dir" "$DB_DATABASE"
fi

case "${PORT:-10000}" in *[!0-9]*|'') echo 'PORT must be numeric' >&2; exit 1;; esac
printf 'Listen %s\n' "${PORT:-10000}" > /etc/apache2/ports.conf
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
if [ "${RUN_MIGRATIONS:-false}" = true ]; then
    php artisan migrate --force --no-interaction
fi
if [ "${SEED_ADMIN:-false}" = true ]; then
    php artisan db:seed --class=AdminSeeder --force --no-interaction
fi
# Demo seeders and key generation are never run during startup.
exec apache2-foreground
