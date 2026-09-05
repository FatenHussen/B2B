---
id: BE2-PRC03
title: Pricing engine with rule precedence
layer: L2
side: Backend
module: Pricing
epic: EP-L2-PRC
sprint: SP-06
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE2-PRC01, BE2-PRC02]
---

# BE2-PRC03 — Pricing engine with rule precedence

## Requirements

1. A pure service taking a context and returning a price, with no knowledge of HTTP or session (DOC-02 §11).
2. Precedence: retailer price, then group price, then zone price, then quantity tier, then base price. Search stops at the first matching effective rule.
3. Every result carries the applied rule id so any price can be explained and audited.
4. Bulk resolve for a full list in one query, with a cache keyed on the whole context and invalidated on any price or product change.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Unit test coverage of the engine is at least 90 percent, including every precedence collision.
- [ ] Two retailers in different zones receive different correct prices for the same product (DOC-04 §8).
- [ ] The applied rule id is present on every returned price.

## Working rules

- Touch only `app-modules/pricing` and its tests.
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
