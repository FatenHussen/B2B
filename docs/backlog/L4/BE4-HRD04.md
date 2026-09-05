---
id: BE4-HRD04
title: Pilot launch
layer: L4
side: Backend
module: —
epic: EP-L4-HRD
sprint: SP-17
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE4-HRD03]
---

# BE4-HRD04 — Pilot launch

## Requirements

1. One channel, a limited zone set, real retailers and real reps.
2. Launch condition: a complete catalog with current prices for the pilot zone (DOC-06 §10.2).
3. A daily observation log of every stumble, with a named owner per issue.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A complete cycle runs with real users and no financial discrepancy.
- [ ] Manual intervention falls to a minimum before the general release.

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
