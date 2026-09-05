---
id: BE2-CAT01
title: Brands
layer: L2
side: Backend
module: Catalog
epic: EP-L2-CAT
sprint: SP-05
points: 5
priority: Highest
contract: proposed
endpoints: []
tables: [brands]
events: []
blocked_by: [BE-T02]
---

# BE2-CAT01 — Brands

## Requirements

1. Name in Arabic and English unique within the channel, logo (minimum 512x512), page banner, short description up to 300 characters.
2. Multiple activities per brand, display order, and an enable flag that hides the brand and its products without deleting them.
3. Brand page sliders configured as name plus source plus count plus order.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Disabling a brand removes it and its products from both apps while preserving every record.
- [ ] Brand name uniqueness is enforced per channel by a database constraint.

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
