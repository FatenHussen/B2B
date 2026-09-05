# HTTP contract

Language-agnostic. Flutter implements this in [flutter-client.md](./flutter-client.md). React in [react-client.md](./react-client.md).

| | |
|---|---|
| Gate | `GET /api/v1/health` → `data.status === "ok"` |
| Base | `/api/v1` — never `/api/v1/auth/*`, never `/admin/channels` |

---

## Request

| Header | When | Value |
|---|---|---|
| `Accept` | Always | `application/json` |
| `Accept-Language` | Always | `ar` (default) or `en` |
| `X-Client` | Always | Product id (`retailer-android`, `rep-ios`, `channel-web`, `warehouse-web`, `platform-web`) |
| `X-App-Version` | Always | Semver, e.g. `1.0.0` |
| `X-Device-Id` | Mobile + warehouse | UUID once per install / workstation |
| `Authorization` | After login | `Bearer {token}` |
| `X-Channel-Id` | Channel dashboard | Required when the user has more than one channel |
| `X-Idempotency-Key` | Every protected `POST` `PUT` `PATCH` `DELETE` | UUID per **user intent**. Retry = same key. New tap = new key |

**Exempt from Bearer and idempotency:** `GET /health`, `GET /public/refs`, public OTP, platform login/2FA, channel OTP, warehouse device-login.

Missing key on a protected write → `400` `idempotency_key_required` (client bug).

`client_op_id` (L2+ shops, L3+ carts, L4 payments) is a **business** id **in addition to** the header. Generate once per local draft; never rotate on retry.

## Response

Bind UI to `data`. Keep `meta.server_time`. On failure show `error.message`. If `code === "validation_failed"`, map `details.{field}` onto inputs. Never render raw Laravel JSON.

## Money and lists

Amounts, limits, FX `rate`, `max_cash_hold`: **integers**. SYP display decimals = **0**. Never `/ 100`. Render `price.label` from the server; do not invent «السعر حسب الكمية».

Lists: `page`, `per_page` (default 25, max 100), `sort`, `search`, `filter[*]`. Exports use **active filters**, not the visible page.

Retailer catalog must **drop** `supply_channel` / `company` in the mapper (REQ-IN-06). Rep product rows **must** show `channel.name`. After an order is **confirmed**, the retailer may see the channel name on that order.

## Error map

| `error.code` | Layer | Client |
|---|---|---|
| `validation_failed` | L1 | Inline fields (422) |
| `rate_limited` | L1 | Disable submit; honour `resend_after` / Retry-After |
| `unauthenticated` / `token_revoked` | L1 | Drop token → login |
| `otp_invalid` | L1 | Clear OTP; do not auto-request |
| `insufficient_permission` | L1 | Hide the action |
| `requires_password_confirm` | L1 | Platform: modal, then **replay** same idempotency key |
| `sod_violation` | L1 | Explain; do not resubmit |
| `conflict` / `illegal_transition` / `idempotency_key_conflict` | L1 | Stop; show server message |
| `idempotency_key_required` | L1 | Client bug |
| `operation_in_progress` | L1 | Wait; retry **same** key |
| `channel_limit_exceeded` | L2 | Banner; stop spinner |
| `product_not_available` | L2 | Quote line error; no invented price |
| `profile_incomplete` | L2 | Back to L1 register / waiting |
| `not_found` | L2 | Product/offer outside targeting: gone, **not** «forbidden» |
| `discount_cap_exceeded` | L3 | Rep submit: show cap; do not send a higher % |
| `offer_no_longer_valid` | L3 | Online submit: 409. Offline: accept + `repricing_diff` |
| `credit_limit_exceeded` | L3/L4 | 423 — do not retry blindly |
| `insufficient_stock` | L3 | Channel confirm / cart |
| `barcode_not_in_order` | L3 | Warehouse scan: 422 |
| `plan_limit_exceeded` | L4 | Loyalty / feature flag |

## Who may call what

| Client | May call | Must not call |
|---|---|---|
| Retailer app | `/public/*`, `/app/session`, `/app/auth/*`, `/app/retailer/*`, `/app/offers`, `/app/pricing/quote`, later `/app/sync/*`, `/app/notifications*`, `/app/content/*` | `/channel/*`, `/platform/*`, `/warehouse/*` |
| Rep app | `/app/rep/*` + shared `/app/*` above | `/channel/*`, `/platform/*` |
| Channel web | `/channel/*` | `/app/*`, `/platform/*` |
| Warehouse web | `/warehouse/*` | `/channel/products`, `/app/*` |
| Platform web | `/platform/*` | `/channel/*` as operator catalog |

## Kit tasks

| ID | Build | Done when |
|---|---|---|
| FE-CORE-01 | Envelope + error map | Health `ok` |
| FE-CORE-02 | Headers + secure token | Logout leaves no Bearer |
| FE-CORE-03 | Idempotency on confirm | Retry does not double a write |
| FE-CORE-04 | `can(code)` from `permissions[]` | Missing permission → control absent |
| FE-CORE-05 | Cache `/public/refs` by cursor | Governorate change filters zones |
| FE-CORE-10 | Integer money + `price.label` | No `/ 100` |
| FE-CORE-11 | Strip channel name on retailer cards | Extra JSON keys never reach widgets |
| FE-CORE-12 | Quote body = `{ lines, zone_id }` | Typed client cannot send `unit_price` |
