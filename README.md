# তোমার বাংলাদেশ কোথায়?

A one-minute Bangla personality quiz that matches you with a place in Bangladesh, and gives you a passport-style card to share.

**Flow:** landing → 8 questions → map reveal → result (vibe match %, traits, second-best place) → share → a friend opens the link and sees your result as a teaser → they play and see how closely their result matches yours.

## Setup

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate   # set DB_* (MySQL) and APP_URL
php artisan migrate --seed                         # prints the admin password unless ADMIN_PASSWORD is set
npm run build
php artisan quiz:og-images                         # link-preview images (needs google-chrome)
```

Admin panel: `/admin`. It has the analytics funnel, question and answer editing, place editing and the scoring balance checker.


## Deploy

Files are in `deploy/`: `nginx.conf`, `env.production.example`, `deploy.sh`, `crontab`. They're written for an Ubuntu VPS with nginx, PHP 8.4-FPM, MySQL and Node 20+, behind Cloudflare.

**First install**

```sh
git clone git@github.com:rbraju3m/amarbangladesh.git /var/www/amarbangladesh && cd /var/www/amarbangladesh
cp deploy/env.production.example .env        # fill APP_URL, DB_*, ADMIN_EMAIL, ADMIN_PASSWORD
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --force --seed            # seed ONCE: quiz content + admin user
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
```

Then remove `ADMIN_PASSWORD` from `.env`, install `deploy/nginx.conf`, and add `deploy/crontab` with `crontab -e` (scheduler + daily DB backup).

**Updates:** `deploy/deploy.sh` (maintenance mode → pull → build → migrate → cache → up).

**Cloudflare**

- SSL/TLS mode **Full (strict)**, with a Cloudflare Origin Certificate on the server. Turn on "Always Use HTTPS".
- Keep `TRUSTED_PROXIES=cloudflare` in `.env`. Without it every visitor shares one rate limit. Check the IP list every few months with `scripts/cloudflare-ips.sh`.
- Cache Rule: *Eligible for cache*, respect origin headers, for `/` and `/r/*`. The app sends `s-maxage` and sets no cookies on those pages.
- Cache Rule: *Bypass cache* for `/admin*` and `/api/*`.
- After changing content in the admin, purge `/` in Cloudflare, or wait up to 10 minutes.

**Shared hosting / cPanel (Apache):** point the domain's document root at `public/` (or upload `public/` into `public_html` and fix the paths in `index.php`). Laravel's `public/.htaccess` handles the rewrites. Add the scheduler line as a cPanel cron job. Run `npm run build` locally and upload `public/build/` if there's no Node on the host.

**After going live:** test a result link in Facebook's Sharing Debugger (developers.facebook.com/tools/debug) and point an uptime monitor at `/up`.

## Content still to provide

- Final place illustrations to replace the placeholder SVGs in `public/images/locations/`, then run `php artisan quiz:og-images`.
- A native-speaker review of all Bangla copy (`database/seeders/data/quiz.php`, or in the admin).
- Production domain in `APP_URL`.
