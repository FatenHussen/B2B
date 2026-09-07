# Install the API

Do this before Flutter or Next.js. Every product uses `http://127.0.0.1:8000/api/v1`.

| | |
|---|---|
| PHP | 8.3+ (`mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `curl`, `fileinfo`, `gd` or `imagick`) |
| Database | MySQL 8 on port **3308** (see `.env.example`). Docker is not used — see section 2. |
| Catalog | `docs/api/catalog/*.php` |
| Postman | `docs/api/b2b-api.postman_collection.json` |

Windows: do not run Horizon (`pcntl`). Use `php artisan queue:work` if jobs must run.

---

## 1. Install

```bash
cd B2BPROJECT
composer install
copy .env.example .env
php artisan key:generate
```

macOS / Linux: `cp .env.example .env`.

## 2. `.env` (local)

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

```bash
php artisan migrate --seed
php artisan serve
```

```bash
curl http://127.0.0.1:8000/api/v1/health
```

Expect `{ "data": { "status": "ok" }, "meta": { "server_time": "…" } }`.  
Base path is `/api/v1` — never `/api/v1/auth/*`.

**Docker: not used.** `docker-compose.yml` is a leftover from the initial commit and does not match `.env` (it creates database `b2b`, password `root`; the app expects `b2b_platform` with an empty password). It also binds host port 3308 and collides with a local MySQL. Use a local MySQL 8 on 3308 — CI does the same and never invokes Compose.

## 3. Seeded logins

| Product | How |
|---|---|
| Platform | `admin@platform.sy` / `password` |
| Channel | phone `+963900000001` — OTP from the log |
| Demo channel | slug `demo-channel` (id usually `1`) |

## 4. OTP (`OTP_CHANNEL=log`)

WhatsApp is not required locally.

```bash
# Windows
Get-Content storage\logs\laravel.log -Tail 30

# macOS / Linux
tail -n 30 storage/logs/laravel.log
```

```
[OTP] login via whatsapp for +963933000000: 482193
```

Use `482193` on the verify screen.

## 5. Warehouse device (warehouse dashboard only)

```bash
php artisan tinker --execute="Modules\Tenancy\Domain\Models\Warehouse::query()->firstOrCreate(['channel_id'=>1], ['name'=>'مستودع تجريبي','status'=>'active']);"
php artisan warehouse:register-device wh-demo-token 4821 1 1 --label=dev-pc
```

Login body: `{ "device_token": "wh-demo-token", "pin": "4821" }`.

## 6. Next

| Stack | File |
|---|---|
| HTTP rules | [http-contract.md](./http-contract.md) |
| Flutter | [flutter-client.md](./flutter-client.md) then `flutter/{retailer\|rep}/L1.md` |
| React | [react-client.md](./react-client.md) then `web/{channel\|warehouse\|platform}/L1.md` |
