---
id: BE-I01
title: Four guards and separate user tables
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [platform_users, channel_users, warehouse_users, retailers, reps]
events: []
blocked_by: [BE-C02]
---

# BE-I01 — Four guards and separate user tables

## Requirements

1. Guards: app (retailer and rep), channel, warehouse, platform — each with its own user table.
2. Sanctum tokens with abilities; a registration-ability token is issued before the profile exists.
3. Presenting a token to the wrong guard returns 403 wrong_guard.
4. One phone number cannot be registered under two kinds; the server rejects the second.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A retailer token on /platform returns 403 wrong_guard, never 200.
- [ ] Registering the same phone as both retailer and rep is rejected with a clear message.

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
