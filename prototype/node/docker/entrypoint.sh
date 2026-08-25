#!/usr/bin/env bash
set -euo pipefail

cd /var/www/src

# node_modules lives in the bind mount so it survives `docker compose down` and
# an image rebuild. A lockfile newer than the tree means the install is stale.
if [ ! -d node_modules ] || [ package-lock.json -nt node_modules ]; then
    npm ci
fi

# Migrates, then seeds the platform admins and the full demo catalog
# (sellers, listings, customers, orders), in one process. Both halves are
# idempotent: admins re-seed to the same rows, and the demo half does nothing
# once a seller row already exists.
node app/cli/prepare-db.ts

# True only when every file the manifest names — the plain output plus its
# .gz and .br siblings — is already on disk and nothing under app/ or
# public/app.js is newer than the manifest, the last file a build writes. A
# missing manifest or a missing hashed/compressed sibling counts as stale
# even when public/app.css looks fresh.
assets_current() {
    [ -f public/app.css ] || return 1
    [ -f public/assets-manifest.json ] || return 1
    [ ! public/app.css -nt public/assets-manifest.json ] || return 1
    [ -z "$(find app public/app.js -newer public/assets-manifest.json -print -quit)" ] || return 1

    for p in $(grep -o '"/[^"]*"' public/assets-manifest.json | tr -d '"'); do
        [ -f "public$p" ] || return 1
        [ -f "public$p.gz" ] || return 1
        [ -f "public$p.br" ] || return 1
    done

    return 0
}

# Tailwind scans the EJS templates, so a build here is the only way to know
# the stylesheet reflects them; skipped when the check above says the output
# is already current.
if ! assets_current; then
    npm run --silent assets
fi

exec "$@"
