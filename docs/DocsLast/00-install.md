# Install and run the API

From nothing to a running API and a valid token for every guard.

Everything on this page was verified against the repository on **2026-09-07**. Where a
number looks arbitrary (port 3308, PIN length 4, OTP TTL 300 s) it is quoted from a
config file, not remembered.

---

## 1. What you need

| | Version | Note |
|---|---|---|
| PHP | 8.3+ | with `pdo_mysql`, `redis` (or `predis`), `bcmath`, `intl` |
| MySQL | 8.0 | **port 3308**, not 3306 — see below |
| Redis | 7 | queues and Horizon |
| Composer | 2.x | |
| Node | 20+ | only for the dashboards, not for the API |

**Docker is not required and not the supported path.** `docker-compose.yml` exists in the
repository, but the decided local setup is a native PHP + MySQL + Redis install. If you
run the containers anyway, publish MySQL on 3308 so it matches `.env.example`, or change
`DB_PORT` in your own `.env` — do not commit that change.

---

## 2. Why 3308

`.env.example` ships with:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=b2b_platform
```

3308 is deliberate — it keeps this project's MySQL clear of any 3306 instance already on
the machine. If your MySQL listens on 3306, either move it or set `DB_PORT=3306` in your
local `.env`. Do not "fix" `.env.example`.

---

## 3. Install

```bash
git clone <repo> b2b-api && cd b2b-api
composer install
cp .env.example .env
php artisan key:generate
```

Create the two databases:

```sql
CREATE DATABASE b2b_platform       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE b2b_platform_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then migrate and seed the development database:

```bash
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
```

Check it answers:

```bash
curl -s http://127.0.0.1:8000/api/v1/health
# {"data":{"status":"ok",...},"meta":{"server_time":"...+03:00"}}
```

`GET /api/v1/health` is unguarded. Use it as your reachability probe; never as a login
check.

---

## 4. `.env.testing` — read this before running tests

The repository ships a committed `.env.testing` pointing at a **separate** database:

```dotenv
APP_ENV=testing
DB_PORT=3308
DB_DATABASE=b2b_platform_test     # <- not b2b_platform
CACHE_STORE=array
QUEUE_CONNECTION=sync
BCRYPT_ROUNDS=4
```

⚠️ **Never run `php artisan migrate --env=testing`, and never `migrate:fresh` before a
test run.** Pest migrates `b2b_platform_test` itself. Passing `--env=testing` to artisan
by hand has been observed to rebuild the *development* database instead, destroying your
seed data. Run tests with `./vendor/bin/pest` and let it manage its own schema.

Queue is `sync` and cache is `array` under testing, so jobs run inline and nothing leaks
between tests.

---

## 5. Background workers

Queues are Redis-backed with four names: `critical`, `default`, `media`, `reports`.

```bash
php artisan horizon        # supervises all four
```

For local frontend work you rarely need Horizon. Only these are asynchronous: audit
export, catalog import/export, and image processing. Everything else answers inline.
If a response hands you a `job_id`, a worker must be running for that job to finish —
and there is currently **no endpoint to poll a job's status**, so treat `job_id` as
"queued", not as something you can await.

---

## 6. Seed accounts — there are three, not five

`database/seeders/DatabaseSeeder.php` creates exactly this:

| Guard | Identity | Credential | Role |
|---|---|---|---|
| `platform` | `admin@platform.sy` | password: `password` | `platform_admin` |
| `channel` | phone `+963900000001` | OTP (see §7) | `channel_manager` |
| — | Supply channel `demo-channel` (“Demo Channel”) | — | owns the channel above |

⚠️ **The seeder creates no `app` user and no `warehouse` device.** The two mobile apps
and the warehouse dashboard have no ready-made account: a retailer or rep account is
created by verifying an OTP for any Syrian phone number (§7), and a warehouse device must
be registered with an artisan command (§9). Do not go looking for seeded credentials that
do not exist.

---

## 7. OTP codes are written to the log

Login for the apps and for the channel dashboard is passwordless. In local development no
WhatsApp message is sent — `config/otp.php` defaults `OTP_CHANNEL=log`, and the code is
written to the Laravel log:

```php
// app-modules/identity/src/Infrastructure/Otp/LogOtpChannel.php
Log::info("[OTP] {$purpose} via {$via} for {$phone}: {$code}");
```

So:

```bash
tail -f storage/logs/laravel.log | grep OTP
```

Values from `config/otp.php`:

| Setting | Default | Meaning |
|---|---|---|
| `otp.ttl` | **300 s** | code lifetime |
| `otp.length` | **6** | digits — `code` is validated `size:6` |
| `otp.max_attempts` | **5** | wrong codes before the OTP dies |
| `otp.resend_cooldown` | **60 s** | before a resend is accepted |

Rate limits are per hour, enforced in `OtpService::assertRateLimits()`:
**3 per phone**, **10 per `X-Device-Id`**, **30 per IP**. Exceeding any of them is
`429 rate_limited`. Three attempts per phone per hour is easy to burn while testing —
use a different phone number rather than waiting.

---

## 8. A token from every guard, in two minutes

Four guards, four different flows. `X-Idempotency-Key` is **required on every write**
except the login calls below (`config/core.php` → `idempotency_exempt`).

### platform — email + password

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/platform/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@platform.sy","password":"password"}'
```

Returns `data.token` directly (the seeded admin has no 2FA). If `data.requires_2fa` is
true, post `data.challenge_token` plus a TOTP to `/platform/auth/2fa/verify`.

### channel — OTP on the seeded manager's phone

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/channel/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"+963900000001"}'
# -> {"data":{"otp_id":"otp_ab12cd"}}

tail -n 50 storage/logs/laravel.log | grep OTP     # read the 6-digit code

curl -s -X POST http://127.0.0.1:8000/api/v1/channel/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"otp_id":"otp_ab12cd","code":"123456"}'
# -> { token, channels: [{id,name}], permissions: [...] }
```

⚠️ Channel `request-otp` returns **only `otp_id`** — no `expires_in`, no `resend_after`,
unlike the public OTP in §8.4. And there is **no channel resend endpoint**: if the code
expires, request a new one.

A phone with no `ChannelUser` row, or one with no channel membership, gets
`403 insufficient_permission` at verify — not 404.

### app (retailer / rep) — public OTP, any Syrian number

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/public/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"+963933000000","purpose":"register","client":"retailer-android"}'
# -> { otp_id, channel_used, expires_in: 300, resend_after: 60 }

tail -n 50 storage/logs/laravel.log | grep OTP

curl -s -X POST http://127.0.0.1:8000/api/v1/public/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"otp_id":"otp_ab12cd","code":"123456","device_id":"dev-uuid-1"}'
```

⚠️ **The token you get back is probably not the token you want.** A user whose profile is
incomplete receives a token whose only ability is `registration` — it opens the two
register routes and nothing else. After registering you must **verify a fresh OTP** to get
a `*` token; registration does not upgrade the token in your hand. This is the single
most expensive thing to learn late — it is explained in full in
[apps/retailer.md](./apps/retailer.md) §2 and [apps/rep.md](./apps/rep.md) §2.

### warehouse — pre-registered device + PIN

There is no self-service warehouse signup. Register a device from the CLI first (§9).

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/warehouse/auth/device-login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"device_token":"wh-token-1","pin":"1234"}'
# -> { token, warehouse: {id,name}, permissions: [...] }
```

Then send `Authorization: Bearer {token}` on every subsequent call.

---

## 9. Registering a warehouse device

`POST /warehouse/auth/device-login` authenticates a device that already exists. Creating
one is deliberately CLI-only — there is no public API:

```bash
php artisan warehouse:register-device wh-token-1 1234 1 1 --label="Bay 1"
#                                     token      pin  wh channel
```

- `pin` must be **exactly 4 digits** (both the command and `DeviceLoginRequest` enforce it).
- The token is stored as a SHA-256 hash; the plain value is never recoverable — write it down.
- `warehouse_id` and `channel_id` must be real ids. With only `demo-channel` seeded,
  `1 1` is the usual pair.
- Re-running with the same token updates that device (it is an `updateOrCreate`), so it is
  also how you reset a forgotten PIN.

A revoked device, a wrong token, or a wrong PIN all return the same
`401 unauthenticated` — the API never says which part was wrong.

---

## 10. Before you report a bug in the API

Run the gates the backend runs. If these are green, the problem is likelier in the client:

```bash
composer deptrac                  # module boundaries
./vendor/bin/pest --group=arch    # architecture rules
./vendor/bin/phpstan analyse      # Larastan level 6
./vendor/bin/pest                 # full suite
./vendor/bin/pint --test          # formatting
```

---

## 11. Next

| You are building | Read |
|---|---|
| Anything | [01-http-contract.md](./01-http-contract.md) — envelope, headers, errors |
| A Next.js dashboard | [02-react-client.md](./02-react-client.md) |
| A Flutter app | [03-flutter-client.md](./03-flutter-client.md) |
| Retailer app | [apps/retailer.md](./apps/retailer.md) |
| Field rep app | [apps/rep.md](./apps/rep.md) |
| Channel dashboard | [apps/channel-web.md](./apps/channel-web.md) |
| Warehouse dashboard | [apps/warehouse-web.md](./apps/warehouse-web.md) |
| Platform admin | [apps/platform-web.md](./apps/platform-web.md) |

Start with [README.md](./README.md) if you want to know whether your app is buildable at
all today — two of the five are partly blocked.
