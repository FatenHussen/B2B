---
id: BE2-PRC04
title: Price change log and controls
layer: L2
side: Backend
module: Pricing
epic: EP-L2-PRC
sprint: SP-06
points: 5
priority: High
contract: proposed
endpoints: []
tables: [price_change_log]
events: []
blocked_by: [BE2-PRC01, BE-C04]
---

# BE2-PRC04 — Price change log and controls

## Requirements

1. Record who changed what, from what to what, when and why, with the reason mandatory.
2. The price-change permission is separate from the catalog-management permission.
3. A rep discount cap at invoice level that the system refuses to exceed, with every use recorded (BR-12).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A price change without a reason is refused.
- [ ] A rep cannot exceed the cap through any path including a direct API call.

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
