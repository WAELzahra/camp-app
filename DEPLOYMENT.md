# Deployment Guide — Camp App (Laravel + Railway)

This document records every server-level setting that must be in place before
going to production. **None of these can be set in Laravel code** — they must
be applied to the host, the PHP runtime, and the reverse proxy.

---

## 1. Required Environment Variables

Set these in Railway → Project Settings → Variables (never commit real values):

```
APP_ENV=production
APP_DEBUG=false          # CRITICAL — true exposes stack traces to clients
APP_KEY=base64:...       # php artisan key:generate --show
APP_URL=https://api.tunisiacamp.tn
FRONTEND_URL=https://app.tunisiacamp.tn

LOG_CHANNEL=stack
LOG_LEVEL=error          # Never "debug" in production

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=cookie
SESSION_LIFETIME=720     # 12 hours
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=app.tunisiacamp.tn

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
```

---

## 2. PHP Configuration (`php.ini`)

Upload size limits must be set at the PHP runtime level.  
On Railway, create a `php.ini` file in the project root (or use a custom
Dockerfile) with:

```ini
; Maximum size of a single uploaded file — must match the 5 MB application limit
upload_max_filesize = 5M

; Maximum total POST body size — set slightly above upload_max_filesize
post_max_size = 6M

; Memory limit for the PHP process
memory_limit = 256M

; Maximum execution time (seconds)
max_execution_time = 60

; Expose PHP version in headers — disable in production
expose_php = Off
```

> **Railway note:** Place `php.ini` at the repo root and add to your
> `Dockerfile`/`nixpacks.toml`:
> ```
> COPY php.ini /usr/local/etc/php/conf.d/app.ini
> ```

---

## 3. Nginx Configuration

If you run Nginx in front of PHP-FPM (or use Railway's built-in proxy),
add a `client_max_body_size` directive to match the PHP limit:

```nginx
# /etc/nginx/conf.d/default.conf  (or equivalent)

server {
    listen 80;
    server_name api.tunisiacamp.tn;

    # Reject payloads larger than 6 MB at the proxy layer
    # before they reach PHP — saves memory and prevents DoS.
    client_max_body_size 6M;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

> **Railway note:** Railway's internal reverse proxy applies its own limit.
> Open a support ticket or use a custom proxy to set `client_max_body_size`.
> Until then the 5 MB validation rule in Laravel is the enforced boundary.

---

## 4. First-Deploy Checklist

Run these commands in order after every deploy:

```bash
# Clear all caches so stale config is not served
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Re-cache for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (includes security indexes + UUID migration)
php artisan migrate --force

# Generate application key if not already set
php artisan key:generate --force   # Only on first deploy

# Ensure storage is linked
php artisan storage:link
```

---

## 5. Database Migration Notes

### Security indexes (2026_05_15_000001_...)
Adds composite indexes on `(user_id, status)` and `status` across all
reservation tables, plus `expires_at` on `password_reset_tokens`.
**Required before go-live** — without them, reservation list queries do
full table scans.

### UUID migration (2026_05_15_000002_...)
Adds a `uuid` column to `users` and back-fills all existing rows.
**Must run before any API request reaches the `/api/user` endpoint** —
`UserResource` expects `uuid` to be non-null.

---

## 6. Blue-Green Deployment on Railway

Railway supports zero-downtime deploys via deployment slots:

1. **Build** the new image on Railway (automatic on `git push`).
2. **Verify** the health check (`GET /api/health` → 200) passes on the new
   instance before traffic is routed to it.
3. **Swap** traffic: Railway's deployment pipeline swaps automatically once
   the health check passes.
4. **Rollback**: In Railway Dashboard → Deployments → click the previous
   successful deploy → **Redeploy**. Traffic rolls back in < 30 seconds.

### Health-check endpoint (add to routes/api.php if not present):
```php
Route::get('/health', function () {
    try {
        \DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'db' => 'connected'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'db' => 'unreachable'], 503);
    }
});
```

---

## 7. Security Headers (Applied by Laravel Middleware)

All responses include the following headers via `SecurityHeaders` middleware:

| Header | Value |
|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=()` |
| `Content-Security-Policy` | Dynamic — see `SecurityHeaders::buildCsp()` |

These require **no server-level configuration** but depend on `APP_URL`
and `FRONTEND_URL` env vars being set correctly in production.

---

## 8. Log Rotation

Security logs are written to `storage/logs/security.log` (daily rotation,
90-day retention). On Railway, forward logs to an external aggregator
(Papertrail, Logtail, Datadog) by setting:

```
LOG_CHANNEL=stack
LOG_PAPERTRAIL_HANDLER=...
PAPERTRAIL_URL=logs.papertrailapp.com
PAPERTRAIL_PORT=xxxxx
```

---

*Last updated: 2026-05-29*
