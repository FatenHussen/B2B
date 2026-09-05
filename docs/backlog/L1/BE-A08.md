---
id: BE-A08
title: Revoke assignment
layer: L1
side: Backend
module: Access
epic: EP-BE-A
sprint: SP-02
points: 2
priority: Medium
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-A07]
---

# BE-A08 — Revoke assignment

## Requirements

1. Accept user_id, role_id and a mandatory reason.
2. Revocation takes effect on the next request, not on the next login.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The revoked user loses the permission immediately.
- [ ] The revocation is audited with its reason.

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
