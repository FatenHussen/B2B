---
id: BE-I04
title: Verify OTP and device binding
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 5
priority: Highest
contract: confirmed
endpoints: [EP-CM-002]
tables: [devices, sessions]
events: []
blocked_by: [BE-I03]
unblocks_frontend: [RP-01, RP-03, RT-06]
---

# BE-I04 — Verify OTP and device binding

**Endpoints:** EP-CM-002

## Requirements

1. Body: otp_id, code, device_id, device_name, platform.
2. Issue a Sanctum token bound to device_uuid; return is_new_user and profile_completed.
3. A new user receives a registration-ability token only; a complete user receives a full token.
4. Record the device and open a session row.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A wrong code returns 401 otp_invalid and consumes one attempt.
- [ ] The issued token is bound to the device and is revoked on logout.
- [ ] is_new_user is computed by the server, not inferred by the client.

## Frontend tickets waiting on this

- RP-01, RP-03, RT-06

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
