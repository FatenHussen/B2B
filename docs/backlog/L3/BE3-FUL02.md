---
id: BE3-FUL02
title: Shortage handling during picking
layer: L3
side: Backend
module: Fulfillment
epic: EP-L3-FUL
sprint: SP-10
points: 5
priority: High
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE3-FUL01]
---

# BE3-FUL02 — Shortage handling during picking

## Requirements

1. Record the actually available quantity with a reason: out of stock, damaged, not at location.
2. Immediate alert to sales for a decision: partial fulfilment, substitute item, or postpone.
3. Substitute suggestions are proposed by the warehouse but approved only by sales.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A substitution never takes effect on warehouse authority alone.
- [ ] The retailer sees the amended invoice amount, not a silent short delivery.

## Working rules

- Touch only `app-modules/fulfillment` and its tests.
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
