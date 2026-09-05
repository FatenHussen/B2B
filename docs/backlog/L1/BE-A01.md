---
id: BE-A01
title: Permission catalog seed
layer: L1
side: Backend
module: Access
epic: EP-BE-A
sprint: SP-02
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [permissions]
events: []
blocked_by: [BE-C01]
---

# BE-A01 — Permission catalog seed

## Requirements

1. Seed the 170 permissions from the DOC-08 catalog literally, including the Arabic name.
2. Each permission carries system, module, severity and a dual-approval flag.
3. Permission codes are used verbatim in code — no renaming, no aliasing.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The seed is idempotent and re-runnable.
- [ ] A permission referenced in code but missing from the catalog fails a test.

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
