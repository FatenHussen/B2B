---
id: BE-C05
title: Generic dual-approval mechanism
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-00
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [approval_requests]
events: []
blocked_by: [BE-C04]
---

# BE-C05 — Generic dual-approval mechanism

## Requirements

1. A sensitive action creates an approval_request instead of executing (draft, pending or deletion_request_id).
2. The approver must differ from the requester; the server rejects self-approval with 403.
3. Approval and rejection both require a written reason and are audited.
4. Execution happens only after approval, inside one transaction.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A creator approving their own role receives 403.
- [ ] No sensitive action ever executes on the first request.
- [ ] The pending state is visible to the requester through the approval inbox.

## Working rules

- Touch only `app-modules/core` and its tests.
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
