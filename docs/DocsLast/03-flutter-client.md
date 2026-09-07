# Flutter API kit

One shared Dart package, two apps. No screen builds a `Dio` call by hand.

| | |
|---|---|
| Package | `packages/b2b_api` |
| Contract | [01-http-contract.md](./01-http-contract.md) |
| Consumers | [retailer](./apps/retailer.md) · [rep](./apps/rep.md) |

Both apps talk to guard `app` and share every line of this package. What differs is only
which paths each may call — enforced server-side by `RequireAppKind`.

This page is working code, not description.

---

## 1. Stack (locked)

| Concern | Choice |
|---|---|
| HTTP | `dio` |
| Storage | `flutter_secure_storage` for the token, `shared_preferences` for the rest |
| Ids | `uuid` |
| State | your choice per app — the package is state-agnostic |

```yaml
dependencies:
  dio: ^5.4.0
  flutter_secure_storage: ^9.0.0
  uuid: ^4.3.0
```

The API is Arabic-first: `Accept-Language: ar` unless the user chose otherwise, and every
screen is RTL.

---

## 2. Layout

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

---

## 3. `envelope.dart`

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

---

## 4. `api_error.dart`

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

  /// A live token belonging to ANOTHER guard, or a registration-only token on a
  /// normal route. Re-login does not fix a genuine wrong_guard.
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

---

## 5. `token_store.dart`

The abilities matter as much as the token. A freshly verified OTP for an unregistered user
returns a token whose only ability is `registration` — store that fact, because every
other call will 403 until you re-verify. See
[apps/retailer.md](./apps/retailer.md) §2.

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

  /// True when this token can ONLY reach /app/retailer/register and /app/rep/register.
  Future<bool> isRegistrationOnly() async =>
      (await _s.read(key: _kRegistrationOnly)) == 'true';

  Future<void> clear() async {
    await _s.delete(key: _kToken);
    await _s.delete(key: _kRegistrationOnly);
  }
}
```

---

## 6. `idempotency.dart`

Every write on this guard needs `X-Idempotency-Key`. A key belongs to a **user intent** —
one tap of Submit — not to an HTTP attempt. This matters more on mobile than anywhere
else: a request that times out on a train has very often already been executed.

```dart
import 'package:uuid/uuid.dart';

class IdempotencyKeys {
  static const _uuid = Uuid();
  final _keys = <String, String>{};

  /// Same intentId in, same key out — for as long as the intent is unresolved.
  String forIntent(String intentId) => _keys.putIfAbsent(intentId, _uuid.v4);

  /// Call ONLY after the intent succeeded, or the user abandoned it.
  void release(String intentId) => _keys.remove(intentId);
}
```

Bind it to the button:

```dart
class SubmitCartButton extends StatefulWidget { /* ... */ }

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

---

## 7. `client.dart`

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

`10.0.2.2` is the Android emulator's alias for the host machine. A physical device needs
your LAN address; `localhost` reaches the phone itself and will always time out.

---

## 8. Handling errors once

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
belongs to someone else, so render it as missing — never as a permission problem.

---

## 9. Money

Integers in the smallest unit. **SYP has 0 decimals**, so the integer is the number you
show.

```dart
String formatMoney(int amount, {int decimals = 0}) {
  if (decimals == 0) return NumberFormat.decimalPattern('ar').format(amount);
  return NumberFormat.decimalPattern('ar').format(amount / pow(10, decimals));
}
```

Never divide by 100 by reflex, and never total a cart locally for display — re-read the
server's total after every mutation. Server rounding is the truth.

---

## 10. Offline

There is **no sync endpoint**: `/app/sync/pull`, `/app/sync/push`, `/app/sync/status` and
`/app/sync/resolve-conflict` all 404 today. An offline outbox therefore has nowhere to
drain.

Build **online-first.** You may cache reads and queue writes locally, but do not ship a UI
that promises deferred delivery — nothing will deliver it.

One endpoint does have a real offline-replay mechanism today, and only one:
`POST /app/rep/customers` accepts a `client_op_id` deduplicated per rep. It is documented
in [apps/rep.md](./apps/rep.md). No other write accepts it.

---

## 11. Checklist

| # | Done when |
|---|---|
| 1 | `GET /health` returns `ok` from the emulator |
| 2 | Token in secure storage; logout leaves none |
| 3 | A write without a key throws in dev, never reaches the network |
| 4 | Tapping Submit ten times offline creates **one** order |
| 5 | A registration-only token routes to the register screen, not to a toast |
| 6 | 422 maps onto form fields via `fieldErrors()` |
| 7 | No `/ 100` anywhere |
| 8 | No UI promises offline delivery |
