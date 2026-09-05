---
id: BE-F05
title: Base migrations and naming conventions
layer: L1
side: Backend
module: —
epic: EP-BE-F
sprint: SP-00
points: 3
priority: High
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-F02]
---

# BE-F05 — Base migrations and naming conventions

## Requirements

1. Tables plural snake_case; columns snake_case with *_id foreign keys; routes kebab-case and plural.
2. States are a PHP Enum persisted as a string; events are past tense; jobs and actions are imperative verbs.
3. Each module owns its migrations, factories and seeders; only composite seeding lives in database/seeders.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A convention violation is caught by a static check, not by a reviewer.
- [ ] Migrations are backward compatible so deployment never requires downtime.

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
