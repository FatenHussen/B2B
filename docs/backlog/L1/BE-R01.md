---
id: BE-R01
title: Reference module core and soft-delete policy
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 5
priority: Highest
contract: internal
endpoints: []
tables: []
events: [ReferenceDisabled]
blocked_by: [BE-C04]
---

# BE-R01 — Reference module core and soft-delete policy

## Requirements

1. A shared pattern for all seven entities: list, create, update with reason, enable and disable.
2. No hard delete on any reference entity; disabling is logical and audited.
3. Disabling emits ReferenceDisabled, consumed by Catalog, Tenancy and Ordering to block new use without touching existing rows.
4. Reference entities carry no channel_id and are not subject to the tenant scope.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] A disabled reference blocks new registration but leaves existing records intact.
- [x] No delete route exists on any reference entity.

## Done — 2026-09-17

Every write on the seven entities goes through one service, `ReferenceMutations`: create,
update with a mandatory reason, and a status change that refuses to disable what is still
in use (`ref_in_use` with the counts), writes a before/after audit row and emits
`ReferenceDisabled`. There is no delete route anywhere; `PlatformRefsTest` reads the route
table to prove it.

**Routes moved.** Every write and the platform's own reads now live under
`/api/v1/platform/refs/*` (the catalog's paths, platform guard, `ad.refs.*` gates with
Spatie's `permission:` middleware so a 403 names the code). The shared `GET /governorates`,
`/zones` and `/currencies` reads stay for the four guards because the channel dashboard
pack lists them; nothing under them mutates.

**"Block new use" is the directory, not a listener.** `ReferenceDirectory::all*Exist()`
now require an active row, so a disabled activity type, root category, equipment or zone
is refused wherever registration, a product, an offer or a price list names it, while
every existing row keeps pointing at it. The event is emitted for Catalog, Tenancy and
Ordering to consume when they have something to do with it; today the directory alone
satisfies acceptance criterion 1.

Six `acrossChannels()` sites were added for the impact counts — the platform back office
counting rows across every channel before disabling a shared entity. They are pinned in
`ChannelScopeEscapeTest` and named in CLAUDE.md rule 10 (42 → 48).

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
