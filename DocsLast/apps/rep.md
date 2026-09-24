# Field rep app (Flutter)

**This is the only file you need.** Backend setup, the Dart package, the HTTP contract and
every endpoint — all of it is here. No other document is required to build this app.

| | |
|---|---|
| Path | `apps/rep` |
| Platform | Flutter — Android + iOS |
| Guard | `app` (Sanctum bearer) |
| Kind gate | `RequireAppKind:rep` on every `/app/rep/*` route |
| `X-Client` | `rep-android` / `rep-ios` *(sent for logs; the server ignores it)* |
| Seed account | **none** — create one with an OTP for any Syrian number (§3) |

Verified against `php artisan route:list` and the controller/action source on
**2026-09-07**. Where the API catalog disagrees with this page, the code wins.

**33 endpoints are reachable from this app** — 24 under `/app/rep`, 5 shared `/app/*`, the
3 public OTP calls and `/health`. The whole field day works — customers, cart,
assignments, warehouse pickup, delivery, returns. The rep **wallet and cash collection do
not exist** (§7), which shapes what you can ship.

### Contents

| § | |
|---|---|
| 1 | Running the backend |
| 2 | The Flutter package — working code |
| 3 | A token in two minutes, and the trap that costs a week |
| 4 | Endpoint reference |
| 5 | The HTTP contract — envelope, errors, money |
| 6 | What to build, in order |
| 7 | Not built — do not mock |
| 8 | Gotchas |

---

## 1. Running the backend

You need the API running locally before the app can do anything.

### 1.1 Requirements

| | Version | Note |
|---|---|---|
| PHP | 8.3+ | with `pdo_mysql`, `redis`, `bcmath`, `intl` |
| MySQL | 8.0 | **port 3308**, not 3306 |
| Redis | 7 | queues |
| Composer | 2.x | |

**Docker is not the supported path.** `docker-compose.yml` exists but the decided local
setup is native PHP + MySQL + Redis. If you use containers anyway, publish MySQL on 3308.

### 1.2 Install

```bash
git clone <repo> b2b-api && cd b2b-api
composer install
cp .env.example .env
php artisan key:generate
```

```sql
CREATE DATABASE b2b_platform      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE b2b_platform_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
curl -s http://127.0.0.1:8000/api/v1/health
# {"data":{"status":"ok",...},"meta":{"server_time":"...+03:00"}}
```

⚠️ **Port 3308 is deliberate** — it keeps this project clear of any MySQL already on 3306.
If yours listens on 3306, set `DB_PORT=3306` in your own `.env`; never edit `.env.example`.

⚠️ **Never run `php artisan migrate --env=testing`, and never `migrate:fresh` before a test
run.** Pest migrates `b2b_platform_test` itself; passing `--env=testing` by hand has been
observed to rebuild the *development* database and destroy your seed data.

### 1.3 The seeder creates no app user — but you need a channel

`database/seeders/DatabaseSeeder.php` creates a platform admin, a channel manager and one
supply channel (`demo-channel`, id `1`) — **and no `app` user at all.** Your rep account is
created by verifying an OTP for any Syrian phone number (§3).

📌 Unlike the retailer, a rep needs a **`supply_channel_id`** at registration, and the
channel must **cover the zones** you request. With only `demo-channel` seeded, that is
`supply_channel_id: 1` — and the zones must be within its coverage or registration fails
validation on `zone_ids`.

### 1.4 OTP codes are written to the log

No WhatsApp message is sent locally. `config/otp.php` defaults `OTP_CHANNEL=log`:

```bash
tail -f storage/logs/laravel.log | grep OTP
# [OTP] register via whatsapp for +963944000000: 481923
```

| Setting | Default | Meaning |
|---|---|---|
| `otp.ttl` | **300 s** | code lifetime |
| `otp.length` | **6** | digits — `code` is validated `size:6` |
| `otp.max_attempts` | **5** | wrong codes before the OTP dies |
| `otp.resend_cooldown` | **60 s** | before a resend is accepted |

Rate limits per hour: **3 per phone**, 10 per `X-Device-Id`, 30 per IP.

### 1.5 Queues

`php artisan horizon` supervises `critical`, `default`, `media`, `reports`. Handover and
OTP run on `critical`. You rarely need Horizon for this app: nothing the rep calls returns
a `job_id`.

---

## 2. The Flutter package

One shared Dart package, `packages/b2b_api`. No screen builds a `Dio` call by hand.

The retailer app uses the identical package — only the paths differ, and the server
enforces that with `RequireAppKind`.

### 2.1 Stack

| Concern | Choice |
|---|---|
| HTTP | `dio` |
| Storage | `flutter_secure_storage` (token), `shared_preferences` (rest) |
| Ids | `uuid` |
| State | your choice — the package is state-agnostic |

```yaml
dependencies:
  dio: ^5.4.0
  flutter_secure_storage: ^9.0.0
  uuid: ^4.3.0
```

The API is Arabic-first: `Accept-Language: ar` unless the user chose otherwise, and every
screen is RTL.

```
packages/b2b_api/lib/
  b2b_api.dart          exports
  src/
    envelope.dart       Ok / Meta parsing
    api_error.dart      ApiError + codes
    token_store.dart    secure token + abilities
    idempotency.dart    key store bound to a user intent
    op_ids.dart         client_op_id for offline field signup  ← rep only
    client.dart         the single Dio
    resources/          one file per surface
```

### 2.2 `envelope.dart`

```dart
class Meta {
  final String serverTime;
  final int? page, perPage, total, lastPage;
  final String? nextCursor, prevCursor;
  final bool? hasMore;

  const Meta({required this.serverTime, this.page, this.perPage, this.total,
              this.lastPage, this.nextCursor, this.prevCursor, this.hasMore});

  factory Meta.fromJson(Map<String, dynamic> j) => Meta(
        serverTime: j['server_time'] as String? ?? '',
        page: j['page'] as int?,
        perPage: j['per_page'] as int?,
        total: j['total'] as int?,
        lastPage: j['last_page'] as int?,
        nextCursor: j['next_cursor'] as String?,
        prevCursor: j['prev_cursor'] as String?,
        hasMore: j['has_more'] as bool?,
      );
}

class Ok<T> {
  final T data;
  final Meta meta;
  const Ok(this.data, this.meta);
}
```

### 2.3 `api_error.dart`

```dart
class ApiError implements Exception {
  final String code;
  final String message;
  final int status;
  final String? permission;
  final Map<String, dynamic>? details;

  const ApiError(this.code, this.message, this.status, {this.permission, this.details});

  /// Token is worthless — clear it and return to the OTP screen.
  bool get isAuthLoss => code == 'unauthenticated' || code == 'token_revoked';

  /// A live token belonging to ANOTHER guard. Re-login does not fix it.
  bool get isWrongGuard => code == 'wrong_guard';

  /// The same request may be retried with the SAME idempotency key.
  bool get isRetryable => code == 'operation_in_progress';

  /// The rep asked for more discount than their cap allows. Never retry the same %.
  bool get isDiscountCapped => code == 'discount_cap_exceeded';

  /// Field -> first message, for form binding.
  Map<String, String> fieldErrors() {
    final out = <String, String>{};
    details?.forEach((k, v) {
      if (v is List && v.isNotEmpty) out[k] = v.first.toString();
    });
    return out;
  }

  @override
  String toString() => 'ApiError($code, $status): $message';
}
```

### 2.4 `token_store.dart` — store the abilities, not just the token

A freshly verified OTP for an unregistered user returns a token whose only ability is
`registration`. Every other call 403s until you re-verify (§3), so persist that fact.

```dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStore {
  static const _s = FlutterSecureStorage();
  static const _kToken = 'b2b.app.token';
  static const _kRegistrationOnly = 'b2b.app.registration_only';

  Future<String?> read() => _s.read(key: _kToken);

  /// [registrationOnly] must be true when the API said profile_completed == false.
  Future<void> save(String token, {required bool registrationOnly}) async {
    await _s.write(key: _kToken, value: token);
    await _s.write(key: _kRegistrationOnly, value: registrationOnly.toString());
  }

  /// True when this token can ONLY reach the two register routes.
  Future<bool> isRegistrationOnly() async =>
      (await _s.read(key: _kRegistrationOnly)) == 'true';

  Future<void> clear() async {
    await _s.delete(key: _kToken);
    await _s.delete(key: _kRegistrationOnly);
  }
}
```

### 2.5 `idempotency.dart` — a key per intent, not per request

Every write needs `X-Idempotency-Key`. The key belongs to a **user intent** — one tap of
Submit — not to an HTTP attempt. This matters more for a rep than anyone: they work in
basements and vans, and a request that times out has very often already been executed.

```dart
import 'package:uuid/uuid.dart';

class IdempotencyKeys {
  static const _uuid = Uuid();
  final _keys = <String, String>{};

  /// Same intentId in, same key out — while the intent is unresolved.
  String forIntent(String intentId) => _keys.putIfAbsent(intentId, _uuid.v4);

  /// Call ONLY after the intent succeeded, or the user abandoned it.
  void release(String intentId) => _keys.remove(intentId);
}
```

Bind it to the button:

```dart
class _SubmitSectionButtonState extends State<SubmitSectionButton> {
  late final String _intentId = 'cart.submit.${widget.retailerId}';
  bool _busy = false;

  Future<void> _submit() async {
    setState(() => _busy = true);
    try {
      // Tap ten times in a bad signal: one sub-order.
      await api.submitSection(widget.retailerId,
          discountPercent: _discount,
          idempotencyKey: keys.forIntent(_intentId));
      keys.release(_intentId);
    } on ApiError catch (e) {
      if (e.isRetryable)      _toast('Still processing — try again.');
      else if (e.isDiscountCapped) _showCapDialog(e.message);   // lower the %, do not retry
      else                    _toast(e.message);
      // Deliberately NOT released.
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}
```

⚠️ Never mint the key inside the Dio interceptor. A key per request turns one submit into
several sub-orders.

⚠️ Persist unresolved keys if you want retry-after-restart. An in-memory map loses the key
when the app is killed mid-submit, and the retry then creates a second order.

⚠️ **Location pings need a fresh key every call.** Reusing one replays the previous
`accepted` count instead of recording anything (§4.5).

### 2.6 `op_ids.dart` — the one true offline key (rep only)

`POST /app/rep/customers` is the **only endpoint in the entire API** with a business-level
replay key. It is deduplicated per `(rep, client_op_id)` by a database unique index, so a
replay carrying a *new* idempotency header still returns the original row.

Generate it once per local draft and **never rotate it on retry**:

```dart
class DraftShop {
  final String clientOpId;      // generated once, persisted with the draft
  DraftShop() : clientOpId = const Uuid().v4();
}
```

Send **both** it and `X-Idempotency-Key`: the header catches a byte-identical retry within
24 h, `client_op_id` catches a genuine offline re-send days later.

⚠️ No other write accepts `client_op_id`. Everywhere else the header is your only guard.

### 2.7 `client.dart`

```dart
import 'package:dio/dio.dart';

const _writeMethods = {'POST', 'PUT', 'PATCH', 'DELETE'};

/// Paths that must NOT carry an idempotency key (config/core.php: idempotency_exempt).
const _exempt = {
  '/public/auth/request-otp',
  '/public/auth/verify-otp',
  '/public/auth/resend-otp',
};

class B2bApi {
  final Dio _dio;
  final TokenStore _tokens;

  B2bApi({required String baseUrl, required TokenStore tokens, required String deviceId})
      : _tokens = tokens,
        _dio = Dio(BaseOptions(
          baseUrl: baseUrl,                       // http://10.0.2.2:8000/api/v1 on the emulator
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 30),
          headers: {'Accept': 'application/json', 'Accept-Language': 'ar'},
          validateStatus: (_) => true,            // we read the envelope ourselves
        )) {
    _dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) async {
      final token = await _tokens.read();
      if (token != null) o.headers['Authorization'] = 'Bearer $token';
      o.headers['X-Device-Id'] = deviceId;        // stable per install; OTP rate limiting uses it
      h.next(o);
    }));
  }

  Future<Ok<T>> request<T>(
    String path, {
    String method = 'GET',
    Object? body,
    Map<String, dynamic>? query,
    String? idempotencyKey,
  }) async {
    final headers = <String, dynamic>{};

    if (_writeMethods.contains(method) && !_exempt.contains(path)) {
      if (idempotencyKey == null) {
        // Fail here, loudly, rather than let the server answer 400.
        throw StateError('$method $path requires an idempotencyKey');
      }
      headers['X-Idempotency-Key'] = idempotencyKey;
    }

    final res = await _dio.request<dynamic>(
      path,
      data: body,
      queryParameters: query,
      options: Options(method: method, headers: headers),
    );

    if (res.statusCode == 204) {
      return Ok<T>(null as T, const Meta(serverTime: ''));
    }

    final json = res.data as Map<String, dynamic>;

    if (json.containsKey('error')) {
      final e = json['error'] as Map<String, dynamic>;
      throw ApiError(
        e['code'] as String? ?? 'unknown',
        e['message'] as String? ?? '',
        res.statusCode ?? 0,
        permission: e['permission'] as String?,
        details: e['details'] as Map<String, dynamic>?,
      );
    }

    return Ok<T>(json['data'] as T, Meta.fromJson(json['meta'] as Map<String, dynamic>? ?? {}));
  }
}
```

📌 `10.0.2.2` is the Android emulator's alias for the host machine. A physical device needs
your LAN address; `localhost` reaches the phone itself and will always time out.

### 2.8 Handling errors once

```dart
Future<void> onApiError(ApiError e) async {
  if (e.isAuthLoss) {
    await tokens.clear();
    router.go('/otp');
    return;
  }
  if (e.code == 'insufficient_permission') {
    // On this guard the usual cause is a registration-only token, NOT a missing grant.
    if (await tokens.isRegistrationOnly()) { router.go('/register'); return; }
  }
  if (e.isDiscountCapped)          { /* show the cap; the rep must lower the % */ return; }
  if (e.code == 'rate_limited')    { /* back off — pings have a 30 s floor */ return; }
  if (e.code == 'not_found')       { /* gone or not yours — never say "forbidden" */ return; }
  if (e.code == 'illegal_transition') { /* refetch — the order moved on */ return; }
  showToast(e.message);
}
```

⚠️ `not_found` also means "not yours". The API never discloses that a resource exists but
belongs to someone else — render it as missing, never as a permission problem.

### 2.9 Money

```dart
String formatMoney(int amount, {int decimals = 0}) {
  if (decimals == 0) return NumberFormat.decimalPattern('ar').format(amount);
  return NumberFormat.decimalPattern('ar').format(amount / pow(10, decimals));
}
```

Integers in the smallest unit. **SYP has 0 decimals**, so the integer is the number you
show. Never `/ 100` by reflex.

⚠️ **The rep cart gives you no line totals and no summary** (§4.3) — you must sum sections
yourself for display, but trust the server's per-section `total`. Discount maths on the
server uses integer truncation, so your float arithmetic will occasionally disagree by a
unit. The server is right.

### 2.10 Offline

There is **no sync endpoint** — `/app/sync/*` all 404 (§7). An offline outbox has nowhere
to drain. Build **online-first**.

The single genuine offline path is `client_op_id` on `POST /app/rep/customers` (§2.6): a
field signup with no signal is replay-safe. Everything else — carts, deliveries, pings —
is online-only. Do not ship a UI promising deferred delivery.

---

## 3. A token in two minutes — and the trap that costs a week

### ⚠️ Read this before writing the login screen

`verify-otp` returns a token whose **abilities depend on whether the profile is complete**:

| Profile | Abilities | What the token opens |
|---|---|---|
| incomplete | `['registration']` | **only** `/app/rep/register` and `/app/retailer/register` |
| complete | `['*']` | everything |

**Registration does not upgrade the token in your hand.** `RegisterRep` writes the profile
and returns — it never re-issues a token. So the obvious flow is wrong:

```
request-otp → verify-otp → register → GET /app/rep/products     ❌ 403 forever
```

The token you are still holding has `registration` only. You must **verify a second OTP**
after registering:

```
request-otp → verify-otp  (token: registration-only)
            → POST /app/rep/register
            → request-otp → verify-otp  (token: *)   ← the second round trip is mandatory
            → GET /app/rep/products                              ✅
```

Branch on `profile_completed` in the verify response, never on "do I have a token".
Persist that flag with the token — see `TokenStore` in the
§2.4.

Symptom if you get this wrong: login appears to work, then **every** call returns 403, and
clearing storage and logging in again reproduces it exactly.

⚠️ **One phone is one kind.** `RequireAppKind` compares `AppUser.kind` to the route's kind.
A rep token on `/app/retailer/*` is `403 insufficient_permission`. There is no switcher and
no way to be both — a tester who registered as a retailer needs a different phone number.

⚠️ A rep also needs `supply_channel_id` at registration, and the channel must **cover the
zones** requested — otherwise registration fails validation on `zone_ids`.

### The calls

```bash
# 1. request a code (no auth, NO idempotency key)
curl -s -X POST http://127.0.0.1:8000/api/v1/public/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"+963944000000","purpose":"register","client":"rep-android"}'
# { "otp_id":"otp_ab12cd", "channel_used":"whatsapp", "expires_in":300, "resend_after":60 }

# 2. read the code from the log — no WhatsApp is sent locally
tail -n 50 storage/logs/laravel.log | grep OTP

# 3. verify
curl -s -X POST http://127.0.0.1:8000/api/v1/public/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"otp_id":"otp_ab12cd","code":"123456","device_id":"dev-uuid-2"}'
```

```jsonc
{ "data": { "token": "13|xxxx", "is_new_user": true, "user_type": null,
            "profile_completed": false,      // ← branch on THIS
            "user": { "id": 8, "name": "", "phone": "+963944000000" } } }
```

```bash
# 4. register (Bearer = the registration-only token, idempotency key REQUIRED)
curl -s -X POST http://127.0.0.1:8000/api/v1/app/rep/register \
  -H 'Authorization: Bearer 13|xxxx' -H 'X-Idempotency-Key: 22222222-2222-2222-2222-222222222222' \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"name":"…","supply_channel_id":1,"activity_type_id":1,"zone_ids":[1]}'

# 5. verify a SECOND OTP for the "*" token, then:
curl -s http://127.0.0.1:8000/api/v1/app/rep/products -H 'Authorization: Bearer {new token}'
```

OTP limits: TTL **300 s**, **5** wrong codes kills it, resend cooldown **60 s**, and
**3 requests per phone per hour** (also 10/device, 30/IP).

### ⚠️ There is no pre-login reference data

`GET /public/refs` and `GET /public/app-config` **do not exist**, and the reference routes
(`/api/v1/governorates`, `/api/v1/zones`) sit behind other guards, not `app`. A screen
shown *before* the OTP cannot fetch zones or activity types.

Registration needs `supply_channel_id`, `activity_type_id` and `zone_ids`, so either
collect them **after** the token exists, or **bundle them in the binary**. Design around
this now.

---

## 4. Endpoint reference

`Stability`: **stable** = registered at its contract path · **moving** = registered
elsewhere, will move · **missing** = catalogued, no route (§7).

### 4.1 Auth and session

| EP-ID | Method | Path | Stability | Notes |
|---|---|---|---|---|
| EP-CM-001 | POST | `/public/auth/request-otp` | stable | no auth, **no** idempotency key |
| EP-CM-002 | POST | `/public/auth/verify-otp` | stable | no auth, **no** idempotency key |
| EP-CM-003 | POST | `/public/auth/resend-otp` | stable | no auth, **no** idempotency key |
| EP-RP-001 | POST | `/app/rep/register` | stable | `registration` ability |
| EP-CM-004 | GET | `/app/session` | stable | |
| EP-CM-005 | POST | `/app/auth/logout` | stable | |
| EP-CORE-001 | GET | `/health` | stable | unguarded probe |

**`POST /app/rep/register`** → **201**

```jsonc
{ "name": "…",                 // required ≤120
  "supply_channel_id": 1,      // required
  "activity_type_id": 1,       // required
  "zone_ids": [1, 2],          // required, min 1 — must be covered by the channel
  "note": null }               // optional ≤500
```

**`GET /app/session`** — same payload as the retailer app; `user.user_type` is `"rep"`.
⚠️ `sync_cursor` is always `""` and `requires_legal_accept` always `false` — placeholders.

**`POST /public/auth/resend-otp`** — `{otp_id, prefer_channel: whatsapp|sms}` →
`{channel_used, resend_after}`. **No `otp_id`** in the response; reuse the one you have.

### 4.2 Duty, zones and customers

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RP-034 | PATCH | `/app/rep/status` | stable |
| EP-RP-071 | POST | `/app/rep/zones` | stable |
| EP-RP-020 | GET | `/app/rep/zones/{id}/shops` | stable |
| — | GET | `/app/rep/customers` | stable* |
| — | POST | `/app/rep/customers` | stable* |

\* live and callable, but carry **no EP-ID** — the catalog has not caught up. Use them;
expect the shape to be confirmed later. Same situation `CLAUDE.md` describes for
`sc.notify.view`.

**`PATCH /app/rep/status`** — `{on_duty: bool}` → `{on_duty, tracking_enabled}`.
⚠️ `tracking_enabled` **always mirrors `on_duty`** — it can never differ. Do not offer it
as a second switch.
📌 **Going on duty is a hard prerequisite for location pings** (§3.5) — an off-duty ping is
`422`.

**`POST /app/rep/zones`** — request coverage of a new zone. `{zone_id, note?}` →
`{"status":"pending_approval"}`. Returns **200, not 201**.
⚠️ **The created request's id is not returned**, so the app cannot reference or poll it
afterwards. Show "submitted" and stop.
Errors: `422` on `zone_id` — zone inactive (`identity.zone_not_found`) or outside your
channel's coverage (`identity.zone_outside_coverage`).

**`GET /app/rep/zones/{id}/shops`** — paginated.

```jsonc
{ "id": 5, "shop_name": "…", "address": null,
  "is_open": true, "is_active": true, "last_order_at": null }
```

⚠️ `is_open` is hardcoded `true`, `last_order_at` hardcoded `null`, and `is_active` is
always `true` (the query pre-filters actives). Render none of them as data.
📌 Search here is the **top-level `search`** parameter — *not* `filter[search]`. It differs
from `/customers` below. `per_page` default 25, max 100.
Errors: `403 insufficient_permission` if the zone is not one of yours.

**`GET /app/rep/customers`** — paginated `{id, shop_name, zone_id, is_active}`.
📌 Search here **is** `filter[search]`. ⚠️ Never send `sort` — no `allowedSorts` is
declared and Spatie will throw. Order is `-created_at`.

**`POST /app/rep/customers`** → **201** — register a shop in the field.

```jsonc
{ "shop_name": "…",        // required ≤160
  "owner_name": "…",       // required ≤120
  "phone": "09…",          // required, Syrian, normalised server-side
  "zone_id": 4,            // required — must be active AND covered by your channel
  "activity_type_id": 1,   // required
  "lat": null, "lng": null,
  "client_op_id": "…" }    // REQUIRED, ≤80 — see below
```
```jsonc
{ "data": { "id": 12, "status": "pending_sync" } }   // id = RepSourcedShop id
```

📌 **`client_op_id` is the only real offline-replay mechanism in the whole API.** It is
deduplicated per `(rep, client_op_id)` with a database unique index. A replay returns
**201 with the previously created row** — no error, no duplicate. Generate it once per
local draft and never rotate it on retry.

This is *in addition to* `X-Idempotency-Key`, and the two catch different cases: the header
replays a byte-identical request within 24 h; `client_op_id` catches a genuine offline
re-send that carries a **new** key. Send both.

⚠️ **No other endpoint accepts `client_op_id`.** For every other write, the mandatory
idempotency header is your only protection.

Errors: all `422 validation_failed` — `zone_id` (`zone_not_found` / `zone_outside_coverage`),
`activity_type_id` (`activity_type_not_found`).

### 4.3 Catalog, cart and orders

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RP-010 | GET | `/app/rep/products` | stable |
| EP-RP-022 | GET | `/app/rep/cart` | stable |
| EP-RP-021 | POST | `/app/rep/cart/lines` | stable |
| EP-RP-023 | POST | `/app/rep/cart/sections/{retailer_id}/submit` | stable |
| EP-RP-030 | GET | `/app/rep/assignments` | stable |
| EP-RP-031 | POST | `/app/rep/assignments/{id}/accept` | stable |
| EP-RP-032 | POST | `/app/rep/assignments/{id}/reject` | stable |
| EP-RP-033 | GET | `/app/rep/scheduled-orders` | stable |

**`GET /app/rep/products`** — paginated.

```jsonc
{ "id": 12, "name": "…",
  "channel": { "id": 2, "name": "…" },
  "price": { "type": "simple|tiered", "value": 5000, "label": "…" },
  "availability": "in_stock|out_of_stock" }
```

📌 **Rep product rows must show `channel.name`** — a rep sells for several channels and
needs to know whose goods these are. (This is the opposite of the retailer app, where the
channel is deliberately hidden.)
⚠️ Prices here are **zone + channel at qty 1**, never retailer-specific — `retailerId` is
passed as `null`. Re-quote through `/app/pricing/quote` before quoting a real customer.
Filters: `filter[channel_id]`, `filter[category_id]`, `filter[brand_id]`,
`filter[search]`, and top-level `barcode`. Zone via top-level `zone`.
⚠️ `filter[zone_id]` is **not** an allowed filter and will throw — use `zone`.
⚠️ Never send `sort`. An unsellable `filter[channel_id]` yields an **empty page, not a
403**.

**`GET /app/rep/cart`** — one cart, grouped by retailer.

```jsonc
{ "data": { "sections": [ {
      "retailer": { "id": 5, "shop_name": "…" },
      "lines": [ {"id":22,"product_id":12,"qty":3,"unit_price":5000} ],
      "total": 15000, "discount": 0 } ] } }
```

⚠️ Rep cart lines are **narrower than the retailer's**: there is **no `name` and no
`line_total`**. Resolve product names from your own catalog cache.
⚠️ There is **no top-level summary** — only `sections`. Sum them yourself for display, but
trust the server's `total` per section.

**`POST /app/rep/cart/lines`** — `{retailer_id, product_id, variant_id?, qty≥1}`. Adding an
existing `(product, variant)` **adds to** the quantity. Returns the cart.
Errors: `404 not_found` if the retailer is unknown **or** the product's channel is not one
you sell.

**`POST /app/rep/cart/sections/{retailer_id}/submit`**

```jsonc
{ "note": "…", "discount_percent": 5 }     // both optional
```
```jsonc
{ "data": { "sub_order": { "id": 9, "sub_order_no": "SO-9",
                           "status": "pending", "total": 14250 } } }
```

⚠️ **`note` is validated and then silently dropped** — `SubmitRepCartSection` never reads
it. Do not promise the customer a note on the order.

📌 **The discount cap.** `discount_percent` is checked against a per-`(channel, rep)` cap:

- **No limit row means a cap of `0`** — *any* discount is rejected. This is the common case
  on a fresh install.
- **There is no endpoint that exposes the cap.** The app cannot pre-validate the slider; it
  must submit and handle `403 discount_cap_exceeded`. Show the server's message and let the
  rep lower the number — never retry the same percentage.
- The cap is resolved against your **first** channel, not the channel of the goods being
  submitted. With several channels the applied cap may belong to a different one.
- `discount_percent: 0` (or omitted) always passes.

Errors: `403 discount_cap_exceeded`, `404 not_found` (unknown retailer),
`422 validation_failed` (empty section).

**`GET /app/rep/assignments`** — plain array, pending assignments.

```jsonc
{ "id": 9, "sub_order_no": "SO-9", "shop": "…", "zone": "…",
  "channel": "…", "invoice_no": null, "created_at": "…" }
```
⚠️ `invoice_no` is always `null` here.

**`POST /assignments/{id}/accept`** — no body → `{"status":"accepted"}`.
**`POST /assignments/{id}/reject`** — `{reason}` required ≤255 → `{"status":"unassigned"}`.
⚠️ `"unassigned"` is a stage label, **not** a real sub-order status — the row actually
returns to `confirmed`. Do not filter your list by it.
Both: `404` if not yours; `409 illegal_transition` if the order has left `assigned`.

**`GET /app/rep/scheduled-orders`** — plain array; optional top-level `date` filter.

```jsonc
{ "shop_logo": null, "shop": "…", "address": "…", "phone": "…",
  "scheduled_at": "…", "status": "postponed", "color": "amber" }
```

⚠️ **There is no `id` in these rows** — you cannot key them to a sub-order or navigate to a
detail screen from here. Treat it as a read-only day list.
⚠️ `shop_logo` always `null`, `color` always `"amber"`, `status` always `"postponed"`.
⚠️ `scheduled_at` is **not set by the postpone call** (§3.5) — postpone ignores the date you
send, so this list may not reflect what the rep chose.

### 4.4 Warehouse pickup — the gate to delivery

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RP-040 | GET | `/app/rep/warehouse-receipts` | stable |
| EP-RP-041 | POST | `/app/rep/warehouse-receipts/{handoverId}/confirm` | stable |

**`GET /app/rep/warehouse-receipts`** — optional top-level `date`.

```jsonc
{ "data": { "date": "2026-09-07", "rep_name": "…", "count": 3,
            "orders": [ {"order_no":"SO-9","shop":"…","zone":"…","handover_id":4} ] } }
```

⚠️ One row **per sub-order**, repeating `handover_id` across rows of the same handover;
`count` counts sub-orders, not handovers. ⚠️ `date` echoes your raw query param verbatim
(`null` when absent). ⚠️ **No numeric `sub_order_id`** — only the `order_no` string.

**`POST /app/rep/warehouse-receipts/{handoverId}/confirm`** — `{temp_code}` exactly **4
characters** (the warehouse reads it off their screen; it can have a leading zero, so keep
it a string).

```jsonc
{ "data": { "status": "on_the_way", "tracking_enabled": true } }
```

📌 **This is the gate for the entire delivery flow.** Until a handover is confirmed,
`POST /deliveries/{id}/complete` returns `409 illegal_transition` (`delivery.no_handover`).
If a rep reports "I can't complete a delivery", check this first.

⚠️ Re-confirming an already-confirmed handover **succeeds with 200 even with a wrong
`temp_code`** — the replay path short-circuits before the check. Do not use a successful
response as proof the code was right.

Errors: `409 illegal_transition` when the handover is missing **or not yours** (note: 409,
not 404); `422 validation_failed` on a wrong `temp_code`.

### 4.5 Delivery

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RP-050 | GET | `/app/rep/deliveries` | stable |
| EP-RP-051 | GET | `/app/rep/deliveries/{id}` | stable |
| EP-RP-052 | PATCH | `/app/rep/deliveries/{id}/lines/{lineId}` | stable |
| EP-RP-053 | POST | `/app/rep/deliveries/{id}/complete` | stable |
| EP-RP-054 | POST | `/app/rep/deliveries/{id}/postpone` | stable |
| EP-RP-055 | POST | `/app/rep/deliveries/{id}/fail` | stable |
| EP-RP-056 | POST | `/app/rep/locations/ping` | stable |

📌 In every route above, **`{id}` is a sub-order id**, not a delivery id.

**`GET /app/rep/deliveries`** — the day's route, grouped by zone. Optional
`filter[zone_id]` (applied in PHP after fetching).

```jsonc
{ "data": { "zones": [ {
      "name": "…", "total": 4, "delivered": 0,
      "cards": [ {"id":9,"shop":"…","zone":"…","channel":"…",
                  "invoice_no":"INV-2","ordered_at":null,
                  "status":"on_the_way","border_color":"green"} ] } ] } }
```

⚠️ **`delivered` is hardcoded `0`** — never a progress count. Compute progress locally.
⚠️ `ordered_at` always `null`. `border_color` is derived, only `green` (`on_the_way`) or
`blue` (`accepted`).
⚠️ This GET **has write side effects** — it creates missing delivery rows. Do not call it
speculatively in a background poller.

**`GET /app/rep/deliveries/{id}`**

```jsonc
{ "data": { "lines": [ {"id":44,"image":null,"name":"…","brand":"…",
                        "variant":null,"qty":3,"price":5000,"status":"pending"} ],
            "invoice_total": 15000 } }
```

`lines[].id` is the **delivery-line id** — the `{lineId}` below. `qty` is the **expected**
quantity; `qty_delivered` is never exposed. ⚠️ `image` and `variant` always `null`.

**`PATCH /deliveries/{id}/lines/{lineId}`** — `{qty_delivered, action, reason?}` where
`action` ∈ `accept|adjust|return|exchange` → `{"new_invoice_total": 12000}`.
Send `qty_delivered` (the rep alias; `qty_received` also works).

**`POST /deliveries/{id}/complete`** —
`{lines:[{line_id, qty_delivered, action}], delivered_at?, signature?}`

```jsonc
{ "data": { "invoice": {"no":"INV-2","total":12000},
            "receipt_no": "…", "ask_payment": true } }
```

⚠️ `ask_payment` is hardcoded `true` — but **there is no rep payments endpoint** (§7), so
it cannot lead anywhere. Do not open a cash-collection screen.
⚠️ `409 illegal_transition` until the handover is confirmed (§3.4).

**`POST /deliveries/{id}/postpone`** — `{scheduled_at, reason}`, both required →
`{"status":"postponed"}`.
⚠️ **Both fields are validated and then ignored.** `scheduled_at` is **not persisted**, so
the date the rep picks is lost and `/scheduled-orders` will not show it. Do not tell the
rep the visit was rescheduled to a specific time.

**`POST /deliveries/{id}/fail`** — `{reason}` required → `{"status":"undelivered",
"border_color":"red"}`. ⚠️ `reason` is validated and **never persisted**.

**`POST /app/rep/locations/ping`** — batched.

```jsonc
{ "pings": [ {"lat":33.5,"lng":36.3,"at":"2026-09-07T10:00:00+03:00","accuracy":12} ] }
```
```jsonc
{ "data": { "accepted": 1 } }
```

⚠️ `accepted` always equals `count(pings)` — it is not a validation result.
📌 **Two hard rules.** The rep must be **on duty** (`PATCH /app/rep/status`) or you get
`422`. And there is a **30-second server-side floor** measured against the newest stored
ping's `at`, not against arrival time — exceeding it is `429 rate_limited`.

⚠️ Those two combine badly: uploading a backlog whose newest `at` is recent **locks out
live pings for 30 s after that timestamp**. Upload backlogs sparingly and keep live pings
on their own cadence.
⚠️ Each ping call needs a **fresh idempotency key** — reusing one replays the old
`accepted` count instead of recording anything.

### 4.6 Returns, pricing and offers

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RP-057 | POST | `/app/rep/return-requests` | stable |
| EP-APP-030 | POST | `/app/pricing/quote` | stable |
| EP-APP-040 | GET | `/app/offers` | stable |
| EP-APP-041 | GET | `/app/offers/{id}` | stable |

The last three sit at `/app/*`, not `/app/rep/*`, and carry no kind gate.

**`POST /app/rep/return-requests`** — `{sub_order_id, type: return|exchange,
lines:[{line_id, qty, reason, photos?}]}` → `{"request_no":"RR-3","status":"pending"}`.
200, and **no numeric id** is returned.
⚠️ `photos` is an untyped array with no element rules — nothing validates its contents.
⚠️ There is **no rep-side list endpoint** for returns; you can create but not review.

**`POST /app/pricing/quote`** — `{lines:[{product_id, variant_id?, qty}], zone_id}`.
⚠️ Never send `unit_price`; it is stripped. The server prices, always.

```jsonc
{ "data": { "lines": [ {"product_id":12,"variant_id":null,"qty":3,"unit_price":5000,
                        "applied_rule":{"type":"qty_tier","id":null,"label":"…"},
                        "tier":{"from":3,"to":10},
                        "discount":0,"line_total":15000,"offer_id":4} ],
            "subtotal": 15000, "currency": "SYP" } }
```
⚠️ `offer_id` appears **only** on lines an offer matched. `tier` is `null` for simple
products. Errors: `422 product_not_available` with `details.product_id`.

**`GET /app/offers` / `/app/offers/{id}`** — offer cards.

```jsonc
{ "id":4, "image":"…", "name":"…", "company":null, "rating":0,
  "components":[{"product_id":12,"qty":2}],
  "price_before":10000, "discount":2000, "price_after":8000,
  "ends_at":"…|null", "days_left":5, "remaining_qty":40, "sold_count":0 }
```
⚠️ `company`, `rating`, `sold_count` hardcoded. `components[]` is ids only.
⚠️ For a **rep** the offer feed derives `channel_ids` from a *retailer* shopping context,
so a rep user gets an **empty list**. Offers are effectively a retailer feature today —
do not build a rep offers tab against it.
Detail adds `images[]`, `long_description`, `icons` (⚠️ a type string, not an icon) and
`same_company_offers` / `same_company_products`, ⚠️ **both always empty**.

---

## 5. The HTTP contract

Everything in this section applies to every call above. It is the same contract the other
four clients obey, restated here so you never need another file.

### 5.1 The envelope

Success carries `data` and always `meta.server_time`:

```jsonc
{ "data": { }, "meta": { "server_time": "2026-09-07T12:00:00+03:00" } }
```

A paginated list:

```jsonc
{ "data": [ ],
  "meta": { "server_time": "…", "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
```

An error — note it has **no `data` and no `meta`**:

```jsonc
{ "error": { "code": "discount_cap_exceeded", "message": "…", "details": { } } }
```

`204` responses have an **empty body** — do not parse an envelope from them.

**Bind your UI to `data`. Branch on `error.code`, never on `error.message`** — the message
is localised for display and will change.

### 5.2 Headers

Only three custom headers are read by the server. Send the rest for your own logs.

| Header | When | Value | Read by the server? |
|---|---|---|---|
| `Accept` | always | `application/json` | yes |
| `Accept-Language` | always | `ar` \| `en` | **yes** — anything else falls back to `ar` |
| `Authorization` | after login | `Bearer {token}` | yes |
| `X-Idempotency-Key` | every write | UUID per user intent | **yes** |
| `X-Device-Id` | always | stable per install | **yes** — OTP rate limiting |
| `X-Client` | always | `rep-android` | **no** — ignored today |
| `X-App-Version` | always | semver | **no** — ignored today |

⚠️ `X-Client` and `X-App-Version` are read by **no** middleware. Nothing varies by them, so
`426 upgrade_required` cannot currently be triggered by version — do not build a
force-update flow on them yet.

### 5.3 Idempotency

`EnsureIdempotency` is appended to the whole `api` group, so **every** `POST`, `PUT`,
`PATCH` and `DELETE` needs `X-Idempotency-Key`. A missing header fails before your
controller is reached with `400 idempotency_key_required`.

The key is hashed from **method + path + body**:

| You do | Server does |
|---|---|
| Same key, same body, previous call finished 2xx | Replays the stored response, adds `Idempotent-Replayed: true` |
| Same key, **different body** | `409 idempotency_key_conflict` |
| Same key, first call still running | `409 operation_in_progress` |

Stored responses live **24 hours**. Only 2xx are stored — a failed write releases its key
immediately, so the rep can lower a discount and resubmit under the same key.

**Exempt paths — for this app, exactly three:**

```
public/auth/request-otp
public/auth/verify-otp
public/auth/resend-otp
```

Omit the header on those. Send it everywhere else, including logout.

📌 Two writes need extra care beyond the header: **field signup** carries `client_op_id`
(§2.6) and **location pings** need a *fresh* key per call (§4.5).

### 5.4 The error catalogue

| HTTP | `error.code` | What the app does |
|---|---|---|
| 400 | `idempotency_key_required` | Your bug — you omitted the header |
| 401 | `unauthenticated` | No usable token → OTP screen |
| 401 | `token_revoked` | Token deleted or expired → drop it, OTP screen |
| 401 | `otp_invalid` | Wrong/expired code → clear the field, **never** auto-resend |
| 403 | `wrong_guard` | A live token from another guard — **do not** re-login |
| 403 | `insufficient_permission` | Usually a **registration-only token** (§3), the wrong app kind, or a zone that is not yours |
| 403 | **`discount_cap_exceeded`** | The rep exceeded their cap. Show it; **never retry the same %** |
| 404 | `not_found` | Missing **or not yours** — render as missing, never "forbidden" |
| 409 | `illegal_transition` | The order moved on, or the handover is missing. Refetch |
| 409 | `operation_in_progress` | Wait, retry the **same** key |
| 422 | `validation_failed` | Map `details.{field}` onto inputs — also covers off-duty pings and a wrong `temp_code` |
| 422 | `product_not_available` | Product outside your channels; `details.product_id` |
| 429 | `rate_limited` | Back off — OTP is 3/phone/hour, pings have a **30 s floor** |
| 503 | `maintenance_mode` | Maintenance screen |

On `422` the **first** message is flattened into `error.message`, so you can show something
useful without walking `details`. A raw Laravel validation payload never reaches you.

⚠️ Two rep-specific quirks worth memorising: a **missing or foreign handover is `409`, not
`404`**, and an **unapproved return is `404`, not `409`**.

### 5.5 Timestamps

Every timestamp is ISO-8601 in **`Asia/Damascus`** (`+03:00`), already converted. Do not
apply an offset on the client.

⚠️ Prefer `meta.server_time` over the device clock — the ping floor is measured against the
newest stored ping's `at`, so a skewed device clock will produce unexplained `429`s.

### 5.6 Money

**Every amount is an integer in the smallest currency unit.** No floats anywhere.

- **SYP has 0 decimals**, so the integer *is* the displayed number. Never `/ 100`.
- The rep cart returns **no line totals and no summary** — sum sections yourself for
  display, but trust the server's per-section `total`.
- Discount maths on the server is **integer truncating** (`intdiv`), applied independently
  at line and section level, so the sum of lines can differ from `sub_order.total` by a few
  units. The server is right.

### 5.7 Lists

| Param | Default | Notes |
|---|---|---|
| `page` | 1 | |
| `per_page` | **25** | **hard max 100 — a larger value is silently clamped**, not rejected |
| `filter[x]` | — | bracket syntax |

⚠️ **Never send `sort`** — no `allowedSorts` is declared and Spatie throws.
⚠️ Search parameters are **inconsistent**: `/customers` uses `filter[search]`, while
`/zones/{id}/shops` uses a top-level `search`. Products use top-level `zone`, and
`filter[zone_id]` throws.

Several endpoints return plain unpaginated arrays: `assignments`, `scheduled-orders`,
`deliveries`, `cart`.

---

## 6. What to build, in order

| # | Screen | Endpoints | Notes |
|---|---|---|---|
| 1 | Splash / reachability | `GET /health` | |
| 2 | Phone → OTP → verify | `request-otp`, `verify-otp`, `resend-otp` | 60 s timer from `resend_after` |
| 3 | Registration | `POST /app/rep/register` | ⚠️ then **verify a second OTP** (§2) |
| 4 | Session bootstrap | `GET /app/session` | route on `profile_completed` |
| 5 | Duty toggle | `PATCH /app/rep/status` | gate the whole day on it — pings need it |
| 6 | My zones → shops | `zones/{id}/shops` | search is top-level `search` |
| 7 | Customers + field signup | `customers` | ⚠️ send `client_op_id`; search is `filter[search]` |
| 8 | Product browse | `products` | show `channel.name`; use `zone`, never `filter[zone_id]` |
| 9 | Build order per shop | `cart`, `cart/lines` | resolve names locally — lines have none |
| 10 | Submit with discount | `cart/sections/{id}/submit` | expect `403 discount_cap_exceeded`; cap is unreadable |
| 11 | Assignments inbox | `assignments`, accept/reject | |
| 12 | Warehouse pickup | `warehouse-receipts`, confirm | 4-char code — **gate for delivery** |
| 13 | Delivery route | `deliveries`, detail, line patch | |
| 14 | Complete / postpone / fail | the three POSTs | ⚠️ postpone loses the date |
| 15 | Location pings | `locations/ping` | on duty + 30 s floor |
| 16 | Returns | `return-requests` | create only; no list |

Do not build a wallet, a cash-collection screen, a receivables list, an offline sync
banner or a notifications inbox — §5.

---

## 7. Not built — do not mock

Every path below **returns 404 today**. No stub, no feature flag. Wire nothing to them, and
do not fake the numbers: a fabricated wallet balance in a cash-handling app is a financial
incident, not a UI placeholder.

**Rep money — SP-13** (blocks the entire collection flow)

| Method | Path | EP |
|---|---|---|
| POST | `/app/rep/payments` | EP-RP-060 |
| GET | `/app/rep/wallet` | EP-RP-061 |
| POST | `/app/rep/wallet/withdrawals` | EP-RP-062 |
| GET | `/app/rep/wallet/withdrawals` | EP-RP-063 |
| GET | `/app/rep/receivables` | EP-RP-064 |
| POST | `/app/receipts/reserve` | EP-CM-050 |

Consequence: `ask_payment: true` from `complete` points nowhere, and `max_cash_hold`
(settable by the channel) is unenforceable in the app. Hide any money UI.

**Offline sync and notifications — SP-14**

| Method | Path | EP |
|---|---|---|
| GET | `/app/sync/pull` | EP-SY-001 |
| POST | `/app/sync/push` | EP-SY-002 |
| GET | `/app/sync/status` | EP-SY-003 |
| POST | `/app/sync/resolve-conflict` | EP-SY-004 |
| GET | `/app/notifications` | EP-CM-060 |
| POST | `/app/notifications/read-all` | EP-CM-061 |
| DELETE | `/app/notifications` | EP-CM-062 |
| POST | `/app/devices/push-token` | EP-CM-063 |

⚠️ **An outbox has nowhere to drain.** Build online-first. The one genuine offline path is
`client_op_id` on `POST /app/rep/customers` (§3.2) — a field signup with no signal is
replay-safe. Nothing else is.

**Content and loyalty — SP-15**

| Method | Path | EP |
|---|---|---|
| GET | `/app/content/home-blocks` | EP-APP-100 |
| GET | `/app/loyalty` | EP-APP-110 |
| POST | `/app/loyalty/redeem` | EP-APP-111 |

📌 The catalog path is **`GET /app/loyalty`** (EP-APP-110). Older internal notes say
`/app/loyalty/wallet` — that path is neither in the catalog nor registered. Use
`/app/loyalty` when it ships.

**Bootstrap references**

| Method | Path | EP | Note |
|---|---|---|---|
| GET | `/public/refs` | EP-PB-001 | **deliberately absent** — see the comment in `app-modules/reference/routes/api.php` |
| GET | `/public/app-config` | EP-PB-010 | not built |

**Also missing on the rep side specifically:** any endpoint exposing the rep's discount cap,
any rep-side returns list, and a `sub_order_id` on `/warehouse-receipts` rows.

---

## 8. Gotchas

| # | Where | Watch out |
|---|---|---|
| 1 | `verify-otp` | An incomplete profile yields a **`registration`-only** token. Register, then **verify a second OTP** — registration never upgrades your token |
| 2 | Any `/app/retailer/*` call | One phone is one kind. Cross-kind is `403 insufficient_permission`; no switcher |
| 3 | Pre-login screens | No `/public/refs`. Zones and activity types come **after** the token, or bundled |
| 4 | All writes | `X-Idempotency-Key` mandatory. Exempt: only the three OTP paths |
| 5 | `POST /customers` | **`client_op_id` is required** and is the only true offline-replay key in the API. Send it *and* the header |
| 6 | Search params | `/customers` uses `filter[search]`; `/zones/{id}/shops` uses top-level `search` |
| 7 | `products` | Use top-level `zone`. `filter[zone_id]` throws. Never send `sort` |
| 8 | `products` | Prices are zone+channel at qty 1, never retailer-specific — re-quote before promising |
| 9 | rep cart | Lines have **no `name` and no `line_total`**, and there is no top-level summary |
| 10 | `submit` | `note` is validated then **silently dropped** |
| 11 | `submit` | Discount cap defaults to **0** with no limit row, is **not exposed by any endpoint**, and resolves against your *first* channel |
| 12 | `assignments/{id}/reject` | `"unassigned"` is a stage label, not a real status |
| 13 | `scheduled-orders` | **No `id`** in the rows — cannot navigate from them |
| 14 | `warehouse-receipts` | Rows carry no `sub_order_id`; `count` counts sub-orders, not handovers |
| 15 | `.../confirm` | **Gate for all deliveries.** Re-confirming succeeds **even with a wrong code** |
| 16 | `.../confirm` | Missing or foreign handover is **409**, not 404 |
| 17 | `GET /deliveries` | Has **write side effects** — do not poll it |
| 18 | `GET /deliveries` | `delivered` hardcoded `0`; `ordered_at` null |
| 19 | `deliveries/{id}` | `qty` is *expected*; `qty_delivered` is never exposed |
| 20 | `complete` | 409 until the handover is confirmed. `ask_payment` is hardcoded and leads nowhere |
| 21 | `postpone` | `scheduled_at` and `reason` are **ignored** — the date is lost |
| 22 | `fail` | `reason` is **ignored** |
| 23 | `locations/ping` | Needs on-duty (else 422) and honours a **30 s floor vs the newest stored `at`** (else 429) |
| 24 | `locations/ping` | Uploading a backlog can lock out live pings; each call needs a **fresh** key |
| 25 | `offers` | The feed resolves channels from a *retailer* context — a rep gets an empty list |
| 26 | `return-requests` | Create only; no rep-side list. No numeric id returned |
| 27 | `not_found` | Also means "not yours". Render as missing, never as forbidden |
| 28 | Money | Integers, minor units. SYP has 0 decimals — never `/ 100` |
