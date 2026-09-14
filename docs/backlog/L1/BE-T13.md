---
id: BE-T13
title: Channel status transitions
layer: L1
side: Backend
module: Tenancy
epic: EP-BE-T
sprint: SP-04
points: 3
priority: High
contract: confirmed
endpoints: [EP-AD-054]
tables: []
events: [ChannelStatusChanged]
blocked_by: [BE-T01]
unblocks_frontend: [AD-75]
---

# BE-T13 — Channel status transitions

**Endpoints:** EP-AD-054

## Requirements

1. Expose allowed_next on the channel resource and validate every transition against the matrix.
2. Suspension freezes new orders only and does not touch orders in flight.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A transition absent from allowed_next returns 409 illegal_transition.
- [ ] Existing orders continue to progress after suspension.

## Status — delivered 2026-09-14, one requirement partially met

Commits `0fc25cf` (route and tests), `37c1326` (Postman), `f9cd363` (events.md, BE4-NTF04).

- Requirement 1 and both acceptance criteria are met and tested through the route
  (`tests/Feature/Tenancy/ChannelTransitionRouteTest.php`): a transition absent from
  `allowed_next` is 409 `illegal_transition`, and an order in flight is accepted by its
  rep after the platform suspends the channel.
- **Requirement 2 is partially met.** "Does not touch orders in flight" holds. "Freezes new
  orders" holds only up to the cart: a suspended channel leaves the retailer's shopping
  context at once (`ChannelDirectory::activeIdsCoveringZone()`), so nothing new can be
  added for it — but a cart filled before the suspension still submits, because Ordering's
  two submit actions never re-check the section's channel. **BE-O16 completes it.** The
  gap is pinned by a `todo` test in the file above, named for that ticket, so it is not
  mistaken for coverage.
- The path is `/admin/channels`, MOVING to `/platform/channels` with its five siblings;
  keep it behind the same constant (platform-web.md §4.5).
- `ad.channels.archive` is enforced in the action for `to_status = archived` and is not
  yet seeded — seeding it from the catalog needs a structured field beside EP-AD-054.
- No listener on `ChannelStatusChanged`, by decision: the freeze is a synchronous read,
  not an event consumer. See `docs/events.md` and BE4-NTF04.

## Frontend tickets waiting on this

- AD-75

Changing a response shape here breaks those tickets. Regenerate OpenAPI and say so in the PR.

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
