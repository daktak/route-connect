# Deployment Guide

Route Connect is a standard Laravel application, so it can be hosted with
**nginx + PHP-FPM** (with PostgreSQL/PostGIS). This guide covers a dedicated
server block and, for operators with an existing `nginx.conf`, hosting the app
under a sub-path such as `https://example.com/RouteConnect`.

## Supported topology

```
Browser ──► nginx ──► PHP-FPM (8.3) ──► PostgreSQL 16 + PostGIS
                 │
                 └── static assets from public/build
cron ──► php artisan schedule:run   (ride reminders)
supervisor (optional) ──► php artisan queue:work
```

## 1. Requirements

- PHP **8.3** FPM with extensions: `pdo_pgsql`, `pgsql`, `simplexml`, `mbstring`,
  `openssl`, `ctype`, `tokenizer`, `xml`, `fileinfo`, `curl`.
- PostgreSQL **16+** with the **PostGIS** extension.
- Composer 2.
- Node.js **18** (build only — Vite is pinned to 5.x).
- nginx.

## 2. Build the application

```sh
cd /var/www/route-connect

composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

`public/build` is gitignored, so assets **must** be built on the server.

## 3. Configure `.env`

Copy `.env.example` to `.env` and set production values:

```env
APP_NAME="Route Connect"
APP_ENV=production
APP_KEY=            # php artisan key:generate
APP_DEBUG=false
APP_URL=https://example.com          # include the sub-path, see section 9

ADMIN_EMAILS=you@example.com

LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=routes
DB_USERNAME=routes_app
DB_PASSWORD=change-me

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database
```

Then:

```sh
php artisan key:generate
```

> Never commit `.env` or set `APP_DEBUG=true` in production.

## 4. Database & PostGIS

PostGIS must be created by a superuser **before** migrating (the migration also
attempts `CREATE EXTENSION`, which requires elevated privileges):

```sh
sudo -u postgres psql -d routes -c "CREATE EXTENSION IF NOT EXISTS postgis;"
sudo -u postgres psql -d routes -c 'CREATE EXTENSION IF NOT EXISTS "uuid-ossp";'
sudo -u postgres psql -d routes -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"
```

Then apply migrations:

```sh
php artisan migrate --force
```

## 5. File permissions

PHP-FPM (typically `www-data`) must own the writable directories:

```sh
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
sudo chmod -R ug+rwX storage bootstrap/cache
```

GPX uploads are stored on the private `local` disk
(`storage/app/private/gpx`) and streamed by the application, so **no
`php artisan storage:link` is required**.

## 6. Cache configuration and routes

```sh
php artisan optimize        # config + route + view + event cache
```

Re-run `php artisan optimize` after every deploy that changes `.env`, routes,
or config. To clear: `php artisan optimize:clear`.

## 7. Scheduler and queue

The ride reminder command (`rides:send-reminders`) is scheduled daily at 08:00
(`routes/console.php`) and requires cron:

```cron
* * * * * cd /var/www/route-connect && php artisan schedule:run >> /dev/null 2>&1
```

Current notifications use the database channel **synchronously**, so a queue
worker is not required. If you later queue jobs, run a worker under Supervisor:

```ini
[program:route-connect-queue]
command=php /var/www/route-connect/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
```

## 8. Web server

### Option A — dedicated server block (simplest)

```nginx
server {
    listen 80;
    server_name routes.example.com;
    root /var/www/route-connect/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Add a `listen 443 ssl` variant for TLS (see section 10). The application's
health endpoint is available at `/up`.

### Option B — sub-path inside an existing `nginx.conf` (`/RouteConnect`)

Use this when you already have a `server {}` block for a site and want to expose
Route Connect at `https://example.com/RouteConnect`.

1. **Expose the `public` directory inside the existing web root** via a symlink.
   This keeps only the public assets reachable and lets the shared PHP handler
   resolve the real file:

   ```sh
   # web root from the existing server block, e.g. root /var/www/html;
   sudo ln -s /var/www/route-connect/public /var/www/html/RouteConnect
   ```

2. **Add one location to the existing `server {}` block** (no new server block
   needed):

   ```nginx
   location /RouteConnect {
       try_files $uri $uri/ /RouteConnect/index.php?$query_string;
   }
   ```

   The server must already have a PHP handler such as the common Debian/Ubuntu
   snippet:

   ```nginx
   location ~ \.php$ {
       include snippets/fastcgi-php.conf;   # sets SCRIPT_FILENAME
       fastcgi_pass unix:/run/php/php8.3-fpm.sock;
   }
   ```

   How it fits together:
   - Requests for `…/RouteConnect/...` match the longer `/RouteConnect` prefix,
     so static assets are served from the symlinked `public` directory.
   - Because the prefix is a **plain prefix (not `^~`)**, the existing
     `~ \.php$` regex location still handles PHP files.
   - `$document_root$fastcgi_script_name` (or `$realpath_root…`) resolves
     `…/public/index.php` through the symlink, so no extra PHP config is needed.

3. **Reload nginx:**

   ```sh
   sudo nginx -t && sudo systemctl reload nginx
   ```

> Do **not** use `location ^~ /RouteConnect` and do **not** add an `alias`
> combined with `try_files` for this pattern — the `^~` flag would bypass the
> shared PHP handler, and `alias` + `try_files` mis-resolves paths.

## 9. Sub-path URL configuration (`.env`)

When hosted under a sub-path, point Laravel at the full public URL so both web
requests and CLI-generated links (notifications, scheduled commands) include the
prefix:

```env
APP_URL=https://example.com/RouteConnect
ASSET_URL=https://example.com/RouteConnect
APP_FORCE_ROOT_URL=true
```

- `APP_URL` — the canonical base URL, including the sub-path.
- `ASSET_URL` — makes `asset()` / `@vite` emit `/RouteConnect/build/...`.
- `APP_FORCE_ROOT_URL=true` — forces URL generation to use `APP_URL`. This is
  required when nginx strips the prefix before forwarding to PHP, and keeps
  CLI/scheduled URLs consistent. It is implemented in
  `app/Providers/AppServiceProvider.php`.

After changing these values run `php artisan optimize` (or `optimize:clear`
first if you are debugging).

## 10. TLS and reverse proxies

- Terminate TLS in nginx (or a load balancer). Keep `APP_URL` on `https://` to
  avoid mixed-content asset warnings.
- If TLS terminates at an upstream proxy and nginx forwards plain HTTP, either
  add `fastcgi_param HTTPS on;` to the PHP location, or register trusted proxies
  in `bootstrap/app.php`:

  ```php
  ->withMiddleware(function (Middleware $middleware): void {
      $middleware->trustProxies(at: '*');
  })
  ```

- Set `SESSION_SECURE_COOKIE=true` once the site is served over HTTPS.

## 11. Verification checklist

1. `curl -I https://example.com/RouteConnect/up` → `200`.
2. Load `/RouteConnect` — assets resolve to `/RouteConnect/build/...` (no 404s,
   no mixed content) and the Group Rides page renders.
3. Log in, then create/join a ride and confirm pages work through the prefix.
4. Confirm a private GPX download works
   (`/RouteConnect/routes/{id}/download`).
5. `php artisan schedule:list` shows `rides:send-reminders`.
6. `tail storage/logs/laravel.log` is free of errors.

## 12. Redeploying

```sh
cd /var/www/route-connect
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
sudo systemctl reload php8.3-fpm
```

Enable a short maintenance window for schema changes with
`php artisan down` / `php artisan up` if needed.
