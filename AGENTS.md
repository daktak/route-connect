# Opencode Agents Configuration

This file defines the agents and skills available for this project.

## Project-Specific Agents

### laravel-expert
**Type**: explore
**Description**: Laravel 11+ specialist for controllers, models, migrations, policies, queues, and testing
**When to use**: Any Laravel-specific task - routing, Eloquent, validation, auth, jobs, Horizon, Pint, Pest

### postgres-gis
**Type**: explore
**Description**: PostgreSQL + PostGIS expert for schema design, spatial queries, indexes, performance
**When to use**: Migrations, geospatial queries, geometry types, GIST indexes, JSONB operations

### frontend-alpine
**Type**: explore
**Description**: Alpine.js + Blade + Leaflet.js specialist for interactive maps, components, state management
**When to use**: Map components, filter UIs, elevation charts, reactive forms, Leaflet integration

### gpx-parser
**Type**: general
**Description**: GPX parsing, geospatial calculations (distance, elevation gain), coordinate transformation
**When to use**: GPX upload processing, route geometry generation, elevation profiles

## Available Skills

### customize-opencode
**Built-in**: Configures opencode itself (agents, skills, permissions, MCP servers)
**Location**: <built-in>
**Use when**: Editing opencode.json, .opencode/, ~/.config/opencode/

## Agent Invocation Patterns

### For Laravel Tasks
```
Task: "Create RouteController with resource actions and policy authorization"
Agent: laravel-expert
```

### For Database Tasks
```
Task: "Add spatial index on routes.geometry and create nearby routes query"
Agent: postgres-gis
```

### For Frontend Tasks
```
Task: "Build Alpine.js component for route filter sidebar with URL sync"
Agent: frontend-alpine
```

### For GPX Tasks
```
Task: "Implement GPX parser service that extracts track points and computes elevation gain"
Agent: gpx-parser
```

## Common Workflows

### New Feature Implementation
1. `laravel-expert` - Create migration, model, controller, policy
2. `postgres-gis` - Review migration for spatial correctness
3. `frontend-alpine` - Build Blade views + Alpine components
4. `laravel-expert` - Write Pest tests

### Bug Fix
1. `laravel-expert` or `postgres-gis` - Diagnose from logs/error
2. Appropriate agent - Implement fix
3. `laravel-expert` - Add regression test

### Performance Issue
1. `postgres-gis` - Analyze query plans, suggest indexes
2. `laravel-expert` - Optimize Eloquent queries, add caching

## Testing Standards

- **Framework**: Pest PHP (preferred) or PHPUnit
- **Browser**: Laravel Dusk for E2E (map interactions)
- **Static Analysis**: Larastan/PHPStan Level 5+
- **Code Style**: Laravel Pint (PSR-12 + Laravel conventions)

Run tests: `./vendor/bin/pest`
Run static analysis: `./vendor/bin/phpstan analyse`
Format code: `./vendor/bin/pint`

## Git Workflow

- Branch naming: `feature/route-gpx-upload`, `fix/ride-version-clearing`
- Conventional commits: `feat:`, `fix:`, `refactor:`, `test:`, `chore:`
- PR template: `.github/pull_request_template.md`