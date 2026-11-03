# Order flow gaps — WhatsApp cycle (2026-11-03) ✅

Achieve the approved field description: channel/warehouse approver, rep auto-assign,
warehouse assign, automatic SKU shortage FIFO + retailer accept_partial|cancel.

**وثيقة التشغيل الكاملة (كل الأطراف):** [`../DocsLast/order-flow.md`](../DocsLast/order-flow.md)

| EP | Path | Status |
|---|---|---|
| EP-SC-130B | `settings.order_approver` ∈ channel\|warehouse | ✅ |
| EP-WH-060 | `GET /warehouse/sub-orders` | ✅ |
| EP-WH-061 | `POST /warehouse/sub-orders/{id}/confirm` | ✅ |
| EP-WH-062 | `POST /warehouse/sub-orders/assign` | ✅ |
| EP-WH-063 | `POST /warehouse/stock-shortages` | ✅ |
| EP-RT-035 | `GET /app/retailer/orders/{id}/shortage` | ✅ |
| EP-RT-036 | `POST /app/retailer/orders/{id}/shortage-decision` | ✅ |

## Acceptance

1. With `order_approver=channel`, only channel confirm succeeds; warehouse confirm → 409.
2. With `order_approver=warehouse`, only warehouse confirm succeeds; channel still lists inbound.
3. Rep-sourced confirm auto-assigns the submitting rep (even off-duty).
4. Warehouse or channel can assign retailer-sourced orders to a zone-covering rep.
5. Warehouse enters total SKU `qty_available`; system allocates FIFO by `created_at`.
6. Retailer `accept_partial` keeps allocated qty; `cancel` cancels the sub-order and releases stock.
7. Picking complete is blocked with 409 `operation_in_progress` while shortage awaits retailer.

Tests: `tests/Feature/Ordering/OrderFlowGapsTest.php`
