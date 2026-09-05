---
id: BE-A16
title: Two-level authorisation enforcement
layer: L1
side: Backend
module: Access
epic: EP-BE-A
sprint: SP-02
points: 5
priority: Highest
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-A01, BE-T02]
---

# BE-A16 — Two-level authorisation enforcement

## Requirements

1. Policy per model plus permission per action — both are required, neither alone is sufficient.
2. Every endpoint declares its permission explicitly; a missing declaration fails a test.
3. A 403 carries the permission key so the client can hide the control.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A bypass attempt on every write endpoint is covered by a test.
- [ ] An endpoint without a declared permission cannot be merged.

## Working rules

- Touch only `app-modules/access` and its tests.
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
