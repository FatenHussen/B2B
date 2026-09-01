# Shared API client — Layer 1

| | |
|---|---|
| **Audience** | Every frontend repository |
| **Package** | `packages/b2b-api-client` (or an identical copy) |
| **Backend gate** | `GET /api/v1/health` (EP-CORE-001) |
| **Depends on** | [00-install-the-api.md](./00-install-the-api.md) (API must be running) |

No product screen ships until this kit is in place.

---

## 0. Install

1. Finish [00-install-the-api.md](./00-install-the-api.md) until `GET /api/v1/health` returns `ok`.
2. Put the client in `packages/b2b-api-client` (TypeScript) and/or a Flutter package consumed by both mobile apps. Do not copy-paste a second envelope parser.

```bash
# TypeScript (web dashboards)
mkdir -p packages/b2b-api-client
cd packages/b2b-api-client
npm init -y
npm install
```

```yaml
# Flutter (retailer + rep) — pubspec.yaml in packages/b2b_api
name: b2b_api
environment:
  sdk: ">=3.5.0 <4.0.0"
dependencies:
  dio: ^5.7.0
  flutter_secure_storage: ^9.2.2
  uuid: ^4.5.1
```

Point `baseURL` at `http://127.0.0.1:8000/api/v1` (Android emulator: `http://10.0.2.2:8000/api/v1`).

---

## 1. Purpose

One HTTP client, one error map, one header policy. Product apps must not invent a second envelope parser.

## 2. Request

| Header | When | Value |
|---|---|---|
| `Accept` | Always | `application/json` |
| `Accept-Language` | Always | `ar` (default) or `en` |
| `X-Client` | Always | See product briefs (`retailer-android`, `rep-ios`, `channel-web`, `warehouse-web`, `platform-web`, …) |
| `X-App-Version` | Always | Semver of the binary, e.g. `1.0.0` |
| `X-Device-Id` | Mobile + warehouse | Stable UUID generated once per install |
| `Authorization` | After login | `Bearer {token}` |
| `X-Channel-Id` | Channel dashboard | Required when the user belongs to more than one channel |
| `X-Idempotency-Key` | Every `POST` `PUT` `PATCH` `DELETE` | UUID per **user intent** (confirm tap). Network retry = **same** key. New tap = new key |

Exempt from Bearer and idempotency: `GET /health`, `GET /public/refs`, public OTP request/verify/resend, platform login/2FA, channel OTP, warehouse device-login.

**Forbidden paths:** `/api/v1/auth/*`, `/admin/channels`.

`baseURL` = `/api/v1`.

## 3. Response

- Success: bind UI to `data`. Keep `meta.server_time` for clock skew.
- Failure: show `error.message`. If `error.code === validation_failed`, map `error.details.{field}` onto inputs. Never display a raw Laravel validation JSON.

| `error.code` | Client behaviour |
|---|---|
| `validation_failed` | Inline field errors (HTTP 422) |
| `rate_limited` | Disable submit; honour `resend_after` / Retry-After |
| `unauthenticated` | Drop token → login |
| `token_revoked` | Same as unauthenticated |
| `otp_invalid` | Clear OTP boxes; do **not** auto-request a new OTP |
| `insufficient_permission` | Toast; hide the action |
| `requires_password_confirm` | Platform only: password modal, then replay the request |
| `sod_violation` | Explain conflict; do not resubmit blindly |
| `conflict` / `illegal_transition` / `idempotency_key_conflict` | Stop; show server message |
| `idempotency_key_required` | Treat as a client bug |
| `operation_in_progress` | Wait; retry with the **same** key |

## 4. Money and lists

- Amounts, limits, FX `rate`: integers from the server. Format with currency `decimals` (SYP → 0).
- Platform tables: `page`, `per_page` (default 25, max 100), `sort`, `search`, `filter[*]`. Exports use **active filters**, not the visible page.

## 5. Tasks

| ID | Build | Acceptance |
|---|---|---|
| FE-CORE-01 | Envelope client + error map | `GET /health` → `data.status = ok` |
| FE-CORE-02 | Header injection + secure token store | After logout, no Bearer remains |
| FE-CORE-03 | Idempotency tied to confirm buttons | Retry after drop does not double a write when the key is reused |
| FE-CORE-04 | `can(code)` from `permissions[]` | Missing permission → control absent or disabled |
| FE-CORE-05 | Cache `GET /public/refs` by `sync_cursor` | Changing governorate filters zones; send `since` when a cursor exists |

## 6. Definition of done

A write without `X-Idempotency-Key` never leaves the client. A 422 paints the named fields in Arabic when `Accept-Language: ar`.
