# Deploying the API to the VPS

> **Not applicable to the current server.**
> `179.198.204.168` runs AlmaLinux 9.8 with WHM/cPanel and Apache. This guide
> assumes Ubuntu (`apt`), installs nginx beside Apache and issues certificates
> with certbot — all three break a cPanel host.
> Follow **[cpanel-deployment.md](cpanel-deployment.md)** instead.
> Kept only for a future non-cPanel server.

Target: `dash.sentraxsy.com` → Laravel API. The React dashboard lives in a separate
repository and is deployed separately to `admin.sentraxsy.com`.

Server: Hostinger KVM 4, `179.198.204.168`. DNS A records already point here.

Deploy the API first. Do not deploy the dashboard until
`https://dash.sentraxsy.com/api/v1/health` answers, otherwise a dead API looks
exactly like a CORS bug.

---

## 1. Connect and create a deploy user

Never run the app as root.

```bash
ssh root@179.198.204.168

adduser deploy
usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
```

Reconnect as `deploy` and stay there for everything below.

## 2. Install the runtime

```bash
sudo apt update && sudo apt upgrade -y
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update

sudo apt install -y php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl \
  nginx mysql-server git unzip

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Confirm `php -v` reports 8.3 — `composer.json` requires `^8.3`.

## 3. Create the database

MySQL on the VPS uses the default port **3306**, not the 3308 used locally.

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE b2b_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'b2b_user'@'127.0.0.1' IDENTIFIED BY 'PUT_A_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON b2b_platform.* TO 'b2b_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Clone the code

```bash
sudo mkdir -p /var/www && sudo chown deploy:deploy /var/www
cd /var/www
git clone https://gitlab.com/FatenHussen/b2b.git b2b-api
cd b2b-api
```

## 5. Install dependencies

`--no-dev` is required: `require-dev` carries Telescope, Pest and Larastan, which
must never run in production.

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

The 22 modules under `app-modules/` resolve through a Composer *path* repository
with `symlink: true`. `vendor/b2b/*` becomes symlinks into `app-modules/`, so the
whole repository must stay on disk — you cannot ship `vendor/` alone.

## 6. Configure the environment

```bash
cp .env.production.example .env
php artisan key:generate
nano .env
```

Set `DB_PASSWORD` to the password from step 3 and `DB_PORT=3306`.

`key:generate` writes a fresh `APP_KEY`. Never reuse the local one.

These four lines are what make cross-subdomain login work:

```env
SESSION_DOMAIN=.sentraxsy.com
SANCTUM_STATEFUL_DOMAINS=admin.sentraxsy.com
CORS_ALLOWED_ORIGINS=https://admin.sentraxsy.com
FRONTEND_URL=https://admin.sentraxsy.com
```

## 7. Migrate

```bash
php artisan migrate --force
```

`--force` is required; without it Laravel refuses to migrate in production.

### Seeding — read before running

`db:seed` calls `RolesPermissionsSeeder` (needed: it seeds the permission
catalogue) but also creates demo data and a platform admin with the password
literally `password`.

Seed, then immediately change that password:

```bash
php artisan db:seed --force
```

Log in as `admin@platform.sy` and change the password before the host is public.
Leaving it is a publicly known admin credential on a live server.

## 8. Permissions

```bash
sudo chown -R deploy:www-data /var/www/b2b-api
sudo find /var/www/b2b-api -type f -exec chmod 644 {} \;
sudo find /var/www/b2b-api -type d -exec chmod 755 {} \;
sudo chmod -R 775 storage bootstrap/cache
```

## 9. Cache for production

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

Re-run these after every `.env` change — a cached config ignores the file.

Do **not** run `view:cache`: this repository has no view layer (CLAUDE.md rule 6).

## 10. nginx

`/etc/nginx/sites-available/dash.sentraxsy.com`:

```nginx
server {
    listen 80;
    server_name dash.sentraxsy.com;
    root /var/www/b2b-api/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 20M;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/dash.sentraxsy.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

The document root is `public/`, never the project root — pointing at the root
exposes `.env`.

## 11. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d dash.sentraxsy.com
```

TLS is not optional here: `SESSION_SECURE_COOKIE=true` means the browser will not
send the session cookie over plain HTTP, so login fails without a certificate.

## 12. Queue worker

`QUEUE_CONNECTION=database`. Horizon is not used (it needs Redis).

`/etc/systemd/system/b2b-queue.service`:

```ini
[Unit]
Description=B2B queue worker
After=network.target mysql.service

[Service]
User=deploy
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /var/www/b2b-api/artisan queue:work --queue=critical,default,media,reports --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now b2b-queue
```

Queue order matters: `critical` (OTP, handover, payments) is listed first so it
drains ahead of reports.

## 13. Scheduler

```bash
crontab -e
```

```cron
* * * * * cd /var/www/b2b-api && php artisan schedule:run >> /dev/null 2>&1
```

## 14. Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Do not open 3306. The database is reached over `127.0.0.1` only.

## 15. Verify

```bash
curl https://dash.sentraxsy.com/api/v1/health
```

Health lives at `/api/v1/health` (`app-modules/core/routes/api.php`). `/up` is
Laravel's own probe and does not prove the modules booted.

Then confirm the deployment is not leaking:

```bash
curl -I https://dash.sentraxsy.com/.env     # must be 403 or 404, never 200
```

---

## If you use Nginx Proxy Manager instead

Skip steps 10 and 11 and let NPM terminate TLS. Forward to the app on the host,
not to the public IP — sending traffic to `179.198.204.168` leaves the server and
comes back.

| Domain | Forward host | Port |
|---|---|---|
| `dash.sentraxsy.com` | `127.0.0.1` | 8000 |
| `admin.sentraxsy.com` | `127.0.0.1` | 3000 |

If NPM runs inside a container, `127.0.0.1` is the container itself — use
`172.17.0.1` instead.

Add to the proxy host's Advanced tab so Laravel builds correct URLs and does not
treat every visitor as the proxy:

```nginx
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header Host $host;
```

A proxied deployment also needs `TrustProxies` configured, or Laravel will
generate `http://` URLs behind an `https://` proxy and the secure session cookie
will never be set.

## Redeploying

```bash
cd /var/www/b2b-api
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan event:cache
sudo systemctl restart b2b-queue
php artisan up
```

Restarting the worker is not optional — `queue:work` holds the old code in memory
until it does.
