---
id: BE3-FIN01
title: Invoices
layer: L3
side: Backend
module: Finance
epic: EP-L3-FIN
sprint: SP-13
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [invoices, invoice_lines]
events: []
blocked_by: [BE3-DLV03, BE-C06]
---

# BE3-FIN01 — Invoices

## Requirements

1. Generated from the sub-order and posted only after delivery and the settlement of shortages and returns.
2. States: draft, posted, partially paid, fully paid, overdue, cancelled, amended by credit note.
3. A posted invoice is immutable; corrections go through a linked credit or debit note (BR-10).
4. PDF with the channel letterhead, exportable and deliverable inside the app.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] No path edits a posted invoice.
- [ ] The invoice total equals the accepted lines minus discounts and returns, to the smallest unit.

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
