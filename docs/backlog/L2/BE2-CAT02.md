---
id: BE2-CAT02
title: Five-level category tree
layer: L2
side: Backend
module: Catalog
epic: EP-L2-CAT
sprint: SP-05
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [categories]
events: []
blocked_by: [BE2-CAT01, BE-R05]
---

# BE2-CAT02 — Five-level category tree

## Requirements

1. Materialised path plus parent_id, with a hard maximum depth of five levels enforced server side.
2. Drag and drop reordering and moving between levels, applied transactionally.
3. A category holding active products cannot be disabled until they are moved or disabled, and the count is returned with the refusal.
4. Category to activity-type binding decides visibility for each retailer.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Creating a sixth level is refused with a clear message, not silently truncated.
- [ ] A live counter shows direct and total product counts per node.
- [ ] Reordering a subtree of 500 nodes stays within the interactive budget.

## Working rules

- Touch only `app-modules/catalog` and its tests.
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
