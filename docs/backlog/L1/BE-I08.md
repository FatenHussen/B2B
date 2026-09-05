---
id: BE-I08
title: App session endpoint
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 3
priority: Highest
contract: confirmed
endpoints: [EP-CM-004]
tables: []
events: []
blocked_by: [BE-I04]
unblocks_frontend: [RP-08, RP-09, RT-01, RT-11, RT-13]
---

# BE-I08 — App session endpoint

**Endpoints:** EP-CM-004

## Requirements

1. Return the user, profile_completed, status, feature_flags, sync_cursor and legal state.
2. An invalid or revoked token returns 401 so the client can drop it.
3. Do not return points or tier in L1 even where a sample payload shows them.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The five splash branches in the retailer brief are all derivable from this one response.
- [ ] requires_legal_accept is a flag only; no legal endpoint exists in L1.

## Frontend tickets waiting on this

- RP-08, RP-09, RT-01, RT-11, RT-13

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Working rules

- Touch only `app-modules/identity` and its tests.
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
