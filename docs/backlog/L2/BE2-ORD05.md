---
id: BE2-ORD05
title: Channel order inbox
layer: L2
side: Backend
module: Ordering
epic: EP-L2-ORD
sprint: SP-08
points: 5
priority: Highest
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE2-ORD04]
---

# BE2-ORD05 — Channel order inbox

## Requirements

1. Default view: pending sub-orders sorted oldest first and grouped by zone.
2. Filters on zone, status, retailer, rep, brand, date range, value range and source.
3. A waiting-time field so the UI can flag orders past the configured threshold.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A channel sees only its own sub-orders under every filter combination.
- [ ] The list stays within the interactive budget at 5,000 orders per day.

## Working rules

- Touch only `app-modules/ordering` and its tests.
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
