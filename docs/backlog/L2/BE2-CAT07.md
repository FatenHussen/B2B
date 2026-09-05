---
id: BE2-CAT07
title: Retailer and rep catalog read API
layer: L2
side: Backend
module: Catalog
epic: EP-L2-CAT
sprint: SP-06
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE2-CAT03, BE2-PRC03]
---

# BE2-CAT07 — Retailer and rep catalog read API

## Requirements

1. List and detail endpoints filtered by zone, activity and retailer group in one query (BR-15).
2. Card payloads are minimal for mobile; detail is fetched on demand (DOC-02 §9).
3. Cursor pagination, not offset, for long lists.
4. Bulk price resolution for the whole page in one call to avoid N+1.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A 60-item page issues one pricing call, not sixty.
- [ ] The retailer never receives a numeric stock quantity, only a logical availability state (BR-14).

## Working rules

- Touch only `app-modules/catalog` and its tests.
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
