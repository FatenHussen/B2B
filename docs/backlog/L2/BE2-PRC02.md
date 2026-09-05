---
id: BE2-PRC02
title: Quantity tiers
layer: L2
side: Backend
module: Pricing
epic: EP-L2-PRC
sprint: SP-06
points: 5
priority: High
contract: proposed
endpoints: []
tables: [qty_tiers]
events: []
blocked_by: [BE2-PRC01]
---

# BE2-PRC02 — Quantity tiers

## Requirements

1. Tiers defined as min and max quantity with a unit price, validated to be contiguous and non-overlapping.
2. The card shows the highest price, which is the price of the smallest quantity, with a quantity-based marker.
3. The detail page shows the full from-to range and recomputes live as quantity and variant change.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Overlapping tiers are refused at save.
- [ ] The card price always equals the lowest tier quantity price.

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
