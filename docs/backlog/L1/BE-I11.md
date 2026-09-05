---
id: BE-I11
title: Warehouse device provisioning and login
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 5
priority: Highest
contract: confirmed
endpoints: [EP-WH-001]
tables: [warehouse_users, devices]
events: []
blocked_by: [BE-I01]
unblocks_frontend: [WH-03]
---

# BE-I11 — Warehouse device provisioning and login

**Endpoints:** EP-WH-001

## Requirements

1. An artisan command warehouse:register-device issues a device_token bound to one warehouse.
2. POST /warehouse/auth/device-login accepts device_token and a four-digit PIN.
3. A failure returns one identical 401 whether the token or the PIN was wrong.
4. The success payload carries warehouse.name and permissions[].

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] No API path can create a device — provisioning is an operator command only.
- [ ] The 401 body is byte-identical for a wrong token and a wrong PIN.
- [ ] PINs are hashed and attempt-limited.

## Frontend tickets waiting on this

- WH-03

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Working rules

- Touch only `app-modules/identity` and its tests.
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
