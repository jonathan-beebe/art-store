#!/usr/bin/env bash
set -euo pipefail

cd /var/www/src

# node_modules lives in the bind mount so it survives `docker compose down` and
# an image rebuild. A lockfile newer than the tree means the install is stale.
if [ ! -d node_modules ] || [ package-lock.json -nt node_modules ]; then
    npm ci
fi

node app/db/migrate.ts

# Seeds the platform admins and the full demo catalog (sellers, listings,
# customers, orders). Both halves are idempotent: admins re-seed to the same
# rows, and the demo half does nothing once a seller row already exists.
node app/db/seed.ts

# Tailwind scans the EJS templates, so the stylesheet is rebuilt on every start
# rather than only when public/app.css is missing.
npm run --silent assets

exec "$@"
