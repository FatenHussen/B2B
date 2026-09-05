---
id: BE-I09
title: App logout and token revocation
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 2
priority: High
contract: confirmed
endpoints: [EP-CM-005]
tables: []
events: []
blocked_by: [BE-I04]
unblocks_frontend: [RP-09, RT-15]
---

# BE-I09 — App logout and token revocation

**Endpoints:** EP-CM-005

## Requirements

1. Revoke the current token and close its session row.
2. A revoked token returns 401 token_revoked on any later request.
3. A session can also be invalidated from the backend (REQ-CM-007).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A token used after logout returns 401 token_revoked.
- [ ] Backend invalidation takes effect on the next request, not on the next login.

## Frontend tickets waiting on this

- RP-09, RT-15

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
