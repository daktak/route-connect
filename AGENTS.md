# Route Connect — Agent Guidelines

Laravel web app for cyclists to share GPX routes, discover rides, and coordinate group rides.

## Stack

- **Backend**: Laravel 13 (PHP 8.3), PostgreSQL 16 + PostGIS
- **Frontend**: Blade + Alpine.js + Leaflet.js (OpenStreetMap) + Chart.js, built with Vite 5 / Tailwind CSS 3
- **Auth**: Laravel Breeze (blade stack)
- **Tests**: PHPUnit
- **License**: GPL-3.0

## Environment

- PHP: `/usr/bin/php` (8.3) is on `PATH`.
- Composer: not on `PATH` by default — invoke as `~/.local/bin/composer` or add it: `export PATH=$PATH:$HOME/bin`.
- Node v18 (Vite is pinned to `^5.4.0`; do not upgrade to a Vite that requires newer Node).
- PostgreSQL + PostGIS. Default DB `routes`, user `routes_app`. Create the PostGIS extension as a superuser first if needed.
- `ADMIN_EMAILS` (comma-separated) grants admin; `APP_TIMEZONE` drives all timestamps (config default `UTC`; `.env.example` sets `Australia/Melbourne`).
- Setup: copy `.env.example` to `.env`, set the `pgsql` credentials, then:

  ```sh
  php artisan key:generate
  php artisan migrate
  npm install && npm run build
  ```

## Commands

| Task         | Command                 |
| ------------ | ----------------------- |
| Dev server   | `php artisan serve`     |
| Assets (dev) | `npm run dev`           |
| Assets (prod)| `npm run build`         |
| Migrate      | `php artisan migrate`   |
| Schedule list| `php artisan schedule:list` |
| Format       | `./vendor/bin/pint`     |
| Tests        | `php artisan test`      |

Scheduled commands (`routes/console.php`): `rides:send-reminders` (08:00),
`rides:mark-past-completed` (00:05). `routes:analyze-surface` is **not**
scheduled and calls the external Valhalla API.

`php artisan test` currently requires the `pdo_sqlite` extension (`php8.3-sqlite3`), which is not installed in this environment. Build a real DB connection or install the extension to run tests.

## Conventions & Gotchas

- **Layouts**: use Breeze class components `<x-app-layout>` / `<x-guest-layout>`. Do **not** use `<x-layouts.app>` (conflicts with `App\View\Components\AppLayout`).
- **Controllers**: never call `$this->middleware(...)` in a controller constructor (removed in this Laravel version). Register middleware in `routes/web.php` instead.
- **Ratings**: `avg_rating` / `rating_count` are **not** columns on `routes`; they live in the `route_avg_ratings` materialized view. `leftJoin('route_avg_ratings as rav', ...)` or use the model accessor — never `select` them from `routes`.
- **Blade + Alpine**: `@if(...)`, `{{ }}` and `@@` are server-side. For JS variables inside `x-data` / `x-for` scopes, use Alpine bindings (`x-show`, `x-text`, `:class`) or `@{{ }}` / `@@` escapes. Never use Blade `@if($jsVar)`.
- **Migrations with PostGIS**: use `DB::unprepared()` for multi-statement SQL (functions, triggers, views). Do not add `notifications` to trigger-managed tables.
- **Route preferences**: `route_filters` (JSON) and `route_view_mode` are columns on `users`, written by `RoutePreferenceController` — not cookies.
- **Weather / air quality**: transient attributes attached to `GroupRide` models in `RideController` (`WeatherService`, `AirQualityService`), not database columns.
- **Notifications**: all notifications deliver via the `database` channel only. `email_notifications` / `telegram_chat_id` are stored profile preferences but email/Telegram delivery is not implemented.
- **Admin**: `User::isAdmin()` matches the user's (mutable) `email` against the `ADMIN_EMAILS` config list; admin routes rely on `UserPolicy`.
- **Code style**: run `./vendor/bin/pint` before committing. Do not add comments unless requested.

## Database

The `add_postgis_extension_and_functions` migration creates the PostGIS extension, the `route_avg_ratings` materialized view, `updated_at` triggers, and the functions `clear_attendees_on_version_change`, `refresh_route_avg_rating`, `get_nearby_routes`, and `get_user_route_stats`.

Migrations are the source of truth — `database/schema.sql` is a legacy dump and
may lag. The `routes` table uses a PostGIS `geometry` column, so the PostGIS
extension must exist **before** the first migration. There are two
notification-preferences migrations (`2026_10_10_105602_...` and the guarded
`2026_10_10_110842_..._v2`); the v2 migration is a no-op when the columns already
exist.

## Git

- Commit only when asked. Use conventional commits: `feat:`, `fix:`, `refactor:`, `test:`, `chore:`.
