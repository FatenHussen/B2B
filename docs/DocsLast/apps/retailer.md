# Retailer app (Flutter)

**This is the only file you need.** Backend setup, the Dart package, the HTTP contract and
every endpoint — all of it is here. No other document is required to build this app.

| | |
|---|---|
| Path | `apps/retailer` |
| Platform | Flutter — Android + iOS |
| Guard | `app` (Sanctum bearer) |
| Kind gate | `RequireAppKind:retailer` on every `/app/retailer/*` route |
| `X-Client` | `retailer-android` / `retailer-ios` *(sent for logs; the server ignores it)* |
| Seed account | **none** — create one with an OTP for any Syrian number (§3) |

Verified against `php artisan route:list` and the controller/action source on
**2026-09-07**. Where the API catalog disagrees with this page, the code wins.

**37 endpoints are reachable from this app** — 28 under `/app/retailer`, 5 shared `/app/*`,
the 3 public OTP calls and `/health`. Everything about *goods* — browse, cart, submit,
track, receive, return — is built. Everything about *money*, *offline sync* and
*engagement* is not (§7).

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

### 1.3 The seeder creates no app user

`database/seeders/DatabaseSeeder.php` creates a platform admin, a channel manager and one
supply channel — **and no `app` user at all.** Your retailer account is created by verifying
an OTP for any Syrian phone number (§3). Do not look for seeded mobile credentials.

### 1.4 OTP is switched off during development

`OTP_BYPASS=true` in the API `.env` turns verification off **until release**. The flow does
not change — call `request-otp`, take the `otp_id`, call `verify-otp` — but any
6-character `code` (send `000000`) verifies, no code is sent, and the resend cooldown and
the per-phone rate limit are skipped. Build the real OTP screen anyway: the switch goes back
to `false` before release and the flow below is what runs then.

The switch is ignored when `APP_ENV=production`; if a shared server does not accept
`000000`, that is why.

With `OTP_BYPASS=false`, no WhatsApp message is sent locally. `config/otp.php` defaults
`OTP_CHANNEL=log`:

```bash
tail -f storage/logs/laravel.log | grep OTP
# [OTP] register via whatsapp for +963933000000: 481923
```

| Setting | Default | Meaning |
|---|---|---|
| `otp.ttl` | **300 s** | code lifetime |
| `otp.length` | **6** | digits — `code` is validated `size:6` |
| `otp.max_attempts` | **5** | wrong codes before the OTP dies |
| `otp.resend_cooldown` | **60 s** | before a resend is accepted |

Rate limits per hour: **3 per phone**, 10 per `X-Device-Id`, 30 per IP. Three per phone is
easy to burn while testing — switch numbers rather than wait.

### 1.5 Queues

`php artisan horizon` supervises `critical`, `default`, `media`, `reports`. You rarely need
it for this app: nothing the retailer calls is asynchronous.

---

## 2. The Flutter package

One shared Dart package, `packages/b2b_api`. No screen builds a `Dio` call by hand.

The rep app uses the identical package — only the paths differ, and the server enforces
that with `RequireAppKind`.

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
Submit — not to an HTTP attempt. This matters more on mobile than anywhere else: a request
that times out on a train has very often already been executed.

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
class _SubmitCartButtonState extends State<SubmitCartButton> {
  late final String _intentId = 'cart.submit.${widget.cartId}';
  bool _busy = false;

  Future<void> _submit() async {
    setState(() => _busy = true);
    try {
      // Tap ten times on a bad connection: one order.
      await api.submitCart(idempotencyKey: keys.forIntent(_intentId));
      keys.release(_intentId);
    } on ApiError catch (e) {
      if (e.isRetryable) {
        _toast('Still processing — try again.');   // same key on the next tap
      } else {
        _toast(e.message);
      }
      // Deliberately NOT released.
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}
```

⚠️ Never mint the key inside the Dio interceptor. A key per request turns one submit into
several orders — the exact failure the header exists to prevent.

⚠️ Persist unresolved keys if you want retry-after-restart. An in-memory map loses the key
when the app is killed mid-submit, and the retry then creates a second order.

### 2.6 `client.dart`

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

### 2.7 Handling errors once

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
  if (e.code == 'rate_limited') { /* back off — OTP is 3/phone/hour */ return; }
  if (e.code == 'not_found')    { /* gone or not yours — never say "forbidden" */ return; }
  if (e.code == 'upgrade_required') { /* force-update screen */ return; }
  showToast(e.message);
}
```

⚠️ `not_found` also means "not yours". The API never discloses that a resource exists but
belongs to someone else — render it as missing, never as a permission problem.

### 2.8 Money

```dart
String formatMoney(int amount, {int decimals = 0}) {
  if (decimals == 0) return NumberFormat.decimalPattern('ar').format(amount);
  return NumberFormat.decimalPattern('ar').format(amount / pow(10, decimals));
}
```

Integers in the smallest unit. **SYP has 0 decimals**, so the integer is the number you
show. Never `/ 100` by reflex, and never total a cart locally for display — re-read the
server's total after every mutation.

### 2.9 Offline

There is **no sync endpoint** — `/app/sync/*` all 404 (§7). An offline outbox has nowhere
to drain. Build **online-first**: cache reads and queue writes locally if you like, but
never ship a UI that promises deferred delivery.

📌 The retailer app has **no** `client_op_id` mechanism. The idempotency header is your only
replay protection. (One rep endpoint has one; nothing here does.)

---

## 3. A token in two minutes — and the trap that costs a week

### ⚠️ Read this before writing the login screen

`verify-otp` returns a token whose **abilities depend on whether the profile is complete**:

| Profile | Abilities | What the token opens |
|---|---|---|
| incomplete | `['registration']` | **only** `/app/retailer/register` and `/app/rep/register` |
| complete | `['*']` | everything |

**Registration does not upgrade the token in your hand.** `RegisterRetailer` writes the
profile and returns — it never re-issues a token. So the obvious flow is wrong:

```
request-otp → verify-otp → register → GET /app/retailer/home     ❌ 403 forever
```

The token you are still holding has `registration` only. You must **verify a second OTP**
after registering:

```
request-otp → verify-otp  (token: registration-only)
            → POST /app/retailer/register
            → request-otp → verify-otp  (token: *)   ← the second round trip is mandatory
            → GET /app/retailer/home                              ✅
```

Branch on `profile_completed` in the verify response, never on "do I have a token".
Persist that flag with the token — see `TokenStore` in the
§2.4.

Symptom if you get this wrong: login appears to work, then **every** product call returns
403, and clearing storage and logging in again reproduces it exactly.

⚠️ **One phone is one kind.** `RequireAppKind` compares `AppUser.kind` to the route's kind.
A retailer token on `/app/rep/*` is `403 insufficient_permission`. There is no switcher and
no way to be both — a tester who registered as a rep needs a different phone number.

### The calls

```bash
# 1. request a code (no auth, NO idempotency key)
curl -s -X POST http://127.0.0.1:8000/api/v1/public/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"+963933000000","purpose":"register","client":"retailer-android"}'
# { "otp_id":"otp_ab12cd", "channel_used":"whatsapp", "expires_in":300, "resend_after":60 }

# 2. read the code from the log — no WhatsApp is sent locally
tail -n 50 storage/logs/laravel.log | grep OTP

# 3. verify (no auth, NO idempotency key)
curl -s -X POST http://127.0.0.1:8000/api/v1/public/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"otp_id":"otp_ab12cd","code":"123456","device_id":"dev-uuid-1"}'
```

```jsonc
{ "data": {
    "token": "12|xxxx",
    "is_new_user": true,
    "user_type": null,              // "retailer" | "rep" once chosen
    "profile_completed": false,     // ← branch on THIS
    "user": { "id": 7, "name": "", "phone": "+963933000000" }
  }, "meta": { "server_time": "..." } }
```

```bash
# 4. register (Bearer = the registration-only token, idempotency key REQUIRED)
curl -s -X POST http://127.0.0.1:8000/api/v1/app/retailer/register \
  -H 'Authorization: Bearer 12|xxxx' -H 'X-Idempotency-Key: 11111111-1111-1111-1111-111111111111' \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"owner_name":"...","shop_name":"...","activity_type_id":1,"category_ids":[1],
       "governorate_id":1,"zone_id":1}'

# 5. verify a SECOND OTP to get the "*" token, then:
curl -s http://127.0.0.1:8000/api/v1/app/retailer/home -H 'Authorization: Bearer {new token}'
```

OTP limits: TTL **300 s**, **5** wrong codes kills it, resend cooldown **60 s**, and
**3 requests per phone per hour** (also 10/device, 30/IP). Three per hour is easy to burn
while testing — switch numbers rather than wait.

### ⚠️ There is no pre-login reference data

`GET /public/refs` and `GET /public/app-config` **do not exist** — the reference routes
(`/api/v1/governorates`, `/api/v1/zones`) sit behind other guards, not `app`. A screen
shown *before* the OTP cannot fetch governorates, zones, activity types or categories.

Registration needs `activity_type_id`, `category_ids`, `governorate_id`, `zone_id`, so
either collect them **after** the token exists, or **bundle them in the binary** and
refresh later. Design the registration flow around this now; it is not a bug to be fixed
before you ship.

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
| EP-RT-001 | POST | `/app/retailer/register` | stable | `registration` ability |
| EP-CM-004 | GET | `/app/session` | stable | |
| EP-CM-005 | POST | `/app/auth/logout` | stable | |
| EP-CORE-001 | GET | `/health` | stable | unguarded probe |

**`POST /public/auth/request-otp`** — `phone` (Syrian, normalised server-side), `purpose`
(`login|register`), `client` (optional, ≤64).
Errors: `422 validation_failed`, `429 rate_limited`.

**`POST /public/auth/verify-otp`** — `otp_id`, `code` (**exactly 6**), `device_id`
(required, ≤64), `device_name`, `platform`. Shape above.
Errors: `401 otp_invalid` (wrong, expired, consumed, or 5 attempts burned).

**`POST /public/auth/resend-otp`** — `otp_id`, `prefer_channel` (`whatsapp|sms`).
Returns `{ channel_used, resend_after }` — **no `otp_id`**, reuse the one you have.
Errors: `429 rate_limited` inside the 60 s cooldown.

**`POST /app/retailer/register`** → **201**

```jsonc
{ "owner_name": "…",  "shop_name": "…",       // required, ≤120 / ≤160
  "activity_type_id": 1,                       // required
  "category_ids": [1, 2],                      // required, min 1
  "equipment_ids": [],                         // optional
  "governorate_id": 1, "zone_id": 4,           // both required
  "lat": null, "lng": null, "address": null }  // optional
```

Remember: this does **not** upgrade your token (§2).

**`GET /app/session`**

```jsonc
{ "data": {
    "user": { "id": 7, "name": "…", "user_type": "retailer", "profile_completed": true },
    "permissions": [ ],
    "feature_flags": { "offline_orders": false, "loyalty": false },
    "sync_cursor": "",
    "server_time": "…",
    "requires_legal_accept": false,
    "legal": { "privacy_version": "2026-03", "terms_version": "2026-01" }
  } }
```

⚠️ `sync_cursor` is always `""` and `requires_legal_accept` always `false` — placeholders.
`feature_flags` comes from config and both flags are `false`; honour them (both features
are unbuilt anyway).

### 4.2 Catalog

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RT-010 | GET | `/app/retailer/home` | stable |
| EP-RT-011 | GET | `/app/retailer/categories` | stable |
| EP-RT-012 | GET | `/app/retailer/products` | stable |
| EP-RT-013 | GET | `/app/retailer/products/{id}` | stable |
| EP-RT-014 | POST | `/app/retailer/products/{id}/favorite` | stable |
| — | GET | `/app/retailer/brands` | stable* |
| — | GET | `/app/retailer/brands/{id}` | stable* |
| — | GET/POST/DELETE | `/app/retailer/shortages`, `/shortages/{id}` | stable* |

\* live and callable, but carry **no EP-ID** — the catalog has not caught up. Use them;
expect the shape to be confirmed later. This is the same situation `CLAUDE.md` describes
for `sc.notify.view`.

**`GET /app/retailer/home`** — one call, the whole home screen.

```jsonc
{ "data": {
    "header": { "shop_name": "…", "zone": {"id":4,"name":"…"},
                "points": 0, "tier": null, "unread_notifications": 0, "pending_sync": 0 },
    "banner": null,
    "quick_actions": [ {"key":"orders","count":0}, {"key":"cart","count":0}, {"key":"debts","count":0} ],
    "stats": { "orders_count": 0, "total_debt": 0, "delivering_today": 0 },
    "categories": [ {"id":1,"name":"…","image":"…"} ],
    "offers_slider": [ /* offer cards, §3.5 */ ],
    "brands_slider": [ {"id":1,"name":"…","logo":"…|null"} ],
    "dynamic_sliders": []
  } }
```

⚠️ **Hardcoded — do not render as data.** `points`, `tier`, `unread_notifications`,
`pending_sync`, `banner`, every `quick_actions[].count`, all three `stats`, and
`dynamic_sliders` are literal `0`/`null`/`[]` in the source. They are not "currently
empty"; nothing computes them. Show nothing rather than a zero that looks real —
especially `total_debt`, since the whole finance surface is unbuilt (§7).

`categories` and `offers_slider`/`brands_slider` **are** real.

**`GET /app/retailer/categories`** — plain array, **not paginated**.
Params: `parent_id`, `level`. No `parent_id` (or `level=1`) → root categories; otherwise
children.

```jsonc
{ "data": [ {"id":1,"name":"…","image":"…","children_count":3,"products_count":42} ] }
```

⚠️ `image` is a **raw column string** on root categories but a **resolved media URL** on
sub-categories. Same key, two meanings — normalise in your mapper.

**`GET /app/retailer/products`** — paginated. The **product card** is the shape reused by
`home`, search and detail:

```jsonc
{ "id": 12, "name": "…",
  "brand": { "id": 3, "name": "…" },        // or null
  "image": "…|null",
  "sale_unit": "…|null", "min_order_qty": 1,
  "price": { "type": "simple|tiered", "value": 5000,
             "from": 5000, "to": 5000, "label": "…" },
  "discount": 0, "price_after": 5000, "sold_count": 0,
  "availability": "in_stock|low|out_of_stock",
  "lead_time_days": null, "rating": 0,
  "is_favorite": false, "has_offer": true, "variants_count": 2 }
```

⚠️ `price.from` and `price.to` are **both always equal to `price.value`** — they are *not*
a range. Do not render "from X to Y". For a tiered product render `price.label`, which the
server localises.
⚠️ `discount`, `sold_count` and `rating` are hardcoded `0`; `price_after` always equals
`price.value`. Do not draw a strike-through price or a rating widget from these.

Filters: `filter[category_id]`, `filter[brand_id]`, `filter[search]` (name or SKU),
`filter[available_only]=1`, `barcode` (top level, not under `filter`).
⚠️ `filter[offer_only]=1` is a **stub that always returns zero rows** (`whereRaw('0=1')`).
Do not ship that toggle.
⚠️ **Never send `sort`** — no `allowedSorts` is declared, so any `sort` param throws a
Spatie error. Order is `-created_at`.

**`GET /app/retailer/products/{id}`** — the card **plus**:

```jsonc
{ "images": ["…"], "model_no": null, "long_description": null,
  "specs": [ {"key":"…","value":"…"} ],
  "variants": [ {"id":9,"combination":{"اللون":"أحمر"},"price":5000,
                 "availability":"in_stock","barcode":null} ],
  "sliders": { "price_comparison": [], "same_brand": [], "similar": [], "suggested": [] } }
```

⚠️ All four `sliders` are **always empty**. ⚠️ Every variant reports the **parent
product's** price and availability — the code quotes `$product->id` inside the loop, so
per-variant pricing is not real yet. Show one price for the product, not per variant.
404 if the product is outside your visible catalog.

**`POST /app/retailer/products/{id}/favorite`** — toggle, returns
`{ "is_favorite": true|false }`. 200, not 201. Idempotency key required.

**`GET /app/retailer/brands`** — paginated; `{id, name, logo, banner}`.
`filter[activity_type_id]`. No `sort` (same trap).
**`GET /app/retailer/brands/{id}`** — `{banner, logo, name, activities[], description,
categories[], sliders}`. ⚠️ **No `id` key**, and `sliders` is always
`[{"key":"best_selling","items":[]}]`.

**Shortages** — `GET` paginated `{id, product_id, name, note}`; `POST` **201**
`{product_id, note?}` → `{id}`; `DELETE /{id}` returns **200** `{success:true}`, not 204.
A product outside your catalog → `422` with `details.product_id`.

### 4.3 Cart

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RT-020 | GET | `/app/retailer/cart` | stable |
| EP-RT-021 | POST | `/app/retailer/cart/lines` | stable |
| EP-RT-022 | PATCH | `/app/retailer/cart/lines/{id}` | stable |
| EP-RT-023 | DELETE | `/app/retailer/cart/lines/{id}` | stable |
| EP-RT-024 | PATCH | `/app/retailer/cart/sections/{ref}` | stable |
| EP-RT-025 | POST | `/app/retailer/cart/submit` | stable |

All five non-submit calls return **the same cart payload**:

```jsonc
{ "data": {
    "sections": [ {
        "supply_channel_ref": "…",              // opaque; the real channel id is never exposed
        "temp_order_no": "T-3-8",               // display only, not a real order number
        "note": null,
        "lines": [ {"id":22,"product_id":12,"name":"…","qty":3,
                    "unit_price":5000,"line_total":15000} ],
        "subtotal": 15000, "discount": 0, "total": 15000
      } ],
    "summary": { "grand_total": 15000,          // PRE-discount
                 "total_discount": 0,
                 "final_total": 15000,          // payable
                 "estimated_delivery": null },
    "applied_offers": [ 4 ]
  } }
```

⚠️ `summary.grand_total` is the **pre**-discount sum and `final_total` is what the user
pays. The names invite the opposite reading — bind the total line to `final_total`.
⚠️ `estimated_delivery` is always `null`.
⚠️ The cart never exposes the supply channel's identity while you are shopping — that is
deliberate (`supply_channel_ref` is opaque). Do not try to resolve it.

**`POST /cart/lines`** — `{product_id, variant_id?, qty≥1, source?}` where `source` is
`browse|shortage|favorite|reorder|offer`. Adding an existing `(product, variant)` **adds
to** the existing qty. Returns the cart. 200, not 201.

**`PATCH /cart/lines/{id}`** — `{qty≥0}`. **`qty: 0` deletes the line.**
⚠️ On `qty ≥ 1` the response carries an **extra key** `removed_offers: [int]` — offers
that no longer apply after repricing. It is **absent** on the `qty: 0` path. Show it, or
the user silently loses a promotion.

**`DELETE /cart/lines/{id}`** — returns the cart. ⚠️ If the line belongs to an offer,
**every line sharing that `offer_id` is deleted too.** Warn before deleting an offer line.

**`PATCH /cart/sections/{ref}`** — `{note?, scheduled_at?}`; `{ref}` is
`supply_channel_ref`. Omitting a key preserves it; **sending explicit `null` also
preserves it**. `scheduled_at` is stored but never echoed back.

**`POST /cart/submit`** → 200

```jsonc
// request
{ "sections": [ {"ref":"…","note":"…","scheduled_at":null} ],
  "client_created_at": null, "offline_created": false }
```
```jsonc
// response
{ "data": {
    "order": { "id": 5, "order_no": "ORD-5",
               "sub_orders": [ {"id":9,"sub_order_no":"SO-9","status":"pending","total":15000} ] },
    "repricing_diff": null       // int when the total moved between cart and submit
  } }
```

⚠️ Unlike the section PATCH, **omitting `note` here overwrites it with `null`.** Send the
note back if you want to keep it.
⚠️ Show `repricing_diff` when non-null — the price changed under the user.
Errors: `422 validation_failed` (empty cart), `409 offer_no_longer_valid` (an offer died
during submit; suppressed when `offline_created: true`).

### 4.4 Orders, receipts, returns

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-RT-030 | GET | `/app/retailer/orders` | stable |
| EP-RT-031 | GET | `/app/retailer/orders/{id}` | stable |
| EP-RT-032 | POST | `/app/retailer/orders/{id}/cancel` | stable |
| EP-RT-033 | POST | `/app/retailer/orders/{id}/reorder` | stable |
| EP-RT-034 | GET | `/app/retailer/orders/{id}/tracking` | stable |
| EP-RT-040 | GET | `/app/retailer/receipts/{subOrderId}` | stable |
| EP-RT-041 | PATCH | `/app/retailer/receipts/{id}/lines/{lineId}` | stable |
| EP-RT-042 | POST | `/app/retailer/receipts/{id}/confirm` | stable |
| EP-RT-043 | POST | `/app/retailer/return-requests` | stable |
| EP-RT-044 | GET | `/app/retailer/return-requests` | stable |
| EP-RT-045 | POST | `/app/retailer/reps/{id}/rate` | stable |

**`GET /orders`** — paginated.

```jsonc
{ "id": 9, "sub_order_no": "SO-9", "created_at": "…",
  "supply_channel": { "id": 2, "name": "…" },   // null while pending or rejected
  "status": "pending", "total": 15000,
  "can_cancel": true, "can_reorder": true }
```

⚠️ **`supply_channel` is `null` while the status is `pending` or `rejected`** — the
supplier's identity is concealed until they accept. Render "قيد المراجعة", not an empty
name. ⚠️ `can_reorder` is hardcoded `true`.

Filters: `filter[status]` — `all` (default), `active` (excludes delivered/cancelled/
rejected), `processing` (matches `processing` **or** `awaiting_handover`), or any literal
status. Plus top-level `date_from` / `date_to`.

Statuses: `pending, confirmed, rejected, cancelled, assigned, accepted, processing,
awaiting_handover, on_the_way, delivered, undelivered, postponed`.

**`GET /orders/{id}`**

```jsonc
{ "data": {
    "lines": [ {"id":31,"name":"…","qty":3,"price":5000} ],
    "invoice": { "id": 2, "no": "INV-2", "total": 15000 },   // null until issued
    "timeline": [ {"stage":"pending","at":"…"} ],
    "rep": { "name": "…", "phone": "…" }                     // null unless on_the_way
  } }
```

⚠️ This payload has **no id, no `sub_order_no`, no status and no totals** — carry them
from the list row. `lines[].price` is the **unit** price, not the line total.

**`POST /orders/{id}/cancel`** — `{reason}` (required, ≤255) → `{"status":"cancelled"}`.
⚠️ Only a **`pending`** order can be cancelled; anything else is `409 illegal_transition`.
Also 409 once the warehouse handover is confirmed. Gate the button on `can_cancel`.

**`POST /orders/{id}/reorder`** → ⚠️ **`{ "data": { "cart": { …cart payload… } } }`** —
nested one level deeper than every other cart response. Lines are **appended** to the
existing cart and duplicates are **not** merged.

**`GET /orders/{id}/tracking`**

```jsonc
{ "data": { "stages": [ {"key":"pending","label":"pending","at":"…"} ],
            "rep": { "name":"…","phone":"…","lat":null,"lng":null },
            "eta_minutes": null } }
```

⚠️ `eta_minutes` always `null`; `rep.lat`/`lng` always `null` (**no live map is
possible**); `label` is identical to `key` — **you must localise stage names yourself.**

**`GET /receipts/{subOrderId}`** — the delivery check-in list.

```jsonc
{ "data": { "lines": [ {"id":44,"image":null,"name":"…","brand":"…",
                        "variant":null,"qty":3,"price":5000,"status":"pending"} ],
            "invoice_total": 15000 } }
```

`lines[].id` is the **delivery-line id** — that is the `{lineId}` below. `qty` is the
**expected** quantity. ⚠️ `image` and `variant` are always `null`.

**`PATCH /receipts/{id}/lines/{lineId}`** — `{qty_received, action, reason?}` where
`action` ∈ `accept|adjust|return|exchange`. Send **`qty_received`** (the retailer alias).
→ `{ "new_invoice_total": 12000 }`.

**`POST /receipts/{id}/confirm`** — `{lines:[{line_id, qty_received, action}], delivered_at?, signature?}`

```jsonc
{ "data": { "invoice": {"no":"INV-2","total":12000},
            "receipt_no": "…", "ask_payment": true } }
```

⚠️ `ask_payment` is hardcoded `true` — but **there is no payments endpoint** (§7), so it
cannot lead anywhere yet. Do not open a payment screen.
⚠️ `409 illegal_transition` until the rep has confirmed the warehouse handover — that is
`delivery.no_handover`, not a bug in your request.

**Returns** — `POST` `{sub_order_id, type: return|exchange, lines:[{line_id, qty, reason,
photos?}]}` → `{ "request_no": "RR-3", "status": "pending" }` (200, **no numeric id**).
`GET` returns a **plain array, unpaginated, unfiltered**, of `{request_no, type, status}`
only.

**`POST /reps/{id}/rate`** — `{stars: 1..5, note?, tags?}` → `{success:true}`.
⚠️ The rating attaches to that rep's **most recent delivered delivery globally**, not
necessarily one of yours. Offer it only right after your own delivery.

### 4.5 Pricing and offers (shared with the rep app)

| EP-ID | Method | Path | Stability |
|---|---|---|---|
| EP-APP-030 | POST | `/app/pricing/quote` | stable |
| EP-APP-040 | GET | `/app/offers` | stable |
| EP-APP-041 | GET | `/app/offers/{id}` | stable |

These sit at `/app/*`, **not** `/app/retailer/*`, and carry no kind gate.

**`POST /app/pricing/quote`** — `{lines:[{product_id, variant_id?, qty}], zone_id}`.
⚠️ Never send `unit_price`; it is stripped. The server prices, always.

```jsonc
{ "data": {
    "lines": [ { "product_id":12, "variant_id":null, "qty":3, "unit_price":5000,
                 "applied_rule": {"type":"qty_tier","id":null,"label":"…"},
                 "tier": {"from":3,"to":10},      // null for simple products
                 "discount": 0, "line_total": 15000,
                 "offer_id": 4 } ],               // present ONLY when an offer matched
    "subtotal": 15000, "currency": "SYP" } }
```

⚠️ `offer_id` is a **conditionally present key** — absent on lines with no offer.
Errors: `422 product_not_available`, with `details.product_id` (and sometimes `details.line`).

**`GET /app/offers`** — paginated offer cards; `filter[zone_id]`, `filter[activity_type_id]`
default to your profile.

```jsonc
{ "id":4, "image":"…", "name":"…", "company":null, "rating":0,
  "components":[{"product_id":12,"qty":2}],
  "price_before":10000, "discount":2000, "price_after":8000,
  "ends_at":"…|null", "days_left":5, "remaining_qty":40, "sold_count":0 }
```

⚠️ `company`, `rating` and `sold_count` are hardcoded. `components[]` gives ids only — no
name, no image; resolve them yourself. `remaining_qty` is `null` for uncapped offers.

**`GET /app/offers/{id}`** — the card plus `images[]`, `long_description`,
`icons` (⚠️ always `["<offer type>"]`, a type string, not an icon URL), and
`same_company_offers` / `same_company_products` — ⚠️ **both always empty**.

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
{ "error": { "code": "validation_failed", "message": "…", "details": { "phone": ["…"] } } }
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
| `X-Client` | always | `retailer-android` | **no** — ignored today |
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
immediately, so the user can fix a validation error and resubmit under the same key.

**Exempt paths — for this app, exactly three:**

```
public/auth/request-otp
public/auth/verify-otp
public/auth/resend-otp
```

Omit the header on those. Send it everywhere else, including logout.

### 5.4 The error catalogue

| HTTP | `error.code` | What the app does |
|---|---|---|
| 400 | `idempotency_key_required` | Your bug — you omitted the header |
| 401 | `unauthenticated` | No usable token → OTP screen |
| 401 | `token_revoked` | Token deleted or expired → drop it, OTP screen |
| 401 | `otp_invalid` | Wrong/expired code → clear the field, **never** auto-resend |
| 403 | `wrong_guard` | A live token from another guard — **do not** re-login |
| 403 | `insufficient_permission` | Usually a **registration-only token** on this guard (§3), or the wrong app kind |
| 403 | `profile_incomplete` | Registration not finished → register screen |
| 404 | `not_found` | Missing **or not yours** — render as missing, never "forbidden" |
| 409 | `illegal_transition` | The order is not in a state that allows this. Refetch |
| 409 | `operation_in_progress` | Wait, retry the **same** key |
| 409 | `offer_no_longer_valid` | An offer died during submit |
| 409 | `idempotency_key_conflict` | Same key, different body — your bug |
| 422 | `validation_failed` | Map `details.{field}` onto inputs |
| 422 | `product_not_available` | Product outside your catalog; `details.product_id` |
| 423 | `plan_limit_exceeded` | Stop the spinner; do not retry |
| 429 | `rate_limited` | Back off — OTP is 3/phone/hour |
| 503 | `maintenance_mode` | Maintenance screen |

On `422` the **first** message is flattened into `error.message`, so you can show something
useful without walking `details`. A raw Laravel validation payload never reaches you, and
no endpoint returns HTML.

### 5.5 Timestamps

Every timestamp is ISO-8601 in **`Asia/Damascus`** (`+03:00`), already converted. Do not
apply an offset on the client. Prefer `meta.server_time` over the device clock for
anything the server will judge — expiries, cooldowns, ordering.

### 5.6 Money

**Every amount is an integer in the smallest currency unit.** No floats anywhere.

- **SYP has 0 decimals**, so the integer *is* the displayed number. Never `/ 100`.
- Never compute a total locally and treat it as truth — re-read the server's total after
  every cart mutation.
- Where the server sends a preformatted label (`price.label`), render the label.

### 5.7 Lists

| Param | Default | Notes |
|---|---|---|
| `page` | 1 | |
| `per_page` | **25** | **hard max 100 — a larger value is silently clamped**, not rejected |
| `filter[x]` | — | bracket syntax |

⚠️ **Never send `sort`** on this app's lists — no `allowedSorts` is declared and Spatie
throws. Two endpoints return plain unpaginated arrays: `categories` and `return-requests`.

---

## 6. What to build, in order

Each step depends only on live endpoints.

| # | Screen | Endpoints | Notes |
|---|---|---|---|
| 1 | Splash / reachability | `GET /health` | fail fast on no network |
| 2 | Phone → OTP → verify | `request-otp`, `verify-otp`, `resend-otp` | 60 s resend timer from `resend_after` |
| 3 | Registration | `POST /app/retailer/register` | ⚠️ then **verify a second OTP** (§2) |
| 4 | Session bootstrap | `GET /app/session` | route on `profile_completed` |
| 5 | Home | `GET /app/retailer/home` | render only `categories` + the two sliders |
| 6 | Categories → products | `categories`, `products` | never send `sort` |
| 7 | Product detail | `products/{id}` | one price; ignore the empty sliders |
| 8 | Brands, shortages, favourites | `brands`, `shortages`, `favorite` | |
| 9 | Cart | the five cart calls | bind totals to `summary.final_total` |
| 10 | Submit | `cart/submit` | one idempotency key per confirm tap |
| 11 | Orders + detail + tracking | `orders*` | hide the channel while pending |
| 12 | Receiving | `receipts/*` | blocked until the rep confirms handover |
| 13 | Returns, rating | `return-requests`, `rate` | |

Do not build a debts chip, a loyalty header, a notifications inbox or an offline banner —
§5.

---

## 7. Not built — do not mock

Every path below **returns 404 today**. There is no stub and no feature flag. Wire nothing
to them, and do not fake the data "until the API is ready": a fake balance in a shop app
is a support incident.

**Money — SP-13** (blocks any finance screen)

| Method | Path | EP |
|---|---|---|
| POST | `/app/retailer/payments` | EP-RT-050 |
| GET | `/app/retailer/account/summary` | EP-RT-051 |
| GET | `/app/retailer/account/statement` | EP-RT-052 |
| POST | `/app/retailer/account/statement/export` | EP-RT-053 |
| GET | `/app/retailer/debts` | EP-RT-054 |
| POST | `/app/receipts/reserve` | EP-CM-050 |

Consequence: `stats.total_debt`, the `debts` quick action and `ask_payment` all point at
screens that cannot exist. Hide them.

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

⚠️ **An outbox has nowhere to drain.** Build online-first. You may queue writes locally,
but never show a UI that promises deferred delivery. `header.pending_sync` and
`unread_notifications` stay hidden.

**Content and loyalty — SP-15**

| Method | Path | EP |
|---|---|---|
| GET | `/app/content/home-blocks` | EP-APP-100 |
| GET | `/app/loyalty` | EP-APP-110 |
| POST | `/app/loyalty/redeem` | EP-APP-111 |

📌 The catalog path is **`GET /app/loyalty`** (EP-APP-110). Older internal notes say
`/app/loyalty/wallet` — that path does not exist in the catalog and is not registered.
Use `/app/loyalty` when it ships.

Home sliders stay empty; `header.points` and `header.tier` stay hidden.

**Bootstrap references**

| Method | Path | EP | Note |
|---|---|---|---|
| GET | `/public/refs` | EP-PB-001 | **deliberately absent** — see the comment in `app-modules/reference/routes/api.php` |
| GET | `/public/app-config` | EP-PB-010 | not built |

See §2 — registration reference data comes post-token or bundled.

---

## 8. Gotchas

| # | Where | Watch out |
|---|---|---|
| 1 | `verify-otp` | An incomplete profile yields a **`registration`-only** token. Register, then **verify a second OTP** — registration never upgrades your token |
| 2 | Any `/app/rep/*` call | One phone is one kind. Cross-kind is `403 insufficient_permission`, and there is no switcher |
| 3 | Pre-login screens | No `/public/refs`. Governorates/zones/activity types come **after** the token, or bundled in the binary |
| 4 | All writes | `X-Idempotency-Key` mandatory. Exempt: only the three OTP paths |
| 5 | Retry after timeout | Same key, same body. A fresh key on retry creates a second order |
| 6 | `home` | `points`, `tier`, `unread_notifications`, `pending_sync`, `banner`, all `quick_actions[].count`, all `stats`, `dynamic_sliders` — hardcoded zeros/nulls |
| 7 | `products` | `price.from` == `price.to` == `price.value`. **Not a range** |
| 8 | `products` | `discount`, `rating`, `sold_count` hardcoded `0`; `price_after` == `price.value` |
| 9 | `products` | Sending `sort` throws. `filter[offer_only]=1` always returns zero rows |
| 10 | `products/{id}` | Every variant shows the **parent's** price and availability |
| 11 | `products/{id}` | All four `sliders` always empty |
| 12 | `categories` | `image` is a raw string at root level, a URL below it |
| 13 | cart | `grand_total` is **pre**-discount; bind the payable to `final_total` |
| 14 | `PATCH /cart/lines/{id}` | `qty:0` deletes. `removed_offers` appears only when `qty ≥ 1` |
| 15 | `DELETE /cart/lines/{id}` | Deletes **every** line sharing that offer |
| 16 | `PATCH /cart/sections/{ref}` | Explicit `null` preserves; but `submit` **overwrites `note` with null** if omitted |
| 17 | `orders` | `supply_channel` is `null` while pending/rejected — by design |
| 18 | `orders/{id}` | No id, no status, no totals in the payload |
| 19 | `orders/{id}/cancel` | Only from `pending`; 409 otherwise and after handover |
| 20 | `orders/{id}/reorder` | Cart is nested under `data.cart`; duplicates are not merged |
| 21 | `tracking` | `eta_minutes` and rep `lat`/`lng` always null; `label` == `key` |
| 22 | `receipts/{id}/confirm` | 409 until the rep confirms the handover. `ask_payment` is a hardcoded `true` leading nowhere |
| 23 | `return-requests` | `GET` is unpaginated and unfiltered; `POST` returns no numeric id |
| 24 | `reps/{id}/rate` | Attaches to that rep's latest delivered delivery **globally** |
| 25 | `quote` | `offer_id` is present only on matched lines. Never send `unit_price` |
| 26 | `offers` | `company`/`rating`/`sold_count` hardcoded; `icons` is a type string |
| 27 | `not_found` | Also means "not yours". Render as missing, never as forbidden |
| 28 | Money | Integers, minor units. SYP has 0 decimals — never `/ 100` |
