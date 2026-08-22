#!/usr/bin/env bash
set -euo pipefail

cd /var/www/src

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

if [ ! -d node_modules ]; then
    npm install --no-audit --no-fund
fi

# Tailwind scans the Blade files, so the CSS is rebuilt on every start rather
# than only when public/build is missing.
npm run build

touch database/database.sqlite
php artisan migrate --force

# Seller uploads live on the public disk; the symlink is what serves them.
# --force so a restart over an existing link exits 0 under `set -e`.
php artisan storage:link --force

exec "$@"
