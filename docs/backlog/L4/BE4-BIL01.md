---
id: BE4-BIL01
title: Plans and subscriptions
layer: L4
side: Backend
module: PlatformBilling
epic: EP-L4-BIL
sprint: SP-17
points: 8
priority: Medium
contract: proposed
endpoints: []
tables: [plans, subscriptions]
events: []
blocked_by: [BE-T12]
---

# BE4-BIL01 — Plans and subscriptions

## Requirements

1. Plan definitions with limits and features that feed the channel limit and feature resolution.
2. Subscription lifecycle with trial, active, past due and cancelled states.
3. A plan change reuses the existing preview and apply path.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A plan limit change propagates to channel enforcement without a code change.
- [ ] A trial expiry moves the subscription automatically.

## Working rules

- Touch only `app-modules/platform-billing` and its tests.
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
