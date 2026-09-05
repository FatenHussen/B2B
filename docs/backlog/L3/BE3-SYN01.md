---
id: BE3-SYN01
title: Outbox intake endpoint
layer: L3
side: Backend
module: Sync
epic: EP-L3-SYN
sprint: SP-12
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [sync_operations]
events: []
blocked_by: [BE-C03]
---

# BE3-SYN01 — Outbox intake endpoint

## Requirements

1. A batch endpoint accepting an ordered set of operations and returning an independent result per operation.
2. Every operation carries a device-generated client_uuid that makes retry safe.
3. Runs on the critical queue and is never starved by reports.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Replaying a whole batch produces no duplicate of any operation (AC-05).
- [ ] A failure on one operation does not reject the batch.

## Working rules

- Touch only `app-modules/sync` and its tests.
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
