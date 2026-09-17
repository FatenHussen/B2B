---
id: BE-R10
title: Public references endpoint
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 5
priority: Highest
contract: confirmed
endpoints: [EP-PB-001]
tables: []
events: []
blocked_by: [BE-R02, BE-R03, BE-R04, BE-R05, BE-R07]
unblocks_frontend: [FE-CORE-05, RP-06, RT-07]
---

# BE-R10 — Public references endpoint

**Endpoints:** EP-PB-001

## Requirements

1. Return active activity_types, root_categories, equipments, governorates and zones in one payload.
2. Carry meta.sync_cursor and accept since= for a differential response.
3. No Bearer and no idempotency key on this path.
4. Channels are never included in this payload (REQ-IN-06).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] A since= request returns only what changed.
- [x] The payload is small enough for a low-end device on 3G.
- [x] No channel data leaks through this endpoint under any parameter.

## Frontend tickets waiting on this

- FE-CORE-05, RP-06, RT-07

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
