# Install the API (do this first)

Every L1 client talks to this Laravel API. Install it on the machine **before** Flutter or Next.js work.

| | |
|---|---|
| **Repo** | this project (`B2BPROJECT`) |
| **PHP** | 8.3+ |
| **Database** | MySQL 8 |
| **Base URL** | `http://127.0.0.1:8000/api/v1` |
| **Catalog** | `docs/api/catalog/*.php` |
| **Postman** | `docs/api/b2b-api.postman_collection.json` |

---

## 1. Prerequisites

- PHP 8.3 with extensions: `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `curl`, `fileinfo`, `gd` or `imagick`
- [Composer](https://getcomposer.org/)
- MySQL 8 listening on the port in `.env` (this repo’s example uses **3308**; Docker Compose publishes **3306**)
- Redis optional for queues (`QUEUE_CONNECTION=sync` is fine locally)
- Git

Windows: skip Horizon (`pcntl`). Use `php artisan queue:work` if you need jobs.

## 2. Clone and install

```bash
cd B2BPROJECT

composer install

copy .env.example .env
php artisan key:generate
```

On macOS/Linux use `cp .env.example .env` instead of `copy`.

## 3. Database

Edit `.env`:

```
APP_URL=http://127.0.0.1:8000
APP_LOCALE=ar
APP_TIMEZONE=Asia/Damascus

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=b2b_platform
DB_USERNAME=root
DB_PASSWORD=

OTP_CHANNEL=log
QUEUE_CONNECTION=sync
```

Create the schema then migrate and seed:

```bash
php artisan migrate --seed
```

Seeded accounts (local only):

| Role | How to sign in |
|---|---|
| Platform admin | email `admin@platform.sy` · password `password` |
| Channel manager | phone `+963900000001` · OTP from the log |
| Demo channel | slug `demo-channel` (id usually `1`) |

## 4. Run the API

```bash
php artisan serve
```

Check:

```bash
curl http://127.0.0.1:8000/api/v1/health
```

Expect:

```json
{ "data": { "status": "ok" }, "meta": { "server_time": "…" } }
```

Leave this process running. Clients use `http://127.0.0.1:8000/api/v1` — not `/api/v1/auth` (removed).

### Docker alternative

```bash
docker compose up -d
```

Nginx is on **8080**. Point clients at `http://127.0.0.1:8080/api/v1` and align `.env` `DB_PORT` with Compose (3306, user/password `root`/`root`, database `b2b`) or keep using host MySQL on 3308.

## 5. OTP in local (`OTP_CHANNEL=log`)

WhatsApp is not required. After `POST /public/auth/request-otp` (or channel OTP), read the code:

```bash
# Windows PowerShell
Get-Content storage\logs\laravel.log -Tail 30

# macOS / Linux
tail -n 30 storage/logs/laravel.log
```

Line shape:

```
[OTP] login via whatsapp for +963933000000: 482193
```

Use `482193` on the verify screen. Do not call Meta from L1 clients.

## 6. Writes need an idempotency key

Every `POST` / `PUT` / `PATCH` / `DELETE` except the public/auth exceptions in the shared client brief:

```
X-Idempotency-Key: <uuid>
```

Missing key → `400` `idempotency_key_required`.

## 7. Warehouse device (optional, warehouse web only)

After migrate, register a terminal (no public API):

```bash
php artisan tinker --execute="Modules\Tenancy\Domain\Models\Warehouse::query()->firstOrCreate(['channel_id'=>1], ['name'=>'مستودع تجريبي','status'=>'active']);"
php artisan warehouse:register-device wh-demo-token 4821 1 1 --label=dev-pc
```

`warehouse_id` and `channel_id` must exist. Then the warehouse UI sends `{ "device_token": "wh-demo-token", "pin": "4821" }`.

## 8. Next

| Team | Open |
|---|---|
| Shared HTTP | [`00-shared-api-client.md`](./00-shared-api-client.md) |
| Retailer app | [`01-retailer-app.md`](./01-retailer-app.md) |
| Rep app | [`02-rep-app.md`](./02-rep-app.md) |
| Channel web | [`03-channel-dashboard.md`](./03-channel-dashboard.md) |
| Warehouse web | [`04-warehouse-web.md`](./04-warehouse-web.md) |
| Platform web | [`05-platform-admin.md`](./05-platform-admin.md) |

Ready API shapes: [`../L1-ready-apis.md`](../L1-ready-apis.md).
