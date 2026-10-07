# Deploying on Laravel Forge

Dev site: https://amarbangladesh-dev.on-forge.com (behind Cloudflare).

## Server requirements

- PHP **8.2+** (the app is Laravel 12, locked to PHP 8.2 packages).
- Node **20.19+** (22 recommended, see `.nvmrc`). Vite 8 fails on Node 18 with
  `SyntaxError: The requested module 'node:util' does not provide an export named 'styleText'`.
- MySQL 8.

### Upgrading Node on the server

Forge servers ship Node from NodeSource. SSH in as `forge` and run (asks for the server's sudo password):

```sh
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs
node -v   # v22.x
```

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
