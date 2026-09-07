# Deploying the API on cPanel

Server: Hostinger KVM 4, AlmaLinux 9.8, `179.198.204.168`, WHM/cPanel 11.136.
Apache (`httpd`) serves ports 80/443. `ea-php83` is installed. No accounts exist yet.

Target: `dash.sentraxsy.com` → this API. `admin.sentraxsy.com` → the React
dashboard, which lives in a separate repository and is deployed after the API
answers.

Because cPanel owns the web server, PHP and the database, everything below goes
through WHM/cPanel. Do not install nginx, do not install PHP from remi, do not run
certbot by hand — cPanel overwrites manual configuration on update.

---

## 1. Create the account (WHM)

Log in to WHM at `https://179.198.204.168:2087` as root.

**Home → Account Functions → Create a New Account**

| Field | Value |
|---|---|
| Domain | `sentraxsy.com` |
| Username | `sentraxsy` |
| Password | generate a strong one |
| Package | `unlimited` (or any) |

This creates `/home/sentraxsy/` and the cPanel login for it.

## 2. Point PHP at 8.3

**WHM → Software → MultiPHP Manager** — set the account PHP version to
`ea-php83`. `composer.json` requires `^8.3`; the account may default to 8.2.

While there, **WHM → EasyApache 4** must have these extensions for `ea-php83`:
`mbstring`, `pdo_mysql`, `xml`, `curl`, `zip`, `bcmath`, `gd`, `intl`, `openssl`,
`tokenizer`, `fileinfo`. Missing `intl` or `bcmath` fails `composer install`.

## 3. Create the subdomain

Log in to cPanel as `sentraxsy` (`https://179.198.204.168:2083`).

**Domains → Create A Domain**

| Field | Value |
|---|---|
| Domain | `dash.sentraxsy.com` |
| Document Root | `/home/sentraxsy/dash.sentraxsy.com/public` |

Note the `/public` suffix. That directory does not exist yet — cPanel creates it
and step 5 replaces it with the repository own `public/`.

The document root must be `public/`, never the project root. Pointing at the root
serves `.env` over HTTP.

## 4. Create the database

**cPanel → MySQL Databases**

1. New database: `b2b_platform` → real name becomes `sentraxsy_b2b_platform`
2. New user: `b2buser` → real name becomes `sentraxsy_b2buser`
3. Add the user to the database with **ALL PRIVILEGES**

cPanel prefixes both names with the account name. Use the prefixed names in `.env`.

## 5. Clone the repository

**cPanel → Terminal** (or SSH as the account user).

```bash
cd ~
rm -rf dash.sentraxsy.com
git clone https://gitlab.com/FatenHussen/b2b.git dash.sentraxsy.com
cd dash.sentraxsy.com
```

Removing the directory first is safe here: it holds only the placeholder cPanel
just created. The clone recreates it with the repository real `public/`, which is
what the document root already points at.

## 6. Install dependencies

```bash
/opt/cpanel/ea-php83/root/usr/bin/php -d memory_limit=-1 \
  /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
```

The full path matters: the default `php` on the command line may be 8.2, and
`composer install` would then resolve against the wrong version.

`--no-dev` is required — `require-dev` carries Telescope, Pest and Larastan, none
of which may run in production.

The 22 modules under `app-modules/` resolve through a Composer *path* repository
with `symlink: true`, so `vendor/b2b/*` are symlinks back into the repository. The
whole tree must stay in place; `vendor/` alone is not deployable.

## 7. Configure the environment

```bash
cp .env.production.example .env
/opt/cpanel/ea-php83/root/usr/bin/php artisan key:generate
nano .env
```

Set the database lines to the cPanel-prefixed names from step 4:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=sentraxsy_b2b_platform
DB_USERNAME=sentraxsy_b2buser
DB_PASSWORD=the password from step 4
```

`key:generate` writes a fresh `APP_KEY`. Never copy the local one.

These four lines are what make cross-subdomain login work at all:

```env
SESSION_DOMAIN=.sentraxsy.com
SANCTUM_STATEFUL_DOMAINS=admin.sentraxsy.com
CORS_ALLOWED_ORIGINS=https://admin.sentraxsy.com
FRONTEND_URL=https://admin.sentraxsy.com
```

## 8. Migrate and seed

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
```

`--force` is required; Laravel refuses to migrate in production without it.

### Before seeding

`db:seed` runs `RolesPermissionsSeeder`, which seeds the permission catalogue and
is genuinely needed. It also creates a platform admin whose password is literally
`password` (`database/seeders/DatabaseSeeder.php`).

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan db:seed --force
```

Change that password before the domain is publicly reachable. Left as seeded, it
is a publicly known admin credential on a live host.

## 9. Permissions

```bash
cd ~/dash.sentraxsy.com
chmod -R 755 storage bootstrap/cache
```

Under cPanel every file already belongs to the account user, so no `chown` is
needed — and 775 is wrong here: cPanel PHP runs as the account user, not as a
separate `www-data`. Group-writable directories trip suPHP/ruid2 checks and
produce a 500.

### SELinux

AlmaLinux enforces SELinux. If writes to `storage/` fail with a 500 and the log
is empty, this is why:

```bash
sudo setsebool -P httpd_unified 1
sudo restorecon -Rv ~/dash.sentraxsy.com
```

## 10. Cache for production

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan event:cache
```

Re-run these after every `.env` edit — a cached config ignores the file entirely.

Do **not** run `view:cache`: this repository has no view layer (CLAUDE.md rule 6).

## 11. HTTPS

**cPanel → SSL/TLS Status** → select `dash.sentraxsy.com` → **Run AutoSSL**.

AutoSSL issues and renews automatically. Do not use certbot; it conflicts.

TLS is not optional: `SESSION_SECURE_COOKIE=true` means the browser withholds the
session cookie over plain HTTP, so login fails without a certificate.

Then force HTTPS — **cPanel → Domains** → toggle **Force HTTPS Redirect**.

## 12. Queue worker

`QUEUE_CONNECTION=database`. Horizon is not used (it needs Redis, and `pcntl` is
often disabled on cPanel).

systemd services are not available to a cPanel account, so the worker runs from
cron instead of `systemctl`.

**cPanel → Cron Jobs**, every minute:

```
* * * * * cd /home/sentraxsy/dash.sentraxsy.com && /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work --queue=critical,default,media,reports --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

`--stop-when-empty --max-time=55` is what makes this safe to run every minute: the
worker exits before the next one starts, so they never pile up. A plain
`queue:work` from cron would spawn a new permanent process every 60 seconds.

Queue order matters: `critical` (OTP, handover, payments) drains ahead of reports.

## 13. Scheduler

A second cron job, also every minute:

```
* * * * * cd /home/sentraxsy/dash.sentraxsy.com && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 14. Verify

```bash
curl https://dash.sentraxsy.com/api/v1/health
```

Health lives at `/api/v1/health` (`app-modules/core/routes/api.php`). The Laravel
`/up` probe does not prove the modules booted.

Then confirm nothing leaks:

```bash
curl -I https://dash.sentraxsy.com/.env
curl -I https://dash.sentraxsy.com/storage/logs/laravel.log
```

Neither may ever answer 200.

`public/.htaccess` already handles the rewrite and forwards the `Authorization`
header, which Sanctum bearer tokens depend on. Do not edit it.

---

## Redeploying

```bash
cd ~/dash.sentraxsy.com
/opt/cpanel/ea-php83/root/usr/bin/php artisan down
git pull
/opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan event:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan up
```

The cron worker picks up new code on its next run, so nothing needs restarting.

## The dashboard

`admin.sentraxsy.com` is a separate repository. Deploy it only after the health
check above answers — a dead API and a CORS misconfiguration look identical from
the browser, and chasing the wrong one costs hours.

Create it as a second cPanel domain with document root
`/home/sentraxsy/admin.sentraxsy.com`, build the React app locally
(`npm run build`) and upload `dist/`, and point its API base URL at
`https://dash.sentraxsy.com/api/v1`. With Sanctum cookies the client must send
`withCredentials: true` and request `/sanctum/csrf-cookie` before the first login.

## What does not apply here

`docs/deploy/vps-deployment.md` describes a manual nginx install and is **not**
usable on this server: it assumes Ubuntu (`apt`), installs nginx alongside Apache,
and issues certificates with certbot. All three conflict with cPanel.
