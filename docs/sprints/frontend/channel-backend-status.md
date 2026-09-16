# Channel backend status — frontend handoff

Updated **2026-09-15**. Source of paths: [`docs/DocsLast/apps/channel-web.md`](../../DocsLast/apps/channel-web.md).
Do not invent routes. This repo is API-only.

## Live on `auth:channel`

| Area | Paths | Notes |
|---|---|---|
| Auth / settings / catalog / pricing / offers | existing L1–L2 | Unchanged. |
| Inbox | `GET /channel/sub-orders` | Paginated. Never send `sort`. |
| Inventory lists | `GET /channel/inventory/levels`, `/movements` | 200. |
| Inventory adjust | `POST /channel/inventory/adjust` | Catalog `dual: true` is **enforced**. First POST returns `{approval_request_id}` and `meta.requires_dual_approval`. A **different** user resubmits the same body with `approval_request_id` + `approval_reason`. Self-approval → `403 sod_violation`. |
| Offers performance | `GET /channel/offers/{id}/performance` | `applied_count` from redemptions. `linked_sales`, `discount_given`, `retailers_count`, `by_zone` from order lines. `net_margin` and `conversion_rate` stay **0** (no cost / no views). `conversion_rate` is an integer at scale 10^4 when it is ever non-zero. |
| Finance | `EP-SC-080`…`086` | List invoices `{id, no, total, status}`. Statuses: `open` / `void` / `credited`. Credit-note and void are dual, same as adjust. Payments FIFO. SOD-01: `sc.orders.confirm` + `sc.finance.payment` → `403 sod_violation` except `channel_manager`. Aging buckets are integers. Settle returns `receipt_pdf_url` + `new_balance`. Credit `on_exceed`: warn\|block\|manual_approval. Foreign id → 404. |
| Notify | `EP-SC-090` / `091A–B` / `092` | `sc.notify.view` is seeded **with** the log route. |
| Content | `EP-SC-100`…`103` | Banner stats are the stored ledger (0 until a serve path writes). `ctr` is integer scale 10^4, never a fabricated 0.07. |
| Loyalty | `EP-SC-110`…`111` | Rules JSON + rewards. |
| Dashboard / reports | `EP-SC-120`…`123` | Read from `daily_snapshots` (ADR-07). `fill_rate` is integer scale 10^4. No live JOIN on HTTP. `GET /channel/reports/margins` is registered **before** `{type}`. |

## Stopped — not in the catalog

There is **no** `GET /channel/retailers`, no retailer approval/360, no `GET /channel/reps`, no rep CRUD.
Catalogued people writes only:

- `PUT /channel/reps/{id}/discount-cap` (already live)
- `POST /channel/reps/{id}/settle` (`EP-SC-084`, live)
- `PUT /channel/retailers/{id}/credit` (`EP-SC-086`, live)

Do not mock list screens.

## Idempotency (for FE-CORE-03)

The **code** is the governor: `EnsureIdempotency` exempts OTP/login paths. `request-otp` is **not** idempotent. Keep the key on writes; do not assert “retry request-otp with the same key does not double-send”.
