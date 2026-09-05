---
id: BE4-LOY01
title: Point rules
layer: L4
side: Backend
module: Loyalty
epic: EP-L4-LOY
sprint: SP-15
points: 5
priority: Medium
contract: proposed
endpoints: []
tables: [loyalty_rules, loyalty_points]
events: []
blocked_by: [BE3-FIN02]
---

# BE4-LOY01 — Point rules

## Requirements

1. Retailer earning: purchase value, monthly continuity, on-time payment, rating a rep, trying a new product.
2. Rep earning: order count, on-time delivery rate, collection, new customers, retailer ratings.
3. Points carry an expiry date and every award is a ledger entry.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The points balance is recomputable from its ledger and always matches.
- [ ] A reversed order reverses its points.

## Working rules

- Touch only `app-modules/loyalty` and its tests.
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
