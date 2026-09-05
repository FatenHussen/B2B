---
id: BE2-PRM03
title: Cart evaluation engine
layer: L2
side: Backend
module: Promotion
epic: EP-L2-PRM
sprint: SP-07
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [offer_usages]
events: []
blocked_by: [BE2-PRM02, BE2-ORD01]
---

# BE2-PRM03 — Cart evaluation engine

## Requirements

1. Operates on the whole cart, not a single line, because conditional offers need to see every item (DOC-02 §12).
2. Three phases: collect eligible offers, evaluate conditions, apply rewards in priority order.
3. Rewards are added as separate cart lines marked as offer-generated and not manually editable.
4. Re-evaluate on every cart change and again immediately before confirmation.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Buy 3 get 1 applies automatically and is computed correctly on the final invoice (DOC-01 §8).
- [ ] Reducing quantity below the threshold removes the offer with a notice before confirmation.

## Working rules

- Touch only `app-modules/promotion` and its tests.
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
