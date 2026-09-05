---
id: BE-Q04
title: Authorisation bypass test suite
layer: L1
side: Backend
module: —
epic: EP-BE-Q
sprint: SP-02
points: 5
priority: Highest
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-A16]
---

# BE-Q04 — Authorisation bypass test suite

## Requirements

1. For every write endpoint, attempt the call without the permission and expect 403 with the permission key.
2. For every guard, attempt a token from another guard and expect 403 wrong_guard.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Feature coverage of authorisation paths is at least 75 percent as required by DOC-02 §17.

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
