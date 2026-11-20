#!/usr/bin/env bash
#
# Just Island Holidays: run on the live server after the new code has been pulled
# (the host's Git deployment "after deploy" command, or by hand over SSH):
#
#     bash justislandholidays/deploy.sh
#
# What it does, in order:
#   1. puts Laravel into its own short maintenance mode, so visitors never see a
#      half-updated site (this is separate from Settings > Maintenance mode in the admin)
#   2. installs the PHP packages the live site needs (not the development ones)
#   3. builds the styles and scripts, if Node.js is available on the server
#   4. updates the database and adds any new reference data (never overwrites admin edits)
#   5. links public/storage, clears old caches and builds new ones
#   6. tells the queue worker to restart with the new code
#   7. brings the site back up
#
# If any step fails, the site is brought back up and the step that failed is shown.

set -euo pipefail

cd "$(dirname "$0")"

say() { printf '\n==> %s\n' "$1"; }

site_up() {
    php artisan up >/dev/null 2>&1 || true
}

on_error() {
    site_up
    printf '\n!! Deploy stopped at the step above. The site is back up; fix the error and run this script again.\n' >&2
}
trap on_error ERR

if [ ! -f .env ]; then
    echo "No .env file in $(pwd). Copy .env.example to .env and fill it in before deploying." >&2
    exit 1
fi

say "Pausing the site while it updates"
php artisan down --retry=30 || true

say "Installing PHP packages"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if command -v npm >/dev/null 2>&1; then
    say "Building styles and scripts"
    npm ci --no-audit --no-fund
    npm run build
    rm -f public/hot   # a left-over dev server file would stop the built files loading
else
    say "Node.js not found: skipping the styles and scripts build"
    if [ ! -f public/build/manifest.json ]; then
        echo "!! public/build is missing, so pages will have no styles. Install Node.js on the server, or upload public/build." >&2
    fi
fi

say "Updating the database"
php artisan migrate --force
php artisan db:seed --force   # reference data and draft legal pages; never overwrites edits made in the admin

say "Linking uploaded files"
if [ ! -e public/storage ]; then
    php artisan storage:link
fi

say "Refreshing caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

say "Restarting the queue worker"
php artisan queue:restart

say "Bringing the site back up"
trap - ERR
site_up

say "Done. Deployed $(git rev-parse --short HEAD 2>/dev/null || echo 'the new code')."
echo "    Optional: php artisan launch:check   (the launch checklist)"
