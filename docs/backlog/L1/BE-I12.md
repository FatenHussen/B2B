---
id: BE-I12
title: Platform login and TOTP
layer: L1
side: Backend
module: Identity
epic: EP-BE-I
sprint: SP-01
points: 5
priority: Highest
contract: confirmed
endpoints: [EP-AD-001, EP-AD-002, EP-AD-003]
tables: [platform_users]
events: []
blocked_by: [BE-I01]
unblocks_frontend: [AD-10, AD-11, AD-13]
---

# BE-I12 — Platform login and TOTP

**Endpoints:** EP-AD-001, EP-AD-002, EP-AD-003

## Requirements

1. Login with email and password; when 2FA is enabled return requires_2fa and a short-lived challenge_token.
2. Verify the TOTP code against the challenge and issue the session token with roles, permissions and expires_at.
3. A 401 reveals nothing about whether the email exists.
4. Logout revokes the token.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The failure message is identical for an unknown email and a wrong password.
- [ ] challenge_token expires in minutes and is single use.

## Frontend tickets waiting on this

- AD-10, AD-11, AD-13

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
