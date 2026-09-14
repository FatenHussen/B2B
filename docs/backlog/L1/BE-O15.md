---
id: BE-O15
title: SubOrder status: guard it, and route every write through the lifecycle
layer: L1
side: Backend
module: Ordering
epic: EP-BE-O
sprint: SP-04
points: 3
priority: High
contract: internal
endpoints: []
tables: [sub_orders, sub_order_events]
events: []
blocked_by: []
---

# BE-O15 — SubOrder status: guard it, and route every write through the lifecycle

## Background

Rule 8: `status` is `$guarded` and changes only through the lifecycle service; every
transition is logged with actor, reason and time.

`SubOrder` breaks it three ways, found while building `ChannelStateMachine` (BE-T01) and
deciding what of `SubOrderStateMachine` to copy. None of it was copied.

1. **`status` is in `$fillable`** ([SubOrder.php](../../../app-modules/ordering/src/Domain/Models/SubOrder.php)).
   `SubOrder::create([... 'status' => ...])` and `->update(['status' => ...])` both work.
2. **The machine only checks; nine places write.** `SubOrderStateMachine::assert()` says
   whether an action is allowed, and then each of `ConfirmSubOrder`, `RejectSubOrder`,
   `CancelSubOrder`, `AssignSubOrders`, `AcceptAssignment`, `RejectAssignment`,
   `ScheduleSubOrder` and `EloquentSubOrderLifecycle::transition()` does
   `$sub->status = X; $sub->save();` and writes its own `SubOrderEvent` for itself.
   `EloquentSubOrderLifecycle` exists as the lifecycle for *other* modules through the
   `SubOrderLifecycle` contract; Ordering's own actions walk past it.
3. **`sub_order_events` has no `reason`.** The column list is `stage, at, actor_type,
   actor_id`. The reason the request validates (`CancelOrderRequest`,
   `RejectSubOrderRequest`, `RejectAssignmentRequest`, `EditSubOrderLinesRequest` all
   require one) goes into a domain event or into `sub_order_assignments.reason`, never
   onto the transition record. A cancelled order has no recorded reason for having been
   cancelled.

This is not a defect in what orders do today — every transition is still checked before
it is written. It is a defect in what can be *proved*: nothing stops the tenth writer, and
the trail cannot answer "why".

## Requirements

1. `status` leaves `$fillable`; the model names it in `$guarded`.
2. One lifecycle service writes the status, the event row and the reason, in one
   transaction — `ChannelLifecycle` in Tenancy is the shape. The eight actions call it
   instead of assigning.
3. `sub_order_events` gains a nullable `reason` column (backward compatible); the
   lifecycle writes it, and the four requests that validate a reason pass it through.
4. An architecture test names the lifecycle as the only file in Ordering that assigns a
   status, as `tests/Architecture/ChannelStatusWriterTest.php` does for Tenancy.
5. `SubOrderStateMachine::allowed()` stops carrying a permission map and an exclusion
   list; permission belongs to the route.

## Acceptance criteria

- [ ] `SubOrder::create()` / `update()` with `status` leave the status unchanged.
- [ ] Every existing transition test still passes, with no action assigning `status`.
- [ ] A cancel, reject, reject-assignment and edit-lines each leave a `sub_order_events`
      row whose `reason` is the one the request carried.
- [ ] The architecture test fails when a second file assigns a status.

## Working rules

- Touch only `app-modules/ordering` and its tests.
- Do not modify another module. Use its `Contracts/` or an event.
- Every migration this ticket adds must be backward compatible.
- The `SubOrderLifecycle` contract's shape is a published surface for Fulfillment and
  Delivery: change its implementation, not its signature.
