---
id: BE-C02
title: Error handler and HTTP status map
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-00
points: 5
priority: Highest
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-C01]
---

# BE-C02 — Error handler and HTTP status map

## Requirements

1. 401 unauthenticated, token_revoked. 403 wrong_guard, insufficient_permission, requires_2fa, requires_password_confirm.
2. 404 not_found for out-of-tenant access. 409 illegal_transition, operation_in_progress, stale_version.
3. 422 validation_failed with details keyed by field. 423 plan_limit_exceeded. 426 upgrade_required. 429 rate_limited. 503 maintenance_mode.
4. Messages are Arabic and translatable; the code is the contract, the message is for the human.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Every code in the map has a feature test that triggers it.
- [ ] A 403 for a permission failure carries the permission key in the payload.
- [ ] Out-of-tenant access returns 404, never 403 — existence is not disclosed.

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
