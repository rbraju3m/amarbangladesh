# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"তোমার বাংলাদেশ কোথায়?" — a mobile-first, Bangla-language 1-minute personality quiz that matches a player to one of 9 Bangladesh places, then pushes them to share the result. V1 is deliberately tiny: **quiz → result → share → friend plays**. No login, profiles, XP, leaderboards etc. (future phases — don't add them unasked).

## Commands

```sh
composer install && npm install
php artisan migrate:fresh --seed     # schema + all quiz content + super admin (needs SUPER_ADMIN_PASSWORD in .env)
php artisan admin:ensure-super-admin [--reset-password]   # create/repair the permanent super admin
php artisan quiz:purge-plays         # delete all results + analytics (demo data) before going live / exporting
npm run build                        # or `npm run dev` with `php artisan serve`
php artisan test                     # MySQL test DB `amarbangladesh_test` (see phpunit.xml); no sqlite driver on this machine
php artisan test --filter=ScoringTest
vendor/bin/pint                      # code style
php artisan quiz:simulate            # result balance across all 4^8 answer combinations (+ adventure-leaning column)
php artisan quiz:og-images           # re-render public/images/og/*.png (needs google-chrome + node_modules)
```

## Architecture

**Single-page quiz.** `/` and `/r/{code}` both render `resources/views/site/app.blade.php`, which embeds all questions/locations as JSON (`#boot`) and runs one Alpine component (`resources/js/quiz.js`) with screens `landing | teaser | question | reveal | result`. Only one API call happens per play (`POST /api/results`). After it, the URL is `pushState`d to `/r/{code}` — the result URL *is* the share URL. On `/r/{code}` the client decides owner vs visitor: owners have the code in `localStorage['bd.mine']` (with their owner token); everyone else sees the teaser, and their own play is sent with `ref` so they get a friend-match %. The teaser is kept compact so its play button sits above the fold on a phone (inside Messenger's browser too); it challenges the visitor to see how much they match the friend and shows how many friends already played from that link (`friendPlays`, one count query in `QuizController::show`).

**Public routes are cookieless and sessionless** (`routes/web.php` strips session/cookie/CSRF middleware). That's what makes pages CDN-cacheable and is the privacy model. Don't add `session()`, `csrf_token()`, `$errors` or auth checks to public views. The only write needing ownership (`PATCH /api/results/{code}/name`) uses a per-result owner token (stored hashed). Admin (`routes/admin.php`) uses normal session auth; every user is an admin, there is no registration. One permanent **super admin** (`config/admin.php`, same identity as the hospital-management project) is guaranteed by `App\Support\SuperAdmin\SuperAdminProvisioner`: after every `migrate` (listener), in `DatabaseSeeder`, via `admin:ensure-super-admin`, and throttled on boot. Its password comes only from `SUPER_ADMIN_PASSWORD` (never commit one: the repo is public); `User` refuses to delete, demote or re-address it, and `is_super_admin` is not fillable.

**Scoring** (`app/Quiz/`) is deterministic and server-only:
- 8 trait dimensions (`traits` table). Each answer has `trait_weights`; each location has a 0–10 `profile`.
- Result = location with highest cosine(player vector, profile) + `BONUS_WEIGHT × location_bonus` (small "signature answer" nudges, e.g. ইলিশ → বরিশাল).
- `QuizConfig` is an array snapshot of active content, cached forever (as arrays — the cache refuses to unserialize objects). **Any content write must call `QuizConfig::forget()`**, which also flushes the embedded quiz JSON (`quiz.boot`) and trait labels.
- Displayed match % is a calibrated "vibe match" (72–97), not raw similarity; friend match is calibrated on random pairs. Both are in `Scorer`.
- Results store their computed output (location, %, traits, reason, `scoring_version`), so editing content never changes old shared results.
- **Balance matters**: every location should win 6–18% of all answer combinations, both uniformly and when players favour the adventurous answers (`Simulator::run(Simulator::FAVOURED_TRAIT)`: the most-adventure answer per question picked 35% of the time; at launch real players did this and বান্দরবান won 47%). `ScoringTest` enforces both; the admin Balance page (thin bar) and `quiz:simulate` (last column) show both. The editors' live preview only shows the uniform case. After changing weights/profiles, re-check. Content changes that must reach production go in a data migration too: deploys run `migrate`, not the seeder (see `2026_10_08_000002_rebalance_adventurous_answers`, which skips rows edited in admin). `App\Quiz\Replay` (Balance page, "Real players, replayed") re-scores each player's latest stored answers with the current content, shows what they got vs would get now, and the real adventurous-answer pick rate to check the 35% assumption; it uses `Simulator::favouredOptions()` so both share one definition, and only flags shares once `Replay::TRUSTED` (100) players exist.

**Share cards**: PHP GD cannot shape Bangla conjuncts. So the shareable card is drawn client-side on canvas (`resources/js/card.js`) in two formats, a 1080×1920 story and a 1080×1080 square for feed posts: 4 designs (`resources/js/cards/{passport,poster,boarding,minimal}.js`; the default export draws the story, the named `square` export the square layout; shared helpers in `common.js`) × 6 colour themes (`cards/themes.js`; `place` = the location's own accent, the original look). Templates take a resolved palette `t` (`ink`/`accent` for the background, `cardInk`/`cardAccent` for white panels so dark themes stay readable); all theme colours must be 6-digit hex because templates append alpha. `renderCard(result, {template, theme, format, scale})` — `scale` draws the small picker previews; use `shadow()` from `common.js` rather than raw `shadowBlur` so shadows scale too. The card is built as soon as a result shows (desktop shows it as the left column; phones show a thumbnail) and re-drawn after the name is typed. The share sheet shows design previews and colour swatches; choices are kept in `localStorage` (`bd.card`, `bd.theme`, `bd.format`) and sent as `template`/`theme`/`format` in `card_saved`/native-share event meta.

**Bangla text rendering**: Open Graph images are pre-rendered per location with headless Chrome (`quiz:og-images`, view `og/image.blade.php`). Personalisation in link previews is only in `og:title` text. Bangla digits/possessives: `App\Support\Bangla` (PHP) mirrored in `resources/js/bn.js` — keep them in sync.

**Share handling**: Facebook/Messenger/Instagram in-app browsers block downloads and file sharing; `share.js` detects them and the UI falls back to "long-press the image to save". Native `navigator.share({files})` is used where available.

**Analytics** are first-party and cookieless: the client batches events to `POST /api/events` with `sendBeacon` (text/plain body); allowed names are `AnalyticsEvent::CLIENT_EVENTS`. `quiz_completed` is recorded server-side. Visitor identity is a random UUID in localStorage; no IP or user agent is stored (only a coarse device class). `App\Analytics\Funnel` builds the admin dashboard: funnel steps are distinct visitors (completed = completers among the period's starters, so rates stay ≤ 100%; `plays` is the raw result count), it takes an optional `$until` for the previous-period comparison, referral numbers (`referred_players`, `referral_conversion`, `viral_k`) count distinct people so replays don't inflate them, `startRateByEntry()` splits the start rate into landing vs friend-link visitors (via the `referred` flag on `quiz_started`), and `trend()` gives daily (weekly past 60 days) rows for the SVG chart in `admin/partials/trend-chart.blade.php`. Raw events are pruned after 180 days (scheduled `model:prune`).

**Result page explore**: `ResultPresenter` adds `places` (every place with this player's % and the answers that pulled toward it, from `Scorer::explore()`; stored winner/runner-up keep their %, others are capped below) and per-trait `answers`; the client resolves option ids to labels from the boot JSON. The same place sheet opens from landing cards in preview mode (no %). Note: MySQL JSON columns don't keep key order, so sort `trait_scores` before use.

**Map**: the Bangladesh outline (`partials/bd-map-path.blade.php`) is projected from Natural Earth data; location `map_x/map_y` are % positions in the same projection. Map dots are rendered server-side because Alpine `x-for` doesn't work inside `<svg>`.

**Admin UI**: `layouts/admin.blade.php` (sidebar on desktop, scrollable tabs on phones, toast for `session('status')`). Small progressive enhancements live in `resources/js/admin.js` (chart tooltip, slider read-outs, "+ Add answer" from a `<template>`, colour picker sync, unsaved-changes guard via `form[data-dirty-guard]`); every page must still work without it. The question and location editors have a live balance preview (`admin/partials/balance-preview.blade.php` + `resources/js/admin/balance-preview.js`): the editor embeds `BalanceController::previewData()` and the browser replays every combination with the unsaved form values; it mirrors `Scorer::rank()`, so keep the two in sync if scoring changes. Answer cards are `admin/partials/answer.blade.php` (also rendered with `__INDEX__` as the add-answer template). Chart series colours are the validated `--viz-1..3` tokens. Also in `resources/js/admin/`: `sortable.js` (drag/keyboard reorder of questions, POSTs the full id list to `admin.questions.reorder`; the ↑/↓ forms are the no-JS fallback) and `previews.js` (live phone preview of a question, live result card of a location via `data-bind`). The dashboard exports CSV via `ExportController` (`/admin/export/{results|daily}.csv?days=`).

## Conventions

- **Laravel 12 on PHP 8.2** (production host runs 8.2; `config.platform.php` is pinned to 8.2.0 so composer only picks 8.2-compatible packages). Don't use PHP 8.3+ features (typed class constants, `#[\Override]`, `json_validate`) or Laravel 13-only APIs (model/command attributes like `#[Fillable]`/`#[Signature]`, `PreventRequestForgery`). Models use `$fillable`/`$hidden`/`$table` properties, commands `$signature`/`$description`; the CSRF middleware is `ValidateCsrfToken`. Test with `php8.2 artisan test` before shipping. The trait model is `PersonalityTrait` (`Trait` is reserved).
- **Node 18 on the server** (Forge dev box has 18.20.5): front-end tooling is pinned to Vite 6, laravel-vite-plugin 1.x and Tailwind `~4.1.18`. Tailwind 4.2+ and Vite 7+ need Node 20, and npm silently skips Tailwind's native binary on 18. Check a build with Node 18 before bumping them (see `deploy/FORGE.md`).
- Times are Asia/Dhaka: `APP_TIMEZONE=Asia/Dhaka` and `DB_TIMEZONE=+06:00` (MySQL session zone, so `useCurrent()` defaults agree with PHP).
- Styling: Tailwind 4 with semantic CSS-variable tokens in `resources/css/app.css` (`bg-paper`, `text-ink`, `text-ink-2`, `border-line`, `bg-accent` …). Dark mode is a token swap under `prefers-color-scheme`, so use tokens rather than `dark:` classes. Per-location colour comes from `--accent`.
- User-facing copy is conversational Bangla using তুমি; admin UI is English.
- Place illustrations are placeholder flat SVGs in `public/images/locations/` (400×300 viewBox); the image question reuses them.
- Production needs a correct `APP_URL` (used in share URLs and `og:image`).
