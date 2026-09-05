---
id: BE4-SUP02
title: Impersonation with guardrails
layer: L4
side: Backend
module: Support
epic: EP-L4-SUP
sprint: SP-16
points: 5
priority: High
contract: proposed
endpoints: []
tables: [impersonation_grants]
events: []
blocked_by: [BE-C05, BE-C04]
---

# BE4-SUP02 — Impersonation with guardrails

## Requirements

1. Impersonation requires an explicit grant with a reason, a time limit and a second approver.
2. Every request made while impersonating is flagged in the audit log.
3. Write actions can be restricted while impersonating.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] No impersonation exists without a grant and a reason.
- [ ] The audit log distinguishes an impersonated action from a genuine one.

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
