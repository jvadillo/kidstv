#!/bin/sh
# Arranque de los contenedores de la aplicación (app, worker y scheduler).
set -e
cd /var/www/html

mkdir -p storage/app/private storage/database storage/backups storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs
[ -f "${DB_DATABASE:-storage/database/nokoplay.sqlite}" ] || touch "${DB_DATABASE:-storage/database/nokoplay.sqlite}"

php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null

exec "$@"
