---
id: BE-C12
title: The /app/* surface after the channel-scope rollout — owner-scoped, channel-split models
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-04
points: 5
priority: Highest
contract: internal
endpoints: []
tables: []
events: []
blocked_by: []
---

# BE-C12 — The /app/* surface after the channel-scope rollout

## What is broken, proved through the routes

Read-only investigation, 2026-09-16. Every `/app/*` route the static reach analysis
flagged was called with a real app token and realistic fixtures — a product **with a
brand**, a retailer whose zone the channel covers, a rep on that channel. These answer
**500 `channel_scope_required`** today:

| Route | Model that throws | Site |
|---|---|---|
| `GET /app/retailer/home` | `Category` | `RetailerHome:32` — `->with('category')` on a Product query |
| `GET /app/retailer/products` | `Brand` | `ListRetailerProducts:28` — `->with(['brand', …])` |
| `GET /app/retailer/products/{id}` | `Brand` | `ShowRetailerProduct:33` — `->with(['brand', …])` |
| `GET /app/retailer/brands/{id}` | `Category` | `RetailerBrands::show` — `->with('category')` |
| `GET /app/retailer/cart` | `CartSection` | `CartAssembler:46/93/149/191` — `Cart->sections` |
| `POST /app/retailer/cart/lines` | `CartSection` | `AddRetailerCartLine:42` + CartAssembler |
| `PATCH /app/retailer/cart/lines/{id}` | `CartSection` | CartAssembler |
| `PATCH /app/retailer/cart/sections/{ref}` | `CartSection` | `UpdateRetailerCartSection:27` |
| `DELETE /app/retailer/cart/lines/{id}` | `CartSection` | CartAssembler |
| `POST /app/retailer/cart/submit` | `CartSection` | `SubmitRetailerCart:75` |
| `POST /app/retailer/orders/{id}/reorder` | `CartSection` | `ReorderSubOrder:44` (after the owner check) |
| `GET /app/retailer/return-requests` | `ReturnRequest` | `ReturnsWorkspace:74` |
| `POST /app/retailer/orders/{id}/cancel` | `SubOrder` | `CancelSubOrder:30` — no owner filter either |
| `GET /app/rep/products` | `Brand` | `ListRepProducts:39` — `->with('brand')` |
| `GET /app/rep/cart` | `CartSection` | CartAssembler |
| `POST /app/rep/cart/sections/{retailer_id}/submit` | `RepCommercialLimit`, then `CartSection` | `EloquentRepCommercialLimits:14`, `SubmitRepCartSection:52` |
| `GET /app/rep/scheduled-orders` | `SubOrder` | `ListRepScheduledOrders:20` — has `where('rep_id')`, no `acrossChannels()` |

Fifteen routes. The retailer app cannot show its home screen, list products, open a
product, hold a cart or submit one; the rep app cannot list products, hold a cart or
submit a section.

Behind validation, unverified (the 422 came first; the strict read is next in line):
`POST /app/rep/cart/lines` (`AddRepCartLine:47`), `POST /app/retailer/return-requests`
and `POST /app/rep/return-requests` (`ReturnsWorkspace`), `POST /app/rep/warehouse-receipts/{id}/confirm`
(`WarehouseWorkspace:543`).

Answering correctly today: brands list, categories, offers, favorite, shortages,
deliveries, assignments, warehouse-receipts list, and the six SubOrder routes repaired
with `acrossChannels()` in e89279f.

## Why the suite is green

Two things hide it. `/app/*` route groups carry no `ResolveTenant` middleware, so no
tenant ever exists there — every strict model read from them throws. And the tests that
cover these routes use fixtures that never reach the throwing line: `Layer2Test`'s
products have **no brand**, so `->with('brand')` runs no query; no test holds a cart with
a section; no test lists a retailer's return requests. `hides a zone-13 product from a
zone-12 retailer` and `shows channel on rep products` are green tests guarding a defect —
they must be rewritten with a brand, with a comment recording what they used to claim.

The root query on every catalog site is already escaped (`Product::withoutGlobalScope('channel')`
constrained by the retailer's `channel_ids`). What was never escaped is the **eager-loaded
relation**: `with('brand')` builds a fresh Brand query, and Brand's own scope runs on it.

## The isolation question, answered

**`CartSection.channel_id` is a split key, not an isolation boundary.** A cart belongs
to an app user (`carts.owner_type`, `owner_id`); `CartAssembler::activeFor($owner)`
selects it by owner, and every section is reached through that cart. `channel_id` on a
section says which channel that part of a multi-channel cart will become a sub-order
for — exactly `SubOrder`'s shape (`retailer_id` owns, `channel_id` splits). The same
holds for `OrderSection` (`Order->sections`) and, through the order header, for
`ReturnRequest` on the retailer side.

`ReturnsWorkspace::listForRetailer` deserves its own line: it reads **every** return
request in the table and filters by retailer in PHP afterwards — isolation after a
full read, not in the query. It throws today, and when it stops throwing it must not be
left as it is.

## Two findings beside the bug

1. CLAUDE.md rule 10 says `acrossChannels()` is used in "two places today". There are
   twenty-three call sites in ten files, and a further **fifteen raw
   `withoutGlobalScope('channel')`** sites (Catalog 5, Core 1, Pricing 2, Promotion 2)
   that bypass the "written with a reason beside it" discipline and are counted by nobody.
   The rule's text and the arch test should count both spellings, or forbid the raw one.
2. `CancelSubOrder` on `/app/retailer/orders/{id}/cancel` has no `retailer_id` filter
   at all (it is shared with the channel route, where the tenant scope does that work).
   Today it throws before reading. Adding `acrossChannels()` there without an owner
   filter beside it turns a loud error into a silent leak — `AppOwnerIsolationTest`
   pins that with a tripwire.

## Options

- **(A) The SubOrder precedent, site by site.** `acrossChannels()` with the owner
  filter beside it at each direct query, and `->with(['brand' => fn ($q) => $q->acrossChannels()])`
  at each relation load. Fourteen sites for CartSection alone, four eager loads in
  Catalog; every future `with('brand')` is a new hole.
- **(B) Relation-level escape where the child shares the parent's channel.**
  `Product::brand()` / `category()` and `Cart::sections()` declared with
  `->withoutGlobalScope('channel')` and the reason in the docblock: the parent query is
  already channel-constrained (or owner-constrained), and a child row cannot belong to
  another channel than its parent. Direct queries still take (A). Fewer sites, and the
  escape lives once, where the relation is defined.
- **(C) A third trait mode for owner-scoped, channel-split models.** Rewrites rule 10;
  not recommended until (B) proves insufficient.

Recommendation: **(B) for relations, (A) for direct queries**, and the arch test
extended to count raw `withoutGlobalScope('channel')` as an `acrossChannels()` site.

## Requirements

1. Every route in the table above answers 2xx with realistic fixtures (a product with a
   brand and a category; a cart with a section; a return request; a postponed sub-order)
   and answers only its owner's data.
2. `ReturnsWorkspace::listForRetailer` filters in the query, not after it.
3. `CancelSubOrder` on the retailer route carries a `retailer_id` filter beside its
   `acrossChannels()`.
4. Rule 10's text and `ChannelScopeTest` count both spellings of the escape.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] Each of the fifteen routes has a "works" test whose fixture reaches the relation
      the route loads (a brand, a section, a return request), and an "other owner sees
      nothing" test — the AppOrderIsolationTest shape, one request per test.
- [x] `Layer2Test`'s two product tests run with a brand on the product and still pass.
- [x] A retailer's return-request list is one query with the retailer in its `where`.
- [x] Retailer A cannot cancel retailer B's order: 404, status unchanged, no event row.
- [x] The arch test fails on a new `withoutGlobalScope('channel')` written without a
      reason comment on the same or the previous line.

## Status — delivered 2026-09-16 on `work/be-c12`, six commits

| Commit | Part |
|---|---|
| `1bc4878` | CancelSubOrder `retailer_id` filter, proved through the action with the scope lifted — before any escape |
| `e1aabe5` | Pricing: rep commercial limit read, escape beside its explicit channel |
| `0f68267` | Ordering: relation-level escapes on `Cart::sections()`, `CartLine::section()`, `Order::sections()/subOrders()`; site-level on seven direct queries; AppCartSurfaceTest (11) |
| `db34ff3` | Catalog: `Product::brand()/category()`; AppCatalogSurfaceTest (6); Layer2Test's two tests rewritten with a brand |
| `b43d81e` | Returns: list isolates in its query via `SubOrderLifecycle::idsForRetailer`; create refuses a foreign order on both apps (was 200) |
| `49cdfac` | ChannelScopeEscapeTest — both spellings, 42 sites in 31 files pinned, a reason within reach of each; rule 10 rewritten |

All acceptance criteria hold; the fifteen routes answer with a branded product and a cart
with a section, and each has its other-owner test. Still unverified behind validation:
`POST /app/rep/warehouse-receipts/{id}/confirm` (`WarehouseWorkspace:543`).

## Working rules

- Catalog, Ordering, Returns, Pricing, and Core (rule 10 text, arch test) — one commit
  per module, tests first and red.
- Not in BE-T07 or any Tenancy ticket: nothing here is Tenancy's.
- Do not set a tenant on `/app/*`. A retailer buys from several channels; the fix is the
  escape with the owner beside it, never a tenant that is wrong by construction.
