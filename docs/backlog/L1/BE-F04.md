---
id: BE-F04
title: Pest architecture tests
layer: L1
side: Backend
module: —
epic: EP-BE-F
sprint: SP-00
points: 3
priority: Highest
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-F02]
---

# BE-F04 — Pest architecture tests

## Requirements

1. No module imports another module Eloquent model (Modules\*\Domain\Models is module-private).
2. No float or double anywhere in a finance or money path.
3. Every channel controller is behind the channel guard middleware.
4. Cross-boundary references use identifiers, never Eloquent relations.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Each rule has a failing example proving the test actually catches it.
- [ ] The suite runs in CI on every pull request.

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
