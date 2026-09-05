---
id: BE4-LOY02
title: Tiers and rewards
layer: L4
side: Backend
module: Loyalty
epic: EP-L4-LOY
sprint: SP-15
points: 5
priority: Medium
contract: proposed
endpoints: []
tables: [loyalty_tiers, rewards, redemptions]
events: []
blocked_by: [BE4-LOY01]
---

# BE4-LOY02 — Tiers and rewards

## Requirements

1. Bronze, silver and gold tiers with bound privileges: extra discount, delivery priority, exclusive offers.
2. A reward catalog with redemption and a complete history.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A tier change applies its privileges automatically.
- [ ] A redemption cannot exceed the available balance under concurrency.

## Working rules

- Touch only `app-modules/loyalty` and its tests.
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
