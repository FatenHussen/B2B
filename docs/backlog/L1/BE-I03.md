---
id: BE-I03
title: Request OTP endpoint
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 3
priority: Highest
contract: confirmed
endpoints: [EP-CM-001]
tables: []
events: []
blocked_by: [BE-I02, BE-X01]
unblocks_frontend: [RP-01, RP-03, RT-03]
---

# BE-I03 — Request OTP endpoint

**Endpoints:** EP-CM-001

## Requirements

1. Body: phone, purpose, client. The server normalises the Syrian number; the client sends it as typed.
2. Response carries otp_id, expires_in (300), resend_after (60) and channel_used.
3. Delivery goes through the WhatsApp contract, with the SMS fallback selectable.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A non-Syrian number returns 422 with the error on the phone field, in Arabic.
- [ ] Exceeding the limit returns 429 with resend_after set.

## Frontend tickets waiting on this

- RP-01, RP-03, RT-03

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
