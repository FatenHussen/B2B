# Frontend documentation

The API is a pure JSON API at `/api/v1` serving five clients: two Flutter apps and three
React dashboards.

**One file per app. Open yours and nothing else.** Each file contains the backend setup,
the client package with working code, the full HTTP contract, every endpoint, and a gotcha
table. There is deliberately no shared reference to cross-check — the duplication is the
point.

| App | File | Guard | Platform / port | Seed account |
|---|---|---|---|---|
| Retailer | [apps/retailer.md](./apps/retailer.md) | `app` | Flutter, Android + iOS | none — OTP any number |
| Field rep | [apps/rep.md](./apps/rep.md) | `app` | Flutter, Android + iOS | none — OTP any number |
| Channel | [apps/channel-web.md](./apps/channel-web.md) | `channel` | React + Vite, **3001** | `+963900000001` + OTP |
| Warehouse | [apps/warehouse-web.md](./apps/warehouse-web.md) | `warehouse` | React + Vite, **3002** | none — CLI device |
| Platform | [apps/platform-web.md](./apps/platform-web.md) | `platform` | React + Vite, **3000** | `admin@platform.sy` / `password` |

Every file has the same eight sections:

| § | |
|---|---|
| 1 | Running the backend — PHP, MySQL on **3308**, seed, OTP in the log |
| 2 | The client package — working Dart or TypeScript |
| 3 | A token in two minutes — real `curl`, from nothing to a working call |
| 4 | Endpoint reference — EP-ID · method · path · Stability · permission |
| 5 | The HTTP contract — envelope, headers, idempotency, errors, money |
| 6 | What to build, in order |
| 7 | Not built — do not mock |
| 8 | Gotchas — numbered |

Everything was verified against `php artisan route:list` and the controller source on
**2026-09-07**. Nothing is written from the backlog or the API catalog. **Where a document
and `route:list` disagree, `route:list` wins** and the document is the thing to fix.

---

## Readiness — who can start today

**178 routes are registered** under `/api/v1`: **158 stable**, **15 moving**, and **5 live
but absent from the catalog entirely**. A further **180 catalogued endpoints have no route
at all**.

Every endpoint table carries a `Stability` column:

| Value | Meaning | What to do |
|---|---|---|
| **stable** | registered at the path the catalog specifies | build on it |
| **moving** | registered, but at a path the catalog does not specify — it will move | build, but keep the base path behind **one constant** |
| **missing** | catalogued, no route, 404 today | do not build, **do not mock** |

### Per app

| App | Reachable | Stability | Can start? | Blocked on |
|---|---:|---|---|---|
| **Retailer** | 37 | all stable | **Yes — fully** | money, sync, loyalty |
| **Field rep** | 33 | all stable | **Yes — fully** | wallet + cash collection, sync |
| **Channel** | 47 | 42 stable · 5 uncatalogued | **Yes — fully** | offer analytics is a **hardcoded stub**; finance, reporting |
| **Warehouse** | 19 | all stable | **Yes — fully** | reject/damage at QC; needs a **2nd device** to approve a stocktake |
| **Platform** | 41 | 36 stable · **5 moving** | **Partly** | **SP-03 and SP-04: 0 of 47 contract paths live** |

**Four of the five can start now.** Platform admin can build identity, IAM and audit
completely, but its channel screens sit on a temporary path and its entire reference
surface is missing.

The two app figures include the shared `/app/*` routes, the 3 public OTP calls and
`/health`, which both apps use. The 57 `/app/*` routes split 28 retailer / 24 rep / 5
shared.

### The moving set — all 15

| Live now | Will become | Used by |
|---|---|---|
| `/admin/channels…` (5) | `/platform/channels…` | platform-web |
| `/governorates…` (5) | `/platform/refs/governorates…` | platform-web (not yet wired) |
| `/zones…` (5) | `/platform/refs/zones…` | platform-web (not yet wired) |

⚠️ `/governorates` and `/zones` answer to a **multi-guard list**
(`platform,channel,warehouse,app`), which contradicts the prefix↔guard rule. They will move
*and* their guard will narrow. Do not build admin screens on them without agreeing it with
the backend first.

**Anyone building on a `moving` path redoes the work when it moves.** One constant per
moving base path is the whole mitigation.

### The missing set, by sprint

| Sprint | Missing | What it blocks |
|---|---:|---|
| SP-03 | 32 | every platform reference screen — governorates, zones, activity types, categories, units, currencies, FX |
| SP-04 | 22 | channel provisioning, transitions, usage, limits, coverage, join applications |
| SP-13 | 18 | **all money** — retailer debts and payments, rep wallet, receivables, statements |
| SP-14 | 20 | **all offline sync** and the notification inbox |
| SP-15 | 14 | home content blocks, loyalty |
| SP-16 | 11 | campaigns, plan SKUs, team, feature flags |
| SP-17 | 63 | reporting and dashboards across every guard |

**SP-03 + SP-04 = 54 endpoints, and zero of them are live at a contract path.** That is the
headline for platform-web.

---

## Three rules that hold everywhere

**1. Idempotency is mandatory on every write.** `X-Idempotency-Key` on every POST, PUT,
PATCH and DELETE, on every guard. Exactly eight paths are exempt — the credential ones.
A key belongs to a **user intent**, not an HTTP attempt: same key on every retry, a new key
only when the user starts over.

**2. Money is an integer in the smallest unit.** No floats, anywhere. SYP has **0
decimals**, so the integer is the number you display — never `/ 100` by reflex. The one
exception on the whole API is `/channel/zones`, which returns decimal **strings**.

**3. Do not mock what is missing.** A `missing` endpoint returns 404 with no stub and no
feature flag. Fabricated data is worse than an empty screen — a fake debt in a shop app, a
fake sales figure for a supplier, a fake stock count in a warehouse and a fake GMV on the
platform dashboard are all incidents someone has to reconcile by hand. Ship the empty
state.

---

## Regenerating the truth

```bash
php artisan route:list --json          # what is actually registered
cat docs/api/b2b-api.catalog.json      # what is specified
```

Re-run after any sprint merge and update the `Stability` columns. Every claim in these
files comes from those two sources and nothing else.
