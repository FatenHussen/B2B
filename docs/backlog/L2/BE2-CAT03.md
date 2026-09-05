---
id: BE2-CAT03
title: Products
layer: L2
side: Backend
module: Catalog
epic: EP-L2-CAT
sprint: SP-05
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [products]
events: []
blocked_by: [BE2-CAT02]
---

# BE2-CAT03 — Products

## Requirements

1. All DOC-01 §4.2.3 tabs: basics, description, media, sale and packaging, pricing type, inventory, availability, variants, marketing.
2. SKU and barcode unique per channel with duplicate detection on entry.
3. Availability is the intersection of zones, activities and retailer groups (BR-15).
4. Soft delete only; disabling hides the product from both apps.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A duplicate SKU is caught at entry, not at save.
- [ ] A product outside the retailer zone never appears in any list or search result.

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
