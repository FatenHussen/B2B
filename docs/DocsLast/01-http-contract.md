# HTTP contract

The rules every client obeys, whatever language it is written in. Read this once; the
five app files assume it and do not repeat it.

Verified against the code on **2026-09-07**. Where this page and the API catalog disagree,
this page is right — it was written from `bootstrap/app.php`, `config/core.php`,
`ApiResponse`, `ErrorCode` and the middleware, not from the catalog.

---

## 1. Base and versioning

| | |
|---|---|
| Base URL | `{host}/api/v1` |
| Probe | `GET /api/v1/health` — unguarded, returns `data.status === "ok"` |

Every path in this documentation is written relative to `/api/v1`.

---

## 2. The envelope

Success carries `data` and always `meta.server_time`:

```jsonc
{ "data": { }, "meta": { "server_time": "2026-09-07T12:00:00+03:00" } }
```

A page-numbered list:

```jsonc
{ "data": [ ],
  "meta": { "server_time": "...", "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
```

A cursor list (some high-volume endpoints):

```jsonc
{ "data": [ ],
  "meta": { "server_time": "...", "per_page": 25,
            "next_cursor": "eyJpZCI6NDJ9", "prev_cursor": null, "has_more": true } }
```

An error — note it has **no `data` and no `meta`**:

```jsonc
{ "error": { "code": "insufficient_permission",
             "message": "…",
             "permission": "sc.orders.confirm",
             "details": { } } }
```

`permission` appears only on a 403 raised by the permission middleware. `details` appears
only when non-empty; on `validation_failed` it is Laravel's field→messages map.

`204 No Content` responses have an **empty body** — do not try to parse an envelope from
them.

**Bind your UI to `data`.** Branch on `error.code`, never on `error.message`: the message
is localised for display and will change.

---

## 3. Timestamps

Every timestamp the API produces is ISO-8601 in **`Asia/Damascus`** (`+03:00`), already
converted. Do not apply an offset on the client. Prefer `meta.server_time` over the device
clock for anything the server will judge — expiries, cooldowns, ordering.

One known exception: `created_at` on the supply-channel resource is emitted in **UTC**.
It is called out in [apps/platform-web.md](./apps/platform-web.md).

---

## 4. Request headers

Only three custom headers are actually read by the server. The rest are conventions worth
sending anyway, for logs and for the future — but do not expect behaviour from them today.

| Header | When | Value | Read by the server? |
|---|---|---|---|
| `Accept` | always | `application/json` | yes (Laravel) |
| `Content-Type` | on writes | `application/json` | yes |
| `Accept-Language` | always | `ar` \| `en` | **yes** — `SetAcceptLanguage`, anything else falls back to `ar` |
| `Authorization` | after login | `Bearer {token}` | yes |
| `X-Idempotency-Key` | **every write** | UUID per user intent | **yes** — §6 |
| `X-Device-Id` | mobile, warehouse | stable per install | **yes** — OTP rate limiting |
| `X-Channel-Id` | see below | channel id | **yes, but only for `platform_admin`** |
| `X-Client` | always | `retailer-android`, `channel-web`, … | **no** — ignored today |
| `X-App-Version` | always | semver | **no** — ignored today |

⚠️ **`X-Channel-Id` does not do what older docs claim.** `ResolveTenant` honours it only
when the caller `hasRole('platform_admin')`. A channel user's tenant is resolved from
their own `supply_channel_id` / default membership, and their `X-Channel-Id` header is
**silently ignored** — it is not an error, it simply has no effect. A channel user
belonging to several channels cannot currently switch between them by header.

⚠️ `X-Client` and `X-App-Version` are read by **no** middleware in this codebase. Nothing
enforces them and nothing varies by them, so `426 upgrade_required` cannot currently be
triggered by version. Send them for your own logging; do not build a force-update flow on
them yet.

---

## 5. Authentication and the four guards

The **route prefix decides the guard.** This is enforced by a test, not by convention:

| Prefix | Guard | How you get a token |
|---|---|---|
| `/platform/*`, `/admin/*` | `auth:platform` | email + password (+ TOTP) |
| `/channel/*` | `auth:channel` | phone OTP |
| `/warehouse/*` | `auth:warehouse` | device token + 4-digit PIN |
| `/app/*` | `auth:app` | phone OTP |
| `/public/*`, `/health` | none | — |

`auth:sanctum` is **not** a guard in this application. If you see it anywhere, it is a bug.

Mobile and warehouse clients send `Authorization: Bearer {token}`. The web dashboards may
use Sanctum's SPA cookie mode against **the same endpoints** on a shared parent domain;
CSRF applies to that cookie flow only. `config/cors.php` sets `supports_credentials: true`
and reads allowed origins from `CORS_ALLOWED_ORIGINS` (default `http://localhost:3000`) —
a credentialed request needs an explicit origin, never `*`.

### The three ways auth fails, and why you must tell them apart

All three are plausible on a first integration and each needs different client behaviour:

| `error.code` | HTTP | Means | What the client does |
|---|---|---|---|
| `unauthenticated` | 401 | No token, or one that resolves to nothing | Send the user to login |
| `token_revoked` | 401 | The token's row is gone, or it expired | Drop the token, send to login, optionally say "signed out elsewhere" |
| `wrong_guard` | **403** | A **valid live token belonging to another guard** | **Do not log the user out.** This is a client routing bug — a rep token hitting `/channel/*`, or a token stored in a shared key across two apps |

`AuthFailureCode` produces this by re-reading the bearer token after the guard has already
failed: the guard alone cannot distinguish "no user" from "another guard's user", so
without it every cross-guard call reported a misleading 401.

**Re-logging in never fixes a `wrong_guard`.** A retry loop on 403 will spin forever.

---

## 6. Idempotency — mandatory on every write

`EnsureIdempotency` is appended to the whole `api` middleware group, so it applies to
**every** `POST`, `PUT`, `PATCH` and `DELETE` on every guard. A missing header fails
before your controller is reached:

```jsonc
{ "error": { "code": "idempotency_key_required",
             "message": "X-Idempotency-Key is required for write requests." } }   // 400
```

The stored key is hashed from **method + path + body**, which produces three behaviours:

| Situation | Response |
|---|---|
| Same key, same body, previous call finished 2xx | The stored response replays, with header `Idempotent-Replayed: true` |
| Same key, **different body** | `409 idempotency_key_conflict` |
| Same key, first call still running | `409 operation_in_progress` |

So a key belongs to a **user intent**, not to an HTTP attempt:

- Generate it when the user commits (taps Confirm), not in your HTTP wrapper.
- Reuse it verbatim on every retry of that intent, including after a timeout.
- Generate a fresh one only when the user starts over.
- Never mutate the body between retries of the same key.

Stored responses live **24 hours** (`IDEMPOTENCY_TTL_HOURS`). Only 2xx are stored — a
failed write releases its key immediately, so the user can fix a validation error and
resubmit under the same key.

### The exempt paths — memorise this list

From `config/core.php` → `idempotency_exempt`. These are the credential-establishing
calls: you cannot hold a key before you hold a session, and replaying an OTP request must
send a fresh code rather than replay the old response.

```
public/auth/request-otp
public/auth/verify-otp
public/auth/resend-otp
platform/auth/login
platform/auth/2fa/verify
channel/auth/request-otp
channel/auth/verify-otp
warehouse/auth/device-login
```

Omit the header on exactly those eight. Send it on everything else, including logout.
Adding to this list is an API change.

---

## 7. The error catalogue

Every code the API may return, from `ErrorCode`. The status is a property of the code, so
these pairings are fixed.

| HTTP | `error.code` | What it means for a client |
|---|---|---|
| 400 | `idempotency_key_required` | Your bug — you omitted the header on a write |
| 401 | `unauthenticated` | No usable token → login |
| 401 | `token_revoked` | Token deleted or expired → drop it, login |
| 403 | `wrong_guard` | Right user, wrong app/prefix → **do not** re-login |
| 403 | `insufficient_permission` | Missing grant; `error.permission` names it → hide the control |
| 403 | `requires_2fa` | Finish the 2FA challenge first |
| 403 | `requires_password_confirm` | Confirm the password, then **replay with the same idempotency key** |
| 403 | `sod_violation` | Separation of duties — a *different* person must act. Do not retry |
| 404 | `not_found` | Missing **or not yours**. Existence is never disclosed — never render "forbidden" |
| 409 | `illegal_transition` | The entity is not in a state that allows this. Refetch and re-render |
| 409 | `operation_in_progress` | Same key still running → wait, retry the **same** key |
| 409 | `stale_version` | Someone else wrote first → refetch, show a conflict, do not clobber |
| 409 | `idempotency_key_conflict` | Same key, different body → your bug |
| 422 | `validation_failed` | Map `details.{field}` onto inputs |
| 422 | `ref_in_use` | A reference entity is referenced elsewhere; disable instead of deleting |
| 423 | `plan_limit_exceeded` | Plan ceiling reached → banner, stop the spinner, do not retry |
| 426 | `upgrade_required` | Force-update (not currently reachable — see §4) |
| 429 | `rate_limited` | Back off. Especially on OTP: 3/phone, 10/device, 30/IP per hour |
| 503 | `maintenance_mode` | Show a maintenance screen |

Two extra codes come from the OTP flow and are not in `ErrorCode`: `otp_invalid` (401,
wrong/expired code — clear the field, never auto-resend) and the OTP cooldown, which
surfaces as `rate_limited`.

On `422 validation_failed` the **first** message is flattened into `error.message`, so you
can show something useful without walking `details`. A raw Laravel validation payload
never reaches you, and no endpoint ever returns HTML.

---

## 8. Money

**Every amount is an integer in the smallest currency unit.** There is no float anywhere
on a money path, in either direction.

- Never divide by 100 speculatively. **SYP has 0 decimals**, so the integer *is* the
  displayed number. The per-currency `decimals` column is the authority.
- Never compute a total locally and treat it as truth. Re-read the total the server
  returns after every mutation of a cart, an order or a price list.
- Where the server sends a preformatted label, render the label.
- Rounding happens on the server. If your arithmetic disagrees, yours is wrong.

---

## 9. Lists

| Param | Default | Notes |
|---|---|---|
| `page` | 1 | page-numbered lists |
| `per_page` | **25** | **hard max 100 — a larger value is silently clamped**, not rejected |
| `search` | — | where documented; matching field varies per endpoint |
| `sort` | per endpoint | prefix `-` to reverse, where supported |
| `filter[x]` | — | bracket syntax, e.g. `filter[status]=pending` |

Not every list is paginated — several endpoints return a plain array. Each app file says
which. Exports act on the **active filters**, not on the page you are looking at.

---

## 10. Stability — how to read the endpoint tables

Every endpoint table in the app files carries a `Stability` column. It is the most
important column on the page.

| Value | Meaning | What you should do |
|---|---|---|
| **stable** | Registered, and at the path the API catalog specifies | Build on it |
| **moving** | Registered and callable, but at a path the catalog does **not** specify. It will move | Build, but put the base path behind one constant |
| **missing** | In the catalog, no route registered. Returns 404 today | Do not build. Do not mock |

Today: **178** routes are registered under `/api/v1`. **163** are stable, **15** are
moving, and **180** catalog endpoints have no route at all.

The moving set is small and entirely predictable — channel administration and the two
reference collections:

| Live now | Will become |
|---|---|
| `/admin/channels…` (5) | `/platform/channels…` |
| `/governorates…` (5) | `/platform/refs/governorates…` |
| `/zones…` (5) | `/platform/refs/zones…` |

The missing set is concentrated: **SP-03 (32)** and **SP-04 (22)** — platform references
and channel administration — plus SP-13 money (18), SP-14 sync (20), SP-15 content and
loyalty (14), SP-16 (11) and SP-17 (63).

A handful of routes are live but appear in **no** catalog entry at all — `brands`,
`shortages`, `customers`, the channel settings pair and channel zones. They are real and
callable; the catalog has simply not caught up, exactly as `CLAUDE.md` describes for
`sc.notify.view`. Each app file lists its own.

---

## 11. Regenerating the truth

Nothing in this documentation is opinion. Reproduce it:

```bash
php artisan route:list --json          # what is actually registered
cat docs/api/b2b-api.catalog.json      # what is specified
```

If a document disagrees with `route:list`, **`route:list` wins** — and the document is the
thing to fix.
