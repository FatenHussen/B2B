# Field-rep app (Flutter)

Separate binary from retailer. Shared code = `packages/b2b_api` only. Same phone cannot be both `kind`s — no in-app switcher.

| | |
|---|---|
| Path | `apps/rep` |
| Package | `sy.b2b.rep` |
| Guard | `app` (`kind=rep`) |
| `X-Client` | `rep-android` / `rep-ios` |
| Invite | `b2b-rep://register?channel_id=` |
| Kit | [`../../_shared/flutter-client.md`](../../_shared/flutter-client.md) |

| Sprint | File | Ships |
|---|---|---|
| L1 | [L1.md](./L1.md) | Invite → OTP → register → waiting |
| L2 | [L2.md](./L2.md) | Product book (with channel name), customers, extra-zone request |
| L3 | [L3.md](./L3.md) | Duty, assignments, cart per shop, warehouse handover, delivery |
| L4 | [L4.md](./L4.md) | Collect, wallet, receivables, sync, inbox |
