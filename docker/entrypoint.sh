#!/bin/sh
set -eu
umask 0022

: "${APP_KEY:?Set APP_KEY in the private Docker environment file before starting}"
: "${APP_URL:?Set APP_URL to the browser-accessible URL}"

# Only named container volumes; never copy or migrate the developer's local data.
mkdir -p /var/lib/site-inspection storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ ! -f /var/lib/site-inspection/database.sqlite ]; then
    touch /var/lib/site-inspection/database.sqlite
fi
chown -R www-data:www-data /var/lib/site-inspection storage bootstrap/cache
chmod 0770 /var/lib/site-inspection
chmod 0660 /var/lib/site-inspection/database.sqlite
chmod 0750 storage/app/private storage/framework storage/logs
chmod 0755 storage/app/public

exec docker-php-entrypoint "$@"
