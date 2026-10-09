# Architecture Document

## Overview
Cycling Routes Web Application - A Laravel 13 application for submitting, discovering, and organizing group rides on cycling routes via GPX upload.

## Tech Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| Framework | Laravel | 13.x | Core framework, routing, ORM, auth |
| Database | PostgreSQL | 16+ | Primary data store with PostGIS |
| Extension | PostGIS | 3.4+ | Geospatial queries, geometry storage |
| Frontend | Blade + Alpine.js | 3.x | Server-rendered with reactive components |
| Build | Vite / Tailwind CSS | 5.x / 3.x | Asset bundling and styling |
| Maps | Leaflet.js | 1.9+ | Interactive maps with OSM tiles |
| Auth | Laravel Breeze | 2.x | Authentication scaffolding (blade stack) |
| Queue | Database driver | - | Background notifications, reminders |
| Charts | Chart.js | 4.x | Elevation profiles |
| GPX Parsing | Native SimpleXML | - | Custom parser service |

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Browser                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  Blade Tpl  │  │  Alpine.js  │  │   Leaflet Map       │  │
│  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘  │
└─────────┼────────────────┼─────────────────────┼─────────────┘
          │                │                     │
          ▼                ▼                     ▼
┌─────────────────────────────────────────────────────────────┐
│                      Laravel Application                    │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌──────────┐  │
│  │ Controllers│ │  Services  │ │  Models    │ │ Policies │  │
│  └────────────┘ └────────────┘ └────────────┘ └──────────┘  │
└─────────────────────────────────────────────────────────────┘
          │                │                     │
          ▼                ▼                     ▼
┌─────────────────────────────────────────────────────────────┐
│                      PostgreSQL + PostGIS                   │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌──────────┐  │
│  │  Routes    │ │  Features  │ │  Rides     │ │  Users   │  │
│  │  (geometry)│ │            │ │  (version) │ │          │  │
│  └────────────┘ └────────────┘ └────────────┘ └──────────┘  │
└─────────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────┐
│                      Database Queue                         │
│  ┌────────────────────┐ ┌────────────────────┐              │
│  │ Notifications      │ │  Ride Reminders    │              │
│  └────────────────────┘ └────────────────────┘              │
└─────────────────────────────────────────────────────────────┘
```

## Database Schema

### Core Tables

**users** - Authentication and profiles
- id, name, email, password, timestamps

**routes** - Core route entity with geospatial data
- id, user_id, name, description, gpx_data (JSONB), geometry (LINESTRING, SRID 4326)
- distance_km, elevation_gain_m, estimated_time_min, difficulty, is_public
- gpx_file_path, timestamps

**route_features** - Checkbox-style features (gravel, steep, scenic, etc.)
- id, route_id, feature_type, feature_subtype, description, timestamps

**route_ratings** - 1-5 star ratings (one per user per route)
- id, route_id, user_id, rating (1-5), timestamps

**route_comments** - Threaded comments on routes
- id, route_id, user_id, parent_id (for replies), content, timestamps

**group_rides** - Group ride coordination
- id, route_id, organizer_id, title, description, ride_date
- meeting_point_lat/lng/name, max_participants, status, version
- timestamps

**ride_attendees** - Users attending rides (cleared on version change)
- id, group_ride_id, user_id, ride_version, status, note, joined_at

### Indexes
- GIST index on routes.geometry for spatial queries
- B-tree indexes on foreign keys and frequently queried columns
- Unique constraints on (route_id, user_id) for ratings and attendees

## Key Design Patterns

### 1. GPX Processing Pipeline
```
Upload → Validation → Parse (GpxParser service) → Compute Stats → Store Geometry + JSONB
```
- Parsed synchronously in the request by `App\Services\GpxParser`
- Parser extracts: track points, elevation, waypoints, bounds
- Computes: distance (Haversine), elevation gain (positive deltas)
- Stores: PostGIS LINESTRING for spatial queries, JSONB for full fidelity

### 2. Group Ride Versioning
```
Ride Created (v1) → Users Join (stored with v1)
     │
     ├─ Route/Date Changed → version++ (v2) → DELETE attendees WHERE ride_version < v2 → Notify
     │
     └─ No Changes → Attendees persist
```
- Atomic version increment on critical field changes
- Cascade delete old attendees via DB trigger or application logic
- Notification dispatched to removed attendees

### 3. Feature Marking (Checkbox Approach)
- Predefined feature types: gravel, steep, technical, scenic, road, water, cafe, shop, custom
- Stored as route_features rows linked to route
- Displayed as badges on cards and detail pages
- Filterable in route list

### 4. Authorization Policies
- RoutePolicy: owner can edit/delete, all can view public routes
- RidePolicy: organizer can edit/cancel, attendees can leave
- CommentPolicy: author can edit/delete own comments

### 5. Weather Forecasts
- Upcoming group rides show a forecast for their **start time and location**
- `App\Services\WeatherService` resolves the location from the meeting point, falling back to the route start (`ST_StartPoint(geometry)`)
- All rides on a page are batched into a single Open-Meteo request; results are cached per location + hour (`WEATHER_CACHE_TTL`, default 30 min)
- Forecast horizon is `WEATHER_FORECAST_DAYS` (default 16); past rides and rides beyond the horizon show no weather
- Rendered as a compact badge on ride cards and a panel on the ride detail page (condition, temperature, rain chance, wind); API failures degrade silently

## API Routes Structure

| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | / | redirect → rides.index | - |
| GET | /dashboard | redirect → rides.index | dashboard |
| GET | /routes | RouteController@index | routes.index |
| GET | /routes/map | RouteController@map | routes.map |
| GET | /routes/create | RouteController@create | routes.create |
| POST | /routes | RouteController@store | routes.store |
| GET | /routes/{route} | RouteController@show | routes.show |
| GET | /routes/{route}/download | RouteController@download | routes.download |
| GET | /routes/{route}/edit | RouteController@edit | routes.edit |
| PUT | /routes/{route} | RouteController@update | routes.update |
| DELETE | /routes/{route} | RouteController@destroy | routes.destroy |
| POST | /routes/{route}/rate | RatingController@store | routes.rate |
| POST | /routes/{route}/comments | CommentController@store | routes.comments.store |
| PUT | /comments/{comment} | CommentController@update | comments.update |
| DELETE | /comments/{comment} | CommentController@destroy | comments.destroy |
| POST | /routes/{route}/features | FeatureController@store | routes.features.store |
| DELETE | /features/{feature} | FeatureController@destroy | features.destroy |
| GET | /rides | RideController@index | rides.index |
| GET | /rides/create | RideController@create | rides.create |
| POST | /rides | RideController@store | rides.store |
| GET | /rides/{ride} | RideController@show | rides.show |
| GET | /rides/{ride}/edit | RideController@edit | rides.edit |
| PUT | /rides/{ride} | RideController@update | rides.update |
| DELETE | /rides/{ride} | RideController@destroy | rides.destroy |
| POST | /rides/{ride}/join | RideAttendeeController@join | rides.join |
| DELETE | /rides/{ride}/leave | RideAttendeeController@leave | rides.leave |
| GET | /rides/{ride}/attendee-status | RideAttendeeController@status | rides.attendee.status |
| GET | /notifications | NotificationController@index | notifications.index |
| POST | /notifications/{notification}/read | NotificationController@markAsRead | notifications.read |
| POST | /notifications/read-all | NotificationController@markAllAsRead | notifications.read-all |
| POST | /api/routes/parse-gpx | GpxController@parse | api.routes.parse-gpx |

## Frontend Components

### Blade Layouts
- `layouts.app` - Main layout with navigation, flash messages
- `layouts.map` - Full-screen map layout for /routes/map

### Alpine.js Components
- `route-filters` - Route filter sidebar (distance, elevation, difficulty, features); all features selected by default
- `route-map` - Leaflet map with route layers, feature markers
- `route-map` (single) / `routeMap` - Ride detail map rendering route geometry and meeting-point marker
- `elevation-chart` - Chart.js elevation profile
- `meeting-point-picker` - Click-to-set meeting point with Nominatim reverse geocoding (name lookup only fills an empty field)
- `route-select` - Create-ride route dropdown that seeds the meeting point from the route start
- `ride-join` - Join/leave button with version awareness
- `notification-bell` - In-app notification dropdown

### Leaflet Map Layers
- Base: OpenStreetMap tiles
- Overlay: Route lines (color by difficulty)
- Markers: Feature points (icons by type), meeting points
- Popup: Route summary on click

## Notifications & Scheduling

| Notification | Trigger | Purpose |
|--------------|---------|---------|
| RideJoined | Attendee joins | Notify the ride organizer |
| RideChanged | Ride updated | Notify attendees when route/date version increments |
| RideCancelled | Ride deleted ("Cancel Ride") | Notify confirmed current-version attendees (excluding organizer) before the ride is removed |
| NewComment / CommentReply | Comment posted | Notify route owner / parent commenter |
| RideReminder | Scheduled (daily) | Notify attendees 24h before a ride |

Notifications are delivered in-app and processed through the database queue (`QUEUE_CONNECTION=database`). GPX parsing is performed synchronously by the `GpxParser` service.

## Security Considerations

- CSRF protection on all forms (Laravel default)
- SQL injection prevention via Eloquent parameter binding
- XSS prevention via Blade {{ }} escaping
- File upload validation: MIME type, size limit (10MB), GPX extension
- Rate limiting on auth routes
- Policy-based authorization on all mutating actions

## Performance Optimizations

- Eager loading relationships (with() on queries)
- Database indexes on all foreign keys and filter columns
- PostGIS spatial index for "nearby routes" queries
- Database cache for route list with filters
- Per-location/hour cache for Open-Meteo weather forecasts (batched per page)
- Pagination (15 per page) on all list views
- Queue notifications so requests stay responsive

## Deployment Requirements

- PHP 8.3+
- PostgreSQL 16+ with PostGIS 3.4+
- Composer 2+
- Node.js 18 (for Vite 5 asset compilation)
- Web server: Nginx + PHP-FPM or Apache + mod_php
- SSL certificate (Let's Encrypt recommended)

## Future Extensibility (v2+)

- Recurring rides (RRULE support)
- Route collections/folders
- GPX export with features embedded
- Strava/Komoot import
- Club/team functionality
- Ride photo uploads
- Mobile app (React Native / Flutter) sharing API