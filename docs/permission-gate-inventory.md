# Permission gate inventory

A contract exercise, not code. **No gate was added and no code was changed.**

Sources, never mixed: **the API catalog first**, DOC-08 by name second. Every inferred row
is labelled `inference` and is a proposal, not a fact.

Generated from `php artisan route:list` on **2026-09-08** · 178 live routes.

---

## 1. Correction to the premise

The brief said *167 of 178 routes carry no `can:`*. Measured, the split is:

| Gate mechanism | Routes |
|---|---:|
| `permission:` (Spatie `PermissionMiddleware`) | **78** |
| `can:` (Laravel `Authorize`) | **11** |
| `role:` (Spatie `RoleMiddleware`) | **5** |
| **Gated, total** | **94** |
| **Ungated** | **84** |

The 167 figure counts only `can:`, which is the *minority* mechanism here — 78 routes are
gated by `permission:` instead. The real exposure is **84**, and most of those are ungated
correctly (§3).

---

## 2. The four-column table

`Source` is `catalog` when the API catalog names the permission, `inference` when only
DOC-08 has a plausible name, and `—` when neither does.

### 2.1 Ungated, and the catalog names the permission (17)

**These are the real gap.** The catalog specifies a permission, the route enforces nothing.
All 17 are already seeded, so gating them is a one-line change each — no vocabulary work.

| Path | Method | Required (DOC-08) | Source |
|---|---|---|---|
| `/app/rep/assignments` | GET | `rp.delivery.accept` | catalog · EP-RP-030 |
| `/app/rep/assignments/{id}/accept` | POST | `rp.delivery.accept` | catalog · EP-RP-031 |
| `/app/rep/assignments/{id}/reject` | POST | `rp.delivery.accept` | catalog · EP-RP-032 |
| `/app/rep/scheduled-orders` | GET | `rp.delivery.accept` | catalog · EP-RP-033 |
| `/app/rep/deliveries` | GET | `rp.delivery.deliver` | catalog · EP-RP-050 |
| `/app/rep/deliveries/{id}` | GET | `rp.delivery.deliver` | catalog · EP-RP-051 |
| `/app/rep/deliveries/{id}/lines/{lineId}` | PATCH | `rp.delivery.deliver` | catalog · EP-RP-052 |
| `/app/rep/deliveries/{id}/complete` | POST | `rp.delivery.deliver` | catalog · EP-RP-053 |
| `/app/rep/deliveries/{id}/fail` | POST | `rp.delivery.deliver` | catalog · EP-RP-055 |
| `/app/rep/deliveries/{id}/postpone` | POST | `rp.delivery.postpone` | catalog · EP-RP-054 |
| `/app/rep/return-requests` | POST | `rp.delivery.return_request` | catalog · EP-RP-057 |
| `/app/rep/warehouse-receipts` | GET | `rp.warehouse.receive` | catalog · EP-RP-040 |
| `/app/rep/warehouse-receipts/{handoverId}/confirm` | POST | `rp.warehouse.receive` | catalog · EP-RP-041 |
| `/app/retailer/receipts/{subOrderId}` | GET | `rt.receive.confirm` | catalog · EP-RT-040 |
| `/app/retailer/receipts/{id}/lines/{lineId}` | PATCH | `rt.receive.confirm` | catalog · EP-RT-041 |
| `/app/retailer/receipts/{id}/confirm` | POST | `rt.receive.confirm` | catalog · EP-RT-042 |
| `/app/retailer/return-requests` | POST | `rt.receive.return_request` | catalog · EP-RT-043 |

📌 Every code above is **already seeded** in `PermissionCatalog`. Nothing new to add to the
vocabulary — only the middleware is missing.

### ⚠️ But gating them today would lock out every mobile user

The vocabulary is ready and the roles are ready. **The link between a user and a role is
not.**

| Layer | State |
|---|---|
| `rt.*` / `rp.*` codes seeded | ✅ 10 + 12 codes |
| `retailer` / `rep` roles exist on the `app` guard | ✅ `RolesPermissionsSeeder` |
| Those roles hold all `rt.*` / `rp.*` codes | ✅ `builtinGrants()` → `codesStartingWith('rt.')` / `('rp.')` |
| **An app user is ever given one of those roles** | ❌ **never** |

`grep -rn 'syncRoles\|assignRole' app-modules` finds exactly two assignment sites, both in
`DatabaseSeeder` — `platform_admin` and `channel_manager`. Neither `RegisterRetailer` nor
`RegisterRep` assigns a role, and no seeder creates an app user at all.

So every retailer and rep authenticates with **zero permissions**. Adding `permission:` to
the 17 routes above would return `403 insufficient_permission` for **all of them, on the
first call**.

**This is a two-step change and the order is not optional:**

1. Assign the `retailer` / `rep` role at registration (or at first `verify-otp`).
2. *Then* add the middleware.

Doing step 2 first takes both mobile apps down. Doing step 1 alone is harmless and can land
independently — which makes it the safer thing to ship first.

### 2.2 Ungated, permission inferred from DOC-08 only (8)

**Proposals, not facts.** The catalog is silent on these; the name below is the closest
DOC-08 code by module and verb.

| Path | Method | Required (DOC-08) | Source |
|---|---|---|---|
| `/app/retailer/orders` | GET | `rt.order.submit` | **inference** |
| `/app/retailer/orders/{id}` | GET | `rt.order.submit` | **inference** |
| `/app/retailer/orders/{id}/cancel` | POST | `rt.order.submit` | **inference** |
| `/app/retailer/orders/{id}/reorder` | POST | `rt.order.submit` | **inference** |
| `/app/retailer/orders/{id}/tracking` | GET | `rt.order.submit` | **inference** |
| `/app/rep/cart` | GET | `rp.order.create` | **inference** |
| `/app/rep/cart/lines` | POST | `rp.order.create` | **inference** |
| `/app/rep/cart/sections/{retailer_id}/submit` | POST | `rp.order.create` | **inference** |

⚠️ `rt.order.submit` is a **write** verb being proposed for three **reads**. That is the
inference straining: DOC-08 has no `rt.order.view`. Either the document is missing a read
permission, or reading your own orders was never meant to be gated. **Do not seed
`rt.order.submit` onto a GET on my say-so** — this needs a DOC-08 decision.

Both inferred codes (`rt.order.submit`, `rp.order.create`) are **unseeded**, so acting on
this section means adding vocabulary. That is exactly the situation `CLAUDE.md` warns
about, and it should not happen on an inference.

### 2.3 Ungated with no permission in either source (59)

Not one table — four distinct reasons, and only one of them is a gap.

| Class | Routes | Verdict |
|---|---:|---|
| Unauthenticated by design | 9 | correct — no gate is possible |
| Self-service (`/platform/me`, `/platform/auth`) | 14 | correct — the token *is* the authorisation |
| App surface (browse, cart, own profile) | 32 | correct — see below |
| Reference reads at the v1 root | 4 | **questionable** |

**Unauthenticated (9)** — `GET /health`, the 3 public OTP calls, channel `request-otp` /
`verify-otp`, platform `login` / `2fa/verify`, warehouse `device-login`. A permission gate
on a login route is a contradiction.

**Self-service (14)** — `/platform/auth/{me,sessions,logout,confirm-password}`,
`/platform/me` and everything under it. These act on *the caller's own* account. Gating
them on a permission would mean an admin could be denied the right to change their own
password. Correct as-is.

**App surface (32)** — retailer browse/cart/home/shortages/brands/favourite/rate,
rep products/customers/zones/status/ping, `/app/session`, `/app/offers`, `/app/pricing/quote`,
both `register` routes, `/app/auth/logout`. These are gated by **guard + `RequireAppKind`**,
which is the real boundary: a retailer token cannot reach `/app/rep/*` at all, and a
`registration`-only token cannot reach anything but the two register routes.

Given that app users hold no roles today (§2.1), guard + app-kind is not merely the
*primary* boundary here — it is the **only** one. That is defensible for a shop owner
browsing their own catalog and cart. It is less defensible for the 17 routes in §2.1, which
the catalog says should be gated and which touch deliveries, handovers and returns.

**Reference reads (4)** — `GET /governorates`, `GET /governorates/{id}`, `GET /zones`,
`GET /zones/{id}`.

⚠️ **This is the one class worth a decision.** Their writes *are* gated
(`ad.refs.create` / `update` / `disable` via `can:`), but the reads are open to a
**four-guard list** (`platform,channel,warehouse,app`). So any authenticated user of any
client can enumerate every governorate and zone. That may be intentional — reference data
is not secret, and the apps genuinely need it — but it is currently the **only** way a
mobile client can read reference data at all, since `/public/refs` does not exist. Gating
these reads would break registration in both apps. Decide deliberately; do not gate them by
reflex.

---

## 3. Answers to the two questions

### How many of the 39 unseeded DOC-08 codes find a home in this table?

**Two.** And both arrive by inference, not from the catalog:

| Unseeded code | Routes it would cover | Source |
|---|---|---|
| `rt.order.submit` | 5 retailer order routes | inference |
| `rp.order.create` | 3 rep cart routes | inference |

**The other 37 find no home** — no live route needs them. That is consistent with the rule
in `CLAUDE.md`: *a code is added when a route needs it, never speculatively*. The 39 are not
a backlog of missing gates; they are vocabulary for endpoints that do not exist yet — the
finance, sync, content and platform-reference surfaces that are all still unbuilt.

So the honest count is: **2 of 39 have a candidate home, and both candidates are
inferences I would not act on without a DOC-08 ruling** (§2.2).

### Which routes have no permission in DOC-08 *or* the catalog?

**59 routes**, listed and classified in §2.3. Named explicitly, the ones that are **not**
self-evidently correct:

| Path | Method | Why it is worth naming |
|---|---|---|
| `/governorates` | GET | Open to 4 guards; no read permission exists in DOC-08 |
| `/governorates/{governorate}` | GET | Same |
| `/zones` | GET | Same |
| `/zones/{zone}` | GET | Same |

The remaining 55 are unauthenticated login routes (9), self-service account routes (14) and
the app browse/cart surface (32). For those, "no permission in DOC-08" is not an omission —
DOC-08 deliberately does not model them, because guard + app-kind is the boundary.

One more worth naming, though it is *gated*: **`/admin/channels` (5 routes) is gated by
`role:platform_admin`, not by a permission.** DOC-08 defines `ad.channels.view`,
`ad.channels.create`, `ad.channels.update`, `ad.channels.delete` and `ad.channels.suspend`
— all five seeded, none used. The role gate is coarser than the document specifies, and it
sits on a `MOVING` path that will become `/platform/channels`. Worth fixing when the path
moves, in one change rather than two.

---

## 4. What I did not do

- Added no gate, changed no route file, seeded no permission.
- Did not act on any inference. §2.2 is a proposal for a human decision.
- Did not treat the 39 unseeded codes as a to-do list. 37 of them correctly have no home.

## 5. Reproducing

```bash
php artisan route:list --json                      # the 178 live routes
grep -oE '\b(ad|sc|wh|rt|rp|cm)\.[a-z_]+\.[a-z_]+' docs/api/doc08.txt | sort -u   # 170 codes
```

Permissions currently seeded: **133** (`PermissionCatalog`). DOC-08 defines **170**. The
gap of **39** is expected, not a defect.
