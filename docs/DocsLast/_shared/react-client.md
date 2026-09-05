# React API kit (Next.js 15)

| | |
|---|---|
| Package | `packages/b2b-api-client` (`@b2b/api-client`) |
| Contract | [http-contract.md](./http-contract.md) |
| Install API | [00-install-the-api.md](./00-install-the-api.md) |

No dashboard ships its own `fetch` wrapper or ad-hoc `Authorization` header.

---

## Stack (locked)

| Concern | Choice |
|---|---|
| Framework | Next.js 15 App Router |
| UI | React 19 |
| Language | TypeScript 5 `strict: true` — no `any` in `lib/` |
| CSS | Tailwind — `<html lang="ar" dir="rtl">` |
| Server state | TanStack Query v5 |
| Token | `sessionStorage` Bearer — **not** Sanctum cookies, not `localStorage` |
| HTTP | `fetch` **inside this package only** |

No Redux, no Axios, no NextAuth. Authenticated calls are Client Components (or a client hook). `middleware.ts` cannot read `sessionStorage` — do not fake auth there.

Logical CSS (`ms-*`, `ps-*`, `text-start`). Phone/OTP/PIN: `dir="ltr"` `inputMode="numeric"`.

---

## Scaffold

```bash
mkdir packages/b2b-api-client
```

```json
{
  "name": "@b2b/api-client",
  "private": true,
  "type": "module",
  "exports": { ".": "./src/index.ts" }
}
```

```bash
npx create-next-app@15 apps/channel-web --typescript --app --tailwind --eslint --src-dir --use-npm
```

```
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_X_CLIENT=channel-web
NEXT_PUBLIC_APP_VERSION=1.0.0
```

Ports: platform `3000`, channel `3001`, warehouse `3002`.

---

## Package layout

```
packages/b2b-api-client/src/
  index.ts
  client.ts
  envelope.ts
  api-error.ts
  idempotency.ts
  storage.ts
  resources/          # grow per layer; apps import only what they may call
```

Channel web must not import `platform-*`. Platform must not import `channel-catalog`.

### Resources by layer

| Layer | Channel | Warehouse | Platform |
|---|---|---|---|
| L1 | `channel-auth` | `warehouse-auth` | `platform-auth`, `platform-me`, `iam`, `refs`, `channels` |
| L2 | `channel-catalog`, `pricing`, `offers` | — | — |
| L3 | `inventory`, `sub-orders`, `returns` | `queues`, `picking`, `handover`, `receiving`, `stocktake` | — |
| L4 | `finance`, `notify`, `content`, `dashboard` | — | `dashboard`, `notify`, `billing`, `support` |

---

## Client

```ts
createClient({
  baseUrl, xClient, appVersion,
  locale?: 'ar' | 'en',
  getToken, getChannelId?, getDeviceId?,
})
```

`client.request<T>(…)` returns inner `data`, throws `ApiError`. Mutations take **required** `idempotencyKey: string`.

```ts
export function createWriteIntent(): string {
  return crypto.randomUUID();
}
```

| Key | Store | Who |
|---|---|---|
| `b2b.token` | `sessionStorage` | All |
| `b2b.permissions` | `sessionStorage` | All |
| `b2b.channelId` | `sessionStorage` | Channel |
| `b2b.deviceId` | `localStorage` | Warehouse (survives sign-out) |

`can(code)` reads `permissions[]`. Query keys **include** `channelId` so a header switch does not leak rows.

TanStack: mutations `retry: false`. On `unauthenticated` / `token_revoked`, clear session once.

`403 requires_password_confirm` (platform): open modal, replay the **same** body and idempotency key.

## App shell

```
src/app/layout.tsx          # lang=ar dir=rtl, QueryClientProvider
src/app/(auth)/             # no chrome
src/app/(app)/              # AuthGate + sidebar
src/lib/api.ts              # createClient bound to this X-Client
src/features/
```

## Kit done

1. `health()` → `ok`.
2. Mutations require `idempotencyKey` in TypeScript.
3. Logout removes `b2b.token`.
4. No raw `fetch(` for the API outside this package.
