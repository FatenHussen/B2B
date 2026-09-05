---
id: BE4-HRD01
title: Performance hardening
layer: L4
side: Backend
module: —
epic: EP-L4-HRD
sprint: SP-16
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE4-RPT01]
---

# BE4-HRD01 — Performance hardening

## Requirements

1. Meet the DOC-01 §7 targets: under one second for daily operational screens, under three seconds for interactive reports.
2. Sustain a catalog of 100,000 items, 10,000 active retailers and 5,000 orders per day per channel without noticeable degradation.
3. Index review on every hot query path; eliminate every N+1 on list screens.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A k6 run at one thousand concurrent users on list and submission paths holds the budget.
- [ ] No list endpoint issues a per-row query.

## Working rules

- Touch only `the module named in the front matter` and its tests.
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
