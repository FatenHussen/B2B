# Warehouse web (Next.js)

Floor console. Port **3002**. Large hit targets (gloves). RTL.

| | |
|---|---|
| Path | `apps/warehouse-web` |
| Guard | `warehouse` |
| `X-Client` | `warehouse-web` |
| Device | `X-Device-Id` required |
| Kit | [`../../_shared/react-client.md`](../../_shared/react-client.md) |

| Sprint | File | Ships |
|---|---|---|
| L1 | [L1.md](./L1.md) | PIN lock |
| L2 | [L2.md](./L2.md) | **No screens** — stay on PIN |
| L3 | [L3.md](./L3.md) | Queues, pick, pack, handover, receiving, stocktake, return sort |
| L4 | [L4.md](./L4.md) | **No screens** — no finance on the floor |

There is no “register device” UI. Ops runs `warehouse:register-device`.
