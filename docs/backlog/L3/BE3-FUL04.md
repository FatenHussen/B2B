---
id: BE3-FUL04
title: Handover with dual confirmation
layer: L3
side: Backend
module: Fulfillment
epic: EP-L3-FUL
sprint: SP-10
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [handovers, handover_confirmations]
events: [HandoverCompleted]
blocked_by: [BE3-FUL03, BE-C03]
---

# BE3-FUL04 — Handover with dual confirmation

## Requirements

1. The warehouse presses hand over and the rep presses receive; neither alone completes the operation (REQ-IN-02).
2. Completion deducts stock physically, moves the sub-order to on_the_way and enables tracking for the retailer.
3. If the rep device is offline, issue a temporary handover code that the rep scans later, leaving the state as awaiting rep confirmation.
4. The whole effect runs in one transaction.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] One-sided confirmation never deducts stock and never changes the state.
- [ ] A temporary code is single use and expires.
- [ ] Retailer tracking activates at the moment both sides confirm.

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
