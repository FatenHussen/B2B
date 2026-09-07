# Retailer app (Flutter)

Everything needed to build the shop-owner app. Self-contained: you should not need
another file except the shared contract and the Dart kit.

| | |
|---|---|
| Path | `apps/retailer` |
| Platform | Flutter — Android + iOS |
| Guard | `app` (Sanctum bearer) |
| Kind gate | `RequireAppKind:retailer` on every `/app/retailer/*` route |
| `X-Client` | `retailer-android` / `retailer-ios` *(sent for logs; the server ignores it)* |
| Seed account | **none** — create one with an OTP for any Syrian number (§2) |
| Shared kit | [../03-flutter-client.md](../03-flutter-client.md) |
| Contract | [../01-http-contract.md](../01-http-contract.md) |

Verified against `php artisan route:list` and the controller/action source on
**2026-09-07**. Where the API catalog disagrees with this page, the code wins.

**26 endpoints are live for this app.** Everything about *goods* — browse, cart, submit,
track, receive, return — is built. Everything about *money*, *offline sync* and
*engagement* is not (§5).

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
[Flutter kit](../03-flutter-client.md#5-token_storedart).

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

## 3. Endpoint reference

`Stability`: **stable** = registered at its contract path · **moving** = registered
elsewhere, will move · **missing** = catalogued, no route (§5).

### 3.1 Auth and session

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

### 3.2 Catalog

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
especially `total_debt`, since the whole finance surface is unbuilt (§5).

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

### 3.3 Cart

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

### 3.4 Orders, receipts, returns

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

⚠️ `ask_payment` is hardcoded `true` — but **there is no payments endpoint** (§5), so it
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

### 3.5 Pricing and offers (shared with the rep app)

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

## 4. What to build, in order

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

## 5. Not built — do not mock

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

## 6. Gotchas

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
