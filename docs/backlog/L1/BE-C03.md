---
id: BE-C03
title: Idempotency middleware
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-00
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [idempotency_keys]
events: []
blocked_by: [BE-C02]
---

# BE-C03 — Idempotency middleware

## Requirements

1. Every write accepts X-Idempotency-Key (BR-AD-24, REQ-CM-012).
2. Known and completed key → replay the stored response byte for byte with its original status.
3. Known and in flight → 409 operation_in_progress.
4. New key → execute, then store the response for 24 hours.
5. Exempt paths: public OTP request / verify / resend, platform login and 2FA, channel OTP, warehouse device-login.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Replaying a completed key creates no second row anywhere.
- [ ] Two concurrent requests with the same key produce one execution and one 409.
- [ ] This is the technical basis of REQ-IN-01 and AC-05 — proven by test, not asserted.

## Working rules

- Touch only `app-modules/core` and its tests.
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
