---
id: BE-R11
title: Reference import with dry run
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 5
priority: Medium
contract: internal
endpoints: []
tables: [import_batches]
events: []
blocked_by: [BE-R01, BE-C03]
---

# BE-R11 — Reference import with dry run

## Requirements

1. Accept a multipart upload with a type and dry_run flag.
2. A dry run validates everything and returns preview rows plus errors[{row, column, message}] without writing.
3. Execution requires dry_run=false and a fresh idempotency key.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A dry run writes nothing, provably.
- [ ] Row errors name the exact row and column.
- [ ] Re-executing with the same key does not double-import.

## Working rules

- Touch only `app-modules/reference` and its tests.
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
