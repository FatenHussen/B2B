---
id: BE4-HRD02
title: Security hardening and penetration pass
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
blocked_by: [BE-Q04, BE-T03]
---

# BE4-HRD02 — Security hardening and penetration pass

## Requirements

1. A deliberate attempt to reach another channel data across every endpoint, expected to fail.
2. Verification that the channel identity and the rep identity cannot be obtained early through any API path (AC-08).
3. File upload, rate limit, session and token handling review.
4. Encrypted daily off-server backup with a documented restore, and a recovery-time objective under four hours.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The isolation attempt fails on every endpoint without exception.
- [ ] A restore is executed and timed against the four-hour objective.

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
