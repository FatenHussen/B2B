---
id: BE-R01
title: Reference module core and soft-delete policy
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 5
priority: Highest
contract: internal
endpoints: []
tables: []
events: [ReferenceDisabled]
blocked_by: [BE-C04]
---

# BE-R01 — Reference module core and soft-delete policy

## Requirements

1. A shared pattern for all seven entities: list, create, update with reason, enable and disable.
2. No hard delete on any reference entity; disabling is logical and audited.
3. Disabling emits ReferenceDisabled, consumed by Catalog, Tenancy and Ordering to block new use without touching existing rows.
4. Reference entities carry no channel_id and are not subject to the tenant scope.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A disabled reference blocks new registration but leaves existing records intact.
- [ ] No delete route exists on any reference entity.

## Working rules

- Touch only `app-modules/reference` and its tests.
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
