#!/bin/sh
set -e

# Copy public assets to the shared volume so nginx can serve them
cp -r /var/www/html/public/. /var/www/html/public-shared/

exec "$@"
