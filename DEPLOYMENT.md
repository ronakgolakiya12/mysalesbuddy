# mySalesBuddy — Deployment Runbook

This document describes how to provision and deploy mySalesBuddy to a
production-class environment. The target deployment is a single Linux host
running PHP-FPM 8.3, with Postgres 16 and Redis 7 as managed services and S3
for blob storage.

---

## 1. Prerequisites

* Ubuntu 24.04 LTS (or equivalent) with systemd
* PHP 8.3 + extensions: `pdo_pgsql`, `pgsql`, `redis`, `intl`, `zip`, `gd`, `bcmath`, `pcntl`, `posix`
* Composer 2.7+
* Node.js 24 LTS + npm 10+
* PostgreSQL 16 (with `pg_trgm` extension)
* Redis 7
* Nginx 1.24+
* TLS certificate (Let's Encrypt or commercial)
* S3-compatible object storage
* Soketi or Pusher account for broadcasting

---

## 2. First-time Deployment

### 2.1 Clone & install

```bash
git clone <repo-url> /var/www/mysalesbuddy
cd /var/www/mysalesbuddy
composer install --no-dev --optimize-autoloader --prefer-dist
npm ci
npm run build
```

### 2.2 Environment

```bash
cp .env.example .env
php artisan key:generate
# edit .env and fill in every required value from HANDOVER.md section 5
```

### 2.3 Database

```bash
sudo -u postgres psql -c "CREATE USER mysalesbuddy WITH PASSWORD '…';"
sudo -u postgres psql -c "CREATE DATABASE mysalesbuddy OWNER mysalesbuddy;"
sudo -u postgres psql -d mysalesbuddy -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"

php artisan migrate --force
```

### 2.4 Storage

```bash
php artisan storage:link
# verify S3 credentials
php artisan tinker --execute='dump(Storage::disk("s3")->files());'
```

### 2.5 Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 2.6 Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage -type d -exec chmod 775 {} \;
sudo find storage -type f -exec chmod 664 {} \;
```

### 2.7 Process supervision

Horizon must run continuously. Create
`/etc/systemd/system/mysalesbuddy-horizon.service`:

```ini
[Unit]
Description=mySalesBuddy Horizon
After=network.target

[Service]
Type=simple
User=www-data
Restart=on-failure
RestartSec=5
WorkingDirectory=/var/www/mysalesbuddy
ExecStart=/usr/bin/php /var/www/mysalesbuddy/artisan horizon
StandardOutput=append:/var/log/mysalesbuddy/horizon.log
StandardError=append:/var/log/mysalesbuddy/horizon.err

[Install]
WantedBy=multi-user.target
```

Schedule the Laravel scheduler via cron:

```cron
* * * * * cd /var/www/mysalesbuddy && php artisan schedule:run >> /dev/null 2>&1
```

### 2.8 Nginx

Sample server block (TLS termination + PHP-FPM):

```nginx
server {
    listen 443 ssl http2;
    server_name app.example.com;
    root /var/www/mysalesbuddy/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;

    client_max_body_size 4M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 60s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 3. Routine Deploys

```bash
cd /var/www/mysalesbuddy
git pull --ff-only
composer install --no-dev --optimize-autoloader --prefer-dist
npm ci
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

sudo systemctl restart php8.3-fpm
sudo systemctl restart mysalesbuddy-horizon
```

Horizon's `terminate` command is preferable to a hard restart in zero-downtime
deploys: `php artisan horizon:terminate` lets it finish in-flight jobs.

---

## 4. Smoke Tests After Deploy

1. `curl -fsS https://app.example.com/up` → 200
2. Log in as a test user via the SPA → confirm dashboard loads
3. Trigger a manual Recall.ai webhook with `webhook-*` headers → confirm 200
   and a job runs in Horizon
4. Inspect `storage/logs/security-YYYY-MM-DD.log` after a deliberate bad login
5. Verify CSP via `curl -I https://app.example.com/api/meetings` returns the
   `Content-Security-Policy` header

---

## 5. Rollback

```bash
cd /var/www/mysalesbuddy
git reset --hard <previous-sha>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate:rollback --step=<n>  # only if the deploy ran a destructive migration
sudo systemctl restart php8.3-fpm mysalesbuddy-horizon
```

Database migrations should be additive whenever possible. Destructive
migrations (drop column, rename table) must be paired with a documented
two-deploy rollout: deploy 1 adds the new column + dual-writes; deploy 2
removes the old column once consumer code is updated.

---

## 6. Monitoring Checklist

* HTTP 5xx rate < 0.1% (Sentry or equivalent)
* Horizon `pending` queue depth < 100 sustained
* PostgreSQL `pg_stat_activity` long-running queries (> 5s) = 0
* Redis memory below 80% maxmemory
* Storage usage on `storage/logs` < 70% disk
* Daily `composer audit` job (`dependency-audit` in CI) passing on `main`

---

## 7. Disaster Recovery

* Database: `pg_dump` nightly to S3, with point-in-time recovery via WAL
  archival for the last 7 days.
* Storage: S3 versioning + cross-region replication on the avatars and PDFs
  buckets.
* Config: `.env` stored in the org's secrets manager (1Password / Vault).
  Never commit `.env`.
