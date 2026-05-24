#!/usr/bin/env bash
set -euo pipefail

if [ -f /var/www/html/composer.json ] && [ ! -d /var/www/html/vendor ]; then
    cd /var/www/html
    composer install --no-interaction --no-progress --prefer-dist
fi

exec "$@"
