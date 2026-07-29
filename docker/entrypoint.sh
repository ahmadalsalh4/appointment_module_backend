#!/bin/bash
set -e

export PORT="${PORT:-80}"
envsubst '$PORT' < /etc/nginx/sites-enabled/default > /tmp/nginx.conf
mv /tmp/nginx.conf /etc/nginx/sites-enabled/default

# 1. Copy .env.example → .env if no .env exists. Never overwrite.
if [ ! -f .env ]; then
    cp .env.example .env
fi

# 2. Generate APP_KEY ONLY if it's missing from BOTH the environment
#    AND the .env file. Never overwrite an existing key.
HAS_KEY=0
if [ -n "$APP_KEY" ]; then
    HAS_KEY=1
elif grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    HAS_KEY=1
fi

if [ "$HAS_KEY" = "0" ]; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force --no-interaction
fi

# 3. Run migrations only if RUN_MIGRATIONS is true (default true).
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force --no-interaction
fi

# 4. Refuse to seed unless APP_ENV=local AND SEED_DATABASE=true.
if [ "${SEED_DATABASE:-false}" = "true" ]; then
    if [ "${APP_ENV:-production}" = "local" ]; then
        echo "Seeding database (local + SEED_DATABASE=true)..."
        php artisan db:seed --force --no-interaction
    else
        echo "Refusing to seed: SEED_DATABASE=true requires APP_ENV=local (currently '${APP_ENV}')." >&2
        exit 1
    fi
fi

# 5. Warm Laravel caches for production.
php artisan config:cache
php artisan route:cache
php artisan event:cache || true
php artisan view:cache
php artisan storage:link --force || true

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
