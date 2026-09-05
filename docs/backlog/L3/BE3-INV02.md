---
id: BE3-INV02
title: Reservation and deduction lifecycle
layer: L3
side: Backend
module: Inventory
epic: EP-L3-INV
sprint: SP-09
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [reservations]
events: [SubOrderConfirmed, HandoverCompleted]
blocked_by: [BE3-INV01, BE2-ORD04]
---

# BE3-INV02 — Reservation and deduction lifecycle

## Requirements

1. Reservation starts at confirmation, not before, and is released automatically on cancellation or expiry (BR-05).
2. Actual deduction happens at handover to the rep, not at picking (BR-05).
3. Reservation and release are transactional and cannot double-reserve under concurrency.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Two confirmations racing for the last unit produce one success and one clear failure.
- [ ] An expired reservation returns the stock without manual intervention.

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
