---
id: BE-A11
title: Segregation of duties rules
layer: L1
side: Backend
module: Access
epic: EP-BE-A
sprint: SP-02
points: 3
priority: High
contract: internal
endpoints: []
tables: [sod_rules]
events: []
blocked_by: [BE-A01]
---

# BE-A11 — Segregation of duties rules

## Requirements

1. Seed the conflicting permission pairs and expose them read only in L1.
2. The rule engine is consumed by role preview, role save and assignment.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] No write endpoint for SoD rules exists in L1.
- [ ] The same engine is used by all three consumers — one implementation, not three.

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
