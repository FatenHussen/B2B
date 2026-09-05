---
id: BE-I17
title: Password confirmation window
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 3
priority: Highest
contract: confirmed
endpoints: [EP-AD-005]
tables: []
events: []
blocked_by: [BE-I12]
unblocks_frontend: [AD-03]
---

# BE-I17 — Password confirmation window

**Endpoints:** EP-AD-005

## Requirements

1. A crit route returns 403 requires_password_confirm when the window has lapsed.
2. Confirming opens a fifteen-minute window bound to the session.
3. The replayed original request must carry the same idempotency key and must not execute twice.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A replay after confirmation produces exactly one execution.
- [ ] The window expires on time and reopens the challenge rather than failing silently.

## Frontend tickets waiting on this

- AD-03

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Working rules

- Touch only `app-modules/identity` and its tests.
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
