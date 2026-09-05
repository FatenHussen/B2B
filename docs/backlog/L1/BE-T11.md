---
id: BE-T11
title: Channel usage series
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 3
priority: Medium
contract: confirmed
endpoints: [EP-AD-056]
tables: []
events: []
blocked_by: [BE-T04]
unblocks_frontend: [AD-73]
---

# BE-T11 — Channel usage series

**Endpoints:** EP-AD-056

## Requirements

1. Return a 30-day usage series computed from real counters.
2. An empty or all-zero series is a valid response; no gap filling and no smoothing.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A channel with no activity returns zeros, not an error and not an empty body.

## Frontend tickets waiting on this

- AD-73

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Working rules

- Touch only `app-modules/tenancy` and its tests.
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
