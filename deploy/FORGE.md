# Deploying on Laravel Forge

Dev site: https://amarbangladesh-dev.on-forge.com (behind Cloudflare).

## Server requirements

- PHP **8.2+** (the app is Laravel 12, locked to PHP 8.2 packages).
- Node **18.18+** (the server has 18.20.5; `.nvmrc` says 18). The front-end toolchain is pinned to
  versions that still run on Node 18: Vite 6, laravel-vite-plugin 1.x and Tailwind **4.1.x**.
  Don't bump Tailwind to 4.2+ or Vite to 7+ while the server is on Node 18: Tailwind 4.2+ declares
  Node 20, so npm silently skips its native binary ("Cannot find native binding"), and Vite 7+ crashes
  with `'node:util' does not provide an export named 'styleText'`.
- MySQL 8.

### Moving to a newer Node later

If the server gets Node 20.19+ (e.g. `curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt-get install -y nodejs`),
the pins in `package.json` can be lifted.

## Environment (Site → Environment)

Start from `deploy/env.production.example`, then set:

```ini
APP_URL=https://amarbangladesh-dev.on-forge.com
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Dhaka
DB_TIMEZONE=+06:00
TRUSTED_PROXIES=cloudflare
DB_DATABASE=…   DB_USERNAME=…   DB_PASSWORD=…   # from Forge's database
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=…                                # first deploy only, then remove
```

`APP_KEY` must be set: run `php artisan key:generate --show` once and paste the value in.

## Deploy script (Site → Deployments → Deploy script)

Keep Forge's release macros, and make the middle part:

```sh
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan cache:clear   # drops the cached quiz content so new content/URLs show up
```

## First deploy only

After the first successful deploy, run once (Site → Commands):

```sh
php artisan db:seed --force      # quiz content + admin user (uses ADMIN_EMAIL / ADMIN_PASSWORD)
```

Then remove `ADMIN_PASSWORD` from the environment, and add the scheduler
(`php artisan schedule:run` every minute) so old analytics events are pruned.
