---
id: BE-R02
title: Governorates
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 3
priority: Highest
contract: confirmed
endpoints: [EP-AD-030, EP-AD-031, EP-AD-042A, EP-AD-043A]
tables: [governorates]
events: []
blocked_by: [BE-R01]
unblocks_frontend: [AD-41]
---

# BE-R02 — Governorates

**Endpoints:** EP-AD-030, EP-AD-031, EP-AD-042A, EP-AD-043A

## Requirements

1. CRUD with name_ar, name_en and code; status toggle with a reason.
2. Seed the Syrian governorates as launch data.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The seed is idempotent.
- [ ] An update without a reason is refused with 422.

## Frontend tickets waiting on this

- AD-41

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

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
