# Field rep app (Flutter)

Everything needed to build the field-representative app. Self-contained: you should not
need another file except the shared contract and the Dart kit.

| | |
|---|---|
| Path | `apps/rep` |
| Platform | Flutter — Android + iOS |
| Guard | `app` (Sanctum bearer) |
| Kind gate | `RequireAppKind:rep` on every `/app/rep/*` route |
| `X-Client` | `rep-android` / `rep-ios` *(sent for logs; the server ignores it)* |
| Seed account | **none** — create one with an OTP for any Syrian number (§2) |
| Shared kit | [../03-flutter-client.md](../03-flutter-client.md) |
| Contract | [../01-http-contract.md](../01-http-contract.md) |

Verified against `php artisan route:list` and the controller/action source on
**2026-09-07**. Where the API catalog disagrees with this page, the code wins.

**26 endpoints are live for this app.** The whole field day works — customers, cart,
assignments, warehouse pickup, delivery, returns. The rep **wallet and cash collection do
not exist** (§5), which shapes what you can ship.

---

## 1. At a glance

```
Base URL   http://10.0.2.2:8000/api/v1     (Android emulator; a device needs your LAN IP)
Auth       Authorization: Bearer {token}
Writes     X-Idempotency-Key on every POST/PATCH/DELETE except the three OTP calls
Money      integer, minor units, SYP renders with 0 decimals
Times      ISO-8601 Asia/Damascus, already converted
```

---

## 2. A token in two minutes — and the trap that costs a week

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
[Flutter kit](../03-flutter-client.md#5-token_storedart).

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

## 3. Endpoint reference

`Stability`: **stable** = registered at its contract path · **moving** = registered
elsewhere, will move · **missing** = catalogued, no route (§5).

### 3.1 Auth and session

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

### 3.2 Duty, zones and customers

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

### 3.3 Catalog, cart and orders

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

### 3.4 Warehouse pickup — the gate to delivery

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

### 3.5 Delivery

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

⚠️ `ask_payment` is hardcoded `true` — but **there is no rep payments endpoint** (§5), so
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

### 3.6 Returns, pricing and offers

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

## 4. What to build, in order

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

## 5. Not built — do not mock

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

## 6. Gotchas

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
