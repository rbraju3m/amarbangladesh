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
SUPER_ADMIN_NAME=Raju
SUPER_ADMIN_EMAIL=rbraju3m@gmail.com
SUPER_ADMIN_PASSWORD=…                          # same as hospital-management; 10+ chars, letters + digits
MAIL_MAILER=smtp  MAIL_HOST=…  MAIL_USERNAME=…  MAIL_PASSWORD=…  MAIL_FROM_ADDRESS=…   # password-reset emails
GOOGLE_CLIENT_ID=…  GOOGLE_CLIENT_SECRET=…       # optional: "Continue with Google"
FACEBOOK_CLIENT_ID=…  FACEBOOK_CLIENT_SECRET=…   # optional: "Continue with Facebook" (needs Meta app review)
SMS_DRIVER=bulksmsbd  BULKSMSBD_API_KEY=…  BULKSMSBD_SENDER_ID=…   # optional: phone sign-in
```

Each community sign-in method shows up only once its keys are set; email sign-in always works (it
needs mail for "forgot password"). OAuth redirect URIs: `${APP_URL}/auth/google/callback` and
`${APP_URL}/auth/facebook/callback`. After changing keys, redeploy (the deploy runs `optimize`,
which caches config).

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
php artisan db:seed --force      # quiz content (+ makes sure the super administrator exists)
```

Don't seed again later. Content changes that ship in code (like the 2026-10-08 rebalance) come as
data migrations, which the deploy's `migrate` applies; they leave anything edited in the admin alone.

The super administrator is created by the deploy's `migrate` already (and re-checked hourly), so
you can log in at `/admin` with the SUPER_ADMIN_* credentials. If you change the password on the
admin Password page it is kept; `php artisan admin:ensure-super-admin --reset-password` sets it
back to SUPER_ADMIN_PASSWORD. Then add the scheduler
(`php artisan schedule:run` every minute) so old analytics events are pruned.

## Going live / exporting the database

Test plays and analytics events from development should not go live. Before exporting:

```sh
php artisan quiz:purge-plays     # deletes all results + analytics events; asks first (--force skips)
mysqldump --single-transaction amarbangladesh > amarbangladesh.sql
```

Quiz content (questions, places, traits) and admin accounts are kept. Shared result links from the
deleted plays stop working, which is what you want for demo data. Community data (members, posts,
answers) is **not** purged: remove test posts in the admin Community page (Remove) before going live.
