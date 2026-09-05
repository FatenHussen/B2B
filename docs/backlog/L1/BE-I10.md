---
id: BE-I10
title: Channel OTP sign-in
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 5
priority: Highest
contract: confirmed
endpoints: [EP-CH-001, EP-CH-002]
tables: [channel_users]
events: []
blocked_by: [BE-I02, BE-T01]
unblocks_frontend: [CH-02, CH-03]
---

# BE-I10 — Channel OTP sign-in

**Endpoints:** EP-CH-001, EP-CH-002

## Requirements

1. Request accepts phone only — purpose is not part of this catalog row.
2. Verify accepts otp_id and code and returns token, permissions[] and channels[].
3. A user with no channel membership receives 403 with a clear message.
4. Membership of more than one channel returns all of them for the picker.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Sending purpose is rejected by the request validation.
- [ ] permissions[] is present on the verify response and drives the sidebar.

## Frontend tickets waiting on this

- CH-02, CH-03

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
