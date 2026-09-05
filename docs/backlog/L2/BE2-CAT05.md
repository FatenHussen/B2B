---
id: BE2-CAT05
title: Media library and image processing
layer: L2
side: Backend
module: Catalog
epic: EP-L2-CAT
sprint: SP-05
points: 5
priority: High
contract: proposed
endpoints: []
tables: [product_media]
events: []
blocked_by: [BE2-CAT03, BE-X03]
---

# BE2-CAT05 — Media library and image processing

## Requirements

1. Multiple images with a primary flag and ordering, optional video.
2. Automatic compression and multi-size generation to serve low-end devices on 3G (REQ-CM-043).
3. Processing runs on the media queue, never inside the request.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A product card request transfers a thumbnail, not the original file.
- [ ] An upload never blocks the response.

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
