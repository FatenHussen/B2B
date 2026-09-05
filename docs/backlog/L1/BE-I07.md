---
id: BE-I07
title: Rep registration with channel invite
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-04
points: 5
priority: High
contract: confirmed
endpoints: [EP-RP-001]
tables: [reps]
events: []
blocked_by: [BE-I04, BE-T01]
unblocks_frontend: [RP-07]
---

# BE-I07 — Rep registration with channel invite

**Endpoints:** EP-RP-001

## Requirements

1. Body: name, supply_channel_id, activity_type_id, zone_ids, note.
2. A zone outside the channel coverage returns 422 on zone_ids.
3. A channel that is not active returns 409.
4. The rep-to-channel relation is many-to-many and carries the caps for each channel.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] supply_channel_id is accepted only from a valid invite, never from a public list.
- [ ] GET /public/refs never exposes channels (REQ-IN-06).

## Frontend tickets waiting on this

- RP-07

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
