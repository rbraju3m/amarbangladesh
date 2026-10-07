#!/usr/bin/env bash
# Update a running production install. Run on the server from the app directory:
#   deploy/deploy.sh
# First install is different — see "Deploy" in README.md.
set -euo pipefail
cd "$(dirname "$0")/.."

php artisan down --render="errors::503" --retry=30
trap 'echo "!! Deploy failed; the site is still in maintenance mode. Fix it, then run: php artisan up" >&2' ERR

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build

php artisan migrate --force
php artisan optimize          # config, routes, views, events
php artisan cache:clear       # drops cached quiz content (quiz.boot), so content/URL changes show up

php artisan up
echo "Deployed $(git rev-parse --short HEAD)"
