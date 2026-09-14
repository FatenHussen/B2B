---
id: BE4-NTF04
title: Tell a channel its status changed (channel.suspended)
layer: L4
side: Backend
module: Notification
epic: EP-L4-NTF
sprint: SP-14
points: 3
priority: Medium
contract: internal
endpoints: []
tables: []
events: [ChannelStatusChanged]
blocked_by: [BE4-NTF02, BE-T01]
---

# BE4-NTF04 — Tell a channel its status changed

## Background

BE-T01 made `ChannelStatusChanged` real: it is dispatched from
`ChannelLifecycle::transition()` — the only writer of `supply_channels.status` — with a
scalar payload (channel id, from, to, actor type and id, reason, time). Nothing listens.

That is correct for the freeze. BR-AD-14 — a suspended channel takes no new orders while
orders in flight continue — is enforced by a synchronous read of `supply_channels.status`
through `ChannelDirectory`, and must stay one: a listener would guard one order where the
read guards all of them, and rule 5 says an event notifies, it does not control.
`docs/events.md` records that decision.

What nobody does today is **tell anyone**. The platform suspends a channel for a late
invoice, the channel's manager opens the dashboard, and the first sign is that orders
stopped arriving. The catalog already names the message: EP-AD-083A lists a platform
notification template with `event_key: channel.suspended`, title «تم تعطيل قناتك»,
delivered by push and in-app (DOC-12E TB-AD-093).

This ticket is the first — and so far the only — subscriber to `ChannelStatusChanged`.

## Requirements

1. A listener on `ChannelStatusChanged` in the Notification module. It reads the scalar
   payload and nothing else — no Tenancy model, no Tenancy enum (rule 4). Rule 5: it never
   writes back to the channel and its failure never affects the transition.
2. `to = suspended` sends the `channel.suspended` template to every user of that channel,
   carrying the `reason` the platform recorded. `suspended → active` sends the lifting.
   `provisioning → active` and `suspended → archived` are for BE4-NTF02's template table
   to decide; if no template exists for a transition, the listener does nothing and logs
   nothing — silence is the intended behaviour, not a failure.
3. Queued on `default`, per CLAUDE.md queues. It is never synchronous.
4. One notification per transition. The listener is idempotent on the event's `at` and
   channel id, so a redelivered event does not send twice.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Suspending a channel through EP-AD-054 leaves a `channel.suspended` notification for
      each of its users, carrying the recorded reason.
- [ ] A transition with no template sends nothing.
- [ ] The listener failing does not roll back or fail the transition; the channel is
      suspended and the event row exists.
- [ ] The same event delivered twice sends once.

## Working rules

- Touch only `app-modules/notification` and its tests.
- Do not modify another module. Use its `Contracts/` or an event.
- The listener is registered in `NotificationServiceProvider`, the way Fulfillment
  registers `CreatePickingListOnConfirm`.
