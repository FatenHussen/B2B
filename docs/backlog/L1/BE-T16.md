---
id: BE-T16
title: Channel list and export
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 5
priority: Highest
contract: confirmed
endpoints: [EP-AD-059C]
tables: []
events: []
blocked_by: [BE-T04, BE-C09]
unblocks_frontend: [AD-61]
---

# BE-T16 — Channel list and export

**Endpoints:** EP-AD-059C

## Requirements

1. Filters: status, plan_id, governorate_id, subscription_status, over_limit and idle — all server side.
2. Columns include retailer, rep, order and GMV counters computed from real data.
3. Export uses the active filters and runs as a job.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The list query stays inside the interactive budget at production volume.
- [ ] No counter is estimated or interpolated.

## Frontend tickets waiting on this

- AD-61

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Working rules

- Touch only `app-modules/tenancy` and its tests.
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
