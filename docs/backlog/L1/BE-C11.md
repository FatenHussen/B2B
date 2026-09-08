---
id: BE-C11
title: Remove the manual channel filters now made redundant by BelongsToChannel
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-03
points: 2
priority: Medium
contract: internal
endpoints: []
tables: []
events: []
blocked_by: [BE-R03]
---

# BE-C11 — Remove the redundant manual channel filters

## Background

Before rule 10 was enforced, twelve models carried a channel column and no `ChannelScope`.
Isolation on those models was a hand-written `->where('channel_id', Tenant::currentId())`
repeated in each query object — protection that held only where someone remembered it, and
that no test could prove was present. Measured at the time: with the tenant set to channel
B, `SubOrder::query()->get()` returned channel A's row.

`BelongsToChannel` now applies to seven of those models, and
`tests/Architecture/ChannelScopeTest.php` fails the build if a new one appears without it.
The manual filters were deliberately left in place: adding the automatic scope and removing
the manual one in a single step would have meant taking the net away in the same commit
that installed the floor.

## The three sites

| File | Line | Shape |
|---|---|---|
| `ordering/src/Application/Queries/ListChannelSubOrders.php` | 29 | `->where('channel_id', Tenant::currentId())` |
| `ordering/src/Application/Queries/ShowChannelSubOrder.php` | 25 | `->where(...)->find($id)` then a 404 throw |
| `returns/src/Application/ReturnsWorkspace.php` | 70 | `->where('channel_id', Tenant::currentId())` |

The duplication is harmless — the same predicate twice — so this is tidying, not a fix.

## Requirements

1. Remove the three filters, one commit each, and confirm the suite after each.
2. `ListChannelSubOrders` must not be touched before its `TypeError` is fixed and it has
   its first test — see the TODO commit for `GET /channel/sub-orders`, which returns 500 to
   every caller today. Editing a route nobody can exercise proves nothing.

## What to watch for

**Removing a filter can widen what a user sees, not narrow it.** Verify each site before
deleting, not after:

- **A filter that is pure isolation** is safely redundant. `ShowChannelSubOrder` was checked
  during BE-R03: the 404 comes from the controller's own `DomainException`, not from
  `findOrFail`, so status and message are identical either way.
- **A filter that is a narrower scope** — a subset of the tenant's rows rather than the
  tenant's rows — is not redundant, and deleting it grants access rather than removing a
  duplicate. Read the predicate before assuming.

**Deletion can surface calls that ran with no tenant.** Today `Tenant::currentId()`
returning null produces `where(column, null)`, which matches nothing and answers a silent
404 or an empty list. With only the global scope in place the same call raises
`MissingChannelScopeException`. That is an improvement — a programming error stops
disguising itself as "not found" — but it converts a quiet wrong answer into a loud 500,
and any such call site needs deciding on its own merits: either it should have a tenant, or
it belongs on `acrossChannels()` with the reason written down.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Each of the three sites is verified as pure isolation before its filter is removed.
- [ ] The full suite is green after each removal, with no test edited to accommodate it.
- [ ] Any call site newly raising MissingChannelScopeException is resolved explicitly —
      given a tenant, or given `acrossChannels()` with a written reason — never by
      restoring the manual filter.

## Working rules

- Touch only the three files named above and their tests.
- Do not remove a filter that is narrower than the channel scope.
- Do not add `acrossChannels()` without a comment saying why that call is cross-channel.
