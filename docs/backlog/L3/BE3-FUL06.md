---
id: BE3-FUL06
title: Inbound receipt
layer: L3
side: Backend
module: Fulfillment
epic: EP-L3-FUL
sprint: SP-10
points: 5
priority: Medium
contract: proposed
endpoints: []
tables: [inbound_receipts]
events: []
blocked_by: [BE3-INV03]
---

# BE3-FUL06 — Inbound receipt

## Requirements

1. Sources: purchase from a supplier, transfer from another warehouse, field returns.
2. Match expected against actually received, record lot and expiry where required.
3. Quality outcome: accept, partially reject or quarantine, with photographs of rejected items.
4. Assign a storage location with an automatic suggestion based on history.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A receipt creates an inbound movement and updates the available balance immediately.

## Working rules

- Touch only `app-modules/fulfillment` and its tests.
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
