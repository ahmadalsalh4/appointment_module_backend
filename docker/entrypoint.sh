#!/bin/bash
set -e

export PORT="${PORT:-80}"
envsubst '$PORT' < /etc/nginx/sites-enabled/default > /tmp/nginx.conf
mv /tmp/nginx.conf /etc/nginx/sites-enabled/default

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "$APP_KEY" ]; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force --no-interaction
fi

php artisan migrate --force --no-interaction

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan storage:link --force || true

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
