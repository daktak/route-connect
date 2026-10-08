# Cycling Routes

A Laravel web application for cyclists to share routes, discover rides, and coordinate group rides.

## Features

- **GPX Route Upload** - Drag & drop GPX files, automatic parsing with distance/elevation calculation
- **Route Discovery** - Filterable list and interactive map view with OpenStreetMap
- **Feature Marking** - Tag routes with checkboxes (gravel, steep, scenic, technical, etc.)
- **Social Features** - Rate routes (1-5 stars), threaded comments
- **Group Ride Coordination** - Create rides, join/leave, automatic attendee clearing when route/date changes
- **In-App Notifications** - Ride changes, comments, reminders

## Tech Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Database**: PostgreSQL 15+ with PostGIS 3.4+
- **Frontend**: Blade templates + Alpine.js 3
- **Maps**: Leaflet.js + OpenStreetMap tiles
- **Charts**: Chart.js for elevation profiles
- **Queue**: Redis + Laravel Horizon
- **Auth**: Laravel Breeze

## Requirements

- PHP 8.2+
- Composer 2+
- PostgreSQL 15+ with PostGIS extension
- Redis 7+
- Node.js 20+ (for Vite)

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

# Configure .env with your database/Redis credentials
# Required: DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# Required: REDIS_HOST, REDIS_PASSWORD (if applicable)

# Generate app key
php artisan key:generate

# Run migrations (creates tables + PostGIS extension)
php artisan migrate

# Build assets
npm run build

# Start development server
php artisan serve

# In separate terminal: start queue worker
php artisan horizon
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
# Start all services
php artisan serve          # Laravel on http://localhost:8000
npm run dev                # Vite HMR
php artisan horizon        # Queue dashboard on /horizon
php artisan reverb:start   # WebSockets (if using broadcasting)

# Run tests
./vendor/bin/pest

# Static analysis
./vendor/bin/phpstan analyse

# Code formatting
./vendor/bin/pint
```

## Project Structure

```
app/
├── Http/Controllers/       # Route, Ride, Rating, Comment, Feature controllers
├── Models/                 # Eloquent models with relationships
├── Policies/               # Authorization policies
├── Services/               # GpxParser, RideVersionManager
├── Jobs/                   # ProcessGpxUpload, NotifyRideChanged
└── Notifications/          # RideChanged, NewComment, RideReminder

resources/
├── views/
│   ├── routes/             # index, map, create, show, edit
│   ├── rides/              # index, show, create
│   ├── components/         # Alpine components, map, charts
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

### Group Ride Versioning
When organizer changes **route** or **date**:
1. `group_rides.version` increments
2. All attendees with `ride_version < new_version` are removed
3. Notifications sent to removed attendees to re-confirm

### GPX Processing
Async job parses GPX → extracts track points → computes distance/elevation → stores PostGIS LINESTRING + JSONB.

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Environment | `local` |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name | `routes` |
| `REDIS_HOST` | Redis host | `127.0.0.1` |
| `MAP_TILE_URL` | Tile server URL | OSM default |

## License

GPL-3.0 License