# Layer 3 — field operations

**Sprints:** SP-09 to SP-13

**Contract status: proposed.** Fix the paths in the API catalog before starting any of these.

**32 tickets · 190 points**

| Ticket | Module | Sprint | Pts | Priority | Title |
|---|---|---|---|---|---|
| [BE3-INV01](./BE3-INV01.md) | Inventory | SP-09 | 8 | Highest | Warehouses and stock balances |
| [BE3-INV02](./BE3-INV02.md) | Inventory | SP-09 | 8 | Highest | Reservation and deduction lifecycle |
| [BE3-INV03](./BE3-INV03.md) | Inventory | SP-09 | 5 | Highest | Immutable movement ledger |
| [BE3-INV04](./BE3-INV04.md) | Inventory | SP-09 | 3 | Medium | Reorder thresholds and alerts |
| [BE3-INV05](./BE3-INV05.md) | Inventory | SP-09 | 5 | Medium | Lots, expiry and FEFO |
| [BE3-INV06](./BE3-INV06.md) | Inventory | SP-09 | 5 | Medium | Transfers and stocktakes |
| [BE3-FUL01](./BE3-FUL01.md) | Fulfillment | SP-10 | 8 | Highest | Picking lists |
| [BE3-FUL02](./BE3-FUL02.md) | Fulfillment | SP-10 | 5 | High | Shortage handling during picking |
| [BE3-FUL03](./BE3-FUL03.md) | Fulfillment | SP-10 | 5 | High | Packing and quality check |
| [BE3-FUL04](./BE3-FUL04.md) | Fulfillment | SP-10 | 8 | Highest | Handover with dual confirmation |
| [BE3-FUL05](./BE3-FUL05.md) | Fulfillment | SP-10 | 5 | High | Rep end-of-day return |
| [BE3-FUL06](./BE3-FUL06.md) | Fulfillment | SP-10 | 5 | Medium | Inbound receipt |
| [BE3-DLV01](./BE3-DLV01.md) | Ordering | SP-11 | 8 | Highest | Assignment engine |
| [BE3-DLV02](./BE3-DLV02.md) | Delivery | SP-11 | 5 | Highest | Rep acceptance queue |
| [BE3-DLV03](./BE3-DLV03.md) | Delivery | SP-11 | 8 | Highest | Delivery execution and line matching |
| [BE3-DLV04](./BE3-DLV04.md) | Delivery | SP-11 | 5 | High | Rep duty state and location |
| [BE3-DLV05](./BE3-DLV05.md) | Delivery | SP-11 | 5 | Medium | Scheduled orders |
| [BE3-DLV06](./BE3-DLV06.md) | Delivery | SP-11 | 5 | Medium | Rep customers and zones |
| [BE3-RET01](./BE3-RET01.md) | Returns | SP-12 | 5 | Highest | Return and exchange requests |
| [BE3-RET02](./BE3-RET02.md) | Returns | SP-12 | 8 | Highest | Decision effects |
| [BE3-RET03](./BE3-RET03.md) | Returns | SP-12 | 5 | High | Warehouse return sorting |
| [BE3-RET04](./BE3-RET04.md) | Returns | SP-12 | 3 | Low | Return reasons report |
| [BE3-SYN01](./BE3-SYN01.md) | Sync | SP-12 | 8 | Highest | Outbox intake endpoint |
| [BE3-SYN02](./BE3-SYN02.md) | Sync | SP-12 | 8 | Highest | Differential sync and cursors |
| [BE3-SYN03](./BE3-SYN03.md) | Sync | SP-12 | 5 | High | Conflict resolution |
| [BE3-SYN04](./BE3-SYN04.md) | Sync | SP-12 | 3 | Medium | Sync state and manual trigger |
| [BE3-FIN01](./BE3-FIN01.md) | Finance | SP-13 | 8 | Highest | Invoices |
| [BE3-FIN02](./BE3-FIN02.md) | Finance | SP-13 | 8 | Highest | Payments with a unique receipt |
| [BE3-FIN03](./BE3-FIN03.md) | Finance | SP-13 | 5 | High | Receivables and ageing |
| [BE3-FIN04](./BE3-FIN04.md) | Finance | SP-13 | 8 | Highest | Rep wallet |
| [BE3-FIN05](./BE3-FIN05.md) | Finance | SP-13 | 5 | High | Rep settlement |
| [BE3-FIN06](./BE3-FIN06.md) | Finance | SP-13 | 5 | Medium | Statements and financial reports |

## How to work this layer

1. Take tickets in sprint order. Within a sprint, follow `blocked_by`.
2. One ticket per session. Do not batch.
3. Run `/implement-ticket {ID}` and let it stop you if a dependency is missing.
