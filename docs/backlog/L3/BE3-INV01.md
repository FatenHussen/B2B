---
id: BE3-INV01
title: Warehouses and stock balances
layer: L3
side: Backend
module: Inventory
epic: EP-L3-INV
sprint: SP-09
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [warehouses, stock_levels]
events: []
blocked_by: [BE2-CAT03, BE-T09]
---

# BE3-INV01 — Warehouses and stock balances

## Requirements

1. Multiple warehouses per channel with a default warehouse per zone.
2. Four balance states per item and variant per warehouse: available, reserved for confirmed orders, in transit with a rep, damaged or quarantined.
3. The retailer is served a logical availability state only, never a number (BR-14).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The four states always sum to the physical balance.
- [ ] No API path returns a numeric quantity to a retailer token.

## Working rules

- Touch only `app-modules/inventory` and its tests.
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
