# ─────────────────────────────────────────────────────────────────────
# Symfony backend — placeholder image
#
# Builds a PHP 8.3 / PHP-FPM runtime ready for a Symfony application.
# The ./backend directory is currently empty; scaffold a Symfony app
# there (see the project README) and this image will pick it up on the
# next `docker compose build backend`.
#
# In local development the source is bind-mounted over this image
# (see docker-compose.override.yml). For staging/production the code is
# baked in by the COPY step below.
# ─────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# ── System packages (build + runtime dependencies) ──────────────────
RUN apk add --no-cache \
        bash \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev

# ── PHP extensions commonly required by Symfony + PostgreSQL ────────
RUN docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        opcache \
        pdo \
        pdo_pgsql \
        zip

# ── Composer ─────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy whatever exists in ./backend today (empty for the placeholder).
COPY . .

# Install dependencies at build time once a Symfony app is present.
RUN if [ -f composer.json ]; then \
        composer install --no-interaction --no-progress --prefer-dist; \
    fi

# php-fpm runs as www-data. Symfony writes its cache and logs under var/
# at runtime (e.g. it warms the prod cache on the first request), so var/
# must be owned by www-data — otherwise those writes fail with EACCES and
# every request returns HTTP 500.
RUN mkdir -p var && chown -R www-data:www-data var

EXPOSE 9000

CMD ["php-fpm"]
