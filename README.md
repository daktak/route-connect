# Cycling Routes

A Laravel web application for cyclists to share routes, discover rides, and coordinate group rides.

## Features

- **GPX Route Upload** - Drag & drop GPX files, automatic parsing with distance/elevation calculation
- **Route Discovery** - Filterable list and interactive map view with OpenStreetMap
- **Feature Marking** - Tag routes with checkboxes (gravel, steep, scenic, technical, etc.)
- **Social Features** - Rate routes (1-5 stars), threaded comments
- **Group Ride Coordination** - Create rides, join/leave, automatic attendee clearing when route/date changes
- **Group Ride Discovery** - Group Rides is the default landing page; filter by status (upcoming/past/all) and date range, defaulting to upcoming rides
- **Meeting Point Picker** - Click the map to set a ride meeting point, with reverse-geocoded place names
- **In-App Notifications** - Ride changes, comments, reminders

## Tech Stack

- **Backend**: Laravel 13, PHP 8.3+
- **Database**: PostgreSQL 16+ with PostGIS 3.4+
- **Frontend**: Blade templates + Alpine.js 3, built with Vite 5 / Tailwind CSS 3
- **Maps**: Leaflet.js + OpenStreetMap tiles
- **Charts**: Chart.js for elevation profiles
- **Queue**: database queue driver (notifications/reminders)
- **Auth**: Laravel Breeze (blade stack)

## Requirements

- PHP 8.3+
- Composer 2+
- PostgreSQL 16+ with PostGIS extension
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

# Run migrations (creates tables + PostGIS extension)
php artisan migrate

# Build assets
npm run build

# Start development server
php artisan serve

# In separate terminal: process queued jobs (notifications)
php artisan queue:work
```

## Database Setup

### Option 1: Laravel Migrations (Recommended)
```bash
php artisan migrate
```

### Option 2: Manual Schema Execution

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

#### 2. Run Schema File

```bash
# From project root
psql -h localhost -U routes_app -d routes -f database/schema.sql
```

Or if using a different host/port:

```bash
psql -h your-host -p 5432 -U routes_app -d routes -f database/schema.sql
```

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
php artisan queue:work     # Process queued notifications

# Run tests
php artisan test

# Code formatting
./vendor/bin/pint
```

## Project Structure

```
app/
├── Http/Controllers/       # Route, Ride, Rating, Comment, Feature, Attendee controllers
├── Models/                 # Eloquent models with relationships
├── Policies/               # Authorization policies
├── Services/               # GpxParser
└── Notifications/          # RideChanged, NewComment, RideReminder, RideJoined

resources/
├── views/
│   ├── routes/             # index, map, create, show, edit
│   ├── rides/              # index, show, create, edit
│   ├── components/         # map sidebar, map, charts
│   └── layouts/            # app, map
├── js/                     # Alpine components, Leaflet init
└── css/                    # Tailwind + custom styles

database/
├── migrations/             # Laravel migrations
└── schema.sql              # Raw SQL for manual setup

routes/
├── web.php                 # Web routes
└── console.php             # Scheduled commands
```

## Key Concepts

### Route Features (Checkboxes)
Predefined types: `gravel`, `steep`, `technical`, `scenic`, `road`, `water`, `cafe`, `shop`, `custom`
Stored as `route_features` rows, filterable in route list.

### Landing Page & Navigation
`/` and `/dashboard` both redirect to the Group Rides list (`/rides`). The app logo also links there.

### Group Ride Filtering
`/rides` filters via query params: `status` (`upcoming` default, `past`, `all`), plus optional `from` / `to` date bounds. Results are paginated and the query string is preserved.

### Group Ride Versioning
When organizer changes **route** or **date**:
1. `group_rides.version` increments
2. All attendees with `ride_version < new_version` are removed
3. Notifications sent to removed attendees to re-confirm

### GPX Processing
`GpxParser` parses GPX synchronously on upload → extracts track points → computes distance/elevation → stores PostGIS LINESTRING + JSONB.

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Environment | `local` |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name | `routes` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `CACHE_STORE` | Cache driver | `database` |
| `MAP_TILE_URL` | Tile server URL | OSM default |

## License

GPL-3.0 License