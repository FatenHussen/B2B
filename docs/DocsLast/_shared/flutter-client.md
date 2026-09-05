# Flutter API kit

| | |
|---|---|
| Package | `packages/b2b_api` — path dependency of **both** apps |
| Contract | [http-contract.md](./http-contract.md) |
| Install API | [00-install-the-api.md](./00-install-the-api.md) |

Neither app ships its own Dio, envelope parser, or token store.

---

## Stack (locked)

| Concern | Package |
|---|---|
| SDK | Flutter 3.24+ / Dart 3.5+ |
| HTTP | `dio` ^5.7 — one instance |
| Token | `flutter_secure_storage` ^9.2 |
| IDs | `uuid` ^4.5 |
| App state | `flutter_riverpod` ^2.6 (in the **app**) |
| Routing | `go_router` ^14 (in the **app**) |

No GetX, no second HTTP client. Codegen (`freezed`) belongs **inside this package**.

---

## Scaffold

```bash
flutter create --template=package packages/b2b_api
```

```yaml
# packages/b2b_api/pubspec.yaml
name: b2b_api
publish_to: none
environment:
  sdk: ">=3.5.0 <4.0.0"
  flutter: ">=3.24.0"
dependencies:
  flutter:
    sdk: flutter
  dio: ^5.7.0
  flutter_secure_storage: ^9.2.2
  uuid: ^4.5.1
```

Each app:

```yaml
dependencies:
  b2b_api:
    path: ../packages/b2b_api
  flutter_riverpod: ^2.6.1
  go_router: ^14.8.1
  flutter_localizations:
    sdk: flutter
  intl: any
```

| Runtime | `API_BASE_URL` |
|---|---|
| iOS simulator | `http://127.0.0.1:8000/api/v1` |
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| Physical device | LAN IP, e.g. `http://192.168.1.10:8000/api/v1` |

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`X-Client` is set from `Platform.isIOS` in the app (`retailer-ios` vs `retailer-android`), not from env.

---

## Package layout

```
packages/b2b_api/lib/
  b2b_api.dart
  src/
    b2b_api.dart
    config.dart
    envelope.dart
    api_exception.dart
    interceptors/          # headers, auth, idempotency
    storage/               # token + device_id
    repos/                 # grow per layer — never from app lib/
    models/
```

Apps import `package:b2b_api/b2b_api.dart` only. Prefix storage keys per app (`retailer.` vs `rep.`) so two binaries on one phone do not share a token.

### Repos by layer

| Layer | Add |
|---|---|
| L1 | `health`, `public_auth`, `session`, `retailer_register`, `rep_register`, `refs` |
| L2 | `catalog`, `rep_catalog`, `pricing` (quote), `offers` |
| L3 | `retailer_cart`, `retailer_orders`, `rep_cart`, `rep_assignments`, `rep_deliveries` |
| L4 | `sync`, `notifications`, `payments`, `loyalty`, `content` |

Retailer app must not import `RepRepo`. Rep app must not import retailer cart.

---

## Client rules

1. Headers: `Accept`, `Accept-Language`, `X-Client`, `X-App-Version`, `X-Device-Id`.
2. Bearer from `TokenStore` except exempt paths.
3. Idempotency key is **caller-supplied** (`WriteIntent`). The interceptor must not mint a new UUID on retry.
4. Parse `{error}` into `ApiException(code, message, details, statusCode)`. Never show `SocketException` text.

```dart
class WriteIntent {
  WriteIntent() : key = const Uuid().v4();
  final String key;
}
```

Logout: delete `auth_token` even if HTTP fails. Keep `device_id`. Clear refs cache (REQ-CM-056).

`MaterialApp.router`: locale `ar`, RTL `Directionality`. Phone/OTP fields: `dir` LTR for digits.

## Kit done

1. `GET /health` → `ok`.
2. A write cannot leave without an idempotency argument.
3. After logout, secure storage has no token.
4. 422 `details.phone` paints the phone field in Arabic.
5. `grep` for `Dio(` under `apps/` is empty except tests.
