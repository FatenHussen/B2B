# Channel dashboard (Next.js)

Everything needed to build the supplier's dashboard. Self-contained: you should not need
another file except the shared contract and the React kit.

| | |
|---|---|
| Path | `apps/channel-web` |
| Port | **3001** |
| Guard | `channel` (Sanctum) |
| `X-Client` | `channel-web` *(sent for logs; the server ignores it)* |
| Seed account | phone **`+963900000001`**, OTP from the log — channel `demo-channel` |
| Shared kit | [../02-react-client.md](../02-react-client.md) |
| Contract | [../01-http-contract.md](../01-http-contract.md) |

Verified against `php artisan route:list` and the controller/action source on
**2026-09-07**. Where the API catalog disagrees with this page, the code wins.

**47 endpoints are live** — the largest surface in the platform, and the most complete.
Catalog, pricing, offers, inventory, orders and returns all work end to end.

---

## 1. At a glance

```
Base URL   http://127.0.0.1:8000/api/v1
Auth       Authorization: Bearer {token}
Writes     X-Idempotency-Key on every POST/PUT/PATCH/DELETE except the two OTP calls
Money      integer minor units everywhere EXCEPT channel zones (§3.8) — read that section
Times      ISO-8601 Asia/Damascus, already converted
Tenant     resolved from your own membership — see below
```

⚠️ **`X-Channel-Id` does nothing for you.** `ResolveTenant` honours that header **only for
`platform_admin`**. A channel user's tenant comes from their own `supply_channel_id` or
default membership, and the header is silently ignored — not an error, just no effect.

`verify-otp` returns a `channels[]` array, so a user *can* belong to several. But **there
is no way to switch between them** from this dashboard today. If `channels.length > 1`,
show the default one and say so; do not build a switcher that cannot work.

Every list is scoped to your channel automatically. A foreign id returns **404, never
403** — existence is never disclosed.

---

## 2. A token in two minutes

```bash
# 1. request an OTP (no auth, NO idempotency key)
curl -s -X POST http://127.0.0.1:8000/api/v1/channel/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"+963900000001"}'
# { "data": { "otp_id": "otp_ab12cd" } }

# 2. read the code from the log — no WhatsApp is sent locally
tail -n 50 storage/logs/laravel.log | grep OTP

# 3. verify (no auth, NO idempotency key)
curl -s -X POST http://127.0.0.1:8000/api/v1/channel/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"otp_id":"otp_ab12cd","code":"123456"}'
```

```jsonc
{ "data": {
    "token": "14|xxxx",
    "channels": [ { "id": 1, "name": "Demo Channel" } ],
    "permissions": [ "sc.catalog.view", "sc.orders.confirm" ]
  }, "meta": { "server_time": "…" } }
```

Then `Authorization: Bearer 14|xxxx` on everything else.

⚠️ **`request-otp` returns only `otp_id`** — no `expires_in`, no `resend_after`, unlike the
mobile OTP. Hardcode the known values: TTL **300 s**, cooldown **60 s**, **5** attempts.
⚠️ **There is no channel resend endpoint.** If the code expires, request a new one.
⚠️ A phone with no `ChannelUser` row, or one with no membership, returns
**`403 insufficient_permission`** — not 404.
📌 Rate limits are per hour: **3/phone**, 10/device, 30/IP. Easy to burn while testing.

`permissions` is the only source for `can()`. Every control below is gated on it.

---

## 3. Endpoint reference

`Stability`: **stable** = registered at its contract path · **moving** = registered
elsewhere, will move · **missing** = catalogued, no route (§5).

📌 Five channel routes are live but appear in **no catalog entry at all** — the settings
pair and the three zone routes. They are marked `stable*`. They are real and callable; the
catalog has not caught up, exactly as `CLAUDE.md` describes for `sc.notify.view`.

### 3.1 Auth and settings

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-001 | POST | `/channel/auth/request-otp` | stable | — |
| EP-SC-002 | POST | `/channel/auth/verify-otp` | stable | — |
| — | GET | `/channel` | stable* | `sc.settings.view` |
| — | PUT | `/channel` | stable* | `sc.settings.update` |

**`GET` / `PUT /channel`** — your own channel profile.

```jsonc
{ "data": { "id": 1, "name": "…", "slug": "demo-channel",
            "legal_name": null, "tax_number": null, "phone": null, "email": null,
            "status": "active", "settings": {},
            "created_at": "2026-09-01T10:00:00+00:00" } }
```

⚠️ `created_at` here is **UTC**, unlike every other timestamp on this guard. Handle it
explicitly.
**`PUT`** accepts `name` (`sometimes`), `legal_name`, `tax_number`, `phone`, `email`,
`settings`. ⚠️ `slug` and `status` are **not** editable here — only the platform can change
them.

### 3.2 Catalog

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-020 | GET | `/channel/products` | stable | `sc.catalog.view` |
| EP-SC-021 | POST | `/channel/products` | stable | `sc.catalog.create` |
| EP-SC-022 | PUT | `/channel/products/{id}` | stable | `sc.catalog.update` |
| EP-SC-023 | POST | `/channel/products/bulk` | stable | `sc.catalog.update` |
| EP-SC-024 | POST | `/channel/products/{id}/variants/generate` | stable | `sc.catalog.variants` |
| EP-SC-025 | GET | `/channel/brands` | stable | `sc.catalog.view` |
| EP-SC-026 | POST | `/channel/brands` | stable | `sc.catalog.create` |
| EP-SC-027 | GET | `/channel/categories/tree` | stable | `sc.catalog.view` |
| EP-SC-028 | POST | `/channel/categories` | stable | `sc.catalog.create` |
| EP-SC-029 | POST | `/channel/categories/reorder` | stable | `sc.catalog.update` |
| EP-SC-030 | GET | `/channel/catalog/export` | stable | `sc.catalog.view` |
| EP-SC-031 | POST | `/channel/catalog/import` | stable | `sc.catalog.import` |

**`GET /channel/products`** — paginated, and **thin**:

```jsonc
{ "data": [ { "id": 12, "sku": "SKU-1", "name_ar": "…", "status": "active" } ] }
```

⚠️ Only these four keys — no brand, no category, no price, no image. Build the list around
them, or fetch per row.
Filters: `filter[brand_id]`, `filter[category_id]`, `filter[status]`, `filter[zone_id]`,
`filter[search]` (name/SKU/barcode). `per_page` default 25, max 100.
⚠️ **Never send `sort`** — no `allowedSorts` is declared, so any `sort` throws a Spatie
error. This applies to *every* list on this guard. Order is `-created_at`.

**`POST /channel/products`** → **201** `{ "id": 12, "sku": "SKU-1" }`.
**`PUT /channel/products/{id}`** → 200 `{ "id": 12 }` — ⚠️ **`sku` is omitted on update**.

⚠️ **`PUT` is a full replace, not a patch.** It uses the same FormRequest, so `name_ar` and
`sku` remain **required** — send the whole product or you will 422.

Body (abridged; all optional unless marked):

```jsonc
{ "name_ar": "…",              // REQUIRED ≤200
  "sku": "SKU-1",              // REQUIRED ≤80, unique per channel
  "name_en": null, "brand_id": null, "category_id": null,
  "model_no": null, "barcode": null, "status": "active|draft|…",
  "short_description": null, "long_description": null,
  "specs": [ {"key":"…","value":"…"} ],
  "media": { "images": [], "primary": null, "video": null },
  "sale_unit_id": null,
  "unit_factors": [ {"from_unit_id":1,"to_unit_id":2,"factor":12} ],
  "min_order_qty": 1, "order_multiple": 1, "weight_gram": 0,
  "pricing": { "type": "simple|tiered", "base_price": 5000, "currency_id": null,
               "tiers": [ {"from":3,"to":10,"price":4500} ] },
  "inventory": { "tracked": true, "reorder_point": 0, "allow_backorder": false },
  "availability": { "zone_ids": [1], "activity_type_ids": [1], "lead_time_days": 0 },
  "marketing": { "tags": [], "sliders": [], "priority": 0 } }
```

📌 `pricing.base_price` and every tier `price` are **integers in minor units**.
⚠️ `availability.retailer_group_ids` is validated and then **never read** — silently
dropped. Do not build UI for it.
Errors: `422 validation_failed` with `details.sku` on a duplicate; `422
channel_limit_exceeded` when the plan's SKU cap is reached (⚠️ this code is a bare string,
**not** in `ErrorCode`, and it is **422 here, not 423**).

**`POST /channel/products/bulk`** — `{product_ids: [≤200], action: activate|disable|delete_draft, payload?}`
→ `{ "affected_count": 7 }`.
⚠️ `payload` is validated then ignored. ⚠️ `delete_draft` only touches `draft` rows, so
`affected_count` can be **lower than the ids you sent** with no per-id report. Show the
count, not "all done".

**`POST /channel/products/{id}/variants/generate`** — `{axes:[{name, values:[…]}]}`

```jsonc
{ "data": { "variants": [ { "id": 9, "sku": "…", "combination": {"اللون":"أحمر"},
                            "price": 5000, "stock": 0, "barcode": null, "image": null } ] } }
```

⚠️ **`stock` is hardcoded `0`**, `image` always `null`, and **every variant carries the
identical price** — the parent's qty-1 quote, computed once outside the loop. Do not
present per-variant pricing or stock from this response.

**`GET /channel/categories/tree`** — plain array, **not paginated**, no params.

Two node shapes. Roots come from the reference table, children from `categories`:

```jsonc
{ "id":1, "name":"…", "image":"…", "icon":null,
  "parent_id": null, "level": 1, "order": 1, "status": "active",
  "direct_products": 0, "total_products": 42,
  "children": [ { "id":5, "name":"…", "parent_id":1, "level":2,
                  "status":"active", "direct_products":12, "total_products":12,
                  "children": [] } ] }
```

⚠️ On **root** nodes `parent_id` is always `null`, `level` always `1`, `status` always the
literal `"active"`, and `direct_products` always `0`. Only `total_products` is real there.
📌 Category depth is capped at **5** — a deeper `parent_id` is `422 catalog.category_level`.

**`POST /channel/categories`** → 201 `{id}`. `{name, parent_id (required), image?, icon?,
order?, activity_type_ids?}`. New categories are always created `active`.

**`POST /channel/categories/reorder`** — `{moves:[{id, parent_id, order}]}` →
`{ "updated": 3 }`. Runs in a transaction, so **one bad move rolls back all of them**.
Errors (all 422, all keyed on `moves`): `category_not_found`, `parent_required`,
`category_cycle`, `category_level`, `parent_not_found`.

**`GET /channel/catalog/export`** → `{ "job_id": "job_catalog_…" }`. Async, queue
`exports`. ⚠️ **There is no job-status endpoint** — show "queued" and stop; do not poll.
Filter: `filter[status]` only.

**`POST /channel/catalog/import`** — multipart `{file, type: products, dry_run?}` → 200

```jsonc
{ "data": { "preview": [ {"sku":"…","name_ar":"…"} ],
            "errors": [ {"row":2,"message":"…"} ],
            "duplicates": ["SKU-1"] } }
```

📌 Always `dry_run=true` first. ⚠️ `preview` reads **only columns A (sku) and B (name_ar)**
and is capped at the **first 500 rows**; `row` counts from the header, so the first data
row is `2`. ⚠️ When `dry_run` is false the job is dispatched but **the response is
identical — the `job_id` is never returned**, so a real import is unobservable from the
UI. Warn the user before they run it.
⚠️ `type` is validated then ignored.

### 3.3 Pricing

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-040 | GET | `/channel/price-lists` | stable | `sc.pricing.view` |
| EP-SC-041 | POST | `/channel/price-lists` | stable | `sc.pricing.update` |
| EP-SC-042 | POST | `/channel/price-lists/{id}/schedule` | stable | `sc.pricing.schedule` |
| EP-SC-043 | POST | `/channel/pricing/bulk-update` | stable | `sc.pricing.update` |
| EP-SC-044 | GET | `/channel/pricing/change-log` | stable | `sc.pricing.view` |
| EP-SC-045 | PUT | `/channel/products/{id}/pricing` | stable | `sc.pricing.update` |
| EP-SC-046 | PUT | `/channel/reps/{id}/discount-cap` | stable | `sc.reps.update` |

**`GET /channel/price-lists`** — paginated `{id, name, type, status}`. `filter[type]`.

**`POST /channel/price-lists`** → **201** `{id, status}`.

```jsonc
{ "name": "…", "type": "zone|retailer|group",
  "zone_ids": [1], "group_id": null, "retailer_id": null,
  "adjustment": { "mode": "percent|fixed", "value": -10 },
  "effective_from": null, "effective_to": null, "reason": null }
```

📌 `status` is **derived, not supplied**: a future `effective_from` yields `scheduled`,
otherwise `active`.
Errors (422): `zone_ids_required`, `retailer_required`, `group_required`,
`zone_not_found` — depending on `type`.

**`POST /channel/price-lists/{id}/schedule`** — `{changes:[{product_id, base_price}],
effective_from}` → `{ "scheduled_job_id": "job_plist_…" }`. Forces the list to
`scheduled`. ⚠️ No status endpoint for that job.

**`POST /channel/pricing/bulk-update`** — `{product_ids: [≤200], mode, value, reason}` →
`{ "affected_count": 12 }`.
⚠️ Products outside your channel and products with no base-price row are **silently
skipped** — no error, no per-id report. Compare `affected_count` against what you sent and
tell the user if they differ.
📌 Percent maths is **integer truncating** (`intdiv`), so results can be a unit off what a
float calculator predicts. The server is right.

**`GET /channel/pricing/change-log`** — paginated.

```jsonc
{ "at": "…", "user": 3, "product": 12, "before": 5000, "after": 4500, "reason": "…" }
```
⚠️ Keys are `user` and `product` — **bare ints**, not `user_id`/`product_id` and not nested
objects. No names are resolved; resolve them yourself.
Filters: `filter[product_id]`, `filter[user_id]`, `filter[date]`.

**`PUT /channel/products/{id}/pricing`** — `{type, base_price, currency_id?, tiers?, reason?}`
→ `{ "price_change_log_id": 44 }`. Full replace.

**`PUT /channel/reps/{id}/discount-cap`** — `{max_discount_percent: 0..100, max_cash_hold}`

```jsonc
{ "data": { "rep": { "id": 8, "max_discount_percent": 10, "max_cash_hold": 500000 } } }
```
📌 Nested under `rep`. ⚠️ `id` is echoed from the URL, not read back from the row.
📌 **Setting this matters:** with no limit row a rep's cap is **0**, and every discount they
attempt is rejected. If reps report "discount always fails", this is why.
⚠️ `max_cash_hold` is stored but **unenforceable today** — the rep wallet endpoints do not
exist (see [rep.md §5](./rep.md#5-not-built--do-not-mock)).
Errors: `422 validation_failed` keyed on **`id`** when the rep is not in your channel.

### 3.4 Offers

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-050 | GET | `/channel/offers` | stable | `sc.offers.view` |
| EP-SC-051 | POST | `/channel/offers` | stable | `sc.offers.create` |
| EP-SC-052 | GET | `/channel/offers/{id}/performance` | stable | `sc.offers.view` |
| EP-SC-053 | PATCH | `/channel/offers/{id}/stop` | stable | `sc.offers.stop` |

**`GET /channel/offers`** — paginated `{id, name, type, status}`. `filter[status]`, `filter[type]`.

**`POST /channel/offers`** → **201** `{id}`. Defaults: `status: draft`, `targeting.scope: all`.

```jsonc
{ "name": "…", "type": "buy_x_get_y|product_discount|…",
  "media": [], "description": null,
  "components": [ {"product_id":12,"qty":2} ],
  "rules": { "buy_qty": 2, "get_qty": 1 },
  "rewards": { "product_id": 13, "qty": 1, "discount_percent": null, "discount_amount": null },
  "targeting": { "scope":"all|zones|groups|retailers",
                 "activity_type_ids": [], "zone_ids": [], "group_ids": [], "retailer_ids": [] },
  "constraints": { "starts_at": null, "ends_at": null, "total_qty": null,
                   "per_retailer_max": null, "per_order_max": null,
                   "min_invoice_value": null, "min_items": null },
  "stackable": false, "priority": 0, "status": "draft" }
```

⚠️ **`targeting.group_ids` is validated and never persisted** — silently dropped. Do not
offer group targeting.
⚠️ **A reward without `product_id` is silently discarded.** The code only writes the reward
block `if isset($rewards['product_id'])`, so a pure percentage discount saves nothing and
returns 201 as if it worked. Require a reward product in your form until this is fixed.

**`GET /channel/offers/{id}/performance`** — ⚠️ **the entire response is a hardcoded stub**:

```jsonc
{ "data": { "applied_count": 0, "linked_sales": 0, "discount_given": 0,
            "net_margin": 0, "retailers_count": 0, "by_zone": [], "conversion_rate": 0 } }
```

Every number is literally `0` and `by_zone` is always `[]`, regardless of real redemptions.
**Do not build the analytics screen.** Showing these as results would tell a supplier their
promotion sold nothing.

**`PATCH /channel/offers/{id}/stop`** — note **PATCH**. `{reason}` required →
`{"status":"stopped"}`. ⚠️ No state guard — stopping a draft or an already-stopped offer
succeeds.

### 3.5 Inventory

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-060 | GET | `/channel/inventory/levels` | stable | `sc.inventory.view` |
| EP-SC-061 | GET | `/channel/inventory/movements` | stable | `sc.inventory.view` |
| EP-SC-062 | POST | `/channel/inventory/adjust` | stable | `sc.inventory.adjust` |
| EP-SC-063 | POST | `/channel/inventory/transfers` | stable | `sc.inventory.transfer` |
| EP-SC-064 | PUT | `/channel/inventory/reorder-points` | stable | `sc.inventory.reorder` |

**`GET /channel/inventory/levels`** — paginated.

```jsonc
{ "product": { "id": 12, "name_ar": "…" },
  "variant": { "id": 9 },              // null when there is no variant
  "warehouse": { "id": 3, "name": "…" },
  "available": 40, "reserved": 5, "in_transit": 0, "damaged": 2 }
```
⚠️ `warehouse.name` falls back to the **empty string**, not null. ⚠️ `variant` carries only
`id` — no SKU, no combination.
Filters: `filter[warehouse_id]`, `filter[product_id]`.

**`GET /channel/inventory/movements`** — paginated
`{id, at, type, qty_before, qty_after}`.
⚠️ **No product, no warehouse, no reason, no actor** — five keys only. You cannot build an
audit view from this alone.

**`POST /channel/inventory/adjust`** — `{product_id, variant_id?, warehouse_id, qty_delta
(signed), reason}` → `{ "movement_id": 88, "available": 35 }`.
Errors: `422` on `warehouse_id`/`product_id`; **`409 insufficient_stock`** when a negative
delta would go below zero.

**`POST /channel/inventory/transfers`** → **201** `{ "id": 4, "status": "sent" }`.
`{from_warehouse_id, to_warehouse_id, lines:[{product_id, variant_id?, qty}]}`.
Errors: `422 same_warehouse`, `422 warehouse_not_found`, `422` keyed
`lines.{i}.product_id`, and **`409 insufficient_stock`**.
⚠️ **Not transactional** — the transfer row is written *before* stock moves, so a 409
leaves an orphaned transfer. Re-fetch after any 409 here rather than assuming nothing
happened.

**`PUT /channel/inventory/reorder-points`** — `{items:[{product_id, warehouse_id,
variant_id?, point}]}` → `{ "updated": 5 }`.
⚠️ **Not transactional** — items before a failing index are already saved. The 422 names
the failing index (`items.{i}.…`); re-send the remainder.

### 3.6 Sub-orders — the operational core

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-070 | GET | `/channel/sub-orders` | stable | `sc.orders.view` |
| EP-SC-071 | GET | `/channel/sub-orders/{id}` | stable | `sc.orders.view` |
| EP-SC-072 | POST | `/channel/sub-orders/{id}/confirm` | stable | `sc.orders.confirm` |
| EP-SC-073 | POST | `/channel/sub-orders/bulk-confirm` | stable | `sc.orders.confirm` |
| EP-SC-074 | POST | `/channel/sub-orders/{id}/reject` | stable | `sc.orders.reject` |
| EP-SC-075 | POST | `/channel/sub-orders/{id}/cancel` | stable | `sc.orders.cancel` |
| EP-SC-076 | PATCH | `/channel/sub-orders/{id}/lines` | stable | `sc.orders.edit_lines` |
| EP-SC-077 | POST | `/channel/sub-orders/assign` | stable | `sc.orders.assign` |
| EP-SC-078 | POST | `/channel/sub-orders/{id}/reassign` | stable | `sc.orders.reassign` |
| EP-SC-079 | POST | `/channel/sub-orders/{id}/schedule` | stable | `sc.orders.schedule` |

**`GET /channel/sub-orders`** — paginated `{id, sub_order_no, status, zone_id, total}`.
⚠️ No retailer name and no `created_at` in the row.
Filters: `filter[status]`, `filter[zone_id]`, `filter[retailer_id]`, `filter[rep_id]`,
`filter[source]`, and `filter[waiting_over_minutes]` (an SLA filter: orders older than N
minutes).

**`GET /channel/sub-orders/{id}`**

```jsonc
{ "data": {
    "header": { "sub_order_no": "SO-9", "status": "pending", "shop": "…" },
    "lines": [ {"id":31,"product_id":12,"qty":3,"unit_price":5000} ],
    "financials": { "subtotal": 15000, "discount": 0, "total": 15000 },
    "note": null,
    "timeline": [ {"stage":"pending","at":"…"} ],
    "allowed_actions": ["confirm","reject","edit_lines"] } }
```

📌 **`allowed_actions` is the contract for your action buttons.** It is computed from the
state machine *and* filtered by your permissions. Render buttons from it — never from your
own status logic — and the 409s below become unreachable.
⚠️ One caveat: if the user's permission list is **empty**, the filter is skipped and *all*
actions are returned. Do not treat `allowed_actions` as an authorisation check.
⚠️ `header` has no `id` and `lines[]` have no product name or line total.

**The state machine.** Legal source states per action:

| Action | Allowed from |
|---|---|
| `confirm`, `reject` | `pending` |
| `cancel` | `pending, confirmed, assigned, accepted, processing, postponed` |
| `edit_lines` | `pending, confirmed` |
| `assign` | `confirmed, postponed` |
| `reassign` | `assigned, accepted` |
| `schedule` | `confirmed, assigned, accepted` |

Anything else is **`409 illegal_transition`**.

**`POST /{id}/confirm`** — **no body**.

```jsonc
{ "data": { "status": "confirmed", "picking_list_id": 7 } }
```
⚠️ `picking_list_id` can be `null` if the listener has not created it yet in the same
request. Do not deep-link on null.
Errors: `409 illegal_transition` (not `pending`); **`409 insufficient_stock`** when the
reservation fails (rolled back); `404 not_found` if the channel has **no default
warehouse** — ⚠️ note this arrives with code `not_found`/404 despite being an inventory
setup problem, so surface a clear message.

**`POST /sub-orders/bulk-confirm`** — `{ids: [≤50]}`

```jsonc
{ "data": { "confirmed": [9, 10],
            "failed": [ {"id": 11, "error": "insufficient_stock"} ] } }
```
⚠️ **Always 200, even when every id fails.** `error` is the bare domain code, no message.
Render both lists; never report success off the status code.

**`POST /{id}/reject`** and **`/{id}/cancel`** — `{reason}` required ≤255 →
`{"status":"rejected"|"cancelled"}`.
⚠️ `cancel` also returns `409` once the warehouse handover is confirmed, even from a legal
state.

**`PATCH /{id}/lines`** — `{changes:[{line_id, qty, removed?}], reason}` →
`{status, total}`. The order is re-quoted and totals recomputed; status is unchanged.
⚠️ Unknown `line_id`s are **silently skipped**. ⚠️ `reason` is validated but **never
persisted or audited** — do not promise an audit trail. ⚠️ Not transactional, and for
non-`pending` orders it releases and re-reserves stock, so **`409 insufficient_stock`** can
leave quantities already saved. Re-fetch after a 409.

**`POST /sub-orders/assign`** — `{sub_order_ids:[…], rep_id, mode?}` →
`{ "assigned": [9, 10] }`.
⚠️ `mode` is validated then ignored. ⚠️ Unresolvable ids are **silently skipped**, and the
loop is **not transactional** — a failure midway leaves earlier ids assigned. Re-fetch the
list after any error.
Errors: `422 validation_failed` — `rep_off_coverage` (rep not in your channel, or the
order's zone is outside the rep's zones) or `rep_off_duty`; `409 illegal_transition`.

**`POST /{id}/reassign`** — `{rep_id, reason?}` → ⚠️ `{"status":"assigned"}` — note the
**response key differs from `assign`** (`status`, not `assigned`). `reason` is not
persisted. Also 409 once the handover is confirmed.

**`POST /{id}/schedule`** — `{scheduled_at, reason?}` →
`{ "status": "postponed", "scheduled_at": "…" }`.
📌 The action is called *schedule* but the resulting status is **`postponed`**. Label the
button accordingly. `reason` is not persisted.

### 3.7 Returns

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-080 | GET | `/channel/return-requests` | stable | `sc.returns.view` |
| EP-SC-081 | POST | `/channel/return-requests/{id}/decide` | stable | `sc.returns.decide` |

**`GET`** — ⚠️ **plain array, not paginated, no filters**, ordered by id desc:
`{id, request_no, type, status}`. Statuses: `pending, approved, rejected, sorted`. Paginate
client-side; this will grow unbounded.

**`POST /{id}/decide`** — `{decision: approve|reject, reason?}` → `{"status":"approved"}`.
⚠️ **No state guard** — an already-decided request can be decided again. Gate the buttons
on `status === "pending"` yourself.
📌 An approved return then goes to the warehouse to be sorted — see
[warehouse-web.md](./warehouse-web.md).

### 3.8 Zones — ⚠️ money is a string here

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| — | GET | `/channel/zones` | stable* | `sc.zones.view` |
| — | POST | `/channel/zones` | stable* | `sc.zones.view` + `sc.zones.manage` |
| — | DELETE | `/channel/zones/{id}` | stable* | `sc.zones.view` + `sc.zones.manage` |

```jsonc
{ "data": [ { "id": 2, "zone_id": 4, "zone_name": "…",
              "delivery_days": ["sun","mon"],
              "delivery_fee": "12.50",       // ⚠️ STRING
              "min_order_value": "0.00" } ] }  // ⚠️ STRING, or null
```

⚠️ **This is the one place in the channel dashboard where money is a decimal string, not an
integer.** `delivery_fee` and `min_order_value` come back as `"12.50"`-style strings via a
money cast. Everything else on this guard is an integer in minor units. Do not run these
through your integer formatter, and do not `parseInt` them.

**`POST`** — `{zone_id (must exist), delivery_days?: sun..sat, delivery_fee?, min_order_value?}`
→ **201**.
📌 It is an **upsert keyed on `zone_id`** — posting an existing zone **updates** it and
still returns 201. There is no PUT.
**`DELETE /{id}`** → **204 with no body** — do not parse an envelope. Hard delete, no
restore.
⚠️ `zone_name` uses `whenLoaded`, so in principle the key can be **absent** rather than
null. Read it defensively.

---

## 4. What to build, in order

| # | Screen | Endpoints | Notes |
|---|---|---|---|
| 1 | Login (phone → OTP) | `auth/request-otp`, `auth/verify-otp` | no resend endpoint; 60 s timer is client-side |
| 2 | App shell + nav | `permissions` from login | every item behind `can()` |
| 3 | Channel settings | `GET`/`PUT /channel` | `created_at` is UTC |
| 4 | Zone coverage | `/channel/zones` | ⚠️ money is a string here |
| 5 | Categories tree | `categories/tree`, `categories`, `reorder` | depth ≤ 5; reorder is all-or-nothing |
| 6 | Brands | `brands` | |
| 7 | Products list + editor | `products`, `POST`, `PUT` | `PUT` is a full replace; never send `sort` |
| 8 | Variants | `variants/generate` | ignore `stock`/`price` in the response |
| 9 | Bulk actions | `products/bulk` | reconcile `affected_count` |
| 10 | Import / export | `catalog/import`, `export` | dry-run first; no job status |
| 11 | Pricing lists + bulk | `price-lists*`, `bulk-update` | reconcile `affected_count` |
| 12 | Price change log | `pricing/change-log` | resolve user/product names yourself |
| 13 | Rep discount caps | `reps/{id}/discount-cap` | without this, rep discounts always fail |
| 14 | **Orders queue** | `sub-orders`, `{id}`, confirm/reject | drive buttons from `allowed_actions` |
| 15 | Bulk confirm | `bulk-confirm` | render `confirmed` **and** `failed` |
| 16 | Assign / reassign / schedule | the three POSTs | note the differing response keys |
| 17 | Inventory levels + adjust | `inventory/*` | expect `409 insufficient_stock` |
| 18 | Transfers, reorder points | `transfers`, `reorder-points` | non-transactional — re-fetch on error |
| 19 | Returns inbox | `return-requests`, `decide` | gate on `status === pending` yourself |
| 20 | Offers | `offers`, `POST`, `stop` | require a reward product; **no analytics** |

Do not build the offer analytics screen (§3.4) or anything in §5.

---

## 5. Not built — do not mock

**`GET /channel/offers/{id}/performance` is live but returns hardcoded zeros** (§3.4).
That is worse than missing: it looks like data. Do not chart it.

Also absent from this guard, in the catalog with no route:

| Area | Note |
|---|---|
| Channel notifications log (`sc.notify.view`, EP-SC-092) | the permission exists in the catalog but the route does not |
| Job status for import/export/schedule | three endpoints hand you a `job_id` with nothing to poll |
| Channel finance — invoices, statements, settlements | SP-13; nothing on this guard |
| Channel reporting and dashboards | SP-17; the largest missing block (63 endpoints) |
| Rep wallet oversight | `max_cash_hold` is settable but unenforceable — see [rep.md §5](./rep.md#5-not-built--do-not-mock) |

Every one of these 404s today. Keep a route shell if you like, but disable submit and
never invent numbers — a fabricated sales figure is worse than an empty screen.

---

## 6. Gotchas

| # | Where | Watch out |
|---|---|---|
| 1 | `X-Channel-Id` | Ignored for channel users — honoured only for `platform_admin`. Multi-channel users cannot switch |
| 2 | `auth/request-otp` | Returns **only `otp_id`**. No `expires_in`/`resend_after`, and **no resend endpoint** |
| 3 | Login failure | No membership → **403 `insufficient_permission`**, not 404 |
| 4 | Every list | **Never send `sort`** — no `allowedSorts` anywhere; Spatie throws |
| 5 | `GET /channel` | `created_at` is **UTC**; every other timestamp is Damascus |
| 6 | `PUT /channel/products/{id}` | Full replace — `name_ar` and `sku` still required. Response omits `sku` |
| 7 | Products | `channel_limit_exceeded` is **422** here, not 423, and is not in `ErrorCode` |
| 8 | `products/bulk` | `delete_draft` skips non-drafts; `affected_count` may be lower than requested |
| 9 | `variants/generate` | `stock` hardcoded `0`, `image` null, **all variants share the parent price** |
| 10 | `categories/tree` | Root nodes: `parent_id` null, `level` 1, `status` "active", `direct_products` 0 — all fixed |
| 11 | `catalog/import` | Preview is columns A/B only, first 500 rows; a real import returns **no `job_id`** |
| 12 | `pricing/bulk-update` | Silently skips foreign/priceless products; percent maths truncates |
| 13 | `pricing/change-log` | Keys are `user` and `product`, bare ints, no names |
| 14 | `reps/{id}/discount-cap` | No limit row = cap **0** = every rep discount fails |
| 15 | Offers | `targeting.group_ids` silently dropped; a reward **without `product_id`** is silently discarded |
| 16 | `offers/{id}/performance` | **Entirely hardcoded zeros.** Do not chart |
| 17 | `offers/{id}/stop` | PATCH, and no state guard |
| 18 | `sub-orders/{id}` | Drive buttons from `allowed_actions` — but it is **not** an authorisation check |
| 19 | `confirm` | `picking_list_id` may be null; no default warehouse surfaces as **404 `not_found`** |
| 20 | `bulk-confirm` | **Always 200.** Read `failed`; `error` is a bare code |
| 21 | `PATCH .../lines` | Unknown lines skipped silently; `reason` never persisted; not transactional |
| 22 | `assign` vs `reassign` | Response keys differ: `{assigned:[…]}` vs `{status:"assigned"}` |
| 23 | `assign` | Silently skips unresolvable ids and is not transactional |
| 24 | `schedule` | Produces status **`postponed`**, not "scheduled" |
| 25 | `cancel` / `reassign` | Also 409 once the warehouse handover is confirmed |
| 26 | `return-requests` | Unpaginated, unfiltered, and `decide` has **no state guard** |
| 27 | `/channel/zones` | ⚠️ `delivery_fee` and `min_order_value` are **decimal strings**; POST is an upsert returning 201; DELETE is **204 no body** |
| 28 | Inventory writes | `transfers` and `reorder-points` are **not transactional** — re-fetch after a 409/422 |
| 29 | `not_found` | Also means "not yours". Never render "forbidden" |
| 30 | Money | Integers in minor units everywhere **except** `/channel/zones` |
