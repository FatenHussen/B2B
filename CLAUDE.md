# B2B Distribution Platform — API repository rules

Read this before touching anything. It is the standard every pull request is judged against.

## What this repository is

**A pure JSON API. Laravel 13, API-only.** It serves five clients that live in a separate repository:
two Flutter apps and three Next.js dashboards.

**There is no Blade, no view layer and no server-rendered page.** If you find yourself writing one, you
have misread the ticket.

- No `resources/views/` for user interfaces.
- No `routes/web.php` beyond a health redirect.
- No redirect responses, no flash messages, no session-driven page state.
- Every endpoint returns JSON in the envelope below, including errors.
- The only templates permitted anywhere are PDF document templates under a module
  `Presentation/Pdf/`, used to render invoices and statements as files. They are documents, not pages.

Each module publishes its own `routes/api.php` and loads it with `loadRoutesFrom`
in its service provider. Base path is `/api/v1`.

**The route prefix decides the guard.** This is enforced by
`tests/Architecture/GuardTest.php`, not by convention:

| Prefix | Guard |
|---|---|
| `/api/v1/platform/*` and `/api/v1/admin/*` | `auth:platform` |
| `/api/v1/channel/*` | `auth:channel` |
| `/api/v1/warehouse/*` | `auth:warehouse` |
| `/api/v1/app/*` | `auth:app` |
| `/api/v1/public/*` | no guard |

No route inside `api/v1` may use any other guard. `auth:sanctum` in particular is
not a guard in this application — Sanctum injects it at runtime with a null provider,
so it resolves instead of failing and then rejects everyone with a silent 401.

## Stack

Laravel 13 · MySQL 8 · Redis · Sanctum · Horizon · Pest · Larastan · Deptrac
Pattern: modular monolith, API-first.

## Where code lives

- `app/` is deliberately thin: Kernel, Providers and Console only. **No controller, model or service here.**
- `app-modules/` holds every piece of business logic, one local Composer path repository per module.
- `docs/backlog/` holds one markdown file per ticket. Implement a ticket by reading its file.

## The four layers and the dependency direction

```
Presentation  (Api: Platform / Channel / Warehouse / App-Retailer / App-Rep)
      ↓
Coordination  (Ordering · Fulfillment · Delivery · Returns · Finance · Sync · Reporting)
      ↓
Domain        (Catalog · Pricing · Promotion · Inventory · Loyalty · Content · Notification · Support · PlatformBilling)
      ↓
Foundation    (Core · Identity · Access · Reference · Tenancy · Integration)
```

**Dependency points downward only.** A Domain module knows nothing about a Coordination module.

## Rules that are never broken

Enforced by CI, not by reviewers. Breaking one fails the build.

1. **No module imports another module Eloquent model.** `Modules\*\Domain\Models` is module-private.
2. **Cross-module communication is through public `Contracts/` or events only.**
3. **No direct query against another module tables.**
4. **Cross-boundary relations are by identifier, never by an Eloquent relation.**
5. **Events notify, they do not control.** The emitting module never waits for a result.
6. **No view layer.** No module may use `Illuminate\View`, the `View` facade or a Blade directive
   outside `Presentation/Pdf/`.
7. **Every amount is a `bigInteger` in the smallest currency unit wrapped in a `Money` value object.**
   `float` and `double` are forbidden on any money path. Rounding happens in `MoneyResource` only.
   **An exchange rate is a `bigInteger` at a fixed scale of 10^6, defined once in `Money`.**
   The scale is never a column. A per-row scale means rows with different scales in one
   table, and the first conversion between two of them is a silent wrong answer with
   nothing to compare against. `fx_rates.rate` carries no scale column for that reason —
   settled at BE-R08.
8. **`status` is `$guarded` and changes only through the lifecycle service.** Every transition is logged
   with actor, reason and time. An illegal transition throws and maps to 409.
9. **Every write accepts `X-Idempotency-Key`.** Known and complete replays the stored response; known and
   in flight returns 409 `operation_in_progress`; new executes and stores for 24 hours.
10. **Every channel-owned model carries a channel column** with a composite index starting on it, and
    `BelongsToChannel` applied automatically. Hand-written
    `->where(channel_column, Tenant::currentId())` in a query is **not** isolation: it protects only
    the query that remembers it, and the next one written without it leaks silently.

    **Two column names**, both counting: `supply_channel_id` (sixteen models) and `channel_id` (the
    rest). The trait defaults to the first; a model on the other declares
    `protected string $channelColumn = 'channel_id';`. Unifying them is a **separate, deliberately
    deferred ticket** — the migration would rewrite a dozen tables plus every query and index naming
    them, which costs more today than the inconsistency does.

    **Two modes.** *Strict* is the default: with no tenant set the query throws
    `MissingChannelScopeException`, because that is a programming error and the exception says so
    where it happens. *Relaxed* — `protected bool $channelScopeOptional = true;` — still filters
    whenever a tenant is set and tolerates only its absence. It is for tables read in order to
    **decide** which channel a caller belongs to, before any tenant exists: `ChannelUserChannel`
    (`ResolveTenant` reads it to find the tenant, so a scope demanding the tenant to read it could
    not terminate), `RepProfile` (registration — the rep is still choosing a channel) and
    `WarehouseDevice` (device login reads the device to discover its channel). A table that decides
    identity cannot be isolated by it. Relaxed is not unscoped, and is always preferred to an
    exemption, which filters never.

    **Two exemptions**, both cross-channel by design: `AuditLog`, read from the platform back office
    across every channel — scoping it would hide exactly the activity an audit log exists to show —
    and `ChannelZoneLookup`, a read-only view of `channel_zone` whose `$fillable` is empty, so every
    write goes through Reference's scoped `ChannelZone`.

    **`acrossChannels()` is written at the call site with the reason beside it, never as a habit.**
    Two places use it today: `EloquentOpenOrderCounter` (the EP-AD-034 impact count is cross-channel
    by design and its caller has no tenant) and `EloquentWarehouseDirectory` (every method
    establishes which channel a warehouse belongs to). An escape hatch that appears three times in
    one file stops being read as an exception and starts being copied where it does not belong.

    `tests/Architecture/ChannelScopeTest.php` enforces all of this: a model whose table has either
    column and does not apply the trait fails the build unless it is in that file's exemption list
    with a written reason, and the list itself is pinned so it cannot grow silently.
11. **A foreign channel id in a URL returns 404, not 403.** Existence is never disclosed.
12. **Reference entities are never hard deleted.** Disabling is logical and audited.

## Authentication

Four guards, four user tables: `app` (retailer and rep), `channel`, `warehouse`, `platform`.

- Mobile clients authenticate with Sanctum personal access tokens bound to `device_uuid`.
- Web dashboards use Sanctum SPA cookie mode on a shared subdomain — **the same endpoints**, not a
  parallel web login. CSRF protection applies to that cookie flow only.
- A token presented to the wrong guard returns 403 `wrong_guard`.

## Response contract

```jsonc
// success
{ "data": { }, "meta": { "server_time": "...", "sync_cursor": "..." } }
// list
{ "data": [ ], "meta": { "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
// error
{ "error": { "code": "insufficient_permission", "message": "...", "permission": "sc.orders.confirm", "details": { } } }
```

The `code` is the contract. The `message` is for the human. A raw Laravel validation payload must never
reach a client, and no endpoint ever returns HTML.

| HTTP | Internal codes |
|---|---|
| 401 | `unauthenticated`, `token_revoked` |
| 403 | `wrong_guard`, `insufficient_permission`, `requires_2fa`, `requires_password_confirm`, `sod_violation` |
| 404 | `not_found` — also used for out-of-tenant access |
| 409 | `illegal_transition`, `operation_in_progress`, `stale_version`, `idempotency_key_conflict` |
| 422 | `validation_failed`, `ref_in_use` |
| 423 | `plan_limit_exceeded` |
| 426 | `upgrade_required` |
| 429 | `rate_limited` |
| 503 | `maintenance_mode` |

## Naming

| Element | Convention | Example |
|---|---|---|
| Tables | plural snake_case | `sub_orders` |
| Columns | snake_case, foreign keys `*_id` | `supply_channel_id` |
| States | PHP Enum, string in the database | `pending`, `confirmed`, `on_the_way` |
| Routes | kebab-case, plural | `/api/v1/channel/sub-orders` |
| Permissions | from the DOC-08 catalog, verbatim | `sc.orders.confirm` |
| Events | past tense | `SubOrderConfirmed` |
| Jobs and Actions | imperative verb | `GenerateDailySnapshot`, `SubmitOrder` |

### Permission vocabulary — two intersecting sources

DOC-08 and the API catalog **intersect; neither contains the other.**

- DOC-08 (`docs/api/doc08.txt`) defines **170** permission codes. It is the source of a
  permission **name**.
- The API catalog (`docs/api/catalog/`) defines the routes. Some routes carry a permission
  DOC-08 does not define — `sc.notify.view` on `EP-SC-092 GET /channel/notifications/log`
  is a real endpoint whose permission the document has not caught up with.

As of 2026-09-05: PermissionCatalog seeds 133 codes. 39 DOC-08 codes are not yet
seeded — a code is added when a route needs it, never speculatively.

On a conflict: **the catalog is the source of the path, DOC-08 is the source of the
permission name.** A permission that exists only in the catalog is a legitimate addition —
record it in `PermissionCatalog` with a comment naming its endpoint, do not delete it and
do not invent a DOC-08 entry for it.

Never seed a permission that appears in neither source. Interim vocabularies invented in
code are how a channel manager ends up able to create a governorate.

## Queues

`critical` (OTP, handover, payments, outbox intake) · `default` (notifications, invoices, repricing) ·
`media` (image processing) · `reports` (snapshots, exports). Supervised by Horizon.

## The API contract is a published artefact

Every endpoint is documented in OpenAPI and the TypeScript client is generated from it. A change to a
response shape is a contract change: regenerate the document, and say so in the pull request. The frontend
repository consumes that generated client and writes no types by hand.

## Before you say a ticket is done

```bash
composer deptrac                          # module boundaries
./vendor/bin/pest --group=arch            # architecture rules
./vendor/bin/phpstan analyse              # Larastan level 6
./vendor/bin/pest                         # full suite
./vendor/bin/pint --test                  # formatting
```

**Five gates must pass.** A ticket is not done because the feature works.

A sixth gate is specified but does not exist yet: `php artisan openapi:generate --check`.
The `openapi` package registers no artisan commands in this repository — see BE-F06.
Until it lands, state any response-shape change explicitly in the pull request.

## How to work a ticket

1. Read `docs/backlog/L{n}/{TICKET-ID}.md` in full, including its front matter.
2. Read the `Working rules` section: it names the only directories you may touch.
3. Do not implement anything outside that ticket. If a dependency is missing, stop and say so.
4. Write a test for every acceptance criterion before declaring completion.
5. Run the five gates above.

## Contract status

Tickets in `docs/backlog/L1/` carry real `EP-ID` values from the API catalog. **They are implementable.**

Tickets in `L2`, `L3` and `L4` have `contract: proposed`. Their endpoints do not exist in the catalog yet.
Do not invent a path, a request body or a response shape. If asked to implement one, stop and say the
contract is not fixed.
