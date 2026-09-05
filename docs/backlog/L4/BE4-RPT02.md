---
id: BE4-RPT02
title: Channel dashboard figures
layer: L4
side: Backend
module: Reporting
epic: EP-L4-RPT
sprint: SP-15
points: 8
priority: High
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE4-RPT01]
---

# BE4-RPT02 — Channel dashboard figures

## Requirements

1. The indicator set from DOC-01 §4.1 with a selectable period and comparison to the previous period.
2. Actionable alerts: overdue pending orders, items at reorder point, returns awaiting decision, reps over their cash cap, retailers over their credit limit, offers expiring within 48 hours, unassigned scheduled orders.
3. Every card and chart column is drillable to its filtered list.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] No figure exists without a route to its underlying documents.
- [ ] The alert list is actionable, with a bulk action attached to each type.

## Working rules

- Touch only `app-modules/reporting` and its tests.
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
