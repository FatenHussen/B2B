---
id: BE-O16
title: Enforce channel suspension at cart submission
layer: L1
side: Backend
module: Ordering
epic: EP-BE-O
sprint: SP-04
points: 2
priority: High
contract: confirmed
endpoints: [EP-RT-025, EP-RP-023]
tables: []
events: []
blocked_by: [BE-T13]
---

# BE-O16 — Enforce channel suspension at cart submission

SubmitRetailerCart and SubmitRepCartSection do not check the channel is active before
creating a sub-order. A cart filled before suspension still produces a new order for a
suspended channel. `ChannelDirectory::isActive()` exists and is the read to make. The
catalog names 409 `offer_no_longer_valid` as the closest code — confirm or add a precise
one.

Blocks the BE-T13 acceptance criterion "suspension freezes new orders only".

## Why here and not in BE-T13

The check belongs to the two actions that create sub-orders, and both are Ordering's.
Fixing it from Tenancy would mean Tenancy knowing when a cart is submitted — a breach of
the boundary the modules were built on. Tenancy publishes the fact (`isActive()`);
Ordering reads it at the moment it matters.

## What holds today, and where it stops

`IdentityRetailerShoppingContext` builds `channel_ids` from
`ChannelDirectory::activeIdsCoveringZone()`, so a suspended channel disappears from the
retailer's shopping context and `AddRetailerCartLine` refuses its products. That is the
synchronous freeze BR-AD-14 asks for, and it is the right mechanism — not an event
listener (see `docs/events.md`, `ChannelStatusChanged`). It stops one step short: a
section already in the cart is never re-checked when the cart is submitted.

## On the error code

`offer_no_longer_valid` is listed on EP-RT-025 for a cart that no longer reprices as it
stood; it is the nearest code in the contract, not one the catalog assigns to this case.
Using it here is a reading, and the owner should confirm it or name a precise code. A
new code means a new row in the DOC-08 status map and `ErrorCode`, with the ErrorMapTest
pin moving with it.

## Requirements

1. `SubmitRetailerCart`: for each section with lines, `isActive((int) $section->channel_id)`
   before any write; refuse the whole submission if any section's channel is not active.
   The retailer is told which section, by its `opaque_ref` — never by the channel's name
   (BR-02).
2. `SubmitRepCartSection`: the same read on the section being submitted.
3. The read happens inside the same request as the write, before the transaction — not
   in a listener, not in a job.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] A cart holding a section for a channel suspended after the section was filled is
      refused at submission, and no order or sub-order row is written.
- [ ] A rep submitting a section for a suspended channel is refused the same way.
- [ ] A cart whose channels are all active submits exactly as before.
- [ ] `tests/Feature/Tenancy/ChannelTransitionRouteTest.php` — the todo named for this
      ticket — is un-todo'd and green, driving the suspension through EP-AD-054.

## Working rules

- Touch only `app-modules/ordering` and its tests (the Tenancy test above is the one
  exception, and only its `todo` marker).
- Do not modify another module. Use its `Contracts/` or an event.
