---
id: BE-A14
title: Audit export job
layer: L1
side: Backend
module: Access
epic: EP-BE-A
sprint: SP-02
points: 3
priority: Medium
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-A13, BE-C10]
---

# BE-A14 — Audit export job

## Requirements

1. Accept filters and format xlsx and return job_id on the reports queue.
2. The export honours the submitted filters, not a page.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The request returns immediately with job_id.
- [ ] The produced file matches the filtered row count exactly.

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
