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

# 4. Seed only when APP_ENV=local AND SEED_DATABASE=true. In production
#    we treat the request as a misconfiguration but warn-and-skip rather
#    than exit, so the web service still boots. The mistake is visible
#    in the deploy logs and worth investigating, but breaking the boot
#    would cause restart loops on Render.
if [ "${SEED_DATABASE:-false}" = "true" ]; then
    if [ "${APP_ENV:-production}" = "local" ]; then
        echo "Seeding database (local + SEED_DATABASE=true)..."
        php artisan db:seed --force --no-interaction
    else
        echo "WARN: SEED_DATABASE=true ignored because APP_ENV='${APP_ENV:-production}'. Set both APP_ENV=local and SEED_DATABASE=true to actually seed." >&2
    fi
fi

# 5. Warm Laravel caches for production.
php artisan config:cache
php artisan route:cache
php artisan event:cache || true
php artisan view:cache
php artisan storage:link --force || true

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
