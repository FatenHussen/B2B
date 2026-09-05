---
id: BE-T08
title: Coverage and overlap detection
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 5
priority: High
contract: confirmed
endpoints: [EP-AD-065A, EP-AD-065B]
tables: [channel_zone]
events: []
blocked_by: [BE-T04, BE-R03]
unblocks_frontend: [AD-70]
---

# BE-T08 — Coverage and overlap detection

**Endpoints:** EP-AD-065A, EP-AD-065B

## Requirements

1. Coverage binds a channel to zones, each with its serving warehouse, delivery days, delivery fee, minimum order value and price list.
2. Detect and report overlap with other channels before the save is committed.
3. A save requires a reason.
4. Rep registration validates zone_ids against this coverage.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Overlap is reported before the write, not discovered afterwards.
- [ ] A zone outside coverage is rejected on rep registration with 422.

## Frontend tickets waiting on this

- AD-70

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

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
