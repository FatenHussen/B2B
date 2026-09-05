# Frontend docs (source of truth)

Open this folder — not `docs/sprints/frontend/`.

Every client talks to the Laravel API at `/api/v1`. Install the API before any app or dashboard.

```
docs/DocsLast/
  _shared/          ← all teams
  flutter/          ← mobile engineers
    retailer/       ← one app, one folder
    rep/
  web/              ← React / Next.js engineers
    channel/
    warehouse/
    platform/
```

Inside each product folder the files are **the same sprint names** as the backend:

| File | Backend | What the UI becomes |
|---|---|---|
| `L1.md` | SP-00 … SP-04 | Identity (sign-in, register, admin IAM/refs/channels) |
| `L2.md` | SP-06 … SP-08 | Catalog, prices, offers — **no cart** |
| `L3.md` | SP-09 … SP-12 | Stock, cart, orders, warehouse floor, delivery |
| `L4.md` | SP-13 … SP-18 | Money, sync, notifications, loyalty, dashboards |

Do not start `L2.md` until that product’s `L1.md` signs in. Do not start catalog screens before Layer 1 identity.

---

## 1. Shared (read once)

| File | Who |
|---|---|
| [_shared/00-install-the-api.md](./_shared/00-install-the-api.md) | Everyone — Composer, `.env`, seed, OTP log |
| [_shared/http-contract.md](./_shared/http-contract.md) | Everyone — envelope, headers, money, error map |
| [_shared/flutter-client.md](./_shared/flutter-client.md) | Retailer + rep — Dio package |
| [_shared/react-client.md](./_shared/react-client.md) | Channel + warehouse + platform — Next.js package |

---

## 2. Flutter apps

| App | Path | Guard | `X-Client` |
|---|---|---|---|
| Retailer | [flutter/retailer/](./flutter/retailer/) | `app` | `retailer-android` / `retailer-ios` |
| Field rep | [flutter/rep/](./flutter/rep/) | `app` | `rep-android` / `rep-ios` |

Separate binaries. Shared Dart = `packages/b2b_api` only.

---

## 3. Web dashboards (Next.js 15)

| Dashboard | Path | Guard | Dev port | `X-Client` |
|---|---|---|---|---|
| Channel | [web/channel/](./web/channel/) | `channel` | `3001` | `channel-web` |
| Warehouse | [web/warehouse/](./web/warehouse/) | `warehouse` | `3002` | `warehouse-web` |
| Platform admin | [web/platform/](./web/platform/) | `platform` | `3000` | `platform-web` |

Three Next apps. Shared TypeScript = `@b2b/api-client` only.

---

## 4. Backend (do not duplicate here)

| Layer | Spec | Live L1 shapes |
|---|---|---|
| L1 | [`../sprints/L1-foundation-backend-spec.md`](../sprints/L1-foundation-backend-spec.md) | [`../sprints/L1-ready-apis.md`](../sprints/L1-ready-apis.md) |
| L2 | [`../sprints/L2-catalog-pricing-offers-spec.md`](../sprints/L2-catalog-pricing-offers-spec.md) | `docs/api/catalog/04-channel-catalog.php`, `07-retailer.php`, `08-rep.php` |
| L3 | [`../sprints/L3-order-fulfillment-spec.md`](../sprints/L3-order-fulfillment-spec.md) | `05-channel-ops.php`, `06-warehouse.php`, `07`, `08` |
| L4 | [`../sprints/L4-finance-platform-spec.md`](../sprints/L4-finance-platform-spec.md) | remaining catalog files |

Binding API: `docs/api/catalog/*.php`.

---

**Envelope:** `{ data, meta.server_time }` / `{ error: { code, message, details? } }`  
**Auth:** `Authorization: Bearer {token}` — not Sanctum cookie SPA.  
**Money:** integer minor units. SYP display decimals = 0.
