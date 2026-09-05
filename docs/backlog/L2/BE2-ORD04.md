---
id: BE2-ORD04
title: Sub-order state machine
layer: L2
side: Backend
module: Ordering
epic: EP-L2-ORD
sprint: SP-08
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [order_events]
events: []
blocked_by: [BE2-ORD03, BE-C07]
---

# BE2-ORD04 — Sub-order state machine

## Requirements

1. Nine states and the explicit transition matrix from DOC-01 §4.6.3.
2. Retailer cancellation is permitted only in the pending state (BR-06); after confirmation it requires the channel.
3. Each transition runs through one action owning all of its side effects, and emits one event.
4. Every transition is logged with actor, reason and time.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A disallowed transition returns 409 illegal_transition.
- [ ] The retailer cancel button is impossible to use after confirmation, server side.

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
