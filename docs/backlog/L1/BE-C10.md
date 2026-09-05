---
id: BE-C10
title: Background job contract
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-00
points: 3
priority: High
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-F07]
---

# BE-C10 — Background job contract

## Requirements

1. A heavy operation returns job_id immediately instead of blocking the request.
2. A job-status endpoint reports progress and the resulting artefact.
3. Exports and provisioning both use this contract.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] No export or provisioning request holds an HTTP connection open.
- [ ] A completed job is retrievable without the client having polled continuously.

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
