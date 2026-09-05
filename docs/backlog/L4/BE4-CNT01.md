---
id: BE4-CNT01
title: Intro and legal pages
layer: L4
side: Backend
module: Content
epic: EP-L4-CNT
sprint: SP-14
points: 3
priority: Medium
contract: proposed
endpoints: []
tables: [intros, legal_pages]
events: []
blocked_by: [BE-T04]
---

# BE4-CNT01 — Intro and legal pages

## Requirements

1. Control the opening screen of both apps: enable, welcome text, logo, image or video, display duration.
2. Target a different intro per activity type or per zone.
3. Versioned terms and privacy policy with an acceptance record per user, required for store approval (NEED-C-08).

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A terms change requires re-acceptance and the acceptance is recorded per version.
- [ ] Disabling the intro takes effect without an app release.

## Working rules

- Touch only `app-modules/content` and its tests.
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
