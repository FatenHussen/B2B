---
id: BE-I02
title: OTP storage and security policy
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [otp_codes]
events: []
blocked_by: [BE-I01, BE-C08]
---

# BE-I02 — OTP storage and security policy

## Requirements

1. Codes are stored hashed, never in clear text, with a five-minute expiry and a capped attempt count.
2. Purpose is recorded (register or login) and a code is single use.
3. Strict rate limiting per phone and per IP with progressive delay.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A database dump exposes no usable code.
- [ ] A consumed or expired code returns 401 otp_invalid.
- [ ] Attempt exhaustion invalidates the code rather than merely rejecting the attempt.

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
