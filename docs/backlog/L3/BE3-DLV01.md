---
id: BE3-DLV01
title: Assignment engine
layer: L3
side: Backend
module: Ordering
epic: EP-L3-DLV
sprint: SP-11
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [assignments]
events: []
blocked_by: [BE2-ORD06, BE-T08]
---

# BE3-DLV01 — Assignment engine

## Requirements

1. Manual assignment showing each rep current load and today order count.
2. Automatic assignment by zone with load cap and duty state respected.
3. Bulk assignment of a whole zone to one rep in a single action.
4. Assignment to an off-duty rep or outside their zones is refused (BR-19).
5. Reassignment records a reason and is blocked after handover except through a warehouse return path.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] An off-duty rep cannot receive an assignment through any path.
- [ ] A rejected order returns to unassigned with a reason and an immediate alert to sales (REQ-IN-03).

## Working rules

- Touch only `app-modules/ordering` and its tests.
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
