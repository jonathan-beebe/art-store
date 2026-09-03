# syntax=docker/dockerfile:1
FROM php:8.3-cli AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# gd is what lets tests build real image bytes with UploadedFile::fake()->image(),
# which the listing upload rule needs to verify content rather than the declared
# mimetype.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl pdo_sqlite zip

# Composer lives in every stage: dev and build install with it, and the
# runtime CMD chain (`composer run deploy`) is what Render's shell-less
# Docker Command field invokes.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/src

# ---------------------------------------------------------------------------
# dev: today's bind-mount workflow. `make up` builds this target; source
# arrives through the compose bind mount, not COPY, and the entrypoint
# installs dependencies, migrates, and builds assets at container start.
# pcov and Node are dev-time tools (coverage, the Vite build), so they live
# here rather than in base.
# ---------------------------------------------------------------------------
FROM base AS dev

# pcov defaults to instrumenting every request, so it is disabled here and
# turned back on with -d only for the composer scripts that collect coverage
# — see docker/pcov.ini.
RUN pecl install pcov && docker-php-ext-enable pcov
COPY docker/pcov.ini /usr/local/etc/php/conf.d/zz-pcov.ini

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ---------------------------------------------------------------------------
# build: installs the production vendor tree and builds the Vite bundle once
# at image build time rather than on every container start. Based on dev for
# its Node and Composer.
# ---------------------------------------------------------------------------
FROM dev AS build

COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY src/ ./

# package:discover (normally a post-autoload-dump script) boots the app, so
# the storage skeleton has to exist first — .dockerignore keeps src/storage
# out of the build context.
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover

RUN npm ci --no-audit --no-fund && npm run build

# ---------------------------------------------------------------------------
# runtime: production configuration, non-root, no bind mount. FrankenPHP
# serves public/ directly and hands only PHP-routed requests to the runtime;
# `composer run deploy` migrates, seeds, and starts it. APP_KEY must arrive
# from the environment. This stage rebases off the official FrankenPHP image
# rather than `base` — classic per-request mode only (no Octane/worker mode:
# App\Logging\LogStore assumes one request per process).
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1.12.7-php8.3.33-bookworm AS runtime

# The image ships gd/intl/zip uncompiled and pdo_sqlite already built in;
# passing all four is idempotent and keeps this list matching `base`'s. gd is
# what lets `UploadedFile::fake()->image()` and the seeder's placeholder
# artwork build real image bytes, so it needs freetype+jpeg support, not a
# gd build with neither.
RUN install-php-extensions gd intl pdo_sqlite zip

# Composer runs the deploy chain (`composer run deploy`), which is what
# Render's shell-less Docker Command field invokes.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# The base image grants the binary cap_net_bind_service so a non-root user
# can bind ports 80/443. This server binds 8000, and a sandboxed runtime
# (Render's) refuses to exec a file-capability binary at all — the whole
# deploy dies with EPERM before the server starts — so the capability comes
# off.
RUN setcap -r /usr/local/bin/frankenphp

WORKDIR /var/www/src

# The database lives under storage/ so one persistent volume covers both the
# SQLite file and the uploaded listing images (storage/app/public).
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_DATABASE=/var/www/src/storage/production.sqlite3 \
    LOG_DATABASE_FILE=/var/www/src/storage/logs.sqlite3 \
    ANALYTICS_DATABASE_FILE=/var/www/src/storage/analytics.sqlite3

COPY --chown=www-data:www-data src/ ./
COPY --chown=www-data:www-data --from=build /var/www/src/vendor ./vendor
COPY --chown=www-data:www-data --from=build /var/www/src/bootstrap/cache ./bootstrap/cache
COPY --chown=www-data:www-data --from=build /var/www/src/public/build ./public/build

COPY docker/Caddyfile /etc/frankenphp/Caddyfile

# The storage skeleton Laravel writes into, and the public/storage symlink
# that serves uploads (what `artisan storage:link` creates in dev). A volume
# mounted over storage/ starts empty, so `composer run deploy` recreates the
# skeleton on every boot. Caddy also writes under XDG_CONFIG_HOME/XDG_DATA_HOME
# (an autosaved config, its certificate storage) even with `auto_https off`;
# the base image points both at /config and /data, owned by root there, so
# www-data needs them handed over too.
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && ln -sfn /var/www/src/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache "$XDG_CONFIG_HOME" "$XDG_DATA_HOME"

USER www-data

VOLUME ["/var/www/src/storage"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
    CMD ["curl", "-fsS", "http://127.0.0.1:8000/up"]

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
