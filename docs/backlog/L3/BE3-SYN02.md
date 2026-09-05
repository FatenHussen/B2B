---
id: BE3-SYN02
title: Differential sync and cursors
layer: L3
side: Backend
module: Sync
epic: EP-L3-SYN
sprint: SP-12
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [sync_cursors]
events: []
blocked_by: [BE2-CAT07]
---

# BE3-SYN02 — Differential sync and cursors

## Requirements

1. Only what changed is returned, never the whole catalog (REQ-CM-016).
2. A cursor per dataset: catalog, prices, offers, customers, zones, orders.
3. PriceListChanged and similar events mark data for the next differential pull.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A day of catalog changes transfers a fraction of a full catalog payload.
- [ ] A device offline for a week catches up without downloading everything.

## Working rules

- Touch only `app-modules/sync` and its tests.
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
