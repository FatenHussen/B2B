# The server as it actually is

Verified read-only over SSH on **2026-09-14**. Every fact below was read from the
machine on that date; nothing here is copied from a plan.

> **`cpanel-deployment.md` and `vps-deployment.md` do not describe this server.**
> The first assumes AlmaLinux with WHM/cPanel; the second assumes nginx, certbot's
> nginx plugin and a `deploy` user. None of that exists. Both files describe
> machines that were never built and are kept only as history. This file is the
> reference until it is superseded.

---

## 1. The machine

| | |
|---|---|
| Provider / host | Hostinger KVM 4, `179.198.204.168`, hostname `srv1959686` |
| OS | Ubuntu 22.04.5 LTS, timezone `Etc/UTC` |
| Disk | 194 GB, 4 % used |
| Access | `root` over SSH with the key in `~/.ssh/id_ed25519`. There is no `deploy` user; `www-data` owns the application trees and has no shell (`/usr/sbin/nologin`). |
| Shared with | **TickMart**, an unrelated Laravel project (`/var/www/apps/`), on the same Apache, MySQL, Redis and Supervisor. |

## 2. Web server: Apache, not nginx

| | |
|---|---|
| Server | Apache 2.4.52, `mpm_prefork` + **`mod_php` (php8.3)**. `php8.3-fpm` is installed and running but no VirtualHost uses it — there is no `proxy_fcgi`. PHP therefore runs inside every Apache process, which is why prefork is loaded. |
| TLS | Let's Encrypt via `certbot` (`certbot.timer` active). Certificates: `api.sentraxsy.com` (expires 2026-12-06), `tickmartsy.com` + `www` + `tickadmin` + `tickdash` (2026-12-06), `tickdash.tickmartsy.com` (2026-12-06). |
| nginx | Installed, **disabled** on 2026-09-14. It had been failing at every start since **2026-09-07 15:23 UTC** — `bind() to 0.0.0.0:80 failed` because Apache already owned the port. It stayed in `failed` state for seven days. Nothing noticed. See §8. |
| PHP CLI | `/usr/bin/php8.3` = PHP 8.3.33. `php` on `PATH` is also 8.3; the Supervisor programs name the binary explicitly anyway. |

### The four VirtualHosts

| Domain | Project | Document root |
|---|---|---|
| `api.sentraxsy.com` | **B2B API (this repository)** | `/var/www/sentrax/backend/public` |
| `tickmartsy.com`, `www.tickmartsy.com` | TickMart website | `/var/www/apps/web/dist` |
| `tickadmin.tickmartsy.com` | TickMart admin dashboard | `/var/www/apps/dashboard/dist` |
| `tickdash.tickmartsy.com` | TickMart API | `/var/www/apps/backend/public` |

`api.sentraxsy.com` had `Options Indexes` on `public/` until 2026-09-14; it is now
`Options FollowSymLinks`. `/.env` answers 404, `/storage/` 403.

### DNS names that point here without a VirtualHost

Six subdomains plus the apex resolve to this machine and have **no** VirtualHost:

`sentraxsy.com`, `www.`, `admin.`, `dash.`, `platform.`, `channel.`, `warehouse.`

Apache's default server for both `:80` and `:443` is `tickadmin.tickmartsy.com`
(`admin.conf` / `admin-le-ssl.conf` sort first). So today
`https://admin.sentraxsy.com/` serves the **TickMart admin dashboard** under the
**`tickmartsy.com` certificate** — a browser sees a certificate error and, behind
it, the wrong product. The same is true for every name in that list. The B2B
dashboards do not exist on this server yet; when they are deployed each needs its
own VirtualHost and certificate before the name is used anywhere.

## 3. The B2B application

| | |
|---|---|
| Path | `/var/www/sentrax/backend` (owner `www-data:www-data`; 203 files outside `vendor/` are owned by `root` because deploys were run as root by hand) |
| Remote | `git@gitlab.com:FatenHussen/b2b.git`, branch `main` |
| Commit on the server | **`7620777`** — both remotes (GitLab `origin`, GitHub `github`) are at the same commit. The local working tree is **seven commits ahead** (`15a9d6e`…`f9cd363`), including `0fc25cf` = BE-T13. None of that is on the server. |
| Runtime | Laravel 13.30.1, PHP 8.3.33, Composer 2.10.3 |
| Dependencies | `composer install --no-dev` — `vendor/pestphp` and `vendor/laravel/telescope` are absent (they were present until 2026-09-14). |
| Caches | config, routes and events are cached. Any `.env` edit needs `config:cache` again. |
| Migrations | 47 ran, 0 pending — matches the 47 files in the repository at `7620777`. |
| Health | `GET /api/v1/health` → `{"status":"ok","checks":{"database":"ok","cache":"ok","queue":"ok"}}` |
| Dashboards | `/horizon` → 403 (gate closed, and Horizon is not running), `/pulse` → 403 (gate closed), `/telescope` → 404. |

### `.env` on the server — the values that matter

| Key | Value | Consequence |
|---|---|---|
| `APP_ENV` | **`staging`** | This is a production domain. `OtpService` disables the OTP bypass only when `isProduction()`, so with the next line **any 6-character code logs in any phone number**. |
| `OTP_BYPASS` | **`true`** | Active, see above. |
| `OTP_CHANNEL` | `log` | OTP codes are written to `laravel.log` together with the full phone number (`LogOtpChannel`). |
| `APP_DEBUG` | `false` | Correct. |
| `LOG_LEVEL` | `debug` | `laravel.log` is 2.4 MB / 16 000 lines. Production example says `error`. |
| `APP_URL` | `https://api.sentraxsy.com` | Correct for this server; the deploy docs said `dash.sentraxsy.com`. |
| `SESSION_DOMAIN` | `.sentraxsy.com` | Correct. |
| `SESSION_SECURE_COOKIE` | `true` | Added 2026-09-14. Was unset (`null`) before. |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:3000,127.0.0.1:3000` | The real dashboards will not get a stateful session. |
| `FRONTEND_URL` | `http://localhost:3000` | Wrong for production. |
| `CORS_ALLOWED_ORIGINS` | six `http://localhost` / `127.0.0.1` origins (3000–3002) | With `supports_credentials=true`, any page on any developer's localhost can make credentialed calls to production. `admin.sentraxsy.com` is not allowed. |
| `QUEUE_CONNECTION` | `redis` | Working since 2026-09-14 (§5). |
| `CACHE_STORE` | `database` | |
| `REDIS_DB` / `REDIS_CACHE_DB` / `REDIS_PREFIX` | `1` / `3` / `sentrax_` | §6. The `REDIS_DB` and `REDIS_CACHE_DB` lines appear twice in the file; the last one wins. |
| `APP_TIMEZONE` | unset → `UTC` | `.env.example` says `Asia/Damascus`. Server timestamps are UTC too. |
| `APP_KEY` | set | Distinct from the key committed in `.env.testing`. |
| `SENTRY_*` | absent | Sentry is not installed (§8). |

## 4. Database

| | |
|---|---|
| Server | MySQL 8.0.46, `127.0.0.1:3306` only (not exposed). `root@localhost` uses `auth_socket`. |
| B2B | `sentrax_db`, user `sentrax_user@localhost` and `@127.0.0.1` — `ALL PRIVILEGES ON sentrax_db.*` and nothing else. 132 tables, ~2.7 MB. |
| TickMart | `tickmart_db`, `tickmart_user`, scoped the same way. |
| Pulse | `pulse_aggregates`, `pulse_entries`, `pulse_values` exist and are written to in-request. |
| Backups | **None.** No dump on disk, no `mysqldump` in any crontab, no off-server copy, no restore ever performed. |

## 5. Services

| Unit | State | Notes |
|---|---|---|
| `apache2` | enabled, active | Serves everything (§2). |
| `mysql` | enabled, active | |
| `redis-server` | enabled, active | Redis 6.0.16, bound to `127.0.0.1` / `::1`, no password. Installed 2026-09-14 together with the `php8.3-redis` extension (`REDIS_CLIENT=phpredis`). |
| `supervisor` | enabled, active | Four programs, below. |
| `php8.3-fpm` | enabled, active | **Unused** — no VirtualHost proxies to it. |
| `cron` | enabled, active | Only TickMart's `schedule:run` is installed. **There is no `schedule:run` for the B2B app.** Today the app defines no scheduled task (`schedule:list` is empty), so nothing is skipped yet; Horizon snapshots and any future schedule will need it. |
| `certbot.timer` | enabled, active | Renews all three certificates. |
| `nginx` | **disabled**, failed | Disabled 2026-09-14 after seven days in `failed`. |

### Supervisor programs

```
sentrax-worker   ×2   /usr/bin/php8.3 /var/www/sentrax/backend/artisan queue:work redis
                      --queue=critical,default,media,reports --sleep=3 --tries=3 --max-time=3600
                      user=www-data   log=/var/log/sentrax-worker.log
tickmart-queue   ×2   php /var/www/apps/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

The B2B workers listen to the four queues named in CLAUDE.md in priority order, so
a job on `critical` is always taken before anything on `reports`. They are plain
`queue:work` processes — **Horizon is not running** (`horizon:status` → inactive).

**Queue-name mismatch, unresolved.** The code does not use the four CLAUDE.md
queues. Every `ShouldQueue` job in `app-modules/` dispatches to either `default`
(`ImportCatalogJob`, `ApplyPriceListScheduleJob`) or **`exports`**
(`ExportCatalogJob`, `ExportAuditLogJob`), and `config/horizon.php` defines
`default`, `notifications`, `exports`, `provisioning`. No worker on this server
listens to `exports`: a catalog or audit-log export queued today sits in Redis
until something is changed. No job dispatches to `critical`, `media` or `reports`.
OTP is sent synchronously and never enters a queue.

## 6. Redis: one instance, two projects

There is a single Redis on the machine. TickMart does not use it today
(`CACHE_STORE`, `QUEUE_CONNECTION` and `SESSION_DRIVER` are all `database`
there), but a Laravel app that switches to Redis without setting anything lands
on Laravel's defaults: database `0` for queue/default, database `1` for cache,
prefix `<app>_database_`. To keep B2B out of that path it was moved off the
defaults on 2026-09-14:

| | B2B | Laravel default (what TickMart would get) |
|---|---|---|
| default / queue connection | DB **1** | DB 0 |
| cache connection | DB **3** | DB 1 |
| key prefix | `sentrax_` | `laravel_database_` |

Both the database index and the prefix matter: the index keeps `KEYS`/`SCAN` and
`FLUSHDB` apart, the prefix keeps keys distinguishable if they ever do share a
database. One caveat to know about: B2B's queue sits on **DB 1, which is
Laravel's default *cache* database**. `php artisan cache:clear` on a Redis cache
store runs `FLUSHDB`, not a prefix-scoped delete — so if TickMart ever adopts a
Redis cache at the defaults, its `cache:clear` empties B2B's queue. Either B2B
moves its queue to an index no default touches (2 or higher), or TickMart pins
its own indexes explicitly the day it switches. Until one of those happens the
separation holds only because TickMart is not on Redis.

## 7. How code reaches the server

By hand, as `root`: `git pull`, `composer install --no-dev`, `migrate --force`,
the three cache commands. That leaves root-owned files in the `www-data` tree
(203 today) and puts the API behind `artisan down` for the duration — a 503, not
zero downtime. `scripts/deploy.sh` in the repository (untracked at the time of
writing) adds the three pre-flight checks — database exists, dump written with a
non-zero size and a `Dump completed` trailer, app boots after `--no-dev` — and
hands ownership back to `www-data`. It has not run on the server yet.

## 8. What is missing

In the order it should be fixed.

1. **No monitoring at all** (BE-F08). No Sentry, no alerting, nothing watches a
   process, a port or an error rate. nginx sat in `failed` for **seven days**
   (2026-09-07 → 2026-09-14) without one alert; it was found by a person running
   `systemctl status` by chance. That is BE-F08 word for word: monitoring is not
   a luxury, it is the only thing that stops a silent failure from living a week.
   The queue was `down` for the same week for the same reason.
2. **`APP_ENV=staging` on a production domain**, with `OTP_BYPASS=true`. Until
   `APP_ENV=production` (or the bypass is set to `false`), login is open.
3. **CORS, Sanctum stateful domains and `FRONTEND_URL` still point at localhost.**
   The real dashboards cannot log in, and developer machines can.
4. **No backups** (BE-F10): no dump, no schedule, no off-server copy, no restore
   test. `scripts/deploy.sh` produces a dump per deploy; that is not a backup
   policy.
5. **No Horizon** (BE-F07): workers are plain `queue:work`; there is no queue
   dashboard, no backlog or latency alert, and the `exports` queue is not served.
6. **Six subdomains plus the apex without a VirtualHost**, all currently answered
   by TickMart's admin dashboard with a mismatched certificate (§2).
7. **`mod_php` under prefork instead of PHP-FPM** — every Apache process carries
   a PHP interpreter, and the two projects share one PHP configuration. FPM is
   already running and unused.
8. **The server is seven commits behind the working tree** (BE-T13 included),
   and neither remote has them either. Push, then deploy.
9. **Logs carry full phone numbers and OTP codes** (`LogOtpChannel`); three such
   lines exist in `laravel.log` today. BE-F08 requires masking.
10. **No `schedule:run` cron for the B2B app**, `LOG_LEVEL=debug`, `APP_TIMEZONE`
    unset, duplicated `REDIS_*` lines in `.env`, `php8.3-fpm` running for nothing.

## 9. Next step

**BE-T13 — channel status transitions.** It was the next ticket before the work
was interrupted by the domain question above. Its endpoint
(`POST /admin/channels/{id}/transition`, EP-AD-054) is implemented locally at
`0fc25cf` with the Postman collection regenerated at `37c1326`; it is not pushed
and not deployed, so neither of its acceptance criteria has been exercised on this
server.
