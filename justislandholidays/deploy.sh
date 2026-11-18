#!/usr/bin/env bash
# Run on the server (20i SSH or Command Executor) from this folder after each Git deploy:
#   bash deploy.sh
# The first time, create .env first (see .env.example) and run: php artisan key:generate
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -f .env ]; then
    echo "No .env file yet. Copy .env.example to .env and fill it in first." >&2
    exit 1
fi

php artisan down --retry=30 || true
trap 'php artisan up' EXIT

composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
rm -f public/hot                      # never serve assets from a dev server

php artisan migrate --force
php artisan db:seed --force          # reference data and draft legal pages; never overwrites edits
php artisan storage:link || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

echo "Deployed. Run 'php artisan launch:check' to see what is still open."
