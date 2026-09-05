---
id: BE2-ORD03
title: Order submission and multi-channel split
layer: L2
side: Backend
module: Ordering
epic: EP-L2-ORD
sprint: SP-07
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [orders, sub_orders, order_lines]
events: []
blocked_by: [BE2-ORD02, BE-C03]
---

# BE2-ORD03 — Order submission and multi-channel split

## Requirements

1. Submission splits the cart on the server into independent sub-orders, one per channel, each with its own number and route (BR-01, REQ-IN-08).
2. Price freeze, offer quota reservation and number generation happen inside one database transaction.
3. The retailer sees one order; each channel sees only its own part.
4. The whole operation is idempotent on the submission key.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A three-channel order produces exactly three sub-orders and one retailer-facing order.
- [ ] A retried submission after a dropped connection creates no duplicate.
- [ ] A channel querying the order sees only its own lines, never the others.

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
