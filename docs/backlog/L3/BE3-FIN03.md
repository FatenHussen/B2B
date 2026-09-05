---
id: BE3-FIN03
title: Receivables and ageing
layer: L3
side: Backend
module: Finance
epic: EP-L3-FIN
sprint: SP-13
points: 5
priority: High
contract: proposed
endpoints: []
tables: [receivables]
events: []
blocked_by: [BE3-FIN02]
---

# BE3-FIN03 — Receivables and ageing

## Requirements

1. Live receivable per retailer per channel.
2. Ageing buckets 0-30, 31-60, 61-90 and over 90 days, groupable by zone and by rep.
3. Credit limit with a grace period and a configurable behaviour on breach: warn, block new orders, or require manual approval.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The retailer statement reconciles to invoices minus payments minus returns at any instant (AC-07).
- [ ] A credit breach behaves exactly as configured, enforced server side.

## Working rules

- Touch only `app-modules/finance` and its tests.
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
