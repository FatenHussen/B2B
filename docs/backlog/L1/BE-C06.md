---
id: BE-C06
title: Money value object and integer columns
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-00
points: 3
priority: Highest
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-F04]
---

# BE-C06 — Money value object and integer columns

## Requirements

1. Every amount is a bigInteger in the smallest currency unit, wrapped in a Money value object (BR-AD-18).
2. float and double are forbidden in any money column, enforced by architecture test.
3. Rounding happens in the presentation layer only, via MoneyResource.
4. An FX-rate change never reprices an existing order: currency and rate are frozen on the order line (BR-AD-19).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The architecture test fails on any float introduced into a money path.
- [ ] SYP renders with zero decimals through the resource, while storage stays integer.

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
