---
id: BE-T07
title: Channel users and manager invite reset
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 5
priority: High
contract: confirmed
endpoints: [EP-AD-063, EP-AD-064]
tables: [channel_users]
events: []
blocked_by: [BE-T04, BE-I17]
unblocks_frontend: [AD-68, AD-69]
---

# BE-T07 — Channel users and manager invite reset

**Endpoints:** EP-AD-063, EP-AD-064

## Requirements

1. List channel users read only.
2. Reset the manager invite with a 72-hour validity; the route is crit and requires password confirmation.
3. Resetting invalidates the previous invite immediately.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The old invite link stops working the moment a new one is issued.
- [ ] The route rejects a request without a fresh password confirmation.

## Frontend tickets waiting on this

- AD-68, AD-69

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
