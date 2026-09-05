---
id: BE-T06
title: Channel read and update
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 3
priority: High
contract: confirmed
endpoints: [EP-AD-052, EP-AD-062]
tables: []
events: []
blocked_by: [BE-T04]
unblocks_frontend: [AD-65, AD-66, AD-67]
---

# BE-T06 — Channel read and update

**Endpoints:** EP-AD-052, EP-AD-062

## Requirements

1. Return the channel with its status, allowed_next, plan, limits, coverage summary and KPI figures.
2. Update requires a reason and is audited.
3. KPI figures are returned as computed, including zeros — never fabricated.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A brand-new channel returns genuine zeros, not placeholder values.
- [ ] The update is refused without a reason.

## Frontend tickets waiting on this

- AD-65, AD-66, AD-67

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
