---
id: BE3-INV06
title: Transfers and stocktakes
layer: L3
side: Backend
module: Inventory
epic: EP-L3-INV
sprint: SP-09
points: 5
priority: Medium
contract: proposed
endpoints: []
tables: [stocktakes]
events: []
blocked_by: [BE3-INV03]
---

# BE3-INV06 — Transfers and stocktakes

## Requirements

1. Inter-warehouse transfer as a document passing through sent and received states.
2. Stocktake types: full scheduled, cycle count, single item on suspicion.
3. Actual quantity entered without showing the book quantity, as a switchable option, to avoid bias.
4. A variance report requires a higher approval before balances are adjusted.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A variance adjustment without approval is impossible.
- [ ] Items under stocktake are frozen for movement.

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
