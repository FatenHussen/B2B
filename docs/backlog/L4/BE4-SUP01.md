---
id: BE4-SUP01
title: Tickets and unified search
layer: L4
side: Backend
module: Support
epic: EP-L4-SUP
sprint: SP-16
points: 5
priority: Medium
contract: proposed
endpoints: []
tables: [tickets, ticket_events]
events: []
blocked_by: [BE-A16]
---

# BE4-SUP01 — Tickets and unified search

## Requirements

1. A ticket with a subject, a linked entity, a state machine and an event log.
2. Unified search across retailers, reps, channels, orders and invoices from one field.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A support agent finds any entity by number or phone in one search.
- [ ] Every ticket action is audited.

## Working rules

- Touch only `app-modules/support` and its tests.
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
