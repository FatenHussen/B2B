---
id: BE2-CAT06
title: Catalog import and export
layer: L2
side: Backend
module: Catalog
epic: EP-L2-CAT
sprint: SP-05
points: 8
priority: High
contract: proposed
endpoints: []
tables: [import_batches]
events: []
blocked_by: [BE2-CAT03, BE-R11]
---

# BE2-CAT06 — Catalog import and export

## Requirements

1. Excel import with a published template, a preview before saving, and a per-row error report.
2. Full or filtered export.
3. Bulk edit of category, brand, status, zones or a price percentage for a selected set.
4. Duplicate detection on SKU and barcode during import.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A file of 10,000 rows imports without timing out, on a queue.
- [ ] A partially invalid file reports every bad row and imports nothing until confirmed.

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
