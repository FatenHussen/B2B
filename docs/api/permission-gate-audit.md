# Permission gate audit

Which live routes enforce a permission, which do not, and what is deliberately left open.

Measured **2026-09-08** against `php artisan route:list --json` and
`docs/api/b2b-api.catalog.json`. Counts are route × verb pairs under `/api/v1`, excluding
`HEAD` and excluding the `/api/v1/public/*` group, which is unauthenticated by design.

---

## Read this first — the earlier version of this audit was wrong

A previous pass reported **11 gated routes and 164 ungated**, and concluded that "94% of
the surface has no permission check at all". That conclusion was false, and it was acted
on: it produced a proposed list of critical routes to gate, all of which turned out to be
gated already.

**The cause was the measuring tool, not the code.** This repository registers permission
gates in two different forms:

| Form | Middleware class in `route:list --json` | Count |
|---|---|---:|
| `->middleware('can:ad.refs.create')` | `Illuminate\Auth\Middleware\Authorize` | 11 |
| `->middleware('permission:ad.iam.role_create')` | `Spatie\Permission\Middleware\PermissionMiddleware` | 78 |

The scan matched only `Authorize`, so it missed the 78 routes using Spatie's
`permission:` middleware — the form used by most of the codebase. `route:list --json`
resolves middleware to fully-qualified class names, so neither the string `can:` nor
`permission:` appears in its output, and a regex written against the route-file syntax
silently matches almost nothing.

**Any future pass must count both classes.** Grepping the route files for `can:` or
`permission:` is not equivalent either: group-level middleware applies to routes that do
not name it, and route-level middleware does not appear on the group.

The lesson is not "be careful". It is that a number produced by a scan is worth what the
scan's coverage is worth, and a scan that can only under-report should be stated as a lower
bound until proven complete.

---

## Current state

| | Routes | |
|---|---:|---|
| **Total** (non-public, per verb) | **175** | |
| **Gated by a permission** | **89** | 51% |
| **Ungated** | **86** | 49% |
| — of which the catalog names a permission | 17 | actionable today |
| — of which the catalog names none | 69 | needs a contract decision |

**Ungated and catalogue-critical: zero.** Every endpoint the API catalog marks `critical`
carries its permission today. That includes all of `sc.inventory.adjust`,
`sc.orders.cancel`, `ad.audit.export`, `ad.iam.role_approve`, `ad.iam.grant_temp` and
`wh.stocktake.approve`.

---

## The three remaining gaps

None is an open security hole. Each is deferred by an explicit decision, recorded here so
the deferral is visible rather than forgotten.

### 1. Seventeen `app` routes with a known permission — deferred

Every one is under `auth:app`, and the catalog names its code. They are the rep delivery
family (`rp.delivery.accept`, `rp.delivery.deliver`, `rp.delivery.postpone`,
`rp.delivery.return_request`), rep warehouse receipts (`rp.warehouse.receive`), and
retailer receipt confirmation and returns (`rt.receive.confirm`,
`rt.receive.return_request`).

**Why this is defence in depth rather than a hole:** the rep/retailer split is structural,
not permission-based. `AppUserKind` distinguishes them and each side's endpoints require a
rep profile or a retailer profile. A retailer cannot complete a delivery today because
they have no rep profile, not because a gate says so. Adding the gates makes the intent
explicit and gives a correct `403 insufficient_permission` with the permission key instead
of a downstream failure — worth doing, not urgent.

**Decision: gate them when BE3-DLV touches this family**, so the gate and its acceptance
test are written together.

### 2. Sixty-nine routes the catalog gives no permission — contract decision

The catalog specifies no `permission` for these endpoints, so there is no code to apply
without inventing one. Inventing permission names in code is exactly what produced the
`settings.*` breach.

**Decision: needs a dedicated session** to assign DOC-08 codes endpoint by endpoint, as a
contract change, not an implementation one.

### 3. Twenty-seven orphaned `sc.` codes — a documentation gap

DOC-08 defines these; the API catalog gives them no endpoint. The distribution is what
makes this a document problem rather than a route problem:

| System | Codes | With an endpoint | Orphaned |
|---|---:|---:|---:|
| `ad.` | 62 | 60 | **2 (3%)** |
| `sc.` | 70 | 43 | **27 (39%)** |
| `wh.` | 16 | 11 | 5 (31%) |
| `rp.` | 12 | 8 | 4 (33%) |
| `rt.` | 10 | 4 | 6 (60%) |

`ad.` is essentially complete at 3%; `sc.` is thirteen times worse. The orphans name whole
families the catalog does not describe: `sc.retailers.*` (5), `sc.reps.*` (5), `sc.iam.*`
(4), `sc.pricing.*` (3), `sc.settings.*` (3), `sc.zones.*` (2), `sc.offers.*` (2).

**Decision: recorded for FE3-CH03/04.** The channel routes already live that have no
catalog entry — `channel/zones` and `channel` — are the same gap seen from the other side,
and are tracked in `docs/backlog/L1/BE-F06.md`.

---

## One gate is wider than its route

`PUT /api/v1/platform/iam/roles/{id}/permissions` is gated on `ad.iam.role_create`, which
DOC-08 defines as *creating* a custom role. The route rewrites the permissions of a role
that already exists, so one name grants two distinct powers.

No better code exists. `ad.iam` has exactly seven members — `role_create`, `role_approve`,
`role_assign`, `grant_temp`, `sod_rules`, `view_catalog`, `simulate` — and none describes
editing an existing role. DOC-08 indicts itself here: `ad.refs` and `ad.team` both separate
creation from modification (`refs.create`/`refs.update`, `team.invite`/`team.update`), and
`ad.iam` alone does not.

**DOC-08 needs `ad.iam.role_update`.** Until it has one, the current gate is the closest
true code; the alternative is inventing a name, which is forbidden. Noted at the route.

---

## How to re-run this

```bash
php artisan route:list --json > routes.json
```

Then, for each `api/v1` route and each verb except `HEAD`, count it as gated when its
middleware list contains **either** `Illuminate\Auth\Middleware\Authorize` **or**
`Spatie\Permission\Middleware\PermissionMiddleware`. Join the ungated ones to
`b2b-api.catalog.json` by method plus path, with `{param}` normalised, to see which of them
the contract already names a permission for.
