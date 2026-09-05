---
id: BE4-CNT03
title: Dynamic sliders and algorithms
layer: L4
side: Backend
module: Content
epic: EP-L4-CNT
sprint: SP-14
points: 8
priority: High
contract: proposed
endpoints: []
tables: [sliders, slider_items]
events: []
blocked_by: [BE4-CNT02, BE2-CAT07]
---

# BE4-CNT03 — Dynamic sliders and algorithms

## Requirements

1. Unlimited sliders with a name, order and placement.
2. Sources: manual selection, brand, category, offer set, or an algorithm.
3. Algorithms: newly arrived, best selling, most ordered by this retailer, suggested for you, similar products, same brand, also bought.
4. The same engine serves the rep app with its own sliders.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] An algorithm slider is personalised per retailer and returns within the list budget.
- [ ] A slider with no eligible items is omitted rather than rendered empty.

## Working rules

- Touch only `app-modules/content` and its tests.
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
