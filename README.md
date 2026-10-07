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

Run the scheduler in production so analytics events older than 180 days are pruned.

## Content still to provide

- Final place illustrations to replace the placeholder SVGs in `public/images/locations/`, then run `php artisan quiz:og-images`.
- A native-speaker review of all Bangla copy (`database/seeders/data/quiz.php`, or in the admin).
- Production domain in `APP_URL`.
