#!/usr/bin/env bash
set -euo pipefail

cd /var/www/src

BUNDLE_MANIFEST=public/build/manifest.json
# Beside public/build rather than inside it: `vite build` empties its output
# directory, so a file written in there is gone after the next build.
BUNDLE_INPUTS_HASH=public/.vite-inputs-hash

# What the bundle is built from. Tailwind emits the classes it finds by
# scanning the project's own source, so every file it scans is an input:
# resources/ holds the CSS entry point and the Blade templates, and the PHP
# under app/, bootstrap/, config/ and routes/ is scanned too. vite.config.js
# decides what Vite produces, package.json and package-lock.json pin the
# toolchain that produces it, and composer.lock pins the vendor pagination
# views that resources/css/app.css names with @source.
#
# storage/framework/views is left out: it is a cache compiled from
# resources/views, so its classes are already covered, and which pages happen
# to have been rendered would otherwise churn the hash.
#
# The hash covers file contents and paths, and sorting makes it independent
# of the order find walks the tree.
bundle_inputs_hash() {
    find resources app bootstrap config routes \
        vite.config.js package.json package-lock.json composer.lock \
        -type f -exec sha256sum {} + \
        | LC_ALL=C sort \
        | sha256sum \
        | cut -d' ' -f1
}

if [ ! -f .env ]; then
    cp .env.example .env
fi

# Stamp lives inside vendor/ so it dies with vendor: a deleted or fresh
# vendor directory has no stamp, which falls through to install same as the
# missing-directory case ever did.
COMPOSER_INPUTS_HASH=vendor/.composer-inputs-hash
composer_inputs_hash() {
    sha256sum composer.json composer.lock | LC_ALL=C sort | sha256sum | cut -d' ' -f1
}

inputs_hash="$(composer_inputs_hash)"
if [ -d vendor ] \
    && [ -f "$COMPOSER_INPUTS_HASH" ] \
    && [ "$(cat "$COMPOSER_INPUTS_HASH")" = "$inputs_hash" ]; then
    echo "entrypoint: composer inputs unchanged, skipping install"
else
    echo "entrypoint: composer inputs changed, installing"
    composer install --no-interaction --prefer-dist
    printf '%s\n' "$inputs_hash" > "$COMPOSER_INPUTS_HASH"
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Same shape as the composer stamp above: lives inside node_modules/ so it
# dies with it.
NPM_INPUTS_HASH=node_modules/.npm-inputs-hash
npm_inputs_hash() {
    sha256sum package.json package-lock.json | LC_ALL=C sort | sha256sum | cut -d' ' -f1
}

inputs_hash="$(npm_inputs_hash)"
if [ -d node_modules ] \
    && [ -f "$NPM_INPUTS_HASH" ] \
    && [ "$(cat "$NPM_INPUTS_HASH")" = "$inputs_hash" ]; then
    echo "entrypoint: npm inputs unchanged, skipping install"
else
    echo "entrypoint: npm inputs changed, installing"
    npm install --no-audit --no-fund
    printf '%s\n' "$inputs_hash" > "$NPM_INPUTS_HASH"
fi

# The entrypoint runs on every `docker compose run` as well as on `up`, so the
# bundle is rebuilt only when its inputs no longer match the hash recorded by
# the build that produced the bundle on disk. The hash is written after the
# build so a failed build leaves the next start rebuilding.
inputs_hash="$(bundle_inputs_hash)"
if [ -f "$BUNDLE_MANIFEST" ] \
    && [ -f "$BUNDLE_INPUTS_HASH" ] \
    && [ "$(cat "$BUNDLE_INPUTS_HASH")" = "$inputs_hash" ]; then
    echo "entrypoint: bundle inputs unchanged, skipping build"
else
    echo "entrypoint: bundle inputs changed, building"
    npm run build
    printf '%s\n' "$inputs_hash" > "$BUNDLE_INPUTS_HASH"
fi

# Laravel's sqlite connector opens an existing file only, so both files
# exist before the first migration touches them. The analytics file is the
# config/database.php default unless the environment names another.
touch database/database.sqlite "${ANALYTICS_DATABASE_FILE:-storage/analytics.sqlite3}"
php artisan migrate --force

# Seller uploads live on the public disk; the symlink is what serves them.
# --force so a restart over an existing link exits 0 under `set -e`.
php artisan storage:link --force

exec "$@"
