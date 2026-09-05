---
id: BE-R06
title: Sale units
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 3
priority: Medium
contract: confirmed
endpoints: [EP-AD-037A, EP-AD-037B, EP-AD-042E, EP-AD-043D]
tables: [sale_units]
events: []
blocked_by: [BE-R01]
unblocks_frontend: [AD-45]
---

# BE-R06 — Sale units

**Endpoints:** EP-AD-037A, EP-AD-037B, EP-AD-042E, EP-AD-043D

## Requirements

1. CRUD plus status; a unit in use cannot be disabled (409 ref_in_use).
2. Unit conversion factors belong to the product in Layer 2, not here.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] 409 is returned with the usage context.

## Frontend tickets waiting on this

- AD-45

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Working rules

- Touch only `app-modules/reference` and its tests.
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
