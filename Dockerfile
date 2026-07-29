FROM php:8.4-fpm

# Production image: only the runtime we need. The frontend SPA is built
# and hosted by Netlify; no Node/npm in this image. Postgres is the only
# driver we ship, with pdo_pgsql + intl + zip.
RUN apt-get update && apt-get install -y \
        nginx \
        supervisor \
        gettext-base \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        zip \
        unzip \
        git \
        curl \
    && docker-php-ext-install pdo pdo_pgsql zip intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy application source. The COPY happens before composer install so
# lockfile-driven installs work. We deliberately leave the source owned
# by root and only grant www-data write access to specific dirs, so a
# code-execution vulnerability cannot trivially modify PHP source.
COPY --chown=root:root --chmod=550 . .

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && composer dump-autoload --optimize --no-dev

# Writable directories only — the rest of /var/www stays root-owned and
# read-only for www-data.
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/supervisor.conf /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f /etc/nginx/sites-enabled/default.orig

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
