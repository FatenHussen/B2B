---
id: BE2-CAT08
title: Text and barcode search
layer: L2
side: Backend
module: Catalog
epic: EP-L2-CAT
sprint: SP-06
points: 5
priority: High
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE2-CAT07]
---

# BE2-CAT08 — Text and barcode search

## Requirements

1. Arabic text search across product, brand and category with tolerance for common spelling variants.
2. Exact barcode lookup returning the variant directly.
3. Search respects the same three-way filter as the listing.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A barcode scan resolves to one variant or a clear not-found.
- [ ] Search never returns a product the retailer cannot be delivered.

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
