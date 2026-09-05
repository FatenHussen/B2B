---
id: BE4-NTF01
title: Notification model and targeting
layer: L4
side: Backend
module: Notification
epic: EP-L4-NTF
sprint: SP-14
points: 5
priority: High
contract: proposed
endpoints: []
tables: [notifications, notification_targets]
events: []
blocked_by: [BE-T02]
---

# BE4-NTF01 — Notification model and targeting

## Requirements

1. Title, body, icon, optional image and a tap action opening a screen, product, offer or link.
2. Targeting: one user, several users, a retailer group, a zone, a city, an activity type, or everyone (REQ-CM-022).
3. Read notifications are hidden from the counter but never deleted from the log (BR-17).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A targeted notification reaches exactly the intended audience, verified by count.
- [ ] Marking as read changes the counter and preserves the record.

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
