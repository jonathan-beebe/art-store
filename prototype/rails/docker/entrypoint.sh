#!/usr/bin/env bash
set -euo pipefail

cd /var/www/src

if ! bundle check >/dev/null 2>&1; then
    bundle install
fi

bin/rails db:prepare

# Tailwind scans the ERB templates, so the stylesheet is rebuilt on every start
# rather than only when app/assets/builds is missing.
bin/rails tailwindcss:build

rm -f tmp/pids/server.pid

exec "$@"
