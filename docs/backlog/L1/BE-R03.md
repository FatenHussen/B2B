---
id: BE-R03
title: Zones with impact counts
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 5
priority: Highest
contract: confirmed
endpoints: [EP-AD-032, EP-AD-033, EP-AD-042B, EP-AD-034]
tables: [zones]
events: []
blocked_by: [BE-R02]
unblocks_frontend: [AD-42, AD-49]
---

# BE-R03 — Zones with impact counts

**Endpoints:** EP-AD-032, EP-AD-033, EP-AD-042B, EP-AD-034

## Requirements

1. List supports filter[governorate_id]; a zone belongs to exactly one governorate.
2. The status route is 034, deliberately outside the 043 family — this is a documented contract exception.
3. Before disabling, return affected.retailers, affected.reps and affected.open_orders (REQ-AD-031).
4. Disabling a zone stops new orders from it without affecting orders already placed.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] The affected counts are computed live, never cached or estimated.
- [x] A contract test pins the 034 route so it cannot drift to 043.

## Decision — the status vocabulary is translated, not unified, 2026-09-07

`zones.status` stores `active` / **`inactive`**. EP-AD-034 sends
`{"status": "disabled"}` and EP-AD-032 reads the field back, so the contract's second word
is **`disabled`** — `RefStatus`'s spelling, not `ZoneStatus`'s.

Unifying them means an `UPDATE zones SET status = 'disabled' WHERE status = 'inactive'`
over every existing row. That is a data migration, so it is **deferred to its own ticket**
and is not part of BE-R03.

Until then the two vocabularies meet at the boundary and nowhere else:
`ZoneStatus::fromContract()` on the way in, `toContract()` on the way out, both on the
enum, with a contract test asserting the column still reads `inactive` while the API only
ever says `disabled`. `fromContract()` returns null for `inactive`, so a request using the
stored spelling is refused 422 rather than admitted through the back door.

This did change a response shape — zones that read `inactive` now read `disabled`. AD-42
and AD-49 are not built yet, which is why the contract was matched now rather than after
they had been written against the wrong word.

## Scope note — this ticket touched four modules

The Working rules below say `app-modules/reference` only. The scope was explicitly widened
to `core`, `identity` and `ordering` because `affected` needs three counts that Reference
cannot compute: retailers and reps live in Identity, open orders in Ordering.

A single `ZoneImpactCounter` contract was rejected — one implementation would have to read
the tables of two modules, breaking rule 3 whichever module hosted it. Instead each count
is declared in `Modules\Core\Contracts` and implemented by the module that owns the data:
`RetailerDirectory::countInZone`, `RepDirectory::countInZone`,
`OpenOrderCounter::countOpenInZone`. Each returns an int, never a model.

`open_orders` is defined by negation — not `delivered`, `cancelled` or `rejected`
(DOC-01 §4.6.3). `SubOrderStatus` has twelve cases; a positive list of nine would go stale
the first time the lifecycle gained a state, silently under-reporting the impact of an
irreversible decision.

## Frontend tickets waiting on this

- AD-42, AD-49

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Working rules

- Touch only `app-modules/reference` and its tests.
- Do not modify another module. Use its `Contracts/` or an event.
- Do not add a route outside the module `Presentation/Routes/` files.
- JSON only. No Blade, no view, no redirect. PDF document templates belong in `Presentation/Pdf/`.
- Every migration this ticket adds must be backward compatible.

## Definition of done

- [ ] Merged after peer review; Larastan level 6, Pint and Rector pass.
- [ ] Deptrac and the Pest architecture suite pass — no cross-module model import, no float on a money path, no view layer.
- [ ] Unit and feature tests green: 90%+ on engines and money paths, 75%+ on endpoints, permissions and isolation.
- [ ] Every write accepts X-Idempotency-Key unless it is on the documented exemption list.
- [ ] Every channel endpoint has an isolation test proving another channel receives 404.
- [ ] The endpoint declares its permission explicitly and returns the permission key on a 403.
- [ ] The response follows the unified JSON envelope and the HTTP status map; no raw Laravel payload escapes.
- [ ] Every financial, inventory or permission mutation writes an immutable audit entry.
- [ ] The OpenAPI document is regenerated and the typed client still builds.
