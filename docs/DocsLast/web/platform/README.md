# Platform admin (Next.js)

Super-admin console. Port **3000**. Largest L1 surface.

| | |
|---|---|
| Path | `apps/platform-web` |
| Guard | `platform` |
| `X-Client` | `platform-web` |
| Kit | [`../../_shared/react-client.md`](../../_shared/react-client.md) |
| API reference | [`api-reference.md`](./api-reference.md) — all 41 live admin endpoints |
| Seed | `admin@platform.sy` / `password` |

| Sprint | File | Ships |
|---|---|---|
| L1 | [L1.md](./L1.md) | Login, 2FA, IAM, refs, channels |
| L2 | [L2.md](./L2.md) | **No catalog** — keep L1; root categories must exist for channel-web |
| L3 | [L3.md](./L3.md) | **No fulfillment screens** — orders live on channel-web |
| L4 | [L4.md](./L4.md) | Snapshot dashboard, broadcasts, billing, support, team, flags |

Do not call `/channel/*` as a catalog operator.
