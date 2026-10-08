# Architecture Document

## Overview
Cycling Routes Web Application - A Laravel 11+ application for submitting, discovering, and organizing group rides on cycling routes via GPX upload.

## Tech Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| Framework | Laravel | 11.x | Core framework, routing, ORM, auth |
| Database | PostgreSQL | 15+ | Primary data store with PostGIS |
| Extension | PostGIS | 3.4+ | Geospatial queries, geometry storage |
| Frontend | Blade + Alpine.js | 3.x | Server-rendered with reactive components |
| Maps | Leaflet.js | 1.9+ | Interactive maps with OSM tiles |
| Auth | Laravel Breeze | 1.x | Authentication scaffolding |
| Queue | Redis + Horizon | 7.x / 5.x | Background jobs, GPX processing |
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
│                      Redis + Horizon                        │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐              │
│  │ GPX Jobs   │ │Notifications│ │  Reminders │              │
│  └────────────┘ └────────────┘ └────────────┘              │
└─────────────────────────────────────────────────────────────┘
```

## Database Schema

### Core Tables

**users** - Authentication and profiles
- id, name, email, password, avatar_url, timestamps

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
Upload → Validation → Parse (Job) → Compute Stats → Store Geometry + JSONB → Notify User
```
- Async via queued job to avoid request timeout
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

## API Routes Structure

| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | /routes | RouteController@index | routes.index |
| GET | /routes/map | RouteController@map | routes.map |
| GET | /routes/create | RouteController@create | routes.create |
| POST | /routes | RouteController@store | routes.store |
| GET | /routes/{route} | RouteController@show | routes.show |
| GET | /routes/{route}/edit | RouteController@edit | routes.edit |
| PUT | /routes/{route} | RouteController@update | routes.update |
| DELETE | /routes/{route} | RouteController@destroy | routes.destroy |
| POST | /routes/{route}/rate | RatingController@store | routes.rate |
| POST | /routes/{route}/comments | CommentController@store | routes.comments.store |
| POST | /routes/{route}/features | FeatureController@store | routes.features.store |
| DELETE | /features/{feature} | FeatureController@destroy | features.destroy |
| GET | /rides | RideController@index | rides.index |
| POST | /rides | RideController@store | rides.store |
| GET | /rides/{ride} | RideController@show | rides.show |
| POST | /rides/{ride}/join | RideAttendeeController@join | rides.join |
| DELETE | /rides/{ride}/leave | RideAttendeeController@leave | rides.leave |
| PUT | /rides/{ride} | RideController@update | rides.update |

## Frontend Components

### Blade Layouts
- `layouts.app` - Main layout with navigation, flash messages
- `layouts.map` - Full-screen map layout for /routes/map

### Alpine.js Components
- `route-filters` - Filter sidebar (distance, elevation, difficulty, features)
- `route-map` - Leaflet map with route layers, feature markers
- `elevation-chart` - Chart.js elevation profile
- `ride-join` - Join/leave button with version awareness
- `notification-bell` - In-app notification dropdown

### Leaflet Map Layers
- Base: OpenStreetMap tiles
- Overlay: Route lines (color by difficulty)
- Markers: Feature points (icons by type), meeting points
- Popup: Route summary on click

## Background Jobs

| Job | Trigger | Purpose |
|-----|---------|---------|
| ProcessGpxUpload | Route stored | Parse GPX, compute stats, generate geometry |
| NotifyRideChanged | Ride updated | Email/in-app to attendees when version increments |
| RideReminder | Scheduled (daily) | Notify attendees 24h before ride |
| GenerateRoutePreview | Route created | Create static map image for social sharing |

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
- Redis caching for route list with filters (TTL 5 min)
- Pagination (15 per page) on all list views
- Queue long-running tasks (GPX parsing, notifications)

## Deployment Requirements

- PHP 8.2+
- PostgreSQL 15+ with PostGIS 3.4+
- Redis 7+
- Composer 2+
- Node.js 20+ (for Vite asset compilation)
- Web server: Nginx + PHP-FPM or Apache + mod_php
- SSL certificate (Let's Encrypt recommended)

## Future Extensibility (v2+)

- Recurring rides (RRULE support)
- Route collections/folders
- GPX export with features embedded
- Strava/Komoot import
- Club/team functionality
- Ride photo uploads
- Weather integration for ride dates
- Mobile app (React Native / Flutter) sharing API