---
id: BE2-PRM02
title: Targeting and constraints
layer: L2
side: Backend
module: Promotion
epic: EP-L2-PRM
sprint: SP-07
points: 5
priority: High
contract: proposed
endpoints: []
tables: [offer_targets]
events: []
blocked_by: [BE2-PRM01]
---

# BE2-PRM02 — Targeting and constraints

## Requirements

1. Target all, an activity type, one or more zones, a retailer group or a specific retailer, combinable with AND.
2. Start and end datetime, total quota, per-retailer cap and per-order cap.
3. Minimum invoice value or item count to activate.
4. Combinability flag with another offer or with an invoice discount, plus a numeric priority for conflicts.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A retailer outside the target never sees the offer in any list.
- [ ] The remaining-quota counter is accurate under concurrent carts.

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
