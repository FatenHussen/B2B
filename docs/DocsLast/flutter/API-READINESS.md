# Flutter — what the backend actually serves today

Verified on **2026-09-07** against `php artisan route:list` on `main`, diffed against
`docs/api/catalog/07-retailer.php`, `08-rep.php`, `09-shared-app.php`, `00-public.php`.

The `L1.md` … `L4.md` files in [retailer/](./retailer/) and [rep/](./rep/) describe the **target**
contract. This file is the **status** of that contract: what is registered and callable right now,
and what still returns 404. Read both — build a screen only when its row here says ready.

> Registered ≠ certified. A route in the ready table exists, is guarded, and returns the envelope.
> It is not a promise that every branch of its payload is populated; check the ticket if a field is empty.

---

## 1. Headline

| Surface | Catalog endpoints | Registered | Missing |
|---|---:|---:|---:|
| Retailer (`EP-RT-*`) | 28 | 23 | 5 |
| Field rep (`EP-RP-*`) | 28 | 23 | 5 |
| Shared app (`EP-CM/APP/SY-*`) | 18 | 5 | 13 |
| Public / auth (`EP-CM-001..003`) | 4 | 3 | 1 |
| **App + public total** | **78** | **53** | **25** |

The whole API registers **178** routes under `/api/v1` — 57 `app`, 47 `channel`, 19 `warehouse`,
36 `platform`, 5 `admin`, 10 reference, 3 public, 1 health.

**The shape of the gap:** everything a shop or a rep does with *goods* is ready — browse, cart,
submit, track, receive, return. Everything to do with *money* (SP-13), *offline sync* (SP-14) and
*engagement* (SP-15) is not. Layers 1 to 3 are buildable end to end. **Layer 4 is not startable.**

---

## 2. Ready now

### Auth — no guard, then guard `app`

| Method | Path | EP | Notes |
|---|---|---|---|
| POST | `/api/v1/public/auth/request-otp` | EP-CM-001 | `{ otp_id, channel_used, expires_in, resend_after }` |
| POST | `/api/v1/public/auth/verify-otp` | EP-CM-002 | issues the token — see §4 |
| POST | `/api/v1/public/auth/resend-otp` | EP-CM-003 | `{ channel_used, resend_after }` |
| POST | `/api/v1/app/retailer/register` | EP-RT-001 | ability `registration` |
| POST | `/api/v1/app/rep/register` | EP-RP-001 | ability `registration` |
| GET | `/api/v1/app/session` | EP-CM-004 | |
| POST | `/api/v1/app/auth/logout` | EP-CM-005 | |
| GET | `/api/v1/health` | EP-CORE-001 | unguarded, use for a reachability probe |

### Retailer — 23 ready

| Method | Path | EP | Layer |
|---|---|---|---|
| GET | `/app/retailer/home` | EP-RT-010 | L2 |
| GET | `/app/retailer/categories` | EP-RT-011 | L2 |
| GET | `/app/retailer/products` | EP-RT-012 | L2 |
| GET | `/app/retailer/products/{id}` | EP-RT-013 | L2 |
| POST | `/app/retailer/products/{id}/favorite` | EP-RT-014 | L2 |
| GET | `/app/retailer/cart` | EP-RT-020 | L3 |
| POST | `/app/retailer/cart/lines` | EP-RT-021 | L3 |
| PATCH | `/app/retailer/cart/lines/{id}` | EP-RT-022 | L3 |
| DELETE | `/app/retailer/cart/lines/{id}` | EP-RT-023 | L3 |
| PATCH | `/app/retailer/cart/sections/{ref}` | EP-RT-024 | L3 |
| POST | `/app/retailer/cart/submit` | EP-RT-025 | L3 |
| GET | `/app/retailer/orders` | EP-RT-030 | L3 |
| GET | `/app/retailer/orders/{id}` | EP-RT-031 | L3 |
| POST | `/app/retailer/orders/{id}/cancel` | EP-RT-032 | L3 |
| POST | `/app/retailer/orders/{id}/reorder` | EP-RT-033 | L3 |
| GET | `/app/retailer/orders/{id}/tracking` | EP-RT-034 | L3 |
| GET | `/app/retailer/receipts/{subOrderId}` | EP-RT-040 | L3 |
| PATCH | `/app/retailer/receipts/{id}/lines/{lineId}` | EP-RT-041 | L3 |
| POST | `/app/retailer/receipts/{id}/confirm` | EP-RT-042 | L3 |
| POST | `/app/retailer/return-requests` | EP-RT-043 | L3 |
| GET | `/app/retailer/return-requests` | EP-RT-044 | L3 |
| POST | `/app/retailer/reps/{id}/rate` | EP-RT-045 | L3 |
| GET/POST/DELETE | `/app/retailer/brands`, `/brands/{id}`, `/shortages`, `/shortages/{id}` | — | see §5 |

### Field rep — 23 ready

| Method | Path | EP | Layer |
|---|---|---|---|
| GET | `/app/rep/products` | EP-RP-010 | L2 |
| POST | `/app/rep/zones` | EP-RP-071 | L2 |
| GET | `/app/rep/zones/{id}/shops` | EP-RP-020 | L3 |
| POST | `/app/rep/cart/lines` | EP-RP-021 | L3 |
| GET | `/app/rep/cart` | EP-RP-022 | L3 |
| POST | `/app/rep/cart/sections/{retailer_id}/submit` | EP-RP-023 | L3 |
| GET | `/app/rep/assignments` | EP-RP-030 | L3 |
| POST | `/app/rep/assignments/{id}/accept` | EP-RP-031 | L3 |
| POST | `/app/rep/assignments/{id}/reject` | EP-RP-032 | L3 |
| GET | `/app/rep/scheduled-orders` | EP-RP-033 | L3 |
| PATCH | `/app/rep/status` | EP-RP-034 | L3 |
| GET | `/app/rep/warehouse-receipts` | EP-RP-040 | L3 |
| POST | `/app/rep/warehouse-receipts/{handoverId}/confirm` | EP-RP-041 | L3 |
| GET | `/app/rep/deliveries` | EP-RP-050 | L3 |
| GET | `/app/rep/deliveries/{id}` | EP-RP-051 | L3 |
| PATCH | `/app/rep/deliveries/{id}/lines/{lineId}` | EP-RP-052 | L3 |
| POST | `/app/rep/deliveries/{id}/complete` | EP-RP-053 | L3 |
| POST | `/app/rep/deliveries/{id}/postpone` | EP-RP-054 | L3 |
| POST | `/app/rep/deliveries/{id}/fail` | EP-RP-055 | L3 |
| POST | `/app/rep/locations/ping` | EP-RP-056 | L3 |
| POST | `/app/rep/return-requests` | EP-RP-057 | L3 |
| GET/POST | `/app/rep/customers` | — | see §5 |

### Shared

| Method | Path | EP |
|---|---|---|
| POST | `/app/pricing/quote` | EP-APP-030 |
| GET | `/app/offers` | EP-APP-040 |
| GET | `/app/offers/{id}` | EP-APP-041 |

---

## 3. Not built — do not code the screen

Every path below returns **404**. There is no stub and no feature flag; wire nothing to them.

### SP-13 money — the entire Layer 4 finance surface

| Method | Path | EP | Blocks |
|---|---|---|---|
| POST | `/app/retailer/payments` | EP-RT-050 | retailer `FE-RT-FIN` |
| GET | `/app/retailer/account/summary` | EP-RT-051 | debts chip on home |
| GET | `/app/retailer/account/statement` | EP-RT-052 | |
| POST | `/app/retailer/account/statement/export` | EP-RT-053 | |
| GET | `/app/retailer/debts` | EP-RT-054 | |
| POST | `/app/rep/payments` | EP-RP-060 | rep collection |
| GET | `/app/rep/wallet` | EP-RP-061 | |
| POST | `/app/rep/wallet/withdrawals` | EP-RP-062 | |
| GET | `/app/rep/wallet/withdrawals` | EP-RP-063 | |
| GET | `/app/rep/receivables` | EP-RP-064 | |
| POST | `/app/receipts/reserve` | EP-CM-050 | shared `receipt_no` reservation |

### SP-14 offline sync + inbox

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

**Consequence for the outbox.** `L4.md` specifies a `client_op_id` outbox draining into
`POST /app/sync/push`. That endpoint does not exist, so an offline queue has nowhere to drain.
Build the app **online-first**. You may persist an outbox locally, but do not ship a UI that
promises deferred delivery until SP-14 lands.

### SP-15 content + loyalty

| Method | Path | EP |
|---|---|---|
| GET | `/app/content/home-blocks` | EP-APP-100 |
| GET | `/app/loyalty` | EP-APP-110 |
| POST | `/app/loyalty/redeem` | EP-APP-111 |

Home sliders stay empty and the loyalty header stays hidden. Per `L2.md`, show nothing rather
than a placeholder number.

> **Correction to [retailer/L4.md](./retailer/L4.md):** it names `GET /app/loyalty/wallet`.
> The catalog endpoint is `GET /app/loyalty` (EP-APP-110). Neither is registered; use the
> catalog path when it ships.

### Bootstrap references

| Method | Path | EP | Note |
|---|---|---|---|
| GET | `/public/refs` | EP-PB-001 | **deliberately absent** — see the comment in `app-modules/reference/routes/api.php` |
| GET | `/public/app-config` | EP-PB-010 | not built |

There is no unauthenticated reference snapshot. A pre-login screen cannot fetch governorates or
activity types today: the reference routes (`/api/v1/governorates`, `/api/v1/zones`) sit behind
`auth:platform`, not `app`. **Registration must collect its reference ids after the OTP token
exists**, or ship them bundled in the binary.

---

## 4. The auth contract, exactly as implemented

`POST /public/auth/verify-otp` returns:

```jsonc
{
  "data": {
    "token": "…",
    "is_new_user": true,
    "user_type": null,          // "retailer" | "rep" once chosen
    "profile_completed": false,
    "user": { "id": 1, "name": "", "phone": "09…" }
  },
  "meta": { "server_time": "2026-09-07T…+03:00" }
}
```

**Token abilities are the gate, and they are not obvious:**

- profile incomplete → the token carries ability **`registration` only**. It opens
  `/app/retailer/register` and `/app/rep/register` and *nothing else*.
- profile complete → ability `*`.

So a half-registered user holds a valid token that fails on every product call. Branch on
`profile_completed`, not on "do I have a token". **Re-verify OTP after registering** to obtain
the `*` token — registration does not upgrade the token you already hold.

**`RequireAppKind` splits the two apps.** Every `/app/retailer/*` route carries
`app.kind:retailer` and every `/app/rep/*` carries `app.kind:rep`. One phone is one kind; a rep
token on a retailer route fails. There is no switcher.

Send `Authorization: Bearer {token}`. Bearer tokens only — the cookie SPA flow is for the web
dashboards.

---

## 5. Live routes the catalog does not list

These are registered and callable but carry no `EP-` id. They are real, and the catalog has not
caught up — the same situation `CLAUDE.md` describes for `sc.notify.view`. Use them; expect the
shape to be confirmed when the catalog entry is written.

| Method | Path | For |
|---|---|---|
| GET | `/app/retailer/brands` · `/app/retailer/brands/{id}` | brand browse |
| GET/POST/DELETE | `/app/retailer/shortages` · `/shortages/{id}` | shortage reporting |
| GET/POST | `/app/rep/customers` | rep customer list + create |

---

## 6. Two rules that will bite on day one

**Idempotency is global and mandatory.** `EnsureIdempotency` is appended to the whole `api`
group, not to selected routes. **Every POST, PUT, PATCH and DELETE** must send
`X-Idempotency-Key` or it fails with **400 `idempotency_key_required`** before reaching the
controller. Generate a UUID per user action; reuse it on retry.

Replay semantics: same key + same body → the stored 2xx response replays for 24 h. Same key +
different body → **409 `idempotency_key_conflict`**. Still running → **409 `operation_in_progress`**.
Non-2xx responses delete the key, so a failed call is safely retryable with the same key.

The **only** exempt paths (`config/core.php` → `idempotency_exempt`) are the credential ones:
`public/auth/request-otp`, `verify-otp`, `resend-otp` — plus the channel, platform and warehouse
login routes the apps never call. Omit the header on exactly those three; send it everywhere else.

**Money is always an integer in minor units.** No floats anywhere on a money path. SYP renders
with 0 decimals. Never multiply a price locally and treat the result as truth — re-read the total
the server returns on every cart mutation.

---

## 7. Envelope

```jsonc
{ "data": {}, "meta": { "server_time": "…" } }
{ "data": [], "meta": { "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
{ "data": [], "meta": { "per_page": 25, "next_cursor": "…", "prev_cursor": null, "has_more": true } }
{ "error": { "code": "…", "message": "…", "permission": "…", "details": {} } }
```

`meta.server_time` is Asia/Damascus ISO-8601 and is present on **every** success. Trust it over
the device clock. `204` responses have no body.

Branch on `error.code`, never on `message` — the message is Arabic or English per
`Accept-Language` and is for display only.

| HTTP | Codes the apps will actually meet |
|---|---|
| 400 | `idempotency_key_required` |
| 401 | `unauthenticated`, `token_revoked` → drop the token, return to OTP |
| 403 | `wrong_guard`, `insufficient_permission` → wrong app, or a `registration`-only token |
| 404 | `not_found` — also means "not yours"; never disclose existence |
| 409 | `illegal_transition`, `operation_in_progress`, `idempotency_key_conflict`, `stale_version` |
| 422 | `validation_failed` — the first message is pre-flattened into `error.message` |
| 423 | `plan_limit_exceeded` |
| 426 | `upgrade_required` → force-update screen |
| 429 | `rate_limited` → back off, especially on OTP |
| 503 | `maintenance_mode` |

A raw Laravel validation payload never reaches you, and no endpoint returns HTML.

---

## 8. Build order

1. **Now:** retailer L1 → L2 → L3, and rep L1 → L2 → L3. Fully unblocked.
2. **Not now:** either app's L4. 22 of its 25 endpoints are missing.
3. **Design around:** no `/public/refs`, so registration reference data comes post-token or bundled.
4. **Recheck** before starting any L4 screen — see §9.

---

## 9. Reproducing this file

Nothing here is hand-maintained opinion; it is a diff of two machine-readable sources.

```bash
php artisan route:list --path=api/v1/app          # implemented
grep -rhoE "EP-[A-Z]+-[0-9]+" docs/api/catalog/   # planned
```

Regenerate after any sprint merge. If a row here disagrees with `route:list`, `route:list` wins.
