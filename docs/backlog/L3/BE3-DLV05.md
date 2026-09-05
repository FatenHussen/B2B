---
id: BE3-DLV05
title: Scheduled orders
layer: L3
side: Backend
module: Delivery
epic: EP-L3-DLV
sprint: SP-11
points: 5
priority: Medium
contract: proposed
endpoints: []
tables: [scheduled_orders]
events: []
blocked_by: [BE3-DLV03]
---

# BE3-DLV05 — Scheduled orders

## Requirements

1. Created from the channel dashboard or as the result of a rep postponement.
2. A weekly and monthly calendar view of scheduled load per zone and per rep.
3. A morning alert for unassigned scheduled orders.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A postponement in the rep app appears immediately in the channel calendar.

## Working rules

- Touch only `app-modules/delivery` and its tests.
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
