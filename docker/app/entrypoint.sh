#!/bin/sh
set -e

# Fix storage permissions (named volume may be owned by root)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy public assets to the shared volume so nginx can serve them
cp -r /var/www/html/public/. /var/www/html/public-shared/

exec "$@"
