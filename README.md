# B2B Platform

Laravel 13 API for Syrian B2B distribution. Five clients consume `/api/v1`: retailer app, rep app, channel dashboard, warehouse web, platform admin.

## Install the API

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

```bash
curl http://127.0.0.1:8000/api/v1/health
```

Requires PHP 8.3 and MySQL 8 on port 3308 (`.env.example`, `phpunit.xml` and CI agree).

Local platform login after seed: `admin@platform.sy` / `password`.  
OTP (dev): switched off with `OTP_BYPASS=true` — any code, or none, verifies; no cooldown, no rate limit;
ignored under `APP_ENV=production`. Set it back to `false` at release. With it off, codes go to
`storage/logs/laravel.log` (`OTP_CHANNEL=log`).

## Documentation

**[docs/README.md](docs/README.md)** is the index. The short version:

| Question | Where |
|---|---|
| What is the contract for an endpoint? | [docs/api/catalog/](docs/api/catalog/) — binding, one `ep()` per route |
| What is live, per client? | [docs/status/00-overview.md](docs/status/00-overview.md) — generated from the catalog and `route:list` |
| What is left to build, in what order? | [docs/plan/platform-admin.md](docs/plan/platform-admin.md), [docs/plan/apps.md](docs/plan/apps.md) |
| What does a frontend developer read? | [docs/DocsLast/](docs/DocsLast/) — one spec and one live JSON per client |
| The rules every PR is judged against | [CLAUDE.md](CLAUDE.md) |

Regenerate the generated pages after any route change:

```bash
php docs/api/generate.php && php docs/status/generate.php
```

## Docker is not used

`docker-compose.yml` is a leftover from the initial commit and **is not how this project runs**.
Its values contradict `.env`: it creates database `b2b` (the app expects `b2b_platform`) with password
`root` (the app uses an empty password), and it defines Redis, Horizon, MinIO and Mailpit services that
the current configuration does not use — `CACHE_STORE=database`, `QUEUE_CONNECTION=database`,
`FILESYSTEM_DISK=local`, `MAIL_MAILER=log`.

`docker compose up -d` also binds host port **3308** and collides with a local MySQL already serving
the project. Run a local MySQL 8 on port 3308 instead. CI does the same and never invokes Compose.
