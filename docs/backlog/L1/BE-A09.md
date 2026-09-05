---
id: BE-A09
title: Permission simulator
layer: L1
side: Backend
module: Access
epic: EP-BE-A
sprint: SP-02
points: 5
priority: Medium
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-A03]
---

# BE-A09 — Permission simulator

## Requirements

1. Accept user_type, user_id, permission, resource_type and resource_id.
2. Return decision_path: the outcome of each layer in order (guard, tenancy scope, role, policy, temporary grant).
3. The simulator is strictly read only and mutates nothing.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The simulated result matches the real authorisation decision in every test case.
- [ ] Every layer is represented in the path, including the ones that passed.

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
