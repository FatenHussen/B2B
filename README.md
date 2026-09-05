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

Full steps (MySQL port, OTP log, seed users, Docker, warehouse device): **[docs/DocsLast/_shared/00-install-the-api.md](docs/DocsLast/_shared/00-install-the-api.md)**

Local platform login after seed: `admin@platform.sy` / `password`.  
OTP codes (dev): `storage/logs/laravel.log` with `OTP_CHANNEL=log`.

## Frontend briefs

**[docs/DocsLast/README.md](docs/DocsLast/README.md)** — Flutter apps and web dashboards, one folder each, `L1.md`…`L4.md` per sprint.

- Flutter kit: [docs/DocsLast/_shared/flutter-client.md](docs/DocsLast/_shared/flutter-client.md)
- React kit: [docs/DocsLast/_shared/react-client.md](docs/DocsLast/_shared/react-client.md)

## Backend specs

| File | Contents |
|---|---|
| [docs/sprints/L1-foundation-backend-spec.md](docs/sprints/L1-foundation-backend-spec.md) | Layer 1 build contract |
| [docs/sprints/L1-ready-apis.md](docs/sprints/L1-ready-apis.md) | Live L1 request/response shapes |
| [docs/api/catalog/](docs/api/catalog/) | Binding API catalog |

Requires PHP 8.3 and MySQL 8.
