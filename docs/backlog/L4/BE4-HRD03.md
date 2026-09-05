---
id: BE4-HRD03
title: Field acceptance criteria verification
layer: L4
side: Backend
module: —
epic: EP-L4-HRD
sprint: SP-17
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: []
events: []
blocked_by: [BE3-SYN03, BE3-FIN04]
---

# BE4-HRD03 — Field acceptance criteria verification

## Requirements

1. Verify AC-01 through AC-10 from DOC-06 §9.1 with real users, not in a meeting room.
2. Registration under sixty seconds, retailer order in three to four taps, rep order under thirty seconds, value understood in ten seconds.
3. Zero lost or duplicated operations after a full day offline with deliberate interruptions.
4. Wallet, statement and stock all reconcile exactly.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Every one of the ten criteria is measured and recorded with real participants.
- [ ] A failure on any criterion blocks the pilot rather than being noted for later.

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
