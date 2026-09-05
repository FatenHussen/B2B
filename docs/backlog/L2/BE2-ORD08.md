---
id: BE2-ORD08
title: Retailer order list and detail API
layer: L2
side: Backend
module: Ordering
epic: EP-L2-ORD
sprint: SP-08
points: 5
priority: High
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE2-ORD04]
---

# BE2-ORD08 — Retailer order list and detail API

## Requirements

1. Filter by all, active, pending, in progress, delivered, cancelled, and by channel.
2. The channel name appears only after confirmation; the rep identity only at on_the_way (BR-02, BR-03, REQ-IN-06).
3. Withholding happens in the resource layer, not in the client.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] An API inspection before confirmation shows no channel identity anywhere in the payload.
- [ ] The rep name and phone are absent until the state is on_the_way.

## Working rules

- Touch only `app-modules/ordering` and its tests.
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
