# Platform admin — API reference

Every endpoint the platform dashboard can call today, as the server actually serves it.

| | |
|---|---|
| Base | `/api/v1` |
| Guard | `platform` (Sanctum bearer) |
| `X-Client` | `platform-web` |
| Envelope + error map | [`../../_shared/http-contract.md`](../../_shared/http-contract.md) |
| Screens and flows | [`L1.md`](./L1.md) · [`L4.md`](./L4.md) |

This file is the **shape** reference: paths, permissions, request bodies, response
bodies. It does not repeat the header rules or the error map — those live in the
shared HTTP contract and are not restated here.

**41 routes are live.** They are listed below with the EP-ID from the API catalog.
Anything not on this page does not exist yet, however plausible its path looks. Do
not code against a path you inferred from a sibling.

---

## 1. Ground rules for this guard

**Auth.** `Authorization: Bearer {token}` on every route except `POST /platform/auth/login`
and `POST /platform/auth/2fa/verify`.

A token issued to another guard does **not** return 401 here — it returns **403
`wrong_guard`**. Treat that as a bug in your token store, not as an expired session:
dropping the token and re-logging in will produce the same 403 forever. Only
`unauthenticated` and `token_revoked` (both 401) mean "go back to login".

**Idempotency.** Every `POST` `PUT` `PATCH` `DELETE` on this guard requires
`X-Idempotency-Key`, with exactly two exemptions: `auth/login` and `auth/2fa/verify`.
A missing key is `400 idempotency_key_required` — a client bug, not a user error.

The key is hashed together with the method, the path **and the request body**. Three
consequences the dashboard has to honour:

| You do | Server does |
|---|---|
| Retry the same key with the same body | Replays the stored response, adds `Idempotent-Replayed: true` |
| Retry the same key with a *changed* body | `409 idempotency_key_conflict` |
| Retry while the first call is still running | `409 operation_in_progress` |

So a key belongs to a **user intent**, not to an HTTP attempt. Keep it in the form
state, not in the fetch wrapper. This is what makes the `requires_password_confirm`
replay in §3 work: confirm the password, then send the *same* key and the *same*
body — a new key would execute the write twice.

Stored responses live 24 h (`IDEMPOTENCY_TTL_HOURS`). Only 2xx are stored; a failed
write releases its key immediately, so the user can correct the form and resubmit
under the same key.

**Locale.** `Accept-Language: ar|en`. Anything else falls back to `ar`. Error messages
and `name_ar` fields come back in the negotiated language.

**Timestamps.** Every timestamp this guard returns is ISO-8601 in **`Asia/Damascus`**,
already converted. Do not re-apply a timezone offset on the client. (One exception —
see §7.)

**Lists.** `?page=`, `?per_page=` (default **25**, hard max **100** — a larger value is
silently clamped, not rejected), plus `?search=` and `filter[*]` where noted.

```jsonc
{ "data": [ ], "meta": { "server_time": "...", "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
```

`meta.server_time` is on **every** response, list or not.

---

## 2. Path warning: `/admin/channels` vs `/platform/channels`

The channel CRUD routes are served under **`/api/v1/admin/channels`**, and they are
guarded by the role `platform_admin` rather than by a permission code.

The API catalog specifies these as `/platform/channels` (EP-AD-051…), and the shared
HTTP contract tells clients "never `/admin/channels`". **Both are true statements about
different things:** the catalog is the target contract, and `/admin/channels` is what
is deployed right now.

Recommendation: put the channel base path behind one constant in the client kit. When
the backend moves these routes to `/platform/channels`, one edit switches you over.
Do not scatter `/admin/channels` through feature code, and do not build against
`/platform/channels` today — it 404s.

The richer channel surface described in `L1.md` (provisioning polling, `allowed_next`
transitions, usage, limits, export, join applications) is **catalog-only**. None of it
is live. What exists is the five plain CRUD routes in §7.

---

## 3. Authentication

### `POST /platform/auth/login` — EP-AD-001

No auth. No idempotency key.

```jsonc
{ "email": "admin@platform.sy", "password": "password" }
```

Two possible successes. Branch on `requires_2fa`:

```jsonc
// 2FA enabled — no token yet
{ "data": { "requires_2fa": true, "challenge_token": "cht_a1b2c3d4" }, "meta": { } }
```

```jsonc
// 2FA not enabled — session issued
{ "data": {
    "token": "17|xxxxxxxx",
    "user": {
      "id": 1, "name": "...", "email": "...",
      "roles": ["platform_admin"],
      "permissions": ["ad.iam.view_catalog"]
    },
    "expires_at": "2026-09-08T12:00:00+03:00"
  }, "meta": { } }
```

Hold `challenge_token` in React state only — never localStorage, never a cookie. It
expires in **10 minutes** and dies after **5** failed attempts.

`expires_at` is computed from `sanctum.expiration`, which is currently `null` — so the
token does **not actually expire server-side**, and the value you get is a nominal
24 h from issue. Do not build a client-side auto-logout on it; trust 401 instead.

Wrong credentials → `401 unauthenticated`, deliberately generic. Do not tell the user
which half was wrong.

### `POST /platform/auth/2fa/verify` — EP-AD-002

No auth. No idempotency key.

```jsonc
{ "challenge_token": "cht_a1b2c3d4", "code": "123456" }
```

Returns the same `{ token, user, expires_at }` as an unchallenged login. `code` accepts
a TOTP **or** one of the recovery codes from §5. Bad code → `401 otp_invalid`; clear the
input, do not auto-retry, and do not re-issue a challenge.

### `GET /platform/auth/me` — EP-AD-004

Also served at `GET /platform/me` (EP-AD-159A) — identical handler, identical payload.

```jsonc
{ "data": {
    "id": 1, "name": "...", "email": "...", "phone": null,
    "roles": ["platform_admin"],
    "permissions": ["ad.iam.view_catalog"],
    "two_factor_enabled": false,
    "last_login_at": "2026-09-07T09:12:00+03:00"
  }, "meta": { } }
```

`permissions` is the **only** source for `can()`. Build every nav item and every action
button from it. Note `roles` matters independently: channel CRUD (§7) is gated on the
role `platform_admin`, not on a permission — a user can hold every `ad.*` permission and
still get 403 on `/admin/channels`.

### `POST /platform/auth/logout` — EP-AD-003

Body empty. Deletes the current token only, not the user's other sessions.
Returns `{ "data": { "success": true } }`.

### `POST /platform/auth/confirm-password` — EP-AD-005

```jsonc
{ "password": "..." }
```
```jsonc
{ "data": { "confirmed_until": "2026-09-07T09:27:00+03:00" } }
```

Opens a **15-minute** window. Exactly one route consumes it: `PUT /platform/me/password`.

The flow: the write returns `403 requires_password_confirm` → show the modal → call this
→ **replay the original write with the same idempotency key and the same body.** Wrong
password here is `401 unauthenticated`, not a 422.

### `GET /platform/auth/sessions` — EP-AD-006

```jsonc
{ "data": [ { "id": "3", "ip": "...", "agent": "...", "last_active_at": "...", "current": true } ] }
```

Not paginated. Newest activity first. `id` is a **string**; `current` marks the caller's
own row — never offer to revoke it without a distinct confirmation.

### `DELETE /platform/auth/sessions/{id}` — EP-AD-007

Needs an idempotency key. `{ "data": { "success": true } }`.

---

## 4. Profile — `/platform/me`

No permission code. Every authenticated admin may call these.

| Method | Path | EP | Notes |
|---|---|---|---|
| `GET` | `/platform/me` | EP-AD-159A | Same payload as `auth/me` |
| `PUT` | `/platform/me` | EP-AD-159B | Profile |
| `PUT` | `/platform/me/password` | EP-AD-159C | **Needs password confirm** |

**`PUT /platform/me`** — `name` is `required` even for a one-field edit, so send the
whole object; a PATCH-style partial drops the name and 422s.

```jsonc
{ "name": "...", "phone": "0912345678" }
```
```jsonc
{ "data": { "updated": true } }
```

`phone` is validated as a **Syrian** number and normalised server-side. Echo back what
`/me` returns rather than what the user typed.

**`PUT /platform/me/password`** — the only route behind `RequirePasswordConfirmation`.

```jsonc
{ "current_password": "...", "password": "...", "password_confirmation": "..." }
```

`min:8` and `confirmed`, so `password_confirmation` must be present and equal — that is a
client-side check you should make first, since the server's 422 arrives after a round
trip. Returns `{ "data": { "updated": true } }`.

---

## 5. Two-factor and API tokens

| Method | Path | EP |
|---|---|---|
| `POST` | `/platform/me/2fa/enable` | EP-AD-159D |
| `POST` | `/platform/me/2fa/confirm` | EP-AD-159E |
| `GET` | `/platform/me/2fa/recovery-codes` | EP-AD-159F |
| `GET` | `/platform/me/api-tokens` | EP-AD-159G |
| `POST` | `/platform/me/api-tokens` | EP-AD-159H |
| `DELETE` | `/platform/me/api-tokens/{id}` | EP-AD-159I |

**`POST .../2fa/enable`** — body empty, key required.

```jsonc
{ "data": {
    "secret": "otpauth://totp/B2B:admin%40platform.sy?secret=...&issuer=B2B",
    "qr_svg": "<svg/>"
  } }
```

`secret` is a **full `otpauth://` URI**, not a bare base32 string — feed it straight to a
QR renderer, do not wrap it in another URI. Two-factor is **not on** after this call; the
secret is only pending. Nothing changes until confirm succeeds.

⚠️ `qr_svg` currently returns an **empty `<svg/>` placeholder**. Render the QR client-side
from `secret`. Do not ship the server's SVG.

**`POST .../2fa/confirm`** — `{ "code": "123456" }`, exactly 6 characters.

```jsonc
{ "data": { "enabled": true, "recovery_codes": ["a1b2c3d4", "e5f6a7b8"] } }
```

The 8 recovery codes are shown **once, here, and never again**. Force a copy/download
step before the dialog can close. A wrong code is `401 otp_invalid` and leaves 2FA off.

**`GET .../2fa/recovery-codes`** returns only `{ "codes_remaining": 8 }` — a count, never
the codes. Label the UI accordingly; there is no "show my codes again".

**`GET /platform/me/api-tokens`** — not paginated, newest first.

```jsonc
{ "data": [ { "id": 12, "name": "ci-bot", "last_used_at": null, "created_at": "..." } ] }
```

**`POST /platform/me/api-tokens`** — note the field name: the current password goes in
`password_confirmation`, which reads like a repeat field but is not.

```jsonc
{ "name": "ci-bot", "password_confirmation": "..." }
```
```jsonc
{ "data": { "id": 13, "token": "13|xxxx" } }
```

`token` is plaintext once. Wrong password → `403 requires_password_confirm` — and note
this route does **not** use the 15-minute window from §3; it checks the password inline
every time. Do not send the user through the confirm-password modal here.

---

## 6. IAM — `/platform/iam`

Nine permission codes gate this section. Read them off `/me.permissions`.

| Method | Path | EP | Permission |
|---|---|---|---|
| `GET` | `/platform/iam/permissions` | EP-AD-010 | `ad.iam.view_catalog` |
| `GET` | `/platform/iam/permissions/{code}/holders` | EP-AD-011 | `ad.iam.view_catalog` |
| `GET` | `/platform/iam/roles` | EP-AD-012 | `ad.iam.view_catalog` |
| `POST` | `/platform/iam/roles` | EP-AD-013 | `ad.iam.role_create` |
| `POST` | `/platform/iam/roles/preview` | EP-AD-027 | `ad.iam.view_catalog` |
| `POST` | `/platform/iam/roles/{id}/approve` | EP-AD-014 | `ad.iam.role_approve` |
| `PUT` | `/platform/iam/roles/{id}/permissions` | EP-AD-015 | `ad.iam.role_create` |
| `POST` | `/platform/iam/assignments` | EP-AD-016 | `ad.iam.role_assign` |
| `DELETE` | `/platform/iam/assignments` | EP-AD-017 | `ad.iam.role_assign` |
| `POST` | `/platform/iam/simulate` | EP-AD-018 | `ad.iam.simulate` |
| `POST` | `/platform/iam/temp-grants` | EP-AD-019 | `ad.iam.grant_temp` |
| `POST` | `/platform/iam/temp-grants/{id}/approve` | EP-AD-020 | `ad.iam.grant_temp` |
| `GET` | `/platform/iam/sod-rules` | EP-AD-021 | `ad.iam.sod_rules` |
| `POST` | `/platform/iam/reviews` | EP-AD-024 | `ad.audit.review` |
| `GET` | `/platform/iam/reviews/{id}` | EP-AD-028 | `ad.audit.review` |
| `POST` | `/platform/iam/reviews/{id}/items/{itemId}/decide` | EP-AD-029 | `ad.audit.review` |
| `GET` | `/platform/iam/approval-requests` | EP-AD-025 | `ad.iam.view_catalog` |
| `POST` | `/platform/iam/approval-requests/{id}/decide` | EP-AD-026 | `ad.iam.role_approve` |

A 403 from any of these names the permission it wanted:

```jsonc
{ "error": { "code": "insufficient_permission", "message": "...", "permission": "ad.iam.role_create" } }
```

Read `error.permission` for a precise message — but prefer hiding the control up front
via `can()`.

### Permissions catalog

**`GET /platform/iam/permissions`** — paginated. `filter[system]`
(`platform|channel|warehouse|app`), `filter[severity]`, `search` (matches the code **or**
`name_ar`).

```jsonc
{ "data": [ {
    "code": "ad.iam.role_create", "name_ar": "...",
    "system": "platform", "module": "access", "severity": "critical",
    "dual_approval": true, "delegatable": false,
    "roles_count": 2, "users_count": 5
  } ], "meta": { "page": 1, "per_page": 25, "total": 133 } }
```

The catalog is a static PHP list, filtered in memory then paginated; `roles_count` and
`users_count` are counted from the database for the current page only. **133 codes are
seeded** — DOC-08 names 170, and the unseeded remainder simply have no route yet.

`dual_approval: true` is your signal to route the action through the approval inbox
rather than expecting it to execute inline.

**`GET /platform/iam/permissions/{code}/holders`** — not paginated, not a list envelope.

```jsonc
{ "data": {
    "roles": [ { "id": 3, "key": "iam_operator" } ],
    "users": [ { "id": 9, "name": "#9" } ],
    "recent_usage": [ { "at": "...", "actor": 1, "action": "..." } ]
  } }
```

⚠️ `users[].name` is literally `"#" + id` — the query never joins the user tables. Render
it as an id, or resolve names yourself. Capped at **50** users with no indication that
more exist. Unknown code → `404 not_found`.

### Roles

**`GET /platform/iam/roles`** — paginated. `filter[system]`, `filter[status]`,
`sort=id|name|created_at` (prefix `-` to reverse), `search` over name and label.

```jsonc
{ "data": [ {
    "id": 3, "key": "iam_operator", "name": "...", "system": "platform",
    "status": "draft", "is_builtin": false,
    "permissions_count": 12, "users_count": 4
  } ] }
```

`key` is the machine name, `name` the display label (falls back to `key`). `is_builtin`
roles must not be offered for deletion.

**`POST /platform/iam/roles/preview`** — call this *before* create, on every change to the
permission selection. Read-only, but a POST.

```jsonc
{ "permissions": ["ad.iam.role_create", "ad.iam.role_approve"] }
```
```jsonc
{ "data": { "summary_ar": "...", "sod_conflicts": [ ] } }
```

`summary_ar` is a ready-to-render Arabic sentence — print it, do not compose your own.
A code outside the catalog throws rather than returning an empty preview.

**`POST /platform/iam/roles`** → **201**.

```jsonc
{ "name": "...", "key": "iam-operator", "system": "platform",
  "description": null, "permissions": ["ad.iam.view_catalog"], "copy_from_role_id": null }
```
```jsonc
{ "data": { "id": 7, "status": "draft", "sod_conflicts": [] } }
```

`key` is `alpha_dash`, max 64. `system` is one of `platform|channel|warehouse|app`.

The new role is **`draft` and inert.** It grants nothing until approved. Never show it as
active, and make the pending state visible in the list — a role stuck in draft because
nobody approved it is the most common IAM support ticket.

**`POST /platform/iam/roles/{id}/approve`** — `{ "reason": "..." }`, 3–500 chars.

```jsonc
{ "data": { "status": "active" } }
```

**The creator cannot approve their own role** → `403 sod_violation`. Check the actor
before showing the button, and when the 403 does arrive, explain that a *second* admin is
required — do not offer a retry.

**`PUT /platform/iam/roles/{id}/permissions`** — full replacement, not a delta.

```jsonc
{ "permissions": ["ad.iam.view_catalog"], "reason": "..." }
```
```jsonc
{ "data": { "changed": ["ad.iam.view_catalog"], "sod_conflicts": [] } }
```

Send the complete desired set; omitted codes are revoked. `changed` is the diff the
server computed — render that, not your own. A permission whose `system` does not match
the role's is `422 validation_failed`.

Note this route is gated on `ad.iam.role_create`, not on a separate edit permission.

### Assignments

**`POST /platform/iam/assignments`** — bulk, **max 50** user ids.

```jsonc
{ "user_ids": [9, 10], "role_id": 3, "expires_at": null, "reason": "..." }
```
```jsonc
{ "data": { "assigned": [9], "rejected": [ { "user_id": 10, "reason": "sod_violation" } ] } }
```

**Partial success is the normal case and still returns 200.** Never report "done" off the
status code — render `assigned` and `rejected` side by side, with each rejection's reason.
A user silently missing from both lists is a server bug worth surfacing.

`expires_at` makes the assignment temporary; null is permanent.

**`DELETE /platform/iam/assignments`** — a DELETE **with a body**:

```jsonc
{ "user_id": 9, "role_id": 3, "reason": "..." }
```
```jsonc
{ "data": { "success": true } }
```

`fetch` and axios both need explicit configuration to send a body on DELETE. Verify it
leaves the browser; a silently dropped body arrives as `422 validation_failed`.

### Simulate

**`POST /platform/iam/simulate`** — read-only "why can/can't this user do X".

```jsonc
{ "user_type": "platform", "user_id": 9, "permission": "ad.iam.role_create",
  "resource_type": null, "resource_id": null }
```
```jsonc
{ "data": {
    "allowed": false,
    "decision_path": [
      { "layer": "guard",      "result": "pass", "reason": "platform" },
      { "layer": "permission", "result": "fail", "reason": "role:viewer" },
      { "layer": "tenant",     "result": "pass", "reason": "same channel" },
      { "layer": "sod",        "result": "pass", "reason": "clear" },
      { "layer": "temp-grant", "result": "skip", "reason": "none" }
    ] } }
```

Five layers, always in this order, `pass|fail|skip`. Render as a trace so the operator
sees *which* layer refused. Two caveats worth putting in the UI copy:

- The `tenant` layer is **hard-coded to pass**. It is not yet a real check.
- `allowed` is `guard && (permission || temp-grant) && !sod`.

### Temporary grants

**`POST /platform/iam/temp-grants`**

```jsonc
{ "user_id": 9, "permission": "ad.iam.role_create", "duration_minutes": 60,
  "reason": "at least twenty characters of justification" }
```
```jsonc
{ "data": { "id": 4, "status": "pending_approval", "expires_request_at": "..." } }
```

`duration_minutes` is **1–240**. `reason` needs **≥ 20 characters** — enforce it in the
form, this is the field users most often trip on. The grant starts `pending_approval` and
confers nothing yet; `expires_request_at` is the deadline for *approving*, not the grant's
own expiry.

**`POST /platform/iam/temp-grants/{id}/approve`** — same `DecideRequest` body as the
approval inbox, so it both approves and rejects:

```jsonc
{ "decision": "approve", "reason": "..." }
```
```jsonc
// approve
{ "data": { "granted_until": "2026-09-07T11:00:00+03:00" } }
// reject
{ "data": { "granted_until": null, "status": "rejected" } }
```

The requester cannot approve their own grant → `403 sod_violation`.

**`GET /platform/iam/sod-rules`** — read-only, not paginated.

```jsonc
{ "data": [ { "code": "SOD-01", "permission_a": "...", "permission_b": "...",
              "reason": "...", "exceptions": [] } ] }
```

Load these once and use them to explain a `sod_violation` in terms the operator
recognises. There is no create/update route.

### Approval inbox

**`GET /platform/iam/approval-requests`** — paginated, `filter[status]`, `filter[type]`,
newest first.

```jsonc
{ "data": [ {
    "id": 5, "permission": "...", "action": "...", "requested_by": 1,
    "payload": { }, "status": "pending", "created_at": "..."
  } ] }
```

`payload` is free-form JSON describing the pending change — render it generically
(key/value), since its shape varies by `action`.

**`POST /platform/iam/approval-requests/{id}/decide`**

```jsonc
{ "decision": "approve", "reason": "..." }
```
```jsonc
{ "data": { "status": "approved", "executed": true } }
```

**`executed` is the field that matters, not `status`.** An approved request whose
execution failed returns `approved` with `executed: false`. Never render "applied" off
`status` alone.

Deciding an already-decided request → `409 conflict`. Approving your own →
`403 sod_violation`.

### Access reviews

**`POST /platform/iam/reviews`** — `{ "quarter": "2026-Q3", "scope": "platform" }`

```jsonc
{ "data": { "campaign_id": 2, "items_count": 48 } }
```

**`GET /platform/iam/reviews/{id}`** — not paginated; every item comes back at once. A
large campaign returns a long array, so virtualise the table rather than paging it.

```jsonc
{ "data": { "campaign_id": 2, "quarter": "2026-Q3", "status": "open",
    "items": [ { "id": 11, "user_id": 9, "role_id": 3,
                 "suggestion": "keep", "decision": null } ] } }
```

`decision: null` means undecided. Drive a progress indicator off the null count.

**`POST /platform/iam/reviews/{id}/items/{itemId}/decide`**

```jsonc
{ "decision": "keep", "reason": "..." }
```
```jsonc
{ "data": { "item_id": 11, "decision": "keep" } }
```

Note the vocabulary differs from the approval inbox: here it is **`keep|revoke`**, there
it is `approve|reject`. Two different dialogs — do not share one component blindly.

---

## 7. Channels — `/admin/channels`

Read §2 before using these. Path is `/api/v1/admin/channels`, **not** `/platform/channels`.

Gate is the **role `platform_admin`**, not a permission code — check `roles` from `/me`,
not `permissions`. A 403 here therefore carries no `error.permission` field.

| Method | Path | Returns |
|---|---|---|
| `GET` | `/admin/channels` | Paginated list, ordered by name |
| `POST` | `/admin/channels` | **201** + the channel |
| `GET` | `/admin/channels/{id}` | One channel |
| `PUT` | `/admin/channels/{id}` | Updated channel |
| `DELETE` | `/admin/channels/{id}` | **204, empty body** |

The resource, identical in every response:

```jsonc
{ "id": 1, "name": "...", "slug": "acme-dist", "legal_name": null, "tax_number": null,
  "phone": null, "email": null, "status": "active", "settings": {},
  "created_at": "2026-09-01T10:00:00+00:00" }
```

⚠️ `created_at` here is **UTC**, unlike every other timestamp on this guard (§1). This one
resource does not apply the Damascus conversion. Handle it explicitly.

**`POST`** requires `name` and `slug`; `slug` is `alpha_dash` and unique. `status` is
`active|suspended`. **`PUT` is a partial update** — fields are `sometimes`, so send only
what changed. That is the opposite of `PUT /platform/me` (§4), which demands the whole
object.

**`DELETE` returns 204 with no body.** Do not parse a JSON envelope from it. The model
uses soft deletes, so the row is hidden rather than destroyed — but there is no restore
route, so treat it as final in the UI.

`GET /admin/channels` takes no filters and no search: `page` and `per_page` only. The
list-side filtering described in `L1.md` belongs to the catalog's `/platform/channels`,
which is not built.

---

## 8. Audit — `/platform/audit`

| Method | Path | EP | Permission |
|---|---|---|---|
| `GET` | `/platform/audit` | EP-AD-022 | `ad.audit.view` |
| `POST` | `/platform/audit/export` | EP-AD-023 | `ad.audit.export` |

**`GET /platform/audit`** — paginated. Filters go in a `filter` object:
`filter[actor]`, `filter[action]`, `filter[channel_id]`, `filter[date_from]`,
`filter[date_to]`.

```jsonc
{ "data": [ {
    "at": "...", "actor": 1, "action": "role.approved",
    "entity_type": "...", "entity_id": 7,
    "before": { }, "after": { },
    "ip": "...", "impersonated": false
  } ], "meta": { "page": 1 } }
```

`before` and `after` are arbitrary JSON and may both be null. Render a generic diff, not a
typed one. `impersonated: true` deserves a visible badge.

**`POST /platform/audit/export`** — asynchronous.

```jsonc
{ "filters": { "date_from": "...", "date_to": "...", "actor": 1,
               "action": "...", "channel_id": 2 },
  "format": "xlsx" }
```
```jsonc
{ "data": { "job_id": "..." } }
```

Note the field is `filters` (plural) here, while the list endpoint reads `filter` — an easy
mistake when you share one filter-state object between the table and its export button.

`format` is `xlsx|csv`, defaulting server-side. **There is no job-status route yet.** Show
a "queued" banner and stop; do not poll, and do not block the button on a completion you
cannot observe. The export honours the filters you send, not the page the user is looking
at.

---

## 9. What is not here

These appear in `L1.md`/`L4.md` or in the API catalog, and are **not implemented** on this
guard. Keep the route shell if you like, but disable submit and show no invented data:

- `GET /platform/dashboard` — live GMV and charts. **Do not mock this.**
- Reference data under `/platform/*`: governorates, zones, activity types, root
  categories, sale units, equipment, currencies, FX. *(`/api/v1/governorates` and
  `/api/v1/zones` do exist, but outside the platform prefix — they are not part of this
  guard's contract, so confirm with the backend before wiring them into admin screens.)*
- Channel provisioning, `allowed_next` transitions, usage, limits, export, join
  applications, bulk plan.
- Notification campaigns, plan SKUs, team CRUD, impersonation, support, feature flags.
- Any job-status endpoint for the audit export.

**Rule for this dashboard:** if a path is not in §3–§8 of this file, it is not live. Ask
the backend for the ticket rather than inferring a shape from a sibling route.

---

## 10. Response-shape cautions, collected

The ones most likely to cost an afternoon:

| # | Where | Watch out |
|---|---|---|
| 1 | All writes | Idempotency key hashes the **body** too — same key + changed body = 409 |
| 2 | `requires_password_confirm` | Replay with the **same** key and body; a new key double-writes |
| 3 | Cross-guard token | **403 `wrong_guard`**, not 401 — re-login will not fix it |
| 4 | `/admin/channels` | Not `/platform/channels`; gated by **role**, not permission |
| 5 | `created_at` on a channel | **UTC**, while everything else is `Asia/Damascus` |
| 6 | `PUT /platform/me` | Full object required — `PUT /admin/channels/{id}` is partial |
| 7 | `POST /me/api-tokens` | Current password goes in `password_confirmation` |
| 8 | `2fa/enable` | `qr_svg` is an **empty placeholder** — render from `secret` yourself |
| 9 | `2fa/confirm` | Recovery codes appear **once**, ever |
| 10 | `permissions/{code}/holders` | `users[].name` is `"#id"`; capped at 50 silently |
| 11 | `POST /iam/assignments` | Partial success returns **200** — read `rejected` |
| 12 | `DELETE /iam/assignments` | DELETE **with a body** |
| 13 | `PUT /iam/roles/{id}/permissions` | Full replacement, not a delta |
| 14 | New roles | Land as **`draft`**, grant nothing until a second admin approves |
| 15 | Self-approval | `403 sod_violation` on roles, grants and approvals |
| 16 | `approval-requests/{id}/decide` | Read **`executed`**, not `status` |
| 17 | Review items | Vocabulary is `keep\|revoke`, not `approve\|reject` |
| 18 | `GET /iam/reviews/{id}` | Unpaginated — virtualise long campaigns |
| 19 | `simulate` | `tenant` layer is hard-coded `pass` |
| 20 | `audit/export` | Body key is `filters`; list uses `filter`. No status route |
| 21 | `DELETE /admin/channels/{id}` | **204, no body** — do not parse an envelope |
| 22 | `expires_at` at login | Nominal; Sanctum expiration is `null`. Trust 401 |
