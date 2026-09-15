# Channel backend status — frontend handoff

Updated **2026-09-14**. Source of paths: [`docs/DocsLast/apps/channel-web.md`](../../DocsLast/apps/channel-web.md).
Do not invent routes. This repo is API-only.

## Build now (L1 + L2 console)

| Excel | Backend |
|---|---|
| L1 `CH-01`…`CH-10` | OTP + `permissions[]` live. `X-Channel-Id` is **ignored** for a channel user — tenant comes from membership. Document that on `CH-04`; do not wait for a switcher. |
| L2 catalog / pricing / offers write-path | Live. |
| L2 `BE2-ORD05` inbox | **`GET /channel/sub-orders` is 200.** Paginated `{id, sub_order_no, status, zone_id, total}`. Never send `sort`. |
| Inventory lists | **`GET /channel/inventory/levels` and `/movements` are 200.** |

## Do not chart / do not wait for dual

| Path | Truth |
|---|---|
| `GET /channel/offers/{id}/performance` | `applied_count` is real. Money figures are **0 until BE2-PRM05** (needs an Ordering contract). |
| `POST /channel/inventory/adjust` | Catalog `dual: true`. **Executes on the first request.** BE-C05 has no Core hook for this guard. |

## Not on this guard (catalogued, no route)

Finance `EP-SC-080`…`086`, notify `090`…`092`, content `100`…`103`, loyalty `110`…`111`, dashboard/reports `120`…`123`.
Retailer/rep **lists** are not in the catalog — do not mock them.

## Idempotency (for FE-CORE-03)

The **code** is the governor: `EnsureIdempotency` exempts OTP/login paths. `request-otp` is **not** idempotent. Keep the key on writes; do not assert “retry request-otp with the same key does not double-send”.
