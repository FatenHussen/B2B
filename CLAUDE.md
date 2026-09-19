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
- `docs/plan/` holds the remaining work as ordered tickets (`platform-admin.md`, `apps.md`);
  `docs/status/` is generated and says, route by route, what is live. `docs/README.md` is the index.

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
   **An exchange rate is a `bigInteger` at a fixed scale of 10^6, defined once as
   `Money::FX_SCALE` / `Money::FX_UNIT`.** A rate of 1.0 is `1_000_000`.
   The scale is never a column. A per-row scale would let two rows on the same currency
   pair carry different scales, and the first conversion between them would be wrong by a
   factor of ten with nothing in the data to reveal it — no exception, no mismatch, just a
   number that is off. `fx_rates.rate` carries no scale column for that reason.
   The FX scale is **not** the money scale: `Money::$scale` is how many minor units a
   *currency* has (0 for SYP, 2 for USD) and comes from `currencies.decimals`. The two are
   unrelated and must never be substituted for each other.
8. **`status` is `$guarded` and changes only through the lifecycle service.** Every transition is logged
   with actor, reason and time. An illegal transition throws and maps to 409.
9. **Every write accepts `X-Idempotency-Key`.** Known and complete replays the stored response; known and
   in flight returns 409 `operation_in_progress`; new executes and stores for 24 hours.
   **Known means known to the same caller**: a row is keyed by (guard, user_id, key) and the
   middleware runs after `auth:*` through the priority list, so the same key from another user, or
   from no user, is that caller's own first request (BE-C13). An in-flight row is held for
   `core.idempotency_lock_seconds`; past that a retry takes it over rather than waiting out the day.
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

    **Three exemptions**, all cross-channel by design: `AuditLog`, read from the platform back office
    across every channel — scoping it would hide exactly the activity an audit log exists to show;
    `ChannelZoneLookup`, a read-only view of `channel_zone` whose `$fillable` is empty, so every
    write goes through Reference's scoped `ChannelZone`; and `ChannelEvent`, the channel status
    trail written by `ChannelLifecycle` from the back office and read on its timeline — the
    platform's record *about* a channel, not data the channel owns, with `channel_id` a foreign key
    to the tenant table exactly as on `AuditLog`.

    **The scope is lifted only with the reason written beside it, never as a habit — and both
    spellings count.** `acrossChannels()` and the raw `withoutGlobalScope('channel')` it wraps are the
    same escape; this rule once said "two places use it today" while the truth was twenty-three of
    one spelling and fifteen of the other that nobody counted. As of BE-C12 there were **42 sites in
    31 files**; the platform reference impact counts (BE-R01–R07, 2026-09-17) added six more —
    **48 sites in 33 files** — every one with a comment saying why on the same line, within the six
    lines above, or in the method's docblock. `tests/Architecture/ChannelScopeEscapeTest.php` pins
    the count per file and fails on an escape with no reason within reach — a new site is a change
    to this inventory, said out loud, not a discovery.

    A third legitimate shape joined the two below with those six: **platform impact counts**, where
    the back office asks how many channels' rows a shared reference entity touches before disabling
    it (EP-AD-043A–D). The question spans every channel by definition, the answer is a number and
    never a row, and the caller is the platform guard — there is no tenant to scope by and nothing
    to leak.

    Two shapes are legitimate. **Site-level**, the SubOrder pattern: `acrossChannels()` on a direct
    query with the *owner* filter beside it — `retailer_id`, `rep_id`, the owner's `cart_id` — on a
    route that sets no tenant (`/app/*` sets none, and must not: a retailer buys from several
    channels). **Relation-level**, where the child cannot belong to another owner or channel than
    its parent: `Product::brand()` and `category()`, `Cart::sections()`, `CartLine::section()`,
    `Order::sections()` and `subOrders()` lift the scope in the relation definition, with the reason
    in its docblock, because the parent query has already constrained everything the relation can
    load and an eager load that re-applied the child's strict scope threw on every `/app/*` route
    (BE-C12). A channel column on such a child — `cart_sections.channel_id`, `sub_orders.channel_id`
    — is the split key of a multi-channel cart or order, not an isolation boundary; ownership is.

    What is never legitimate: an escape without an owner filter beside it on an app route.
    `CancelSubOrder` had none on `/app/retailer/orders/{id}/cancel`; adding `acrossChannels()` there
    would have turned a loud 500 into a silent leak, which is why the filter landed first, in its
    own commit, proved before the scope was touched.

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
| Migrations | one unique timestamp per file, across every module | `2026_09_17_093000_create_x_table.php` |

**Every migration carries a unique timestamp; no two are equal.** Laravel orders migrations by
filename, so two files on the same stamp run in alphabetical order of the rest of the name — which
module sorts first, not which table the other depends on. Sixteen files across six stamps predate
this rule and stay as they are: renaming a migration that has already run makes every deployed
database see it as new. `tests/Architecture/MigrationTimestampTest.php` pins those sixteen by name
and fails on any other shared stamp; `docs/debt-ledger.md` records them. A new file takes the
minute it was written.

### Permission vocabulary — two intersecting sources

DOC-08 and the API catalog **intersect; neither contains the other.**

- DOC-08 (`docs/api/doc08.txt`) defines **170** permission codes. It is the source of a
  permission **name**.
- The API catalog (`docs/api/catalog/`) defines the routes. Some routes carry a permission
  DOC-08 does not define — `sc.notify.view` on `EP-SC-092 GET /channel/notifications/log`
  is a real endpoint whose permission the document has not caught up with.

As of 2026-09-17: PermissionCatalog seeds 134 codes. 37 DOC-08 codes are not yet
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

**The Larastan gate runs against a baseline since 2026-09-16.** It had never
passed before that date — 1184 findings, of which configuration alone accounted for 812:
the analyser read no module migrations, typed `$this` in a Pest closure as Pest's call
object, and read `casts()`'s declared return type instead of its body. What remained —
372, or **585 with no identifier ignored**, which is how it is counted: the 213 relation
declarations without a related type are the cause of 173 of the rest, and hiding a cause
while freezing its symptoms is not a baseline — is in `phpstan-baseline.neon`, pinned
exactly at 475 by `tests/Architecture/PhpstanBaselineTest.php`: the number only falls,
and a fall lowers the pin in the same commit. A new finding in new code is fixed, never added to the
baseline — the gate is green for new code and red for regressions. The pay-down is one
module per commit; its table is in `docs/debt-ledger.md`.

A sixth gate is specified but does not exist yet: `php artisan openapi:generate --check`.
The `openapi` package registers no artisan commands in this repository (`docs/debt-ledger.md`).
Until it lands, state any response-shape change explicitly in the pull request.

## How to work a ticket

1. Find it in `docs/plan/platform-admin.md` or `docs/plan/apps.md`. The ticket names the module,
   the tables, the permissions, what it depends on and its acceptance lines.
2. Read the catalog entry (`docs/api/catalog/*.php`) for every `EP-` it names. That is the request
   and response contract; the plan never repeats it. Do not invent a path, a body or a shape.
3. Check `docs/status/` to confirm the route is still ❌ — another branch may have landed it.
4. Do not implement anything outside that ticket. If a dependency is missing, stop and say so.
5. Write a test for every acceptance line before declaring completion.
6. Run the five gates above, then `php docs/api/generate.php && php docs/status/generate.php`
   and flip the ticket to ✅ with the date.

## Contract status

Every endpoint in `docs/plan/` has a real `EP-ID` in the catalog and is implementable. A route that
the catalog does not name does not get built: add it to the catalog first, with `b`/`r`/`e`, in its
own commit, and say so.
