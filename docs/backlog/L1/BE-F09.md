---
id: BE-F09
title: Health endpoint
layer: L1
side: Backend
module: Core
epic: EP-BE-F
sprint: SP-00
points: 1
priority: Highest
contract: confirmed
endpoints: [EP-CORE-001]
tables: []
events: []
blocked_by: [BE-C01]
unblocks_frontend: [FE-CORE-01]
---

# BE-F09 — Health endpoint

**Endpoints:** EP-CORE-001

## Requirements

1. GET /api/v1/health returns data.status = ok with no authentication and no idempotency key.
2. Report database, cache and queue reachability.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The endpoint answers without a Bearer token.
- [ ] It is the first endpoint the shared frontend kit calls successfully.

## Frontend tickets waiting on this

- FE-CORE-01

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

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
