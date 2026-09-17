---
id: BE-T12
title: Limits, overrides and plan enforcement
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 5
priority: High
contract: confirmed
endpoints: [EP-AD-055]
tables: [channel_limits]
events: []
blocked_by: [BE-T04, BE-T10]
unblocks_frontend: [AD-74]
---

# BE-T12 — Limits, overrides and plan enforcement

**Endpoints:** EP-AD-055

## Requirements

1. Override a limit with temporary_until and a mandatory reason.
2. Exceeding a limit returns 423 plan_limit_exceeded with the limit named.
3. All limits are integers; an expired temporary override reverts automatically.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] An expired override reverts without a scheduled job having to run on time.
- [x] 423 names which limit was hit so the client can explain it.

## Frontend tickets waiting on this

- AD-74

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

## Done — 2026-09-17

`ChannelLimitResolver` replaces the 5000-SKU stub behind `ChannelLimits`: override →
channel row → plan → defaults, per key, with `temporary_until` compared on every read so an
expired override reverts with no job. Overrides live in their own nullable columns beside
the base the plan wrote, so the base is never lost.

Where a limit bites today: `reps` at rep registration (`RegisterRep`), `skus` at product
creation (`SaveProduct`, which used to answer a bespoke 422). `users` and `warehouses`
have no add path in the API yet (channel-user invites are BE-T07, warehouses come from
provisioning), so they are resolved and reported on EP-AD-056 but not yet enforced.
`assertCanAdd()` is the one place to call when those paths land.

The route is `PUT /platform/channels/{id}/limits`, the catalog's path, while the sibling
channel routes still sit under `/admin/channels` and are moving.

## Working rules

- Touch only `app-modules/tenancy` and its tests.
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
