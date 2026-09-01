# Layer 1 frontend

Start here. Do not open a product brief until the API responds to `/health`.

1. **[Install the API](./00-install-the-api.md)** — Composer, `.env`, migrate, serve, OTP log, seed users  
2. **[Shared HTTP client](./00-shared-api-client.md)** — envelope, headers, idempotency  
3. Then only the product you own:

| # | Product | Surface | File |
|---|---|---|---|
| 1 | Retailer | Flutter Android / iOS | [01-retailer-app.md](./01-retailer-app.md) |
| 2 | Field rep | Flutter Android / iOS | [02-rep-app.md](./02-rep-app.md) |
| 3 | Channel | Next.js web | [03-channel-dashboard.md](./03-channel-dashboard.md) |
| 4 | Warehouse | Next.js web | [04-warehouse-web.md](./04-warehouse-web.md) |
| 5 | Platform admin | Next.js web | [05-platform-admin.md](./05-platform-admin.md) |

**API:** `http://127.0.0.1:8000/api/v1` + catalog path.  
**Envelope:** `{ data, meta.server_time }` / `{ error: { code, message, details? } }`.  
**Backend spec:** [`../L1-foundation-backend-spec.md`](../L1-foundation-backend-spec.md).

L1 builds identity and platform admin only. No catalog, cart, orders, or KPI boards.
