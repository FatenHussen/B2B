---
id: BE-R12
title: Mandatory reason on reference mutations
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 2
priority: High
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-R01, BE-C04]
---

# BE-R12 — Mandatory reason on reference mutations

## Requirements

1. Every reference mutation requires a reason in the request body, validated server side.
2. The reason is written to the audit log with the before and after values.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] A mutation without a reason is refused with 422 — the client cannot bypass it.

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
