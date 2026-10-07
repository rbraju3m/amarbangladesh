# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"তোমার বাংলাদেশ কোথায়?" — a mobile-first, Bangla-language 1-minute personality quiz that matches a player to one of 9 Bangladesh places, then pushes them to share the result. V1 is deliberately tiny: **quiz → result → share → friend plays**. No login, profiles, XP, leaderboards etc. (future phases — don't add them unasked).

## Commands

```sh
composer install && npm install
php artisan migrate:fresh --seed     # schema + all quiz content + admin user (prints password unless ADMIN_PASSWORD set)
npm run build                        # or `npm run dev` with `php artisan serve`
php artisan test                     # MySQL test DB `amarbangladesh_test` (see phpunit.xml); no sqlite driver on this machine
php artisan test --filter=ScoringTest
vendor/bin/pint                      # code style
php artisan quiz:simulate            # result balance across all 4^8 answer combinations
php artisan quiz:og-images           # re-render public/images/og/*.png (needs google-chrome + node_modules)
```

## Architecture

**Single-page quiz.** `/` and `/r/{code}` both render `resources/views/site/app.blade.php`, which embeds all questions/locations as JSON (`#boot`) and runs one Alpine component (`resources/js/quiz.js`) with screens `landing | teaser | question | reveal | result`. Only one API call happens per play (`POST /api/results`). After it, the URL is `pushState`d to `/r/{code}` — the result URL *is* the share URL. On `/r/{code}` the client decides owner vs visitor: owners have the code in `localStorage['bd.mine']` (with their owner token); everyone else sees the teaser, and their own play is sent with `ref` so they get a friend-match %.

**Public routes are cookieless and sessionless** (`routes/web.php` strips session/cookie/CSRF middleware). That's what makes pages CDN-cacheable and is the privacy model. Don't add `session()`, `csrf_token()`, `$errors` or auth checks to public views. The only write needing ownership (`PATCH /api/results/{code}/name`) uses a per-result owner token (stored hashed). Admin (`routes/admin.php`) uses normal session auth; every user is an admin, there is no registration.

**Scoring** (`app/Quiz/`) is deterministic and server-only:
- 8 trait dimensions (`traits` table). Each answer has `trait_weights`; each location has a 0–10 `profile`.
- Result = location with highest cosine(player vector, profile) + `BONUS_WEIGHT × location_bonus` (small "signature answer" nudges, e.g. ইলিশ → বরিশাল).
- `QuizConfig` is an array snapshot of active content, cached forever (as arrays — the cache refuses to unserialize objects). **Any content write must call `QuizConfig::forget()`**, which also flushes the embedded quiz JSON (`quiz.boot`) and trait labels.
- Displayed match % is a calibrated "vibe match" (72–97), not raw similarity; friend match is calibrated on random pairs. Both are in `Scorer`.
- Results store their computed output (location, %, traits, reason, `scoring_version`), so editing content never changes old shared results.
- **Balance matters**: every location should win 6–18% of all answer combinations. `ScoringTest` enforces this; the admin Balance page and `quiz:simulate` show it. After changing weights/profiles, re-check — content in `database/seeders/data/quiz.php` was tuned for ≈11% each.

**Bangla text rendering**: PHP GD cannot shape Bangla conjuncts. So the shareable story card (1080×1920) is drawn client-side on canvas (`resources/js/card.js`), and Open Graph images are pre-rendered per location with headless Chrome (`quiz:og-images`, view `og/image.blade.php`). Personalisation in link previews is only in `og:title` text. Bangla digits/possessives: `App\Support\Bangla` (PHP) mirrored in `resources/js/bn.js` — keep them in sync.

**Share handling**: Facebook/Messenger/Instagram in-app browsers block downloads and file sharing; `share.js` detects them and the UI falls back to "long-press the image to save". Native `navigator.share({files})` is used where available.

**Analytics** are first-party and cookieless: the client batches events to `POST /api/events` with `sendBeacon` (text/plain body); allowed names are `AnalyticsEvent::CLIENT_EVENTS`. `quiz_completed` is recorded server-side. Visitor identity is a random UUID in localStorage; no IP or user agent is stored (only a coarse device class). `App\Analytics\Funnel` builds the admin dashboard. Raw events are pruned after 180 days (scheduled `model:prune`).

**Map**: the Bangladesh outline (`partials/bd-map-path.blade.php`) is projected from Natural Earth data; location `map_x/map_y` are % positions in the same projection. Map dots are rendered server-side because Alpine `x-for` doesn't work inside `<svg>`.

## Conventions

- Laravel 13 attribute style on models (`#[Fillable]`, `#[Hidden]`, `#[Table]`), console commands via `#[Signature]`/`#[Description]`. The trait model is `PersonalityTrait` (`Trait` is reserved).
- Laravel 13's CSRF middleware is `PreventRequestForgery` (not `ValidateCsrfToken`).
- Styling: Tailwind 4 with semantic CSS-variable tokens in `resources/css/app.css` (`bg-paper`, `text-ink`, `text-ink-2`, `border-line`, `bg-accent` …). Dark mode is a token swap under `prefers-color-scheme`, so use tokens rather than `dark:` classes. Per-location colour comes from `--accent`.
- User-facing copy is conversational Bangla using তুমি; admin UI is English.
- Place illustrations are placeholder flat SVGs in `public/images/locations/` (400×300 viewBox); the image question reuses them.
- Production needs a correct `APP_URL` (used in share URLs and `og:image`).
