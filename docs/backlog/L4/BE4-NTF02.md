---
id: BE4-NTF02
title: Automatic event templates
layer: L4
side: Backend
module: Notification
epic: EP-L4-NTF
sprint: SP-14
points: 5
priority: High
contract: proposed
endpoints: []
tables: [notification_templates]
events: []
blocked_by: [BE4-NTF01, BE2-ORD04]
---

# BE4-NTF02 — Automatic event templates

## Requirements

1. Templates bound to system events: order confirmed, rep on the way, delivered, postponed, an awaited product back in stock, a return decision, a payment falling due, a new offer matching the activity.
2. Send only on a change that matters to the user, without flooding (REQ-CM-023).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Every state transition that concerns a user produces exactly one notification.
- [ ] No event produces a notification that requires no action and carries no information.

## Working rules

- Touch only `app-modules/notification` and its tests.
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
