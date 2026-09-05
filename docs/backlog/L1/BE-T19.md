---
id: BE-T19
title: Channel join applications
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 5
priority: High
contract: internal
endpoints: []
tables: [channel_applications]
events: []
blocked_by: [BE-T05]
---

# BE-T19 — Channel join applications

## Requirements

1. GET /platform/channel-applications with filter[status]=under_review.
2. Decide approve or reject with a mandatory reason; approval also carries plan_id and trial_days.
3. Approval creates a channel in provisioning and hands off to the provisioning job.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] An approved application produces exactly one channel.
- [ ] A rejection without a reason is refused.

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
