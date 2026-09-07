---
id: BE-F06
title: OpenAPI contract and generated clients
layer: L1
side: Backend
module: —
epic: EP-BE-F
sprint: SP-00
points: 5
priority: High
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-F01]
---

# BE-F06 — OpenAPI contract and generated clients

## Requirements

1. Generate the OpenAPI document from the route and resource definitions.
2. Generate a typed TypeScript client and Zod schemas for the three web frontends.
3. The catalog fields b (request body), r (data shape) and e (error codes) are the contract of record.
4. Register the `openapi:generate` artisan command. CLAUDE.md names
   `php artisan openapi:generate --check` as a sixth gate; the `openapi` namespace
   currently registers no commands at all, so the gate cannot run and CLAUDE.md documents
   it as pending against this ticket.
5. Add the five live channel routes below to the catalog. **They are legitimate and must
   not be deleted** — the catalog is incomplete, not the routes.

## Catalog gap — five live routes with no entry, recorded 2026-09-07

A search of all 338 catalog entries found none for these, though every one is served
today and covered by tests:

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/channel/zones` | `sc.zones.view` |
| POST | `/api/v1/channel/zones` | `sc.zones.manage` |
| DELETE | `/api/v1/channel/zones/{id}` | `sc.zones.manage` |
| GET | `/api/v1/channel` | `sc.settings.view` |
| PUT | `/api/v1/channel` | `sc.settings.update` |

The evidence that the gap is the catalog's and not the routes': **DOC-08 defines all four
permissions these routes use** — `sc.zones.view`, `sc.zones.manage`, `sc.settings.view`
and `sc.settings.update`. A permission catalogue does not describe who may manage channel
zones unless channel zones are meant to be managed. The catalog's only coverage endpoints
(EP-AD-065A/B) are platform-scoped, and channel settings appear nowhere in it.

DOC-08 also defines `sc.settings.audit`, which no route serves yet — a sixth entry this
ticket may need.

Until an entry exists these five have no `b`/`r`/`e` of record, so requirement 3 cannot be
applied to them and their response shapes are unverifiable against the contract.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A frontend developer writes no type by hand.
- [ ] A server-side shape change breaks the frontend build rather than failing silently at runtime.

## Working rules

- Touch only `the module named in the front matter` and its tests.
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
