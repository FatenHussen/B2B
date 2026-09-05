---
id: BE2-PRC05
title: Price freeze on the order
layer: L2
side: Backend
module: Pricing
epic: EP-L2-PRC
sprint: SP-07
points: 5
priority: Highest
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE2-PRC03, BE2-ORD03]
---

# BE2-PRC05 — Price freeze on the order

## Requirements

1. The unit price, currency and applied rule id are copied onto the order line at submission (BR-04).
2. A later price-list change never affects a live order.
3. An offline-created order is repriced on the server at intake with the difference reported to the user (REQ-CM-018).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Changing a price list after submission leaves the order total unchanged, proven by test.
- [ ] A repriced offline order shows the retailer the delta before confirmation.

## Working rules

- Touch only `app-modules/pricing` and its tests.
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
