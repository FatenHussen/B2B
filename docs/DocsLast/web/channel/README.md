# Channel dashboard (Next.js)

Operations console for a distributing company. Port **3001**.

| | |
|---|---|
| Path | `apps/channel-web` |
| Guard | `channel` + `X-Channel-Id` |
| `X-Client` | `channel-web` |
| Kit | [`../../_shared/react-client.md`](../../_shared/react-client.md) |
| Seed | `+963900000001` — OTP in API log |

| Sprint | File | Ships |
|---|---|---|
| L1 | [L1.md](./L1.md) | OTP, channel picker, empty office |
| L2 | [L2.md](./L2.md) | Brands, categories, products, prices, offers — **source of SKUs for Flutter** |
| L3 | [L3.md](./L3.md) | Inventory numbers, sub-orders machine, returns decision |
| L4 | [L4.md](./L4.md) | Invoices, credit, notify, content/loyalty, snapshot dashboard |

Do not call `/app/*` or `/platform/*`.
