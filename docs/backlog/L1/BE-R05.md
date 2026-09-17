---
id: BE-R05
title: Root categories
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 3
priority: High
contract: confirmed
endpoints: [EP-AD-036A, EP-AD-036B, EP-AD-042D, EP-AD-043C]
tables: [root_categories]
events: []
blocked_by: [BE-R01]
unblocks_frontend: [AD-44]
---

# BE-R05 — Root categories

**Endpoints:** EP-AD-036A, EP-AD-036B, EP-AD-042D, EP-AD-043C

## Requirements

1. CRUD plus status; disabling a category that is in use returns 409 ref_in_use with the usage count.
2. Root categories are platform-level; the five-level channel tree in Catalog hangs off them in Layer 2.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] 409 carries the number of dependent records so the message can be specific.

## Frontend tickets waiting on this

- AD-44

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Decision — the refusal is 422, not 409 — 2026-09-17

The catalog example on EP-AD-043C/D says `409 ref_in_use`. CLAUDE.md's status map and
`ErrorCode::RefInUse` put `ref_in_use` at **422**, and one renderer decides the status of a
code so it cannot reach a client under two numbers depending on who threw it. The response
carries `error.details.affected.{child_categories, active_products}` (and `products` for
sale units) so the message can be specific, which is what the acceptance criterion asks.
Both counts come from Catalog through `CatalogProductLookup`, never from a catalog table.

## Working rules

- Touch only `app-modules/reference` and its tests.
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
