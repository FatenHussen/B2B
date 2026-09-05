---
id: BE-T01
title: Supply channels and the channel state machine
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [supply_channels]
events: [ChannelStatusChanged]
blocked_by: [BE-C07]
---

# BE-T01 — Supply channels and the channel state machine

## Requirements

1. Model the channel with its lifecycle states and an explicit allowed_next transition matrix.
2. active → archived does not exist as a transition and must not be reachable.
3. A status change emits ChannelStatusChanged, which freezes new orders only (BR-AD-14).
4. Every transition is recorded with actor, reason and timestamp.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A disallowed transition returns 409 illegal_transition.
- [ ] allowed_next is returned by the API so the client renders exactly the permitted buttons.

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
