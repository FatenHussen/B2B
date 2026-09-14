# Inter-module events

| Event | Emitted by | Listened to by | Effect | Layer |
|---|---|---|---|---|
| `ChannelStatusChanged` | Tenancy — **dispatched** since BE-T01, from `ChannelLifecycle::transition()` only, which is the sole writer of `supply_channels.status`; payload is scalars (channel id, from, to, actor type and id, reason, time) | **No listener, by decision — not by omission.** The freeze BR-AD-14 describes is done by a **synchronous read** of `supply_channels.status` through `ChannelDirectory::activeIdsCoveringZone()`, which drops a suspended channel from the retailer's shopping context the moment the row changes. It is deliberately *not* a listener: a listener would act on one order where the read guards every order, always, and rule 5 says an event notifies, it does not control. BE-T13 (EP-AD-054) added no listener for that reason. Do not go looking for a missing one. The first real subscriber is Notification's `channel.suspended` template (EP-AD-083A, SP-14) — **BE4-NTF04** | Announces a transition; the freeze itself is a synchronous read (BR-AD-14) | L1 |
| `ReferenceDisabled` | Reference | Catalog, Tenancy, Ordering | Blocks new use without touching existing records | L1 |
| `OrderSubmitted` | Ordering | Inventory, Notification, Reporting | No reservation yet — channel notification only | Layer 2 |
| `SubOrderConfirmed` | Ordering | Inventory, Fulfillment, Notification | Reserve stock and create the picking list (BR-05) | Layer 2 |
| `HandoverCompleted` | Fulfillment | Inventory, Ordering, Notification | Actual stock deduction and the on_the_way state | Layer 3 |
| `DeliveryCompleted` | Delivery | Finance, Loyalty, Notification | Generate the invoice, create the receivable, award points | Layer 3 |
| `PaymentRecorded` | Finance | Finance/Wallet, Notification, Reporting | Increase the rep wallet and reduce the retailer receivable | Layer 3 |
| `ReturnApproved` | Returns | Finance, Inventory | Credit note plus stock return according to condition | Layer 3 |
| `PriceListChanged` | Pricing | Sync, Reporting | Marks data for differential sync; never touches a live order | Layer 2 |
