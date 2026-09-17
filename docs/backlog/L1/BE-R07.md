---
id: BE-R07
title: Equipment
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 2
priority: Low
contract: confirmed
endpoints: [EP-AD-038A, EP-AD-038B, EP-AD-042F, EP-AD-043E]
tables: [equipments]
events: []
blocked_by: [BE-R01]
unblocks_frontend: [AD-46]
---

# BE-R07 — Equipment

**Endpoints:** EP-AD-038A, EP-AD-038B, EP-AD-042F, EP-AD-043E

## Requirements

1. CRUD plus status; equipment is used for targeting and recommendations.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] The entity follows the shared reference pattern with no bespoke code.

## Frontend tickets waiting on this

- AD-46

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
