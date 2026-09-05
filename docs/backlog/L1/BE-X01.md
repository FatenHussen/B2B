---
id: BE-X01
title: WhatsApp Business API adapter
layer: L1
side: Backend
module: Integration
epic: EP-BE-X
sprint: SP-01
points: 5
priority: Highest
contract: internal
endpoints: []
tables: [integration_settings]
events: []
blocked_by: [BE-F07]
---

# BE-X01 — WhatsApp Business API adapter

## Requirements

1. Implement the OTP delivery contract behind an interface; the Identity module never calls the vendor directly.
2. Delivery runs on the critical queue with retry and a delivery record.
3. Credentials live in configuration, never in code.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Identity has no compile-time knowledge of the provider.
- [ ] The provider can be swapped in configuration with no code change.

## Working rules

- Touch only `app-modules/integration` and its tests.
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
