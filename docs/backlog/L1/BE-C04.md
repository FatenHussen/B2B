---
id: BE-C04
title: Immutable audit log
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-00
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [audit_logs]
events: []
blocked_by: [BE-C01]
---

# BE-C04 — Immutable audit log

## Requirements

1. Record actor, action, entity, before and after values, IP, impersonation flag and timestamp.
2. No update and no delete path exists for the table (BR-16).
3. Every financial, inventory and permission mutation writes an entry.
4. Retention of at least two years.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] An attempt to update or delete an audit row fails at the model layer.
- [ ] Any figure in any report can be traced back to its originating document.

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
