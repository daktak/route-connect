# Cycling Routes

A Laravel web application for cyclists to share routes, discover rides, and coordinate group rides.

![](screenshots/routes-routes.png?raw=true)

## Features

- **GPX Route Upload** - Drag & drop GPX files, automatic parsing with distance/elevation calculation, optional start/end cropping. Routes are stored as PostGIS `LINESTRING` + JSONB and downloadable as clean GPX (with elevation when available).
- **Route Discovery** - Filterable list and interactive map view with OpenStreetMap; toggle between card and list layouts. Filters (search, difficulty, distance, elevation, features, sort) and the view mode are saved to your account.
- **Feature Marking** - Tag routes with predefined features (gravel, steep, technical, scenic, road, bike path, water, cafe, shop, MTB) plus free-form custom features.
- **Surface Analysis** - Compute paved/gravel percentages from OpenStreetMap data via Valhalla (on upload and with the `routes:analyze-surface` command).
- **Social Features** - Rate routes (1-5 stars, averaged via a materialized view) and threaded comments.
- **Group Ride Coordination** - Create rides, join/leave, automatic attendee clearing when route/date changes, and multi-route voting.
- **Group Ride Discovery** - Group Rides is the default landing page; filter by status (upcoming/past/all) and date range, defaulting to upcoming rides.
- **Meeting Point Picker** - Click the map to set a ride meeting point, with reverse-geocoded place names.
- **Weather & Air Quality** - Open-Meteo forecasts for upcoming rides.
- **In-App Notifications** - Ride changes, cancellations, joins, reminders, comments and replies (database channel).
- **Admin User Management** - Users listed in `ADMIN_EMAILS` can view, edit, and delete other users.

> **Notifications:** the profile form stores `email_notifications` and `telegram_chat_id` preferences, but only the in-app `database` channel is delivered today. Email/Telegram delivery is not yet implemented.

## Tech Stack

- **Backend**: Laravel 13, PHP 8.3+
- **Database**: PostgreSQL 16+ with PostGIS
- **Frontend**: Blade templates + Alpine.js 3, built with Vite 5 / Tailwind CSS 3
- **Maps**: Leaflet.js + OpenStreetMap tiles
- **Charts**: Chart.js for elevation profiles
- **Queue**: `database` driver is configured; notifications currently send **synchronously**, so a queue worker is optional
- **Auth**: Laravel Breeze (blade stack)

## Requirements

- PHP 8.3+ with `pdo_pgsql`, `pgsql`, `simplexml`, `mbstring`, `openssl`, `ctype`, `tokenizer`, `xml`, `fileinfo`, `curl`
- Composer 2+
- PostgreSQL 16+ with the PostGIS extension
- Node.js 18 (Vite is pinned to Vite 5)

## Installation

```bash
# Clone repository
git clone <repository-url>
cd Routes

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Copy environment file
cp .env.example .env

# Configure .env with your PostgreSQL credentials
# Required: DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# Generate app key
php artisan key:generate

# Ensure PostGIS exists (superuser), then run migrations
#   sudo -u postgres psql -d routes -c "CREATE EXTENSION IF NOT EXISTS postgis;"
php artisan migrate

# Build assets
npm run build

# Start development server
php artisan serve
```

## Database Setup

Laravel migrations require the **PostGIS extension to already exist** in the
target database: the `routes` migration creates a `geometry` column and a GiST
index, and PostGIS functions/triggers are created later. Create the extension as
a superuser first, then migrate.

### Option 1: Laravel Migrations (Recommended)

```bash
# As a superuser (once per database)
sudo -u postgres psql -d routes -c "CREATE EXTENSION IF NOT EXISTS postgis;"
sudo -u postgres psql -d routes -c 'CREATE EXTENSION IF NOT EXISTS "uuid-ossp";'
sudo -u postgres psql -d routes -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"

# As the application user
php artisan migrate
```

The migrations create the `route_avg_ratings` materialized view, `updated_at`
triggers, and the functions `clear_attendees_on_version_change`,
`refresh_route_avg_rating`, `get_nearby_routes`, and `get_user_route_stats`.

### Option 2: Create the Database & User Manually

Use this when you want to provision the database and role yourself, then let
Laravel run the migrations.

#### 1. Create Database User & Grant Permissions

Connect to PostgreSQL as superuser (postgres):

```bash
sudo -u postgres psql
```

Then run:

```sql
-- Create database
CREATE DATABASE routes;

-- Create user with password
CREATE USER routes_app WITH ENCRYPTED PASSWORD 'your_secure_password';

-- Grant privileges on database
GRANT ALL PRIVILEGES ON DATABASE routes TO routes_app;

-- Connect to the routes database
\c routes

-- Enable PostGIS extension (requires superuser)
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Grant schema privileges
GRANT ALL ON SCHEMA public TO routes_app;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO routes_app;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO routes_app;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO routes_app;

-- Set default privileges for future objects
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO routes_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO routes_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT EXECUTE ON FUNCTIONS TO routes_app;

-- Exit psql
\q
```

#### 2. Run Migrations

```bash
php artisan migrate
```

> `database/schema.sql` is a legacy raw-SQL snapshot and may lag behind the
> migrations. Prefer migrations; if you use the dump, verify it matches
> `php artisan migrate` output (in particular the `route_avg_ratings` trigger,
> which must not use `REFRESH MATERIALIZED VIEW CONCURRENTLY`).

#### 3. Verify Installation

```bash
psql -h localhost -U routes_app -d routes -c "\dt"
psql -h localhost -U routes_app -d routes -c "SELECT PostGIS_Version();"
```

### Configure Laravel .env

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=routes
DB_USERNAME=routes_app
DB_PASSWORD=your_secure_password
```

## Development

```bash
# Start services
php artisan serve          # Laravel dev server
npm run dev                # Vite HMR

# Run tests
php artisan test

# Code formatting
./vendor/bin/pint
```

`php artisan test` needs a PostGIS-capable database connection (the migrations
require PostGIS). Build a real connection or install the required extensions
first — do **not** point `RefreshDatabase` at a database whose tables you want
to keep.

## Background Jobs

Two commands are scheduled (`routes/console.php`):

- `rides:send-reminders` — daily at 08:00; notifies confirmed attendees about rides starting soon.
- `rides:mark-past-completed` — daily at 00:05; marks past planned/confirmed rides as completed.

`routes:analyze-surface` is **not** scheduled; run it manually (e.g. `php artisan
routes:analyze-surface --missing`) or wire it into your own schedule. It calls the
external Valhalla API.

Run the scheduler with cron:

```
* * * * * cd /path/to/Routes && php artisan schedule:run >> /dev/null 2>&1
```

## Project Structure

```
app/
├── Console/Commands/       # SendRideReminders, MarkPastRidesCompleted, AnalyzeRouteSurfaces
├── Http/Controllers/       # Route, Ride, Attendee, Vote, Rating, Comment, Feature, Gpx,
│                           # Notification, RoutePreference, AdminUser, Profile, Auth
├── Models/                 # Eloquent models with relationships
├── Policies/               # Route, Ride, Comment, User authorization policies
├── Services/               # GpxParser, GpxGenerator, WeatherService, AirQualityService,
│                           # SurfaceAnalyzer
└── Notifications/          # RideReminder, RideChanged, RideCancelled, RideJoined,
                            # NewComment, CommentReply

resources/
├── views/
│   ├── routes/             # index, map, create, show, edit
│   ├── rides/              # index, show, create, edit, partials (cards, weather)
│   ├── components/         # map-sidebar + Breeze UI components
│   └── layouts/            # app, map
├── js/                     # Alpine components, Leaflet init
└── css/                    # Tailwind + custom styles

database/
├── migrations/             # Laravel migrations (source of truth)
└── schema.sql              # Legacy raw SQL dump (may lag)

routes/
├── web.php                 # Web routes
└── console.php             # Scheduled commands
```

## Key Concepts

### Route Features
Predefined types: `gravel`, `steep`, `technical`, `scenic`, `road`, `bikepath`,
`water`, `cafe`, `shop`, `mtb`, `custom`. Stored as `route_features` rows and
filterable in the route list.

### Landing Page & Navigation
`/` and `/dashboard` both redirect to the Group Rides list (`/rides`). The app logo also links there.

### Route Filters & View Mode
The routes index filters via query params (`search`, `difficulty`, distance/elevation
ranges, `features`, `sort`). The chosen filters and card/list view mode are persisted
to the signed-in user (`users.route_filters`, `users.route_view_mode`) through
`RoutePreferenceController`, and shared across routes pages.

### Group Ride Filtering
`/rides` filters via query params: `status` (`upcoming` default, `past`, `all`), plus optional `from` / `to` date bounds. Results are paginated and the query string is preserved.

### Group Ride Versioning & Voting
When the organizer changes **route** or **date**:
1. `group_rides.version` increments
2. Attendees/votes with `ride_version < new_version` are cleared (trigger `clear_attendees_on_version_change`)
3. Notifications are sent to removed attendees to re-confirm

A ride may offer multiple routes (`group_ride_route_options`); attendees vote
(`ride_route_votes`) and the leading route is surfaced.

### GPX Processing
`GpxParser` parses GPX synchronously on upload → extracts track points → computes
distance/elevation → stores PostGIS `LINESTRING` + JSONB. Downloads are regenerated
by `GpxGenerator` as a clean `<rte>` with `<ele>` where known.

### Surface Analysis
`SurfaceAnalyzer` queries Valhalla/OSM for the route geometry and stores a
`surface_breakdown` JSON, `gravel_pct`, `surface_source`, and `surface_analyzed_at`.

### Weather & Air Quality
`WeatherService` / `AirQualityService` (Open-Meteo) produce forecasts for upcoming
rides. They are transient attributes attached to `GroupRide` models in the
controller — **not** database columns.

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Environment | `local` |
| `APP_URL` | Base URL (include sub-path if hosted under one) | `http://localhost:8000` |
| `APP_TIMEZONE` | Application timezone | `.env.example`: `Australia/Melbourne`; config default `UTC` |
| `ASSET_URL` / `APP_FORCE_ROOT_URL` | Sub-path hosting for `asset()`/`@vite` | empty / `false` |
| `ADMIN_EMAILS` | Comma-separated admin emails | empty |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name | `routes` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `CACHE_STORE` | Cache driver | `database` |
| `MAIL_*` | Mailer settings (needed only if email delivery is enabled) | `log` |
| `OPEN_METEO_BASE_URL` | Weather API base | Open-Meteo forecast URL |
| `WEATHER_CACHE_TTL` / `WEATHER_FORECAST_DAYS` | Weather caching/forecast window | `1800` / `16` |
| `OPEN_METEO_AIR_QUALITY_URL` | Air quality API base | Open-Meteo air-quality URL |
| `AIR_QUALITY_CACHE_TTL` / `AIR_QUALITY_FORECAST_DAYS` | Air quality caching/window | `1800` / `5` |
| `VALHALLA_BASE_URL` / `VALHALLA_TIMEOUT` | Surface analysis API | `https://valhalla1.openstreetmap.de` / `30` |

## License

GPL-3.0 License
