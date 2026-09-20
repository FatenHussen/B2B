# Rep App — Flutter + GetX · API Integration Contract

| | |
|---|---|
| Purpose | The single file an AI coding agent (Cursor) or a Flutter developer needs to wire the **field-rep app** to this backend. Nothing here is from the backlog — every path, body and response was read from `route:list`, the controllers and the FormRequests on **2026-09-20**. |
| App | Flutter · **GetX** (state, DI, routing) · Dio · Arabic RTL · Material 3 |
| Guard | `app` + `app.kind:rep` · token = Sanctum personal access token |
| Sibling files | `flutter-rep.md` (Arabic screen-by-screen UX spec) · `flutter-rep.json` (machine contract) · `docs/status/05-rep-app.md` + `06-shared-app.md` (what is live) |
| Priority when two documents disagree | `route:list` → controller/FormRequest → `flutter-rep.json` → **this file** → `flutter-rep.md` |

> **For Cursor.** Read §0 first. Build exactly what §5 lists, with the folder layout in §2 and the core client in §3. **Screen chrome follows `flutter-rep.md` §7–§10.** OTP screens stay as they are until the rest of the client build is done. Do not invent a path, a field or a response key. If a screen needs something the API does not return, render an honest empty state (§8) — never fake it.

---

## Contents

| § | Section |
|---|---|
| 0 | Rules for the agent |
| 1 | Environment, hosts, headers, seed accounts |
| 2 | Project layout (GetX) and `pubspec.yaml` |
| 3 | Core: envelope, errors, Dio client, interceptors, idempotency, storage, money, time |
| 4 | Auth & session flow (state machine, route guard, controllers) |
| 5 | Endpoint reference — live routes, each with JSON + Dart |
| 6 | Composite journeys (sequence of calls) |
| 7 | Error catalogue → Arabic UI strings |
| 8 | What is NOT live (do not build, do not mock) |
| 9 | Screen ↔ route ↔ controller map + completion checklist |
| 10 | Review checklist (used when files are sent back for revision) |

---

## 0. Rules for the agent

1. **One HTTP client** (`ApiClient`, §3.3). No screen calls Dio directly. Services call `ApiClient`; controllers call services; views only read controllers.
2. **Every response is an envelope.** Success `{data, meta}`, error `{error:{code,message,details?}}`. Parse through `ApiEnvelope` / `ApiException` only.
3. **`error.code` is the contract, `error.message` is not.** The server forces locale `en` on every request (`SetAcceptLanguage`), so `message` arrives in English. The UI shows the Arabic string from §7 keyed by `code`; it never prints `message` to the user (log it).
4. **Every write carries `X-Idempotency-Key`** except the three `/public/auth/*` OTP calls. Missing key → `400 idempotency_key_required`. One key per *user intent*; a retry of the same tap re-sends the **same** key; a new tap makes a new key.
5. **Money is `int`** in the smallest unit. SYP has 0 decimals: `12000` displays as `12,000 ل.س`. Never `double` on a money path. Never round.
6. **Never send `X-Channel-Id`.** `/app/*` sets no tenant; the rep's channel is inferred from the profile.
7. **`session.permissions` does not gate screens.** `/app/rep/*` is gated by `app.kind:rep`, not by `rp.*` grants. Every screen is visible once the profile is complete.
8. **Foreign or missing ids return 404**, never 403. Show «غير موجود».
9. **`/app/*` middleware order**: `auth:app` → `guard.tokenable:app` → `app.kind:rep` → profile check. A retailer token on a rep route → `403 insufficient_permission` → treat as *wrong app*, sign out.
10. **Do not poll `GET /app/rep/deliveries`.** It has a write side-effect (it materialises delivery rows). Pull-to-refresh only.
11. **No offline mode**, no sync, no push, no notifications inbox, no loyalty, no home blocks — §8. `feature_flags.offline_orders` is `false`.
12. Hand-written `fromJson`/`toJson` (no codegen) unless the existing Flutter project already uses `freezed`/`json_serializable` — then follow the project.
13. **Home-task screens match `flutter-rep.md` wireframes**, not a generic CRUD list: register-order (§7), deliveries (§8.3–8.5), collect-payment (§9.2), accept-orders (§8.1), scheduled (§8.9), warehouse (§8.2). API keys still come from this file.

---

## 1. Environment

### 1.1 Hosts

| Target | Base URL | Note |
|---|---|---|
| Local API (`php artisan serve`) | `http://127.0.0.1:8000` | iOS simulator / desktop |
| Local from Android emulator | `http://10.0.2.2:8000` | emulator's alias for host loopback |
| Local from a physical phone | `http://<LAN-IP>:8000` | run `php artisan serve --host=0.0.0.0` |
| Production | `https://api.sentraxsy.com` | confirm with the backend team before release |

All paths below are relative to **`/api/v1`**.

```dart
// lib/app/core/config/app_config.dart
abstract class AppConfig {
  static const String apiHost = String.fromEnvironment('API_HOST', defaultValue: 'http://10.0.2.2:8000');
  static const String basePath = '/api/v1';
  static String get baseUrl => '$apiHost$basePath';

  static const String clientId = 'rep-android'; // 'rep-ios' on iOS — set at runtime, see ApiClient
  static const int otpLength = int.fromEnvironment('OTP_LENGTH', defaultValue: 4); // 4 local, 6 production
  static const bool isProduction = bool.fromEnvironment('PROD', defaultValue: false);
}
```

Run: `flutter run --dart-define=API_HOST=http://10.0.2.2:8000 --dart-define=OTP_LENGTH=4`
Release: `--dart-define=API_HOST=https://api.sentraxsy.com --dart-define=OTP_LENGTH=6 --dart-define=PROD=true`

### 1.2 Headers — on every request

| Header | Value | When |
|---|---|---|
| `Accept` | `application/json` | always |
| `Content-Type` | `application/json` | writes |
| `Authorization` | `Bearer <token>` | everything except `GET /health`, `GET /public/refs`, `GET /public/content/intro`, `POST /public/auth/*` |
| `X-Client` | `rep-android` \| `rep-ios` | always — **must start with `rep-`** (it is what makes the local OTP code all zeros) |
| `X-Device-Id` | stable UUID per install | always (rate-limit key on OTP; matches `device_id` in verify) |
| `X-App-Version` | `1.0.0 (1)` | always |
| `X-Idempotency-Key` | UUID v4 | every `POST`/`PATCH`/`PUT`/`DELETE` except the 3 OTP calls |
| `X-Channel-Id` | — | **never** |

Response header on a replayed write: `Idempotent-Replayed: true`.

### 1.3 OTP behaviour by environment

| | Local / staging (`APP_ENV≠production`) | Production |
|---|---|---|
| `OTP_BYPASS=true` (current `.env`) | **any** `code` verifies, even missing; cooldown and rate limits skipped | ignored — bypass is dead in production |
| Code generated for `X-Client: rep-*` | all zeros (`0000`/`000000`) | random 6 digits sent by WhatsApp, SMS fallback |
| Validation of `code` | `regex ^\d{4,6}$` (when not bypassed) | `size:6` |
| Attempts per `otp_id` | 5 → then `otp_invalid` | 5 |
| TTL / resend cooldown | 300 s / 60 s | 300 s / 60 s |
| Rate limits (per hour) | phone 3 · device 10 · IP 30 (skipped under bypass) | same |

**UI:** the OTP screen has `AppConfig.otpLength` boxes. `code` is sent as a **String** (`"0000"`), never an int (`0000` → `0` → rejected when validation is on).

### 1.4 Seed accounts (demo seeder)

| Phone | Name | Zones | on_duty | discount cap | cash cap |
|---|---|---|---|---|---|
| `+963932000001` (`0932000001`) | عمر الشامي | المزة · المالكي · كفرسوسة (دمشق) | true | 10 % | 5,000,000 |
| `+963932000002` | ياسر حمود | الميدان · باب توما · القصاع · برزة · جرمانا | true | 5 % | 2,000,000 |
| `+963932000003` | نور الدين حلبي | الحمدانية · حلب الجديدة (حلب) | false | 15 % | 3,000,000 |

Any other Syrian mobile (`+9639XXXXXXXX`) + `purpose: "register"` creates a new user. After `migrate:fresh` every `otp_id` and token is dead — request a new one.

Phone normalisation is server-side (`PhoneNumber::normalize`): `0932000001`, `932000001`, `+963932000001`, `00963932000001` all become `+963932000001`. Valid shape: `^\+9639\d{8}$`. Normalise on the client too so the UI can validate before sending.

---

## 2. Project layout (GetX) and dependencies

### 2.1 `pubspec.yaml`

```yaml
name: rep_app
environment:
  sdk: ">=3.4.0 <4.0.0"

dependencies:
  flutter:
    sdk: flutter
  flutter_localizations:
    sdk: flutter
  get: ^4.6.6
  dio: ^5.7.0
  flutter_secure_storage: ^9.2.2
  get_storage: ^2.1.1
  uuid: ^4.5.1
  intl: ^0.19.0
  device_info_plus: ^11.1.0
  package_info_plus: ^8.1.0
  connectivity_plus: ^6.1.0
  geolocator: ^13.0.1
  pin_code_fields: ^8.0.1
  mobile_scanner: ^6.0.2      # barcode → GET /app/rep/products?barcode=
  signature: ^5.5.0           # optional — complete.signature data-URL
  video_player: ^2.9.2        # intro when media_type=video and media_id is http
  google_fonts: ^6.2.1

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^5.0.0
  mocktail: ^1.0.4
```

### 2.2 Folders

```
lib/
├─ main.dart
└─ app/
   ├─ core/
   │  ├─ config/app_config.dart
   │  ├─ network/
   │  │  ├─ api_client.dart               # Dio + interceptors (§3.3)
   │  │  ├─ api_envelope.dart             # ApiEnvelope<T>, PageMeta (§3.1)
   │  │  ├─ api_exception.dart            # ApiException (§3.2)
   │  │  ├─ idempotency.dart              # IdempotencyKey (§3.4)
   │  │  └─ interceptors/
   │  │     ├─ headers_interceptor.dart
   │  │     ├─ auth_interceptor.dart
   │  │     └─ error_interceptor.dart
   │  ├─ storage/
   │  │  ├─ token_storage.dart            # flutter_secure_storage (§3.5)
   │  │  └─ local_store.dart              # get_storage: device id, zone ids, product-name cache, refs
   │  ├─ utils/money.dart · phone.dart · damascus_time.dart
   │  ├─ errors/error_messages.dart       # code → Arabic (§7)
   │  ├─ enums/                           # §5 enums
   │  └─ theme/app_theme.dart             # tokens from flutter-rep.md §2
   ├─ data/
   │  ├─ models/                          # one file per aggregate (session, product, cart, delivery …)
   │  └─ services/                        # one per API area, extend GetxService
   │     ├─ health_service.dart
   │     ├─ content_service.dart          # GET /public/content/intro
     ├─ home_service.dart             # GET /app/rep/home
   │     ├─ refs_service.dart
   │     ├─ auth_service.dart             # OTP + register + session + logout + status
   │     ├─ customer_service.dart         # customers + zones
   │     ├─ catalog_service.dart          # products + quote + offers
   │     ├─ cart_service.dart
   │     ├─ assignment_service.dart       # assignments + scheduled
   │     ├─ warehouse_service.dart
   │     ├─ delivery_service.dart         # deliveries + ping + return-requests
   │     └─ finance_service.dart          # wallet, payments, receipts, withdrawals, receivables
   ├─ modules/                            # one folder per screen: binding + controller + view
   │  ├─ splash/  intro/  auth/phone/  auth/otp/  auth/register/
   │  ├─ shell/   home/   order_capture/  customers/  zones/  products/  offers/  cart/
   │  ├─ assignments/  scheduled/  warehouse/  deliveries/  delivery_detail/
   │  ├─ returns/  wallet/  collect_payment/  receivables/  withdrawals/  settings/
   ├─ middleware/auth_middleware.dart     # GetMiddleware route guard (§4.3)
   └─ routes/app_routes.dart · app_pages.dart
```

### 2.3 Bootstrap

```dart
// lib/main.dart
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await GetStorage.init();
  await Get.putAsync(() => TokenStorage().init());
  await Get.putAsync(() => LocalStore().init());
  await Get.putAsync(() => ApiClient().init());
  Get.put(AuthService());
  Get.put(ContentService());
  Get.put(HomeService());
  Get.put(SessionController(), permanent: true);
  runApp(const RepApp());
}

class RepApp extends StatelessWidget {
  const RepApp({super.key});
  @override
  Widget build(BuildContext context) => GetMaterialApp(
        title: 'تطبيق المندوب',
        locale: const Locale('ar'),
        fallbackLocale: const Locale('ar'),
        supportedLocales: const [Locale('ar')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        theme: AppTheme.light,
        darkTheme: AppTheme.dark,
        initialRoute: AppRoutes.splash,
        getPages: AppPages.pages,
        builder: (context, child) => Directionality(textDirection: TextDirection.rtl, child: child!),
      );
}
```

---

## 3. Core

### 3.1 Envelope

```jsonc
// success (object)
{ "data": { }, "meta": { "server_time": "2026-09-19T11:41:00+03:00" } }
// success (paginated list)
{ "data": [ ], "meta": { "server_time": "...", "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
// error
{ "error": { "code": "validation_failed", "message": "The zone id field is required.", "details": { "zone_id": ["..."] } } }
```

`per_page` default 25, max **100** (silently capped). Non-paginated lists (array or object in `data`, no `page`): `assignments`, `scheduled-orders`, `deliveries`, `cart`, `wallet`, `receivables`, `warehouse-receipts`, `wallet/withdrawals` (GET).

```dart
// lib/app/core/network/api_envelope.dart
class PageMeta {
  final int page, perPage, total, lastPage;
  const PageMeta({required this.page, required this.perPage, required this.total, required this.lastPage});
  bool get hasMore => page < lastPage;
  factory PageMeta.fromJson(Map<String, dynamic> m) => PageMeta(
        page: m['page'] as int, perPage: m['per_page'] as int,
        total: m['total'] as int, lastPage: m['last_page'] as int);
}

class ApiEnvelope<T> {
  final T data;
  final Map<String, dynamic> meta;
  final PageMeta? page;
  final bool replayed; // Idempotent-Replayed: true
  const ApiEnvelope({required this.data, required this.meta, this.page, this.replayed = false});

  String? get serverTime => meta['server_time'] as String?;

  static ApiEnvelope<T> parse<T>(Response res, T Function(dynamic json) map) {
    final body = res.data as Map<String, dynamic>;
    final meta = (body['meta'] as Map<String, dynamic>?) ?? const {};
    return ApiEnvelope<T>(
      data: map(body['data']),
      meta: meta,
      page: meta.containsKey('page') ? PageMeta.fromJson(meta) : null,
      replayed: res.headers.value('Idempotent-Replayed') == 'true',
    );
  }
}

/// A page of items with its meta — what every paginated service method returns.
class Paged<T> {
  final List<T> items;
  final PageMeta meta;
  const Paged(this.items, this.meta);
}
```

### 3.2 Errors

```dart
// lib/app/core/network/api_exception.dart
class ApiException implements Exception {
  final int status;            // HTTP status, 0 = no response (network)
  final String code;           // error.code — THE contract
  final String message;        // error.message — English, for logs
  final Map<String, dynamic> details; // 422 → field => [messages]
  final String? permission;    // present on some 403s
  const ApiException({required this.status, required this.code, required this.message,
      this.details = const {}, this.permission});

  bool get isNetwork => status == 0;
  bool get isUnauthenticated => status == 401 && (code == 'unauthenticated' || code == 'token_revoked');
  bool get isOtpError => code == 'otp_invalid' || code == 'otp_expired';
  bool get isValidation => status == 422;

  /// First message for a given 422 field, or null.
  String? fieldError(String field) {
    final v = details[field];
    if (v is List && v.isNotEmpty) return v.first.toString();
    return null;
  }

  /// Arabic text for the UI (§7). Never show [message].
  String get userMessage => ErrorMessages.of(this);

  factory ApiException.fromResponse(Response res) {
    final body = res.data;
    if (body is Map && body['error'] is Map) {
      final e = body['error'] as Map<String, dynamic>;
      return ApiException(
        status: res.statusCode ?? 0,
        code: e['code'] as String? ?? 'http_error',
        message: e['message'] as String? ?? '',
        details: (e['details'] as Map<String, dynamic>?) ?? const {},
        permission: e['permission'] as String?,
      );
    }
    return ApiException(status: res.statusCode ?? 0, code: 'http_error', message: 'Non-JSON response');
  }

  static const network = ApiException(status: 0, code: 'network', message: 'No connection');
}
```

### 3.3 Dio client and interceptors

```dart
// lib/app/core/network/api_client.dart
class ApiClient extends GetxService {
  late final Dio dio;
  late final String client;       // rep-android | rep-ios
  late final String appVersion;   // "1.0.0 (1)"

  Future<ApiClient> init() async {
    client = Platform.isIOS ? 'rep-ios' : 'rep-android';
    final info = await PackageInfo.fromPlatform();
    appVersion = '${info.version} (${info.buildNumber})';
    dio = Dio(BaseOptions(
      baseUrl: AppConfig.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json'},
      validateStatus: (_) => true, // we map status ourselves in ErrorInterceptor
    ));
    dio.interceptors.addAll([
      HeadersInterceptor(this),
      AuthInterceptor(),
      ErrorInterceptor(),
      if (!AppConfig.isProduction) LogInterceptor(requestBody: true, responseBody: true),
    ]);
    return this;
  }

  // ---- typed helpers -------------------------------------------------------

  Future<ApiEnvelope<T>> get<T>(String path, T Function(dynamic) map, {Map<String, dynamic>? query}) async {
    final res = await dio.get(path, queryParameters: query);
    return ApiEnvelope.parse<T>(res, map);
  }

  /// Every write. [key] is REQUIRED except for the OTP paths; pass the same key on retry.
  Future<ApiEnvelope<T>> post<T>(String path, T Function(dynamic) map,
      {Object? body, IdempotencyKey? key, Map<String, dynamic>? query}) async {
    final res = await dio.post(path, data: body ?? const {}, queryParameters: query,
        options: Options(headers: key?.header));
    return ApiEnvelope.parse<T>(res, map);
  }

  Future<ApiEnvelope<T>> patch<T>(String path, T Function(dynamic) map,
      {Object? body, required IdempotencyKey key}) async {
    final res = await dio.patch(path, data: body ?? const {}, options: Options(headers: key.header));
    return ApiEnvelope.parse<T>(res, map);
  }

  Future<Paged<T>> paged<T>(String path, T Function(Map<String, dynamic>) map,
      {Map<String, dynamic>? query}) async {
    final env = await get<List<T>>(path,
        (d) => (d as List).map((e) => map(e as Map<String, dynamic>)).toList(), query: query);
    return Paged(env.data, env.page!);
  }
}
```

```dart
// lib/app/core/network/interceptors/headers_interceptor.dart
class HeadersInterceptor extends Interceptor {
  final ApiClient c;
  HeadersInterceptor(this.c);
  @override
  void onRequest(RequestOptions o, RequestInterceptorHandler h) {
    o.headers['X-Client'] = c.client;
    o.headers['X-Device-Id'] = Get.find<LocalStore>().deviceId;
    o.headers['X-App-Version'] = c.appVersion;
    if (o.data != null) o.headers['Content-Type'] = 'application/json';
    o.headers.remove('X-Channel-Id'); // rule 6 — never
    h.next(o);
  }
}

// lib/app/core/network/interceptors/auth_interceptor.dart
class AuthInterceptor extends Interceptor {
  static const _public = ['/health', '/public/'];
  @override
  void onRequest(RequestOptions o, RequestInterceptorHandler h) {
    final isPublic = _public.any((p) => o.path.startsWith(p));
    final token = Get.find<TokenStorage>().token;
    if (!isPublic && token != null) o.headers['Authorization'] = 'Bearer $token';
    h.next(o);
  }
}

// lib/app/core/network/interceptors/error_interceptor.dart
class ErrorInterceptor extends Interceptor {
  @override
  void onResponse(Response r, ResponseInterceptorHandler h) {
    final s = r.statusCode ?? 0;
    if (s >= 200 && s < 300) return h.next(r);
    final ex = ApiException.fromResponse(r);
    _sideEffects(ex, r.requestOptions.path);
    h.reject(DioException(requestOptions: r.requestOptions, response: r, error: ex, type: DioExceptionType.badResponse));
  }

  @override
  void onError(DioException e, ErrorInterceptorHandler h) {
    if (e.error is ApiException) return h.next(e);
    // transport failure → ApiException.network
    h.next(DioException(requestOptions: e.requestOptions, error: ApiException.network, type: e.type));
  }

  void _sideEffects(ApiException ex, String path) {
    final onOtpScreen = path.contains('/public/auth/');
    if (ex.isUnauthenticated && !onOtpScreen) Get.find<SessionController>().forceSignOut('session_expired');
    if (ex.code == 'wrong_guard') Get.find<SessionController>().forceSignOut('wrong_guard');
  }
}
```

Services unwrap `DioException.error` once:

```dart
// lib/app/data/services/base_service.dart
abstract class BaseService extends GetxService {
  ApiClient get api => Get.find<ApiClient>();

  /// Turns DioException into ApiException so controllers only ever catch one type.
  Future<T> guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw (e.error is ApiException) ? e.error as ApiException : ApiException.network;
    }
  }
}
```

### 3.4 Idempotency

```dart
// lib/app/core/network/idempotency.dart
class IdempotencyKey {
  final String value;
  IdempotencyKey._(this.value);
  factory IdempotencyKey.create() => IdempotencyKey._(const Uuid().v4());
  Map<String, String> get header => {'X-Idempotency-Key': value};
}
```

Controller pattern — the key lives with the *intent*, not with the request:

```dart
IdempotencyKey? _submitKey;

Future<void> submit() async {
  _submitKey ??= IdempotencyKey.create();          // same key on every retry of this tap
  isSubmitting.value = true;
  try {
    final r = await cartService.submit(retailerId, note: note.text, discountPercent: discount.value, key: _submitKey!);
    _submitKey = null;                              // success → next tap is a new intent
    Get.offNamed(AppRoutes.orderDone, arguments: r);
  } on ApiException catch (e) {
    if (e.code == 'operation_in_progress') { /* keep key, tell user to wait, retry later */ }
    else if (e.status == 422 || e.status == 403 || e.status == 404) { _submitKey = null; } // body will change → new key
    showError(e);
  } finally { isSubmitting.value = false; }
}
```

Rules: same key + same body → stored response replayed (24 h, `Idempotent-Replayed: true`). Same key + **different** body → `409 idempotency_key_conflict` (so after the user edits the form, drop the key). Known key still running → `409 operation_in_progress`.

### 3.5 Storage

```dart
// lib/app/core/storage/token_storage.dart
class TokenStorage extends GetxService {
  static const _k = 'auth_token';
  final _s = const FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));
  String? _token;
  String? get token => _token;
  Future<TokenStorage> init() async { _token = await _s.read(key: _k); return this; }
  Future<void> save(String t) async { _token = t; await _s.write(key: _k, value: t); }
  Future<void> clear() async { _token = null; await _s.delete(key: _k); }
}

// lib/app/core/storage/local_store.dart  (GetStorage — non-secret, survives restarts)
class LocalStore extends GetxService {
  final _b = GetStorage();
  Future<LocalStore> init() async {
    if (_b.read('device_id') == null) await _b.write('device_id', const Uuid().v4());
    return this;
  }
  String get deviceId => _b.read('device_id');

  List<int> get zoneIds => List<int>.from(_b.read('zone_ids') ?? const []);
  Future<void> saveZoneIds(List<int> ids) => _b.write('zone_ids', ids);

  Map<String, dynamic>? get refs => _b.read('refs');            // GET /public/refs snapshot
  Future<void> saveRefs(Map<String, dynamic> r) => _b.write('refs', r);

  // product id → name cache; cart lines also include `name` now
  String? productName(int id) => (_b.read('product_names') ?? const {})['$id'];
  Future<void> rememberProducts(Iterable<Product> ps) async {
    final m = Map<String, dynamic>.from(_b.read('product_names') ?? const {});
    for (final p in ps) { m['${p.id}'] = p.name; }
    await _b.write('product_names', m);
  }

  Future<void> clearUserData() async {
    for (final k in ['zone_ids', 'product_names', 'session']) { await _b.remove(k); }
  }
}
```

### 3.6 Money and time

```dart
// lib/app/core/utils/money.dart
class Money {
  static final _f = NumberFormat('#,##0', 'en'); // Western digits, tabular in the theme
  static String syp(int minor) => '${_f.format(minor)} ل.س';   // SYP has 0 decimals — no division
}
// lib/app/core/utils/damascus_time.dart
class DamascusTime {
  /// Server strings are ISO-8601 with +03:00. Display them as-is; never convert to device zone
  /// for money, delivery or scheduling.
  static String time(String iso) => DateFormat('HH:mm', 'en').format(DateTime.parse(iso));
  static String date(String iso) => DateFormat('yyyy-MM-dd', 'en').format(DateTime.parse(iso));
  static String nowIso() => DateTime.now().toUtc().add(const Duration(hours: 3)).toIso8601String().replaceFirst('Z', '+03:00');
}
// lib/app/core/utils/phone.dart
class Phone {
  static String normalize(String input) {
    var d = input.replaceAll(RegExp(r'[^\d+]'), '').replaceFirst(RegExp(r'^\+'), '');
    if (d.startsWith('00963')) d = d.substring(2);
    if (d.startsWith('0')) d = '963${d.substring(1)}';
    if (d.length == 9 && d.startsWith('9')) d = '963$d';
    return '+$d';
  }
  static bool isValid(String normalized) => RegExp(r'^\+9639\d{8}$').hasMatch(normalized);
}
```

---

## 4. Auth & session

### 4.1 State machine

```
Splash ──GET /health──▶ (token?) ─yes─▶ GET /app/session ──▶ user_type=='retailer' ─▶ wrong app → sign out
                          │                                    profile_completed==false ─▶ Register ─▶ replace token ─▶ session
                          │                                    else ─▶ Shell (skip intro)
                          │no
                          ▼
              GET /public/content/intro
                          │
             enabled==true ─▶ IntroView (logo + text + optional video, duration s, Skip) ─▶ Phone
             enabled==false ─▶ Phone (splash logo ≤2s)
                          │
                          ▼
                    Phone ─▶ OTP ─verify─▶ ┐
                                           ▼
                    is_new / profile incomplete ─▶ Register
                    existing complete rep ─▶ Shell
```

The app **reads** `GET /public/content/intro` (EP-PB-011, no Bearer). Same singleton the back office writes with `PUT /platform/content/intro`.

Do **not** call `GET /platform/content/intro` or `GET /channel/content/intro` (`403 wrong_guard`). `GET /public/app-config` is still 404 — force-update, **not** intro.

Vacant store after `migrate:fresh`: `{enabled:false, text:null, media_type:null, media_id:null, duration:0, targeting:{…:[]}}`. That is correct — do not fake a video.

`media_id` is an opaque string, not a URL. If it starts with `http`, play it; else look up `assets/intro/{media_id}`; else logo + `text`. Ignore `targeting` on first run (no zone yet). Cap display at 8 seconds; `duration==0` with `enabled` → 3s. Cache the payload; network fail with no cache → logo flash then Phone — do not block launch.

Guest is **local only** (the server has no guest token). Phone screen «تصفّح كزائر» sets `prefs.guest=true` and opens Shell so the rep can see the home (tasks / route / collections). Any service (order, cart, delivery, wallet, add shop) → dialog **«يجب أن تسجّل حساباً لاستخدام هذه الخدمة»** → Phone. Do not call `/app/rep/*` without a Bearer.

Decision table after `verify-otp`:

| Response | Next |
|---|---|
| `user_type == "retailer"` | wrong app — clear token, show «هذا الحساب تاجر. استخدم تطبيق التاجر.» |
| `is_new_user == true` or `profile_completed == false` | save token (abilities = `registration` only) → Register screen |
| `user_type == "rep"` and `profile_completed == true` | save token (abilities `*`) → `GET /app/session` → Shell |

After `POST /app/rep/register` the response carries a **new token** with full abilities; the old registration token is revoked server-side. **Replace it immediately** or every `/app/rep/*` call fails.

### 4.2 `SessionController` (permanent)

```dart
class SessionController extends GetxController {
  final auth = Get.find<AuthService>();
  final session = Rxn<AppSession>();
  bool get signedIn => Get.find<TokenStorage>().token != null;
  bool get ready => session.value?.user.profileCompleted == true;

  /// Called on splash when a token exists, and on app resume.
  Future<void> bootstrap() async {
    if (!signedIn) return Get.offAllNamed(AppRoutes.phone);
    try {
      final s = await auth.session();
      if (s.user.userType == 'retailer') return forceSignOut('wrong_app');
      session.value = s;
      if (!s.user.profileCompleted) return Get.offAllNamed(AppRoutes.register);
      Get.offAllNamed(AppRoutes.shell);
    } on ApiException catch (e) {
      if (e.isNetwork && session.value != null) return Get.offAllNamed(AppRoutes.shell); // keep last session
      if (e.isNetwork) return Get.offAllNamed(AppRoutes.offline);
      forceSignOut(e.code);
    }
  }

  Future<void> forceSignOut(String reason) async {
    await Get.find<TokenStorage>().clear();
    await Get.find<LocalStore>().clearUserData();
    session.value = null;
    Get.offAllNamed(AppRoutes.phone, arguments: {'reason': reason});
  }

  Future<void> logout() async {
    try { await auth.logout(); } catch (_) {/* clear locally anyway */}
    await forceSignOut('logout');
  }
}
```

### 4.3 Route guard

```dart
class AuthMiddleware extends GetMiddleware {
  @override
  RouteSettings? redirect(String? route) {
    final s = Get.find<SessionController>();
    final guest = Get.find<LocalStore>().guest;
    if (!s.signedIn) {
      if (guest && route == AppRoutes.shell) return null;
      return const RouteSettings(name: AppRoutes.phone);
    }
    if (!s.ready && route != AppRoutes.register) return const RouteSettings(name: AppRoutes.register);
    return null;
  }
}
// AppPages: every route under the shell gets `middlewares: [AuthMiddleware()]`.
// Guest: Shell is allowed; every write button / remote tab shows
// «يجب أن تسجّل حساباً لاستخدام هذه الخدمة» then AppRoutes.phone.
```

---

## 5. Endpoint reference

Legend: 🔁 needs `X-Idempotency-Key` · 🔓 no Bearer · 📄 paginated · ⚠️ caveat you must honour.

Every `Response` block shows only `data` (the envelope wraps it, §3.1). HTTP status is 200 unless written.

### 5.0 Index

| # | Method | Path | Service method | Section |
|---|---|---|---|---|
| 1 | GET 🔓 | `/health` | `HealthService.check()` | 5.1 |
| 1b | GET 🔓 | `/public/content/intro` | `ContentService.intro()` | 5.1b |
| 2 | GET 🔓 | `/public/refs` | `RefsService.snapshot()` | 5.2 |
| 3 | POST 🔓 | `/public/auth/request-otp` | `AuthService.requestOtp()` | 5.3 |
| 4 | POST 🔓 | `/public/auth/verify-otp` | `AuthService.verifyOtp()` | 5.3 |
| 5 | POST 🔓 | `/public/auth/resend-otp` | `AuthService.resendOtp()` | 5.3 |
| 6 | POST 🔁 | `/app/rep/register` | `AuthService.register()` | 5.4 |
| 7 | GET | `/app/session` | `AuthService.session()` | 5.5 |
| 7b | GET | `/app/rep/home` | `HomeService.snapshot()` | 5.5b |
| 8 | POST 🔁 | `/app/auth/logout` | `AuthService.logout()` | 5.5 |
| 9 | PATCH 🔁 | `/app/rep/status` | `AuthService.setDuty()` | 5.6 |
| 10 | GET 📄 | `/app/rep/customers` | `CustomerService.list()` | 5.7 |
| 10b | GET | `/app/rep/customers/{id}` | `CustomerService.show()` | 5.7 |
| 11 | POST 🔁 | `/app/rep/customers` | `CustomerService.create()` | 5.7 |
| 12 | GET | `/app/rep/zones` | `CustomerService.zones()` | 5.8 |
| 12b | GET 📄 | `/app/rep/zones/{id}/shops` | `CustomerService.zoneShops()` | 5.8 |
| 13 | POST 🔁 | `/app/rep/zones` | `CustomerService.requestZone()` | 5.8 |
| 14 | GET 📄 | `/app/rep/products` | `CatalogService.products()` | 5.9 |
| 14b | GET | `/app/rep/products/{id}` | `CatalogService.product()` | 5.9 |
| 15 | POST 🔁 | `/app/pricing/quote` | `CatalogService.quote()` | 5.10 |
| 16 | GET 📄 | `/app/offers` | `CatalogService.offers()` | 5.11 |
| 17 | GET | `/app/offers/{id}` | `CatalogService.offer()` | 5.11 |
| 18 | POST 🔁 | `/app/rep/cart/lines` | `CartService.addLine()` | 5.12 |
| 19 | GET | `/app/rep/cart` | `CartService.cart()` | 5.12 |
| 20 | POST 🔁 | `/app/rep/cart/sections/{retailer_id}/submit` | `CartService.submit()` | 5.12 |
| 20b | GET | `/app/rep/orders` | `CartService.orders()` | 5.12b |
| 21 | GET | `/app/rep/assignments` | `AssignmentService.list()` | 5.13 |
| 22 | POST 🔁 | `/app/rep/assignments/{id}/accept` | `AssignmentService.accept()` | 5.13 |
| 23 | POST 🔁 | `/app/rep/assignments/{id}/reject` | `AssignmentService.reject()` | 5.13 |
| 24 | GET | `/app/rep/scheduled-orders` | `AssignmentService.scheduled()` | 5.14 |
| 25 | GET | `/app/rep/warehouse-receipts` | `WarehouseService.receipts()` | 5.15 |
| 26 | POST 🔁 | `/app/rep/warehouse-receipts/{handoverId}/confirm` | `WarehouseService.confirm()` | 5.15 |
| 27 | GET | `/app/rep/deliveries` | `DeliveryService.list()` | 5.16 |
| 28 | GET | `/app/rep/deliveries/{id}` | `DeliveryService.detail()` | 5.17 |
| 29 | PATCH 🔁 | `/app/rep/deliveries/{id}/lines/{lineId}` | `DeliveryService.patchLine()` | 5.17 |
| 30 | POST 🔁 | `/app/rep/deliveries/{id}/complete` | `DeliveryService.complete()` | 5.17 |
| 31 | POST 🔁 | `/app/rep/deliveries/{id}/postpone` | `DeliveryService.postpone()` | 5.17 |
| 32 | POST 🔁 | `/app/rep/deliveries/{id}/fail` | `DeliveryService.fail()` | 5.17 |
| 33 | POST 🔁 | `/app/rep/locations/ping` | `DeliveryService.ping()` | 5.18 |
| 34 | POST 🔁 | `/app/rep/return-requests` | `DeliveryService.createReturn()` | 5.19 |
| 35 | GET | `/app/rep/wallet` | `FinanceService.wallet()` | 5.20 |
| 36 | POST 🔁 | `/app/receipts/reserve` | `FinanceService.reserveReceipt()` | 5.20 |
| 37 | POST 🔁 | `/app/rep/payments` | `FinanceService.collect()` | 5.20 |
| 38 | GET | `/app/rep/receivables` | `FinanceService.receivables()` | 5.20 |
| 39 | POST 🔁 | `/app/rep/wallet/withdrawals` | `FinanceService.withdraw()` | 5.20 |
| 40 | GET | `/app/rep/wallet/withdrawals` | `FinanceService.withdrawals()` | 5.20 |

(46+ rows after EP-RP-011 / 024 / 070C / 072; regenerate `flutter-rep.json` with `php docs/api/generate-rep-live.php`.)

---

### 5.1 Health 🔓

`GET /health` — no auth, no key. Always 200, even when a dependency is down.

```json
{ "status": "ok", "app": "B2B Platform", "env": "local",
  "checks": { "database": "ok", "cache": "ok", "queue": "ok" } }
```

`status` is `ok` or `degraded`. Show the splash; a transport error (no response at all) → «تعذّر الوصول للخدمة» + retry. `degraded` still proceeds.

```dart
class HealthService extends BaseService {
  Future<bool> check() => guard(() async {
        final r = await api.get('/health', (d) => d as Map<String, dynamic>);
        return r.data['status'] == 'ok' || r.data['status'] == 'degraded';
      });
}
```

---

### 5.1b Public intro 🔓 — first-run splash

`GET /public/content/intro` — no auth, no idempotency key. **EP-PB-011 · live.** Same row `PUT /platform/content/intro` writes.

```json
{
  "enabled": true,
  "text": "مرحباً بك في شبكة التوزيع",
  "media_type": "video",
  "media_id": "media_intro_default",
  "duration": 8,
  "targeting": { "activity_type_ids": [], "zone_ids": [] }
}
```

| Key | App |
|---|---|
| `enabled` | `false` → no Intro screen. `true` → show, then auto-advance after `duration` (cap 8s) or Skip |
| `text` | Welcome line under the logo. `null` → logo only |
| `media_type` | `image` \| `video` \| `null` |
| `media_id` | Opaque. `http…` → play URL; else `assets/intro/{id}`; else logo + text |
| `duration` | Seconds. `0` + `enabled` → treat as 3 |
| `targeting` | **Ignore** on first run |

```dart
class IntroContent {
  final bool enabled;
  final String? text, mediaType, mediaId;
  final int duration;
  factory IntroContent.fromJson(Map<String, dynamic> j) => IntroContent(
    enabled: j['enabled'] == true,
    text: j['text'] as String?,
    mediaType: j['media_type'] as String?,
    mediaId: j['media_id'] as String?,
    duration: (j['duration'] as int?) ?? 0,
  );
}

class ContentService extends BaseService {
  Future<IntroContent> intro() => guard(() async =>
      (await api.get('/public/content/intro',
          (d) => IntroContent.fromJson(d as Map<String, dynamic>))).data);
}
```

`SplashController`: `Future.wait([health.check(), if (!signedIn) content.intro()])`. Token present → `SessionController.bootstrap()` (skip intro). No token + `enabled` → `IntroView` then Phone. Phone copy: logo + one welcome line + optional video. No marketing carousel.

---

### 5.2 Public reference data 🔓

`GET /public/refs?since=<cursor>` — no auth, no key. Feeds the **register** and **new-customer** pickers as dropdowns. Channels are **never** here (REQ-IN-06).

| Widget | Source array | Select | Submit |
|---|---|---|---|
| Governorate | `governorates` | **single** (filter only) | do **not** POST |
| Zones | `zones` where `governorate_id` matches (or all) | **multi** — `value=id`, `label=name` | `zone_ids: [12, 13]` ints, min 1 |
| Activity | `activity_types` | **single** | `activity_type_id: 3` |

The server does **not** nest zones inside governorates. Group on the device. Hide `status != 'active'`.

```json
{
  "governorates": [
    { "id": 1, "name": "دمشق", "order": 1, "status": "active" },
    { "id": 2, "name": "ريف دمشق", "order": 2, "status": "active" }
  ],
  "zones": [
    { "id": 12, "name": "المزة", "governorate_id": 1, "district": "المزة", "order": 1, "status": "active" },
    { "id": 13, "name": "المالكي", "governorate_id": 1, "district": "المالكي", "order": 2, "status": "active" },
    { "id": 21, "name": "جرمانا", "governorate_id": 2, "district": "جرمانا", "order": 1, "status": "active" }
  ],
  "activity_types": [
    { "id": 3, "name": "بقالة", "icon": "grocery", "order": 1, "status": "active", "suggested_category_ids": [10] },
    { "id": 2, "name": "سوبر ماركت", "icon": "cart", "order": 2, "status": "active", "suggested_category_ids": [10] }
  ],
  "root_categories": [ { "id": 10, "name": "مواد غذائية", "icon": null, "image": null, "order": 1, "status": "active" } ],
  "sale_units": [ { "id": 3, "name": "قطعة", "abbr": "pcs", "default_factor": 1, "status": "active" } ],
  "equipments": [ { "id": 1, "name": "ثلاجة عرض", "icon": null, "order": 1, "status": "active" } ],
  "sync_cursor": "c_20260919100000"
}
```

`meta.sync_cursor` carries the same cursor. Without `since` → full active snapshot. With `since` → **only rows changed after it, including rows that became `inactive`** — drop those locally. Cache the merged result in `LocalStore.refs` and send `since` next time.

```dart
class RefZone { final int id, governorateId; final String name, status; /* fromJson */ }
class RefActivityType { final int id; final String name, status; final List<int> suggestedCategoryIds; /* fromJson */ }

class RefsService extends BaseService {
  Future<Map<String, dynamic>> snapshot({String? since}) => guard(() async {
        final r = await api.get('/public/refs', (d) => d as Map<String, dynamic>,
            query: since == null ? null : {'since': since});
        return r.data;
      });
}
```

```dart
/// Group active zones for a MultiSelect grouped by governorate name.
List<Map<String, dynamic>> zoneDropdownItems(Map<String, dynamic> refs) {
  final govs = { for (final g in (refs['governorates'] as List)) g['id']: g['name'] };
  return (refs['zones'] as List)
      .where((z) => z['status'] == 'active')
      .map((z) => {
            'id': z['id'],
            'label': z['name'],
            'group': govs[z['governorate_id']] ?? '',
          })
      .toList();
}
```

Register selected ids as `List<int> selectedZoneIds` → body `'zone_ids': selectedZoneIds`.

Merge rule (in a `RefsController`): `merged[entity] = {...old by id, ...new by id}` then remove entries whose `status != 'active'`.

---

### 5.3 OTP 🔓 (no idempotency key on these three)

#### Request

`POST /public/auth/request-otp`

```json
{ "phone": "+963932000001", "purpose": "login", "client": "rep-android" }
```

| Field | Rule |
|---|---|
| `phone` | required, Syrian mobile (any of the forms in §1.4) |
| `purpose` | `login` (existing) \| `register` (new phone) |
| `client` | optional ≤64; falls back to `X-Client` header |

Response:

```json
{ "otp_id": "9f1c…", "channel_used": "whatsapp", "expires_in": 300, "resend_after": 60 }
```

Errors: `422 validation_failed` (phone) · `429 rate_limited` (cooldown or hourly limit; message says the seconds).

**UI:** one Syrian field, visible `+963` prefix, numeric keyboard. Note under the field: «يجب أن يكون واتساب مفعّلاً على هذا الرقم لإرسال رمز التحقق». No email. Target: register in under 60 seconds.

#### Verify

`POST /public/auth/verify-otp`

```json
{ "otp_id": "9f1c…", "code": "0000", "device_id": "<same as X-Device-Id>",
  "device_name": "Redmi Note 13", "platform": "android" }
```

| Field | Rule |
|---|---|
| `otp_id` | required |
| `code` | **String**. Local: 4–6 digits (any value under bypass). Prod: exactly 6 |
| `device_id` | required ≤64 |
| `device_name` | optional ≤128 |
| `platform` | optional ≤32 |

Response — existing rep:

```json
{ "token": "12|Kx9…", "is_new_user": false, "user_type": "rep", "profile_completed": true,
  "user": { "id": 7, "name": "عمر الشامي", "phone": "+963932000001" } }
```

Response — new phone (token has ability `registration` only):

```json
{ "token": "31|Ab…", "is_new_user": true, "user_type": null, "profile_completed": false,
  "user": { "id": 42, "name": "", "phone": "+963935555555" } }
```

Errors: `401 otp_invalid` (wrong code, or >5 attempts) · `401 otp_expired` · `422 validation_failed`. **Stay on the OTP screen for 401s here** — the error interceptor already skips sign-out on `/public/auth/*`.

#### Resend

`POST /public/auth/resend-otp` — `{ "otp_id": "9f1c…", "prefer_channel": "whatsapp" }` (`whatsapp` \| `sms`)
→ `{ "channel_used": "sms", "resend_after": 60 }`. Errors: `404 not_found` (dead `otp_id` → go back to phone) · `429 rate_limited`.

```dart
class OtpRequest { final String otpId, channelUsed; final int expiresIn, resendAfter; /* fromJson */ }
class VerifyResult {
  final String token; final bool isNewUser, profileCompleted; final String? userType;
  final int userId; final String name, phone;
  factory VerifyResult.fromJson(Map<String, dynamic> j) => VerifyResult(
      token: j['token'], isNewUser: j['is_new_user'], profileCompleted: j['profile_completed'],
      userType: j['user_type'], userId: j['user']['id'], name: j['user']['name'] ?? '', phone: j['user']['phone']);
  bool get isRetailer => userType == 'retailer';
  bool get needsRegistration => isNewUser || !profileCompleted;
}

class AuthService extends BaseService {
  Future<OtpRequest> requestOtp(String phone, {required bool register}) => guard(() async {
        final r = await api.post('/public/auth/request-otp', (d) => OtpRequest.fromJson(d),
            body: {'phone': Phone.normalize(phone), 'purpose': register ? 'register' : 'login', 'client': api.client});
        return r.data;
      });

  Future<VerifyResult> verifyOtp(String otpId, String code, {String? deviceName}) => guard(() async {
        final r = await api.post('/public/auth/verify-otp', (d) => VerifyResult.fromJson(d), body: {
          'otp_id': otpId, 'code': code, // String!
          'device_id': Get.find<LocalStore>().deviceId,
          'device_name': deviceName, 'platform': Platform.isIOS ? 'ios' : 'android',
        });
        await Get.find<TokenStorage>().save(r.data.token);
        return r.data;
      });

  Future<int> resendOtp(String otpId, {String prefer = 'whatsapp'}) => guard(() async {
        final r = await api.post('/public/auth/resend-otp', (d) => d['resend_after'] as int,
            body: {'otp_id': otpId, 'prefer_channel': prefer});
        return r.data;
      });
}
```

`OtpController`: countdown from `resend_after` (the response value, not a constant); on `otp_invalid` shake the boxes and clear; after `otp_expired` offer resend.

---

### 5.4 Complete registration 🔁

`POST /app/rep/register` — Bearer with `registration` ability (a full `*` token also passes). Returns **201**.

```json
{ "name": "عمر الشامي", "supply_channel_id": 1, "activity_type_id": 2,
  "zone_ids": [12, 13, 14], "note": "أعمل في المزة" }
```

| Field | Rule |
|---|---|
| `name` | required ≤120 |
| `supply_channel_id` | required int — **single** dropdown. No `GET /channels`; bind one item from invite / `--dart-define=DEFAULT_CHANNEL_ID` / last successful id. Empty list → numeric «رمز الانضمام» field, still POSTed as this int |
| `activity_type_id` | required, must exist → **single** dropdown from `/public/refs.activity_types` (`id`/`name`) |
| `zone_ids` | required, ≥1, all inside the channel's coverage → **multi-select** dropdown from `/public/refs.zones`, grouped by `governorate_id`. Governorate dropdown is a filter only — never POSTed |
| `note` | optional ≤500 |

Response 201:

```json
{ "rep": { "id": 42, "name": "عمر الشامي",
           "channel": { "id": 1, "name": "قناة الشام" },
           "zones": [ { "id": 12, "name": null }, { "id": 13, "name": null } ],
           "status": "pending_review" },
  "token": "33|Zq…" }
```

`rep.id` is the **app user id** (the same `id` the session returns). `zones[].name` is `null` — resolve names from the refs cache.

| Error | Meaning |
|---|---|
| `409 conflict` | channel not active |
| `422 validation_failed` · `details.zone_ids` | a zone is outside the channel's coverage |
| `422` · `details.activity_type_id` | unknown activity type |
| `422` · `details.phone` | this phone already belongs to a retailer |
| `423 plan_limit_exceeded` | the channel's rep quota is full |

⚠️ `supply_channel_id`: there is no `GET /channels` (REQ-IN-06). Product wants a dropdown — bind a **one-item** list from the invite / QA define / last success. Do **not** invent a channel directory. If the list is empty, a numeric «رمز الانضمام» field. 409 `conflict` → «القناة غير متاحة». Single select, never multi.

```dart
class RegisterResult { final int repId; final String status, token; final int channelId; final String? channelName; /* fromJson */ }

Future<RegisterResult> register({required String name, required int channelId, required int activityTypeId,
    required List<int> zoneIds, String? note, required IdempotencyKey key}) => guard(() async {
      final r = await api.post('/app/rep/register', (d) => RegisterResult.fromJson(d), key: key, body: {
        'name': name, 'supply_channel_id': channelId, 'activity_type_id': activityTypeId,
        'zone_ids': zoneIds, if (note != null && note.isNotEmpty) 'note': note,
        // never send email — RegisterRepRequest prohibits it
      });
      await Get.find<TokenStorage>().save(r.data.token);          // REPLACE the registration token
      await Get.find<LocalStore>().saveZoneIds(zoneIds);          // the session never returns zones
      return r.data;
    });
```

After success → `SessionController.bootstrap()`. A rep in `pending_review` is **blocked** from `/app/rep/*` (403 `insufficient_permission`) including home; session and logout stay open. Show a «قيد المراجعة» badge — do not fake home counts.

---

### 5.5 Session and logout

`GET /app/session` — call on every cold start and resume.

```json
{
  "user": { "id": 7, "name": "عمر الشامي", "user_type": "rep", "profile_completed": true, "avatar": null },
  "permissions": ["rp.delivery.accept","rp.delivery.deliver","rp.delivery.postpone","rp.delivery.return_request",
                  "rp.payment.collect","rp.payment.withdraw","rp.wallet.view","rp.warehouse.receive"],
  "feature_flags": { "offline_orders": false, "loyalty": false },
  "sync_cursor": "",
  "server_time": "2026-09-19T11:41:00+03:00",
  "requires_legal_accept": false,
  "legal": { "privacy_version": "2026-03", "terms_version": "2026-01" },
  "commercial_limits": { "max_discount_percent": 10, "max_cash_hold": 5000000 },
  "duty": { "on_duty": true, "tracking_enabled": true }
}
```

| Key | Use |
|---|---|
| `user.user_type` | must be `rep`; `retailer` → wrong app |
| `user.profile_completed` | `false` → Register |
| `permissions` | informational only — do not hide screens |
| `commercial_limits.max_discount_percent` | cap for the cart discount slider; `0` → hide the discount field |
| `commercial_limits.max_cash_hold` | `0` = no cap; else block a collection locally when `net_balance + amount > cap` |
| `user.avatar` | always `null` — letter avatar next to «أهلاً بك» |
| `duty.on_duty` | header in/out-of-service switch (write is PATCH /status) |

⚠️ The session does **not** return the rep's zones. Use `LocalStore.zoneIds` (saved at registration) or `zone_id` values seen on customers.

`POST /app/auth/logout` 🔁 — body `{}` → `{ "success": true }`. Revokes the current token only. Clear local storage even if the call fails.

```dart
class AppSession {
  final SessionUser user; final List<String> permissions; final int maxDiscountPercent, maxCashHold;
  final String serverTime, privacyVersion, termsVersion;
  factory AppSession.fromJson(Map<String, dynamic> j) => AppSession(
      user: SessionUser.fromJson(j['user']), permissions: List<String>.from(j['permissions'] ?? const []),
      maxDiscountPercent: (j['commercial_limits']?['max_discount_percent'] ?? 0) as int,
      maxCashHold: (j['commercial_limits']?['max_cash_hold'] ?? 0) as int,
      serverTime: j['server_time'], privacyVersion: j['legal']['privacy_version'], termsVersion: j['legal']['terms_version']);
}
class SessionUser { final int id; final String name; final String? userType; final bool profileCompleted; /* fromJson */ }

Future<AppSession> session() => guard(() async => (await api.get('/app/session', (d) => AppSession.fromJson(d))).data);
Future<void> logout() => guard(() async { await api.post('/app/auth/logout', (_) => null, key: IdempotencyKey.create()); });
```

---

### 5.6 Duty status 🔁

`PATCH /app/rep/status` — `{ "on_duty": true }` → `{ "on_duty": true, "tracking_enabled": true }`.

`tracking_enabled` mirrors `on_duty`. Location pings (§5.18) return 422 while off duty. The top-bar switch calls this; optimistic UI, revert on error.

```dart
class DutyState { final bool onDuty, trackingEnabled; /* fromJson */ }
Future<DutyState> setDuty(bool on) => guard(() async =>
    (await api.patch('/app/rep/status', (d) => DutyState.fromJson(d), body: {'on_duty': on}, key: IdempotencyKey.create())).data);
```

---

### 5.5b Morning home

`GET /app/rep/home` — Bearer, no idempotency key. **EP-RP-002 · live.** Bind the morning screen here. **Do not** call `GET /app/rep/deliveries` from Home (that list materialises delivery rows).

```json
{
  "greeting": { "name": "عمر الشامي", "avatar": null },
  "server_time": "2026-09-19T09:12:44+03:00",
  "on_duty": true,
  "tracking_enabled": true,
  "tasks": {
    "orders_today": 0,
    "deliveries_pending": 3,
    "collected_today": 48000,
    "assignments": 2,
    "scheduled": 1,
    "warehouse_receipts": 2
  },
  "loyalty": null,
  "unread_notifications": 0
}
```

| Key | UI |
|---|---|
| `greeting.name` + `avatar` | «أهلاً بك» + letter avatar (`avatar` is always null) |
| `server_time` | day + date |
| `on_duty` | in/out of service switch — write via PATCH /status |
| `tasks.orders_today` | Register-order badge |
| `tasks.deliveries_pending` | Deliver-orders badge (drops after a complete + refresh) |
| `tasks.collected_today` | Collect-payment badge — integer minor units |
| `tasks.assignments` | Accept orders |
| `tasks.scheduled` | Scheduled |
| `tasks.warehouse_receipts` | Warehouse pickup |
| `loyalty` | `null` → hide points bar. Do not call `GET /app/loyalty` |
| `unread_notifications` | bell badge (0 until EP-CM-060). Inbox is 404 |

Three circles (products / customers / zones) are local navigation. Bottom nav is **five buttons**: home · orders · cart · wallet · account (home in the center, `initialIndex = 2`). Guest: do not call this path; zeros + lock dialog «يجب أن تسجّل حساباً لاستخدام هذه الخدمة». 401 without a bearer.

The home **primary task block** is four buttons in this order (product brief): تسجيل طلب · تسليم الطلبات · استلام دفعة · قبول الطلبات. Scheduled + warehouse are a second row. Layouts: `flutter-rep.md` §7, §8.3, §9.2, §8.1, §8.9, §8.2.

```dart
class HomeService extends BaseService {
  Future<RepHome> snapshot() => guard(() async =>
      (await api.get('/app/rep/home', (d) => RepHome.fromJson(d as Map<String, dynamic>))).data);
}
```

Empty: «لا إسنادات ولا عهدة. ابدأ بزيارة محل.» Pull-to-refresh re-GETs home only.

---

### 5.7 Customers

#### List 📄

`GET /app/rep/customers?filter[search]=النور&page=1&per_page=25`

⚠️ Search is `filter[search]` (Spatie) and matches `shop_name` only. Returns shops the rep registered **plus** active shops in the rep's zones.

```json
[ { "id": 15, "shop_name": "سوبر ماركت النور", "logo": null, "zone_id": 12, "zone": "المزة",
    "address": "المزة - شارع الجلاء", "phone": "+963931000002", "lat": 33.51, "lng": 36.27,
    "is_open": true, "is_active": true, "last_order_at": null } ]
```

`id` **is the `retailer_id`** used by the cart, submit and payments. `is_active:false` = pending review (visible, cart may 404 later if rejected). `logo` and `last_order_at` are always null. `is_open` is always true.

#### Show  — `GET /app/rep/customers/{id}`

Same card plus `owner_name`, `activity_type_id`, `categories[]`, `equipments[]`. 404 if not sourced by this rep and not an active shop in coverage.

#### Register a shop in the field 🔁 — returns **201**

`POST /app/rep/customers`

```json
{ "shop_name": "سوبر ماركت النور", "owner_name": "أحمد النور", "phone": "0933111222",
  "zone_id": 12, "activity_type_id": 2, "lat": 33.5102, "lng": 36.2913,
  "address": "المزة فيلات شرقية", "category_ids": [1], "equipment_ids": [],
  "client_op_id": "c7a1…-uuid" }
```

| Field | Rule |
|---|---|
| `shop_name` | required ≤160 |
| `owner_name` | required ≤120 |
| `phone` | required, Syrian mobile |
| `zone_id` | required, active and inside the channel coverage → **single** dropdown from `/public/refs.zones` |
| `activity_type_id` | required, exists → **single** dropdown from `/public/refs.activity_types` |
| `lat`/`lng` | optional numeric (from GPS) |
| `address` | optional ≤255 |
| `category_ids` | optional ints from refs `root_categories` |
| `equipment_ids` | optional ints from refs `equipments` |
| `client_op_id` | required ≤80 — generate a UUID **before** the first attempt; same value = same row (server-side dedupe, independent of the idempotency key) |

Response 201: `{ "id": 88, "status": "pending_sync" }`

⚠️ `id` is the **sourced-shop row**, not a `retailer_id`. After creating, refresh `GET /customers` — the new shop appears there with the right `id`.

Errors: `422` on `zone_id` (`zone_not_found` / `zone_outside_coverage`), `activity_type_id`, `phone`.

```dart
class Customer {
  final int id, zoneId; final String shopName; final String? zone, address, phone; final bool isActive;
  /* fromJson — logo always null, is_open always true, last_order_at always null */
}

class CustomerService extends BaseService {
  Future<Paged<Customer>> list({String? search, int page = 1, int perPage = 25}) => guard(() =>
      api.paged('/app/rep/customers', Customer.fromJson,
          query: {'page': page, 'per_page': perPage, if (search != null && search.isNotEmpty) 'filter[search]': search}));

  Future<Customer> show(int id) => guard(() async =>
      (await api.get('/app/rep/customers/$id', (d) => Customer.fromJson(d as Map<String, dynamic>))).data);

  Future<void> create(NewCustomer c, {required IdempotencyKey key}) => guard(() async {
        await api.post('/app/rep/customers', (_) => null, key: key, body: c.toJson()); // then re-list
      });
}
```

Paginated controller pattern (reuse everywhere):

```dart
class CustomersController extends GetxController {
  final _svc = Get.find<CustomerService>();
  final items = <Customer>[].obs; final loading = false.obs; final search = ''.obs;
  PageMeta? _meta; int _page = 1;
  bool get hasMore => _meta?.hasMore ?? false;

  @override void onInit() { super.onInit(); debounce(search, (_) => refreshList(), time: const Duration(milliseconds: 400)); refreshList(); }

  Future<void> refreshList() async { _page = 1; items.clear(); await _load(); }
  Future<void> loadMore() async { if (!hasMore || loading.value) return; _page++; await _load(); }

  Future<void> _load() async {
    loading.value = true;
    try { final p = await _svc.list(search: search.value, page: _page); items.addAll(p.items); _meta = p.meta; }
    on ApiException catch (e) { AppSnack.error(e); }
    finally { loading.value = false; }
  }
}
```

---

### 5.8 Zones

#### Coverage list

`GET /app/rep/zones` — unpaginated array of assigned zones.

```json
[ { "id": 12, "name": "المزة", "governorate_id": 1, "shops_count": 4 } ]
```

Governorate label from `GET /public/refs`. `shops_count` = active retailers in that zone.

#### Shops in a zone 📄

`GET /app/rep/zones/{id}/shops?search=النور&page=1&per_page=25`

⚠️ Here the search param is **top-level `search`**, not `filter[search]`. Only `active` shops.

```json
[ { "id": 15, "shop_name": "سوبر ماركت النور", "logo": null, "zone_id": 12, "zone": "المزة",
    "address": "المزة - شارع الجلاء", "phone": "+963931000002", "lat": 33.51, "lng": 36.27,
    "is_open": true, "is_active": true, "last_order_at": null } ]
```

Same shop card as `GET /customers`. `is_open` is always `true` and `last_order_at` always `null` — do not invent a closing hour or a last-order row.

Errors: `403 insufficient_permission` → zone not in the rep's coverage → «هذه المنطقة خارج تغطيتك».

#### Request an extra zone 🔁

`POST /app/rep/zones` — `{ "zone_id": 17, "note": "أزور المنطقة أسبوعياً" }` → `{ "status": "pending_approval" }`.

Picker: governorate **filter** (not posted) then a **single** zone dropdown from refs, excluding `LocalStore.zoneIds`. No list of past requests exists. Toast «طلبك قيد الموافقة» and pop. Errors: `422 zone_id` (`zone_not_found` / `zone_outside_coverage`).

```dart
class ZoneCard { final int id, shopsCount; final int? governorateId; final String name; /* fromJson */ }
class ZoneShop { final int id, zoneId; final String shopName; final String? zone, address, phone; final bool isActive; /* fromJson */ }
Future<List<ZoneCard>> zones() => guard(() async =>
    (await api.get('/app/rep/zones', (d) => (d as List).map((e) => ZoneCard.fromJson(e as Map<String, dynamic>)).toList())).data);
Future<Paged<ZoneShop>> zoneShops(int zoneId, {String? search, int page = 1}) => guard(() =>
    api.paged('/app/rep/zones/$zoneId/shops', ZoneShop.fromJson, query: {'page': page, if (search?.isNotEmpty == true) 'search': search}));
Future<String> requestZone(int zoneId, {String? note, required IdempotencyKey key}) => guard(() async =>
    (await api.post('/app/rep/zones', (d) => d['status'] as String, key: key, body: {'zone_id': zoneId, if (note != null) 'note': note})).data);
```

---

### 5.9 Products 📄

`GET /app/rep/products?page=1&per_page=25&filter[search]=زيت&filter[category_id]=3&filter[brand_id]=9&filter[channel_id]=1&barcode=6291…&zone=12`

| Query | Note |
|---|---|
| `filter[search]` | Arabic name or SKU |
| `filter[category_id]`, `filter[brand_id]`, `filter[channel_id]` | exact |
| `barcode` | **top-level** (from the scanner) |
| `zone` | **top-level**; the zone used to price qty 1. Send the **customer's** zone when browsing for a specific shop. Falls back to the rep's first zone |

⚠️ Do **not** send `sort`, `filter[zone_id]` (Spatie rejects unknown filters with a 400/422), `filter[offer_only]`, `filter[available_only]`.

```json
[ { "id": 101, "name": "زيت عباد الشمس 1ل", "image": null,
    "brand": { "id": 12, "name": "نور" },
    "channel": { "id": 1, "name": "قناة الشام" },
    "price": { "type": "simple", "value": 12000, "label": "12000" },
    "availability": "in_stock",
    "variants": [ { "id": 1, "label": "حبة", "barcode": null } ] } ]
```

`price.type`: `simple` \| `tiered` (label «حسب الكمية» when tiered). `availability`: `in_stock` \| `low` \| `out_of_stock`. `image` is often null — letter avatar. Empty `variants[]` → one qty chip and omit `variant_id`. Never invent variant names.

`GET /app/rep/products/{id}` adds `images[]` and `long_description`. 404 outside the rep channel. Do not call `/app/retailer/products/{id}`.

```dart
class ProductVariant { final int id; final String label; final String? barcode; /* fromJson */ }
class Product {
  final int id, channelId, price; final String name, channelName, priceType, priceLabel, availability;
  final String? image; final Map<String, dynamic>? brand; final List<ProductVariant> variants;
  factory Product.fromJson(Map<String, dynamic> j) => Product(
      id: j['id'], name: j['name'], image: j['image'] as String?,
      brand: j['brand'] as Map<String, dynamic>?,
      channelId: j['channel']['id'], channelName: j['channel']['name'] ?? '',
      price: j['price']['value'] as int, priceType: j['price']['type'], priceLabel: j['price']['label'] ?? '',
      availability: j['availability'],
      variants: ((j['variants'] as List?) ?? []).map((e) => ProductVariant.fromJson(e as Map<String, dynamic>)).toList());
  bool get isTiered => priceType == 'tiered';
}
```

Future<Paged<Product>> products({String? search, int? categoryId, int? brandId, String? barcode, int? zoneId, int page = 1}) =>
    guard(() async {
      final p = await api.paged('/app/rep/products', Product.fromJson, query: {
        'page': page, 'per_page': 25,
        if (search?.isNotEmpty == true) 'filter[search]': search,
        if (categoryId != null) 'filter[category_id]': categoryId,
        if (brandId != null) 'filter[brand_id]': brandId,
        if (barcode != null) 'barcode': barcode,
        if (zoneId != null) 'zone': zoneId,
      });
      await Get.find<LocalStore>().rememberProducts(p.items);
      return p;
    });
```

---

### 5.10 Price quote 🔁

`POST /app/pricing/quote` — call before confirming a quantity (tiered prices, offers).

```json
{ "zone_id": 12, "lines": [ { "product_id": 101, "qty": 24 }, { "product_id": 205, "variant_id": null, "qty": 6 } ] }
```

`zone_id` = **the customer's zone**. Never send `unit_price` (ignored).

```json
{ "lines": [ { "product_id": 101, "variant_id": null, "qty": 24, "unit_price": 11500,
               "applied_rule": { "type": "qty_tier", "id": 4, "label": "12+" }, "tier": { "from": 12, "to": null },
               "discount": 0, "line_total": 276000 },
             { "product_id": 205, "variant_id": null, "qty": 6, "unit_price": 3000,
               "applied_rule": { "type": "base_price", "id": 9, "label": "" }, "tier": null,
               "discount": 1800, "line_total": 16200, "offer_id": 3 } ],
  "subtotal": 292200, "currency": "SYP" }
```

`applied_rule.type`: `base_price` \| `qty_tier` \| `<type>_list`. `offer_id` appears only when an offer applied. Errors: `422 product_not_available` (`details.product_id`).

```dart
class QuoteLine { final int productId, qty, unitPrice, discount, lineTotal; final int? variantId, offerId; final String ruleType, ruleLabel; /* fromJson */ }
class Quote { final List<QuoteLine> lines; final int subtotal; final String currency; /* fromJson */ }

Future<Quote> quote(int zoneId, List<({int productId, int qty, int? variantId})> lines, {required IdempotencyKey key}) =>
    guard(() async => (await api.post('/app/pricing/quote', (d) => Quote.fromJson(d), key: key, body: {
      'zone_id': zoneId,
      'lines': [for (final l in lines) {'product_id': l.productId, 'qty': l.qty, if (l.variantId != null) 'variant_id': l.variantId}],
    })).data);
```

---

### 5.11 Offers

`GET /app/offers?filter[zone_id]=12&filter[activity_type_id]=2&page=1` 📄 — both filters optional (default: rep's first zone / activity type).

```json
[ { "id": 3, "image": null, "name": "اشترِ 12 زيت واحصل على 1 مجاناً", "company": null, "rating": 0,
    "components": [ { "product_id": 101, "qty": 12 } ],
    "price_before": 144000, "discount": 12000, "price_after": 132000,
    "ends_at": "2026-10-01T00:00:00+03:00", "days_left": 12, "remaining_qty": 40, "sold_count": 0 } ]
```

`GET /app/offers/{id}` → the same card plus:

```json
{ "images": [], "long_description": "…", "icons": ["buy_x_get_y"], "same_company_offers": [], "same_company_products": [] }
```

`icons[0]` is the offer type: `product_discount` \| `invoice_discount` \| `buy_x_get_y` \| `bundle` \| `tiered_discount` \| `gift`. `company` is always `null` for the app. An empty list is **correct** when nothing matches — not an error. 404 when the offer is not visible to this rep.

"Add to cart" = `POST /cart/lines` for each `components[]` product with its qty (§5.12); the server applies the offer on repricing.

```dart
class OfferCard { final int id, priceBefore, discount, priceAfter, daysLeft; final int? remainingQty; final String name; final String? image, endsAt; final List<({int productId, int qty})> components; /* fromJson */ }
class OfferDetail extends OfferCard { final List<String> images; final String? longDescription; final String type; /* fromJson */ }
Future<Paged<OfferCard>> offers({int? zoneId, int? activityTypeId, int page = 1}) => guard(() => api.paged('/app/offers', OfferCard.fromJson,
    query: {'page': page, if (zoneId != null) 'filter[zone_id]': zoneId, if (activityTypeId != null) 'filter[activity_type_id]': activityTypeId}));
Future<OfferDetail> offer(int id) => guard(() async => (await api.get('/app/offers/$id', (d) => OfferDetail.fromJson(d))).data);
```

---

### 5.12 Cart (one section per customer)

#### Add a line 🔁

`POST /app/rep/cart/lines` — `{ "retailer_id": 15, "product_id": 101, "variant_id": null, "qty": 24 }`

⚠️ `qty` is **added** to an existing line for the same product/variant. There is **no PATCH and no DELETE** for a cart line. The product brief therefore stages qty in a **local draft** on `OrderCaptureView` (`flutter-rep.md` §7.6) and only then POSTs. After a line is on the server, the honest UI says «لا يمكن الإنقاص من الخادم. أرسل الطلب الحالي أو تواصل مع القناة.» — do not fake a local delete, the server still holds the qty.

Response = the whole cart (same shape as GET below). Errors: `404 not_found` (unknown retailer, or product not in the rep's channel).

#### Read the cart

`GET /app/rep/cart`

```json
{ "sections": [
    { "retailer": { "id": 15, "shop_name": "سوبر ماركت النور", "zone_id": 12 },
      "channel": { "id": 1, "name": "قناة الشام" },
      "created_at": "2026-03-01T10:00:00+03:00",
      "lines": [ { "id": 501, "product_id": 101, "name": "زيت عباد الشمس 1ل", "qty": 24, "unit_price": 11500, "line_total": 276000 } ],
      "total": 276000, "discount": 0 } ] }
```

Bind `name` and `line_total` from the server. `total` and `discount` per section are server truth.

#### Submit one customer's section 🔁

`POST /app/rep/cart/sections/{retailer_id}/submit` — `{ "note": "التسليم صباحاً", "discount_percent": 5 }` (both optional; `discount_percent` int 0–100).

```json
{ "sub_order": { "id": 913, "sub_order_no": "SO-913", "status": "pending", "total": 262200 } }
```

| Error | UI |
|---|---|
| `403 discount_cap_exceeded` | «الخصم أعلى من سقفك (%N)» — cap from session; hide the field when cap is 0 |
| `422 validation_failed` | section empty |
| `404 not_found` | unknown customer |

The server deletes the section; remove it from the UI on success. Then refresh `GET /app/rep/orders`.

```dart
class CartLine { final int id, productId, qty, unitPrice, lineTotal; final String name; /* fromJson */ }
class CartSection { final int retailerId, total, discount; final String shopName; final String? channelName, createdAt; final List<CartLine> lines; /* fromJson */ }
class Cart { final List<CartSection> sections; int get grandTotal => sections.fold(0, (s, x) => s + x.total); /* fromJson */ }
class SubmittedOrder { final int id, total; final String subOrderNo, status; /* fromJson */ }

class CartService extends BaseService {
  Future<Cart> cart() => guard(() async => (await api.get('/app/rep/cart', (d) => Cart.fromJson(d))).data);
  Future<Cart> addLine({required int retailerId, required int productId, int? variantId, required int qty, required IdempotencyKey key}) =>
      guard(() async => (await api.post('/app/rep/cart/lines', (d) => Cart.fromJson(d), key: key,
          body: {'retailer_id': retailerId, 'product_id': productId, if (variantId != null) 'variant_id': variantId, 'qty': qty})).data);
  Future<SubmittedOrder> submit(int retailerId, {String? note, int discountPercent = 0, required IdempotencyKey key}) =>
      guard(() async => (await api.post('/app/rep/cart/sections/$retailerId/submit', (d) => SubmittedOrder.fromJson(d['sub_order']), key: key,
          body: {if (note?.isNotEmpty == true) 'note': note, if (discountPercent > 0) 'discount_percent': discountPercent})).data);
  Future<List<RepOrder>> orders() => guard(() async =>
      (await api.get('/app/rep/orders', (d) => (d as List).map((e) => RepOrder.fromJson(e as Map<String, dynamic>)).toList())).data);
}
```

---

### 5.12b Submitted orders

`GET /app/rep/orders` — unpaginated. Sub-orders this rep submitted (`rep_id` is written on submit) or was later assigned.

```json
[ { "id": 9001, "sub_order_no": "SO-9001", "invoice_no": null, "created_at": "2026-03-01T10:00:00+03:00",
    "status": "pending", "shop": "بقالية النور", "zone": "المزة", "channel": "شركة النور", "total": 47040 } ]
```

`invoice_no` is null until finance issues one — show «—».

```dart
class RepOrder { final int id, total; final String subOrderNo, createdAt, status; final String? invoiceNo, shop, zone, channel; /* fromJson */ }
```

---

### 5.13 Assignments (orders waiting for the rep's accept)

`GET /app/rep/assignments` — plain array, no pagination.

```json
[ { "id": 913, "sub_order_no": "SO-913", "shop": "سوبر ماركت النور", "zone": "المزة", "channel": "قناة الشام",
    "invoice_no": null, "created_at": "2026-09-19T09:12:00+03:00" } ]
```

`id` = `sub_order_id`. `invoice_no` is always `null` here — still **show the row** as «—» (the product card lists the field).

UI (`flutter-rep.md` §8.1): count of `data.length` at the top; horizontal cards grouped by `zone`; buttons قبول / رفض / تفاصيل. Accept removes the card (toast: confirm warehouse, then open deliveries). Do not `GET /deliveries` from this screen. Details = sheet of the same fields; no assignment-show route, no invented invoice lines.

`POST /app/rep/assignments/{id}/accept` 🔁 — body `{}` → `{ "status": "accepted" }`
`POST /app/rep/assignments/{id}/reject` 🔁 — `{ "reason": "خارج مساري اليوم" }` (≤255) → `{ "status": "unassigned" }`

Errors: `404 not_found` (not assigned to me) · `409 illegal_transition` (order already left `assigned`). ⚠️ `unassigned` is a stage label from the server, not a status the app tracks — after reject just remove the card.

```dart
class Assignment { final int id; final String subOrderNo, createdAt; final String? shop, zone, channel; /* fromJson */ }
class AssignmentService extends BaseService {
  Future<List<Assignment>> list() => guard(() async =>
      (await api.get('/app/rep/assignments', (d) => (d as List).map((e) => Assignment.fromJson(e)).toList())).data);
  Future<void> accept(int id, {required IdempotencyKey key}) => guard(() => api.post('/app/rep/assignments/$id/accept', (_) => null, key: key));
  Future<void> reject(int id, String reason, {required IdempotencyKey key}) =>
      guard(() => api.post('/app/rep/assignments/$id/reject', (_) => null, key: key, body: {'reason': reason}));
}
```

---

### 5.14 Scheduled (postponed) orders

`GET /app/rep/scheduled-orders?date=2026-09-20` — array; without `date` = every postponed order.

```json
[ { "id": 913, "shop_logo": null, "shop": "سوبر ماركت النور", "address": "المزة - شارع الجلاء", "phone": "+963933111222",
    "scheduled_at": "2026-09-20T10:00:00+03:00", "status": "postponed", "color": "amber" } ]
```

`id` = `sub_order_id`. `shop_logo` always null — letter avatar. `color` always `amber` — **paint the status icon blue** for postponed (product brief), not amber.

UI (`flutter-rep.md` §8.9): horizontal cards. Each card, in order: shop logo · name · address · mobile (`tel:`) · scheduled date/time · **تفاصيل الطلب** (invoice via GET `/deliveries/{id}`) · status picker · colored icon.

Status picker on the card:

| Choice | Call | After success |
|---|---|---|
| تم التسليم | `POST /deliveries/{id}/complete` | **remove the card** |
| لم يتم التسليم | reason → `fail` | icon **gray**; refresh |
| مؤجلة | new datetime + reason → `postpone` (server notifies the channel board) | stay, new `scheduled_at`, icon **blue** |
| ملغى | reason → `fail` | icon **red**; refresh |

Icon colors on **this** screen only: green delivered · gray not-delivered · blue postponed · red cancelled. Do not reuse the delivery-list border table (§5.16).

```dart
class ScheduledOrder { final int id; final String? shop, address, phone, scheduledAt; /* fromJson */ }
Future<List<ScheduledOrder>> scheduled({String? date}) => guard(() async => (await api.get('/app/rep/scheduled-orders',
    (d) => (d as List).map((e) => ScheduledOrder.fromJson(e)).toList(), query: date == null ? null : {'date': date})).data);
```

---

### 5.15 Warehouse handover (the gate before delivery)

**Without a confirmed handover, `complete` returns 409.**

`GET /app/rep/warehouse-receipts?date=2026-09-19` — object; `date` optional.

```json
{ "date": "2026-09-19", "rep_name": "عمر الشامي", "count": 3,
  "orders": [ { "sub_order_id": 913, "order_no": "SO-913", "shop": "سوبر ماركت النور", "zone": "المزة", "handover_id": 55 },
              { "sub_order_id": 914, "order_no": "SO-914", "shop": "بقالة أبو خالد", "zone": "المالكي", "handover_id": 55 },
              { "sub_order_id": 920, "order_no": "SO-920", "shop": "مطعم الياسمين", "zone": "كفرسوسة", "handover_id": 56 } ] }
```

`count` = number of **orders**, not handovers.

UI (`flutter-rep.md` §8.2): date, rep name, count, then a **table** of assigned orders — order/invoice no (`order_no`), shop, zone, **تفاصيل** (invoice; GET `/deliveries/{sub_order_id}` only, never the deliveries **list**), **استلام** to confirm receipt.

Confirm is per **handover** with a 4-digit code that matches the warehouse panel: tapping استلام opens that field. Rows sharing `handover_id` confirm together and leave the table.

`POST /app/rep/warehouse-receipts/{handoverId}/confirm` 🔁 — `{ "temp_code": "4821" }` (**exactly 4 chars**, String)

→ `{ "status": "on_the_way", "tracking_enabled": true }` — start pings if on duty.

| Error | Meaning |
|---|---|
| `422 validation_failed` | wrong code (or not 4 chars) |
| `409 illegal_transition` | ⚠️ handover id unknown **or not yours** — it is 409 here, not 404 |
| re-confirm of an already confirmed handover | 200 same success **even with a wrong code** — do not use that as a QA check of the code |

```dart
class ReceiptOrder { final int subOrderId, handoverId; final String? orderNo, shop, zone; /* fromJson */ }
class WarehouseReceipts { final String? date; final String repName; final int count; final List<ReceiptOrder> orders;
  Map<int, List<ReceiptOrder>> get byHandover => groupBy(orders, (o) => o.handoverId); /* fromJson */ }
class WarehouseService extends BaseService {
  Future<WarehouseReceipts> receipts({String? date}) => guard(() async =>
      (await api.get('/app/rep/warehouse-receipts', (d) => WarehouseReceipts.fromJson(d), query: date == null ? null : {'date': date})).data);
  Future<void> confirm(int handoverId, String tempCode, {required IdempotencyKey key}) =>
      guard(() => api.post('/app/rep/warehouse-receipts/$handoverId/confirm', (_) => null, key: key, body: {'temp_code': tempCode}));
}
```

---

### 5.16 Today's route (delivery list)

`GET /app/rep/deliveries?filter[zone_id]=12` — object. ⚠️ **Has a write side-effect** (creates delivery rows on first read). Pull-to-refresh only; never poll.

```json
{ "zones": [
    { "name": "المزة", "total": 2, "delivered": 1,
      "cards": [ { "id": 913, "shop": "سوبر ماركت النور", "zone": "المزة", "channel": "قناة الشام", "invoice_no": null,
                   "ordered_at": "2026-09-19T09:12:00+03:00", "status": "on_the_way", "border_color": "green" },
                 { "id": 905, "shop": "بقالة سامر", "zone": "المزة", "channel": "قناة الشام", "invoice_no": "INV-905",
                   "ordered_at": "2026-09-18T15:40:00+03:00", "status": "delivered", "border_color": "gray" } ] } ] }
```

| `status` | `border_color` | token |
|---|---|---|
| `on_the_way` | `green` | `--success` |
| `accepted` (not yet handed over) | `blue` | `--info` |
| `delivered` (**today only** — earlier days are not listed) | `gray` | `--text-2` |

`zones[].delivered` counts today's delivered cards. `invoice_no` may be null until completion.

UI (`flutter-rep.md` §8.3): day+date; per zone name + الكلي + المسلّم; **horizontal green-bordered** cards with shop, zone, channel, invoice, ordered_at, and on-card إلغاء / لم يتم التسليم / تأجيل / خريطة. After complete → gray; fail → red; postpone → **blue** (scheduled list still returns `amber` — paint the frame blue). Map uses cached shop coordinates only; never invent ETA.

```dart
class DeliveryCard { final int id; final String? shop, zone, channel, invoiceNo, orderedAt; final String status, borderColor; /* fromJson */ }
class DeliveryZone { final String name; final int total, delivered; final List<DeliveryCard> cards; /* fromJson */ }
Future<List<DeliveryZone>> list({int? zoneId}) => guard(() async => (await api.get('/app/rep/deliveries',
    (d) => (d['zones'] as List).map((e) => DeliveryZone.fromJson(e)).toList(), query: zoneId == null ? null : {'filter[zone_id]': zoneId})).data);
```

---

### 5.17 Delivery detail, line edits, complete / postpone / fail

`GET /app/rep/deliveries/{id}` (`id` = `sub_order_id`)

```json
{ "lines": [ { "id": 7001, "image": null, "name": "زيت عباد الشمس 1ل", "brand": "الشمس", "variant": null,
               "qty": 24, "qty_delivered": 0, "price": 11500, "status": "pending" } ],
  "invoice_total": 276000 }
```

`lines[].id` is the **delivery line id** (used by PATCH and `complete.lines[].line_id`). `qty` = expected, `qty_delivered` = so far. `status`: `pending` → then the action applied. `invoice_total` = Σ price × (pending ? qty : qty_delivered).

#### Edit one line 🔁

`PATCH /app/rep/deliveries/{id}/lines/{lineId}` — `{ "action": "adjust", "qty_delivered": 20, "reason": "تالف 4" }`

| Field | Rule |
|---|---|
| `action` | required: `accept` \| `adjust` \| `return` \| `exchange` |
| `qty_delivered` | optional int ≥0 (`qty_received` accepted as alias) |
| `reason` | optional |

→ `{ "new_invoice_total": 230000 }` — update the total immediately.

#### Complete 🔁

`POST /app/rep/deliveries/{id}/complete`

```json
{ "lines": [ { "line_id": 7001, "qty_delivered": 24, "action": "accept" } ],
  "delivered_at": "2026-09-19T11:41:00+03:00",
  "signature": "data:image/png;base64,iVBOR…" }
```

All three optional. Omitted lines are delivered at their expected qty.

```json
{ "invoice": { "no": "INV-913", "total": 276000 }, "receipt_no": "RC-1-000431", "ask_payment": true }
```

`ask_payment` is always true → show **استلام دفعة** (product brief) and open the collect sheet (§5.20 / §6.4b) pre-filled with `invoice.no`, `receipt_no`, `invoice.total`. Card border becomes gray after refresh. Calling complete again on a delivered order returns the same invoice and receipt (safe). Do not reserve a second receipt.

Detail chrome (`flutter-rep.md` §8.4): each line shows image/letter + name + brand + attributes + qty + price, then **تعديل كمية** / **إرجاع** / **استبدال**. Matching purchase qty → **تم الاستلام**. Return/exchange: reason dialog → PATCH + `POST /return-requests` → badge «بانتظار قرار القناة». Do not paint money yellow/gray until the server says so (no follow-up list exists). Invoice total at the bottom of the list.

Errors: `409 illegal_transition` → no confirmed handover → «أكّد استلام العهدة من المستودع أولاً» · `404`.

#### Postpone 🔁

`POST /app/rep/deliveries/{id}/postpone` — `{ "scheduled_at": "2026-09-20T10:00:00+03:00", "reason": "المحل مغلق" }` (both required) → `{ "status": "postponed" }`. The card moves to §5.14.

#### Fail 🔁

`POST /app/rep/deliveries/{id}/fail` — `{ "reason": "رفض الاستلام" }` → `{ "status": "undelivered", "border_color": "red" }`. Two-step confirmation in the UI — there is no undo from the app.

```dart
class DeliveryLine { final int id, qty, qtyDelivered, price; final String name, status; final String? brand, image; /* fromJson */ }
class DeliveryDetail { final List<DeliveryLine> lines; final int invoiceTotal; /* fromJson */ }
class CompletionResult { final String invoiceNo, receiptNo; final int invoiceTotal; final bool askPayment;
  factory CompletionResult.fromJson(Map<String, dynamic> j) => CompletionResult(invoiceNo: j['invoice']['no'] ?? '', invoiceTotal: j['invoice']['total'] as int, receiptNo: j['receipt_no'] ?? '', askPayment: j['ask_payment'] == true); }

class DeliveryService extends BaseService {
  Future<DeliveryDetail> detail(int id) => guard(() async => (await api.get('/app/rep/deliveries/$id', (d) => DeliveryDetail.fromJson(d))).data);
  Future<int> patchLine(int id, int lineId, {required String action, int? qtyDelivered, String? reason, required IdempotencyKey key}) =>
      guard(() async => (await api.patch('/app/rep/deliveries/$id/lines/$lineId', (d) => d['new_invoice_total'] as int, key: key,
          body: {'action': action, if (qtyDelivered != null) 'qty_delivered': qtyDelivered, if (reason != null) 'reason': reason})).data);
  Future<CompletionResult> complete(int id, {List<Map<String, dynamic>>? lines, String? signatureDataUrl, required IdempotencyKey key}) =>
      guard(() async => (await api.post('/app/rep/deliveries/$id/complete', (d) => CompletionResult.fromJson(d), key: key,
          body: {if (lines != null) 'lines': lines, 'delivered_at': DamascusTime.nowIso(), if (signatureDataUrl != null) 'signature': signatureDataUrl})).data);
  Future<void> postpone(int id, {required String scheduledAtIso, required String reason, required IdempotencyKey key}) =>
      guard(() => api.post('/app/rep/deliveries/$id/postpone', (_) => null, key: key, body: {'scheduled_at': scheduledAtIso, 'reason': reason}));
  Future<void> fail(int id, {required String reason, required IdempotencyKey key}) =>
      guard(() => api.post('/app/rep/deliveries/$id/fail', (_) => null, key: key, body: {'reason': reason}));
}
```

---

### 5.18 Location pings 🔁

`POST /app/rep/locations/ping`

```json
{ "pings": [ { "lat": 33.5102, "lng": 36.2913, "at": "2026-09-19T11:41:00+03:00", "accuracy": 12 } ] }
```

→ `{ "accepted": 1 }`

| Condition | Error |
|---|---|
| rep not on duty | `422 validation_failed` — stop the timer |
| last stored ping < 30 s ago | `429 rate_limited` — back off |

Batch the pings gathered since the last call into one request; each call gets a **new** idempotency key (each batch is a new intent). Do not upload large historical backlogs at once. Run the timer only while `on_duty` and while a handover is confirmed (`tracking_enabled`).

```dart
Future<int> ping(List<({double lat, double lng, String at, int? accuracy})> pings) => guard(() async =>
    (await api.post('/app/rep/locations/ping', (d) => d['accepted'] as int, key: IdempotencyKey.create(),
        body: {'pings': [for (final p in pings) {'lat': p.lat, 'lng': p.lng, 'at': p.at, if (p.accuracy != null) 'accuracy': p.accuracy}]})).data);
```

`LocationController`: `geolocator` stream with `distanceFilter: 25`, buffer, `Timer.periodic(45 s)` → `ping(buffer)`; on 422 cancel; on 429 skip a tick.

---

### 5.19 Field return / exchange request 🔁

`POST /app/rep/return-requests`

```json
{ "sub_order_id": 913, "type": "return",
  "lines": [ { "line_id": 7001, "qty": 2, "reason": "عبوة تالفة", "photos": [] } ] }
```

| Field | Rule |
|---|---|
| `sub_order_id` | required; must be this rep's order (else 404) |
| `type` | `return` \| `exchange` |
| `lines[].line_id` | delivery line id (§5.17) |
| `lines[].qty` | ≥1 |
| `lines[].reason` | required ≤255 |
| `lines[].photos` | optional array — **no media upload exists**; send `[]` or omit |

→ `{ "request_no": "RR-17", "status": "pending" }`. There is no list endpoint for the rep — show the number and keep it in `LocalStore` for the Settings › «مرتجعاتي» list.

```dart
Future<String> createReturn({required int subOrderId, required String type, required List<({int lineId, int qty, String reason})> lines, required IdempotencyKey key}) =>
    guard(() async => (await api.post('/app/rep/return-requests', (d) => d['request_no'] as String, key: key, body: {
      'sub_order_id': subOrderId, 'type': type,
      'lines': [for (final l in lines) {'line_id': l.lineId, 'qty': l.qty, 'reason': l.reason}],
    })).data);
```

---

### 5.20 Wallet, receipts, collection, receivables, cash hand-over

#### Wallet

`GET /app/rep/wallet`

```json
{ "net_balance": 1450000,
  "stats": { "invoices_delivered": 38, "collected_total": 9800000, "receivables": 2150000 },
  "today": { "invoices": 4, "collected": 620000, "receivables": 180000 } }
```

`net_balance = Σ collected − Σ settled`. Show as returned. `receivables` in `--danger` when > 0.

#### Reserve a receipt number 🔁 (only when collecting without a completion)

`POST /app/receipts/reserve` — body `{}` → `{ "receipt_no": "RC-1-000432" }`. Valid 24 h. `complete` already reserves one — do not reserve a second for the same invoice.

#### Collect cash 🔁

`POST /app/rep/payments`

```json
{ "receipt_no": "RC-1-000431", "retailer_id": 15, "invoice_no": "INV-913", "amount": 276000,
  "paid_at": "2026-09-19T11:45:00+03:00", "client_op_id": "e3b0…-uuid" }
```

| Field | Rule |
|---|---|
| `receipt_no` | required ≤32 — from `complete` or `reserve`; the user never types it (paste at most) |
| `retailer_id` | required — customer id (§5.7) or `receivables.by_shop[].retailer_id` |
| `invoice_no` | required ≤32 |
| `amount` | int ≥1 |
| `paid_at` | ISO datetime |
| `client_op_id` | required ≤80 — UUID made before the first attempt; same value = same payment returned again |

```json
{ "payment": { "id": 771 }, "wallet_balance": 1726000, "retailer_receivable": 0 }
```

An amount above the invoice is allocated FIFO across the shop's other open invoices — show `retailer_receivable` after.

Screen (`flutter-rep.md` §9.2): receipt no (shop-app linked, display/share) · date/time + rep name · shop picker · invoice picker (copy says optional; **request still requires it**) · helper text about invoice vs account · amount · تأكيد الاستلام → «تم استلام المبلغ» + wallet. From home: `reserve` on open. From complete: reuse `receipt_no`.

| Error | UI |
|---|---|
| `409 duplicate_receipt_no` | «رقم الوصل مستخدم» |
| `422` · `details.receipt_no` = receipt_not_reserved / receipt_expired | reserve a new receipt |
| `403 cash_cap_exceeded` | «تجاوزت سقف حيازة النقد» + show the cap; pre-check locally `net_balance + amount > max_cash_hold` when cap > 0 |
| `404 not_found` | invoice/customer is not yours |

#### Open receivables per shop

`GET /app/rep/receivables`

```json
{ "by_shop": [ { "retailer_id": 15, "shop": "سوبر ماركت النور", "total": 180000,
                 "invoices": [ { "no": "INV-913", "total": 276000, "paid": 96000, "remaining": 180000 } ] } ] }
```

Tap an invoice → collect form pre-filled with `retailer_id`, `invoice_no`, `remaining`; reserve a receipt first (§ above) since there is no completion in this path.

#### Hand cash to the accountant (withdrawal) 🔁

`POST /app/rep/wallet/withdrawals` — `{ "amount": 1000000, "operation_no": "OP-2026-0917-07", "operated_at": "2026-09-19" }`

`operation_no` ≤32 comes from the accountant and is unique per channel; re-sending the same `operation_no` replays the same settlement. → `{ "remaining_balance": 726000 }`. Error: `422` · `details.amount` = exceeds wallet.

`GET /app/rep/wallet/withdrawals?date_from=2026-09-01&date_to=2026-09-30` → `{ "rows": [ { "operation_no": "OP-…", "amount": 1000000, "operated_at": "2026-09-19" } ], "total": 1000000 }` (`operated_at` is a **date**, Damascus day).

```dart
class Wallet { final int netBalance, invoicesDelivered, collectedTotal, receivables, todayInvoices, todayCollected, todayReceivables; /* fromJson */ }
class PaymentResult { final int paymentId, walletBalance, retailerReceivable; /* fromJson */ }
class ReceivableInvoice { final String no; final int total, paid, remaining; /* fromJson */ }
class ShopReceivable { final int retailerId, total; final String? shop; final List<ReceivableInvoice> invoices; /* fromJson */ }
class Withdrawal { final String operationNo, operatedAt; final int amount; /* fromJson */ }

class FinanceService extends BaseService {
  Future<Wallet> wallet() => guard(() async => (await api.get('/app/rep/wallet', (d) => Wallet.fromJson(d))).data);
  Future<String> reserveReceipt({required IdempotencyKey key}) => guard(() async => (await api.post('/app/receipts/reserve', (d) => d['receipt_no'] as String, key: key)).data);
  Future<PaymentResult> collect({required String receiptNo, required int retailerId, required String invoiceNo, required int amount,
      required String clientOpId, required IdempotencyKey key}) => guard(() async =>
      (await api.post('/app/rep/payments', (d) => PaymentResult.fromJson(d), key: key, body: {
        'receipt_no': receiptNo, 'retailer_id': retailerId, 'invoice_no': invoiceNo, 'amount': amount,
        'paid_at': DamascusTime.nowIso(), 'client_op_id': clientOpId})).data);
  Future<List<ShopReceivable>> receivables() => guard(() async => (await api.get('/app/rep/receivables',
      (d) => (d['by_shop'] as List).map((e) => ShopReceivable.fromJson(e)).toList())).data);
  Future<int> withdraw({required int amount, required String operationNo, required String operatedAtDate, required IdempotencyKey key}) =>
      guard(() async => (await api.post('/app/rep/wallet/withdrawals', (d) => d['remaining_balance'] as int, key: key,
          body: {'amount': amount, 'operation_no': operationNo, 'operated_at': operatedAtDate})).data);
  Future<({List<Withdrawal> rows, int total})> withdrawals({String? from, String? to}) => guard(() async {
        final r = await api.get('/app/rep/wallet/withdrawals', (d) => d as Map<String, dynamic>,
            query: {if (from != null) 'date_from': from, if (to != null) 'date_to': to});
        return (rows: (r.data['rows'] as List).map((e) => Withdrawal.fromJson(e)).toList(), total: r.data['total'] as int);
      });
}
```

---

## 6. Composite journeys

### 6.1 First run (new rep)

`GET /health` + `GET /public/content/intro` → Intro if `enabled` → Phone (WhatsApp note, `+963`) → `GET /public/refs` (cache) → `request-otp(register)` → `verify-otp` (token: registration) → Register (name + channel **single** dropdown from invite/define + activity **single** + zones **multi-select** grouped by governorate + optional note; no email) → `POST /app/rep/register` → **replace token**, save `zone_ids` → `GET /app/session` → Shell. Duty is off until the rep flips it. Target: under 60 seconds.

### 6.2 Returning rep

`GET /health` → token present → **skip intro** → `GET /app/session` → Shell → `GET /app/rep/home` for the six task badges, duty switch, greeting. Home is tasks + route + collections. Do **not** `Future.wait` deliveries/wallet/assignments from Home.

### 6.3 Take an order for a shop (product brief — one screen)

Home → **تسجيل طلب** → `OrderCaptureView` (do not split into a wizard):

1. Zone dropdown (rep's saved `zone_ids`).
2. Shop dropdown (`GET /zones/{id}/shops` or customers) — locked until a zone is picked.
3. Product search (`filter[search]` / voice / barcode).
4. Category chips from refs `root_categories` → `filter[category_id]`; hide the strip if none.
5. Hide «الأكثر طلباً» (no ranking API).
6. Offers horizontal slider (`GET /app/offers?filter[zone_id]=<shop zone>`).
7. All channel products (`GET /app/rep/products?zone=<shop zone>`).
8. Alibaba variants: independent `RxInt` **above** each variant from `variants[]`; qty 0 = not selected. Empty array → one qty chip, omit `variant_id`. Never invent variant names.
9. Selections go to a **local draft** (`stagedLines`) named after the shop — not the cart. Floating bar **«قائمة المنتجات والعروض المختارة»** (count + estimated total). Tap → review sheet titled with the shop name, local +/−/delete, then **«أضف للسلة باسم {shop}»** → one `POST /cart/lines` per staged line (new idempotency key each). Then Cart tab `submit` with discount ≤ cap.

Quote on debounce when a staged qty settles on a `tiered` price. Empty bar when the draft is empty. Guest: lock dialog.

### 6.4 Delivery day

**قبول الطلبات:** count at the top; horizontal cards grouped by zone; each card: shop, zone, channel, invoice (show «—» when null), date/time, **قبول** / **رفض** / **تفاصيل**. Accept → remove the card; toast to confirm warehouse handover; do **not** `GET /deliveries` from this screen. Reject → reason dialog.

Warehouse `temp_code` (4 digits) on **استلام طلب مستودع**: date + rep name + count + table (order no, shop, zone, details, receive). Then pings start if on duty.

**طلبات مجدولة:** horizontal cards (logo, name, address, phone, scheduled_at, تفاصيل, status picker). Icon: green delivered (then remove) · gray not-delivered · blue postponed · red cancelled. Postpone sends a new datetime through `postpone` (channel board is server-side).

**تسليم الطلبات:** day+date header; per zone: name + الكلي + المسلّم; **horizontal green-bordered cards** with shop, zone, channel, invoice, ordered_at, and on-card actions:

- إلغاء → confirm → `fail` reason «ألغاه المندوب» → red border
- لم يتم التسليم → reason dialog → `fail` → red
- تأجيل → date+time+reason → `postpone` → blue border, card moves to scheduled
- عرض على الخريطة → shop `lat`/`lng` from customer cache only; no invented ETA

Tap the card body → detail: image/letter, name, brand, attributes (`variant`), qty, price, buttons **تعديل كمية** / **إرجاع** / **استبدال**. Invoice total at the bottom. Matching qty → **تم الاستلام** (`complete`). Shortage → adjust then save. Damaged/rejected → reason sheet → `PATCH` + `POST /return-requests`; badge «بانتظار قرار القناة» — do not fake channel accept or yellow/gray money. After complete: gray border + **استلام دفعة** opens §6.4b pre-filled (`receipt_no`, `invoice.no`, `invoice.total`). Do not reserve a second receipt.

### 6.4b Collect payment

Home or the post-complete button. Screen fields in this order: receipt no (shop-app linked — display/share, never typed except paste) · date/time + rep name · shop picker · invoice picker (UI optional copy; **API requires `invoice_no`** — disable confirm until one is chosen, or pick from receivables) · amount · **تأكيد الاستلام**. Success: «تم استلام المبلغ» + `wallet_balance`. Refresh home so `collected_today` moves.

Stand-alone from home: `reserve` first. From complete: use the returned receipt.

### 6.5 End of day

Wallet → receivables (reserve receipt → collect) → withdrawal with the accountant's `operation_no` → withdrawals list for the paper signature.

### 6.6 Guest (local)

Intro → Phone → «تصفّح كزائر» (`prefs.guest=true`) → Shell with no Bearer. Home is visible so the rep can learn tasks/route/collections. Any service tap: **«يجب أن تسجّل حساباً لاستخدام هذه الخدمة»** → Phone. Never call `/app/rep/*` or send `X-Idempotency-Key` without a session.

---

## 7. Error catalogue → Arabic

```dart
// lib/app/core/errors/error_messages.dart
class ErrorMessages {
  static const _byCode = <String, String>{
    'network': 'تعذّر الاتصال بالخادم. تحقق من الشبكة وأعد المحاولة.',
    'unauthenticated': 'انتهت الجلسة. سجّل الدخول من جديد.',
    'token_revoked': 'انتهت الجلسة. سجّل الدخول من جديد.',
    'otp_invalid': 'رمز التحقق غير صحيح.',
    'otp_expired': 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.',
    'wrong_guard': 'هذا الحساب ليس حساب تطبيق. استخدم اللوحة.',
    'insufficient_permission': 'هذا الإجراء غير متاح لحسابك.',
    'profile_incomplete': 'أكمل ملفك أولاً.',
    'discount_cap_exceeded': 'الخصم أعلى من السقف المسموح لك.',
    'cash_cap_exceeded': 'تجاوزت سقف حيازة النقد. سلّم النقدية أولاً.',
    'not_found': 'غير موجود.',
    'illegal_transition': 'هذه الخطوة غير مسموحة الآن.',
    'conflict': 'تعذّر تنفيذ الطلب بسبب تعارض.',
    'duplicate_receipt_no': 'رقم الوصل مستخدم مسبقاً.',
    'idempotency_key_conflict': 'أُعيد إرسال الطلب ببيانات مختلفة. ابدأ من جديد.',
    'idempotency_key_required': 'خطأ داخلي في التطبيق (مفتاح التكرار مفقود).',
    'operation_in_progress': 'العملية ما زالت قيد التنفيذ. انتظر قليلاً.',
    'validation_failed': 'تحقق من الحقول المدخلة.',
    'product_not_available': 'المنتج غير متاح حالياً.',
    'plan_limit_exceeded': 'وصلت القناة إلى الحد الأقصى من المندوبين.',
    'rate_limited': 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.',
    'maintenance_mode': 'الخدمة قيد الصيانة. حاول لاحقاً.',
    'http_error': 'حدث خطأ غير متوقع.',
  };

  // 422 field-level keys the server returns in details (message text is English; map by field+situation)
  static const _byField = <String, String>{
    'phone': 'رقم الهاتف غير صحيح أو مسجّل بدور آخر.',
    'zone_id': 'المنطقة غير موجودة أو خارج تغطية القناة.',
    'zone_ids': 'منطقة أو أكثر خارج تغطية القناة.',
    'activity_type_id': 'نوع النشاط غير موجود.',
    'receipt_no': 'رقم الوصل غير محجوز أو منتهي. احجز وصلاً جديداً.',
    'amount': 'المبلغ غير صالح أو يتجاوز الرصيد.',
    'temp_code': 'الرمز غير صحيح.',
    'code': 'رمز التحقق غير صحيح.',
  };

  static String of(ApiException e) {
    if (e.status == 422 && e.details.isNotEmpty) {
      final field = e.details.keys.first;
      return _byField[field] ?? _byCode['validation_failed']!;
    }
    return _byCode[e.code] ?? _byCode['http_error']!;
  }
}
```

HTTP → behaviour summary:

| HTTP | code(s) | App behaviour |
|---|---|---|
| 400 | `idempotency_key_required` | bug in the client — every write must pass a key |
| 401 | `unauthenticated`, `token_revoked` | clear token → Phone screen (**except** on `/public/auth/*`) |
| 401 | `otp_invalid`, `otp_expired` | stay on OTP |
| 403 | `wrong_guard` | sign out |
| 403 | `insufficient_permission` | on `/app/rep/*` with a retailer account = wrong app → sign out; on `zones/{id}/shops` = zone not covered |
| 403 | `discount_cap_exceeded`, `cash_cap_exceeded` | inline message, keep form |
| 404 | `not_found` | «غير موجود» — also for out-of-tenant ids |
| 409 | `illegal_transition`, `conflict`, `duplicate_receipt_no`, `idempotency_key_conflict`, `operation_in_progress` | dialog with the mapped text; refresh the list |
| 422 | `validation_failed`, `product_not_available` | highlight fields from `details` |
| 423 | `plan_limit_exceeded` | dialog |
| 429 | `rate_limited` | back off; OTP timer / ping timer |
| 503 | `maintenance_mode` | full-screen maintenance |
| 0 | `network` | bottom bar «تعذّر الاتصال» + retry |

---

## 8. Not live — do not build, do not mock

| Path / feature | Status | What the app does instead |
|---|---|---|
| `GET /app/notifications`, `POST /app/notifications/read-all`, `DELETE /app/notifications`, `POST /app/devices/push-token` | ❌ 404 | build the inbox chrome empty (`RemoteNotReady`); no FCM |
| `GET /app/sync/pull`, `POST /app/sync/push`, `GET /app/sync/status`, `POST /app/sync/resolve-conflict` | ❌ | online only; retry on reconnect |
| `GET /app/content/home-blocks` | ❌ | composed home from `GET /app/rep/home`; newest slider = first page of products |
| `GET /app/loyalty`, `POST /app/loyalty/redeem` | ❌ | no points tab |
| `GET /public/app-config` | ❌ | no forced update; store review manual. **Not** the intro source |
| `GET/PUT /platform/content/intro`, `GET/PUT /channel/content/intro` | live on **other** guards | 403 `wrong_guard` — never call. Apps read `GET /public/content/intro` |
| `GET /channels` (any channel directory) | never planned for the app | channel id from invite / QA constant |
| `PATCH`/`DELETE /app/rep/cart/lines/{id}` | ❌ | qty only goes up; honest message |
| `GET /app/rep/return-requests` (list) | ❌ | keep `request_no` locally |
| media upload (photos, product images) | ❌ | `image`/`logo` is null everywhere; letter avatar |
| `/app/retailer/*` | live but **retailer kind only** → 403 | never call from the rep app |

Anything in the catalog marked `contract: proposed` is not a route.

---

## 9. Screen ↔ route ↔ controller map (Cursor's work list)

| # | Screen (module) | Controller | Calls | Empty state (ar) |
|---|---|---|---|---|
| 1 | `splash` | `SplashController` | health + (if no token) `GET /public/content/intro`; token → session bootstrap (skip intro) | — |
| 1b | `intro` | `IntroController` | payload from splash; `ContentService.intro()` — never `/platform` or `/channel` intro | — |
| 2 | `auth/phone` | `PhoneController` | request-otp | — |
| 3 | `auth/otp` | `OtpController` | verify-otp, resend-otp | — |
| 4 | `auth/register` | `RegisterController` | refs, register | — |
| 5 | `shell` | `ShellController` | setDuty (top bar) | — |
| 6 | `home` | `HomeController` | `GET /app/rep/home` only (never deliveries) | «لا إسنادات ولا عهدة. ابدأ بزيارة محل.» |
| 6b | `order_capture` | `OrderCaptureController` | zones/shops or customers, products, offers, quote, cart/lines | «اختر المنطقة ثم المحل.» |
| 7 | `customers` (+ detail + new customer sheet) | `CustomersController`, `CustomerDetailController`, `NewCustomerController` | customers GET/POST/`{id}`, refs | «لا زبائن بعد. أضف محلًا أو افتح منطقة.» |
| 8 | `zones` (coverage + shops + request) | `ZonesController`, `ZoneShopsController`, `RequestZoneController` | zones GET, zones/{id}/shops, zones POST, refs | «لا مناطق معيّنة.» |
| 9 | `products` (+ detail + barcode) | `ProductsController`, `ProductDetailController` | products, products/{id}, quote | «لا منتجات تطابق البحث.» |
| 10 | `offers` (list + detail) | `OffersController`, `OfferDetailController` | offers, offers/{id}, cart/lines | «لا عروض متاحة الآن.» |
| 11 | `cart` | `CartController` | cart, cart/lines, submit | «السلة فارغة. اختر محلًا وأضف منتجات.» |
| 11b | `orders` | `OrdersController` | `GET /app/rep/orders` | «لا طلبات بعد. أرسل سلة محل.» |
| 12 | `assignments` | `AssignmentsController` | assignments, accept, reject | «لا إسنادات تنتظر قبولك.» |
| 13 | `scheduled` | `ScheduledController` | scheduled-orders, deliveries/{id} complete/fail/postpone | «لا طلبات مؤجَّلة.» |
| 14 | `warehouse` | `WarehouseController` | warehouse-receipts, confirm | «لا عهدة بانتظارك. راجع المستودع.» |
| 15 | `deliveries` | `DeliveriesController` | deliveries | «لا مسار اليوم. اقبل إسنادًا أو أكّد عهدة.» |
| 16 | `delivery_detail` | `DeliveryDetailController` | deliveries/{id}, patch line, complete, postpone, fail, return-requests | — |
| 17 | `returns` (create sheet) | `ReturnRequestController` | return-requests | «لا مرتجعات محفوظة.» |
| 18 | `wallet` | `WalletController` | wallet | — |
| 19 | `collect_payment` | `CollectPaymentController` | receipts/reserve, payments | — |
| 20 | `receivables` | `ReceivablesController` | receivables | «لا ذمم مفتوحة.» |
| 21 | `withdrawals` (+ new) | `WithdrawalsController` | wallet/withdrawals GET/POST | «لا تسليمات نقدية بعد.» |
| 22 | `settings` | `SettingsController` | logout, wallet.stats, customers meta | — |
| 23 | `notifications` | `NotificationsController` | none — `RemoteNotReady` | «لا إشعارات بعد.» |
| — | background | `LocationController` | locations/ping | — |

### Completion checklist per screen

- [ ] Binding registers the controller and its service (`Get.lazyPut`).
- [ ] Every write passes an `IdempotencyKey` held in the controller; cleared on success or when the form changes.
- [ ] `client_op_id` (customers, payments) generated once per form open.
- [ ] Loading skeleton, empty state text above, error via `ApiException.userMessage`.
- [ ] 422 → `fieldError('<field>')` shown under the input.
- [ ] Money via `Money.syp()`, times via `DamascusTime`.
- [ ] No call to any path in §8.
- [ ] Pull-to-refresh on lists; pagination on 📄 lists (`hasMore` → `loadMore`).
- [ ] RTL; digits/phones/codes in LTR spans.

---

## 10. Review checklist (for files sent back for revision)

When Flutter files are submitted, they are checked against this list, in this order:

1. **Path & method match §5.0 exactly** (`/app/rep/…`, kebab-case, plural). No invented routes, no `/app/retailer/*`.
2. **Headers**: `X-Client` starts with `rep-`; `X-Device-Id` stable; `X-Idempotency-Key` on every write except the 3 OTP paths; no `X-Channel-Id`.
3. **Body fields** match the FormRequest tables — names, types (`code` as String, `qty` int, money int), required/optional.
4. **Response parsing** only through `ApiEnvelope`; models read the exact keys listed; no key that the server does not send.
5. **Error handling**: catches `ApiException` only; maps `code` via §7; 401 on OTP screen does not sign out; 403 `insufficient_permission` on a rep route signs out.
6. **Idempotency**: key reused on retry, replaced after success or form edit; `operation_in_progress` keeps the key.
7. **Money & time**: `int` everywhere; no `double`, no `/100`; Damascus ISO strings displayed as-is.
8. **Forbidden features** absent (§8), including local "delete cart line" or fake notifications.
9. **GetX hygiene**: services are `GetxService`, controllers dispose streams/timers in `onClose`, no business logic in views, `Obx` scoped to what changes.
10. **Pagination & refresh**: `per_page ≤ 100`; `GET /deliveries` never polled.
11. **Security**: token only in `flutter_secure_storage`; cleared on logout/401; no token in logs in release builds.
12. **Session**: bootstrap on start and resume; `user_type`/`profile_completed` decision table (§4.1) implemented.
