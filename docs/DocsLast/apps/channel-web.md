# Channel dashboard (React)

**This is the only file you need.** Backend setup, the React package, the HTTP contract and
every endpoint — all of it is here. No other document is required to build this dashboard.

| | |
|---|---|
| Path | `apps/channel-web` |
| Port | **3001** |
| Guard | `channel` (Sanctum) |
| `X-Client` | `channel-web` *(sent for logs; the server ignores it)* |
| Seed account | phone **`+963900000001`**, OTP from the log — channel `demo-channel` |

Verified against `php artisan route:list`, the controller/action source and a real-token
probe of every `GET` on this guard on **2026-09-14**. Where the API catalog disagrees with
this page, the code wins.

**47 channel endpoints are live**, plus **three shared reference reads** (governorates, zones,
currencies) this dashboard needs for its pickers — §4.0. Catalog, pricing, offers, orders
and returns work end to end. Inbox and inventory lists were 500 until 2026-09-14; they
return the envelope now.

### What changed since 2026-09-07

| | Where |
|---|---|
| **`GET /channel/sub-orders`, `GET /channel/inventory/levels` and `GET /channel/inventory/movements` are live** — the `allowedFilters([...])` TypeError is fixed. Build the orders queue and the two inventory lists | §4.5, §4.6 |
| **A staging API exists**: `https://api.sentraxsy.com`, seed account present, OTP bypass on, CORS already allows `localhost:3001`. You do not have to run PHP locally | §1.0 |
| `Accept-Language` is now **ignored** — every message comes back in English | §5.2 |
| `sort` on a list does **not** throw; it is silently ignored *and* cancels the default order | §5.7 |
| `GET /channel` gained `allowed_next` — meaningless on this guard, ignore it | §4.1 |
| `GET /governorates`, `GET /zones`, `GET /currencies` are readable with a channel token | §4.0 |
| `Idempotent-Replayed` cannot be read from a browser — CORS exposes no headers | §5.3 |
| `verify-otp` / `request-otp` error codes are listed: `otp_invalid`, `otp_expired`, `rate_limited` | §3 |
| Seed manager's `permissions` list is **47** codes; the routes gate on 30 of them | §2.8 |

### Contents

| § | |
|---|---|
| 1 | Running the backend |
| 2 | The React package — working code |
| 3 | A token in two minutes |
| 4 | Endpoint reference |
| 5 | The HTTP contract — envelope, errors, money |
| 6 | What to build, in order |
| 7 | Not built — do not mock |
| 8 | Gotchas |

---

## 1. Running the backend

You need an API to talk to. There are two: the staging server, which needs nothing
installed, and a local copy.

### 1.0 Staging — start here

| | |
|---|---|
| Base URL | **`https://api.sentraxsy.com/api/v1`** |
| Health | `GET /health` → `{"data":{"status":"ok","env":"staging",…}}` |
| Seed account | `+963900000001`, channel `demo-channel` — same as local |
| OTP | **bypass is on** — send `000000` as the code, nothing is sent anywhere |
| CORS | `http://localhost:3000–3002` and `http://127.0.0.1:3000–3002` are allowed, credentials on |

```dotenv
VITE_API_BASE_URL=https://api.sentraxsy.com/api/v1
```

Verified 2026-09-14 by a preflight from `Origin: http://localhost:3001`.

Three things to know about it:

⚠️ **It lags the repository.** Deploys are by hand; on 2026-09-14 the server was several
commits behind. Everything on this page is on the server *except* where a section says
otherwise, but if a call disagrees with this page, check `git log` before filing a bug.
⚠️ **Nothing serves the `exports` queue.** Catalog export (`GET /channel/catalog/export`)
queues a job that no worker picks up, so it never completes there. Import and price-list
scheduling go to `default`, which is served.
⚠️ **It is shared.** Every developer logs into the same demo channel; the data you see is
whatever the last person left. Do not build a test on the assumption that the catalog is
empty.

If staging is enough for you, skip to §2.

### 1.1 Requirements — local copy

| | Version | Note |
|---|---|---|
| PHP | 8.3+ | with `pdo_mysql`, `redis`, `bcmath`, `intl` |
| MySQL | 8.0 | **port 3308**, not 3306 |
| Redis | 7 | queues and Horizon |
| Composer | 2.x | |
| Node | 20+ | for this dashboard |

**Docker is not the supported path.** `docker-compose.yml` exists but the decided local
setup is native PHP + MySQL + Redis. If you use containers anyway, publish MySQL on 3308.

### 1.2 Install

```bash
git clone <repo> b2b-api && cd b2b-api
composer install
cp .env.example .env
php artisan key:generate
```

```sql
CREATE DATABASE b2b_platform      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE b2b_platform_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
curl -s http://127.0.0.1:8000/api/v1/health
# {"data":{"status":"ok",...},"meta":{"server_time":"...+03:00"}}
```

⚠️ **Port 3308 is deliberate** — it keeps this project clear of any MySQL already on 3306.
If yours listens on 3306, set `DB_PORT=3306` in your own `.env`; never edit `.env.example`.

⚠️ **Never run `php artisan migrate --env=testing`, and never `migrate:fresh` before a test
run.** Pest migrates `b2b_platform_test` itself; passing `--env=testing` by hand has been
observed to rebuild the *development* database and destroy your seed data.

### 1.3 Your account

The seeder creates a channel manager bound to the demo channel:

| | |
|---|---|
| Phone | **`+963900000001`** |
| Channel | `demo-channel` (id `1`) |
| Role | `channel_manager` |

Login is passwordless — an OTP is sent to that phone.

**During development OTP is switched off**: `OTP_BYPASS=true` in the API `.env`. The flow
does not change — `request-otp`, take the `otp_id`, `verify-otp` — but any 6-character
`code` (send `000000`) verifies, no code is sent, and the cooldown and per-phone rate limit
are skipped. Build the real OTP screen anyway: the switch goes back to `false` before
release. It is ignored when `APP_ENV=production`.

With `OTP_BYPASS=false`, locally no WhatsApp is sent: the code is written to the log.

```bash
tail -f storage/logs/laravel.log | grep OTP
# [OTP] channel_login via whatsapp for +963900000001: 481923
```

| Setting | Default |
|---|---|
| `otp.ttl` | **300 s** |
| `otp.length` | **6** |
| `otp.max_attempts` | **5** |
| `otp.resend_cooldown` | **60 s** |

Rate limits per hour: **3 per phone**, 10 per device, 30 per IP.

⚠️ There is **no channel resend endpoint** — if the code expires, request a new one.

### 1.4 CORS and the dev origin

`config/cors.php` sets `supports_credentials: true` and reads allowed origins from
`CORS_ALLOWED_ORIGINS` (default `http://localhost:3000`). A credentialed request needs an
explicit origin — never `*`. Add your dev port:

```dotenv
CORS_ALLOWED_ORIGINS=http://localhost:3001,http://127.0.0.1:3001
```

⚠️ `localhost` and `127.0.0.1` are **different origins** to a browser. Vite prints
whichever it bound to — list both, or the preflight fails with a CORS error that looks
like an API outage.

### 1.5 Queues

`php artisan horizon` supervises the queues locally.

Three things on this dashboard are asynchronous and need a worker: **catalog import**
(queue `default`), **catalog export** (queue **`exports`**) and a **scheduled price list**
(`default`). Each returns a `job_id` (or nothing at all) and there is **no endpoint to poll
it** — so without a worker they queue silently and never complete. On staging only
`default` is served (§1.0).

---

## 2. The React package

One shared TypeScript package, `packages/b2b-api-client` (`@b2b/api-client`), used by all
three dashboards. **No dashboard ships its own `fetch` wrapper or an ad-hoc
`Authorization` header.**

This section is working code, not description.

### 2.1 Stack (locked)

| Concern | Choice |
|---|---|
| Build | **Vite 6** |
| UI | **React 19 — plain SPA, no Next.js, no SSR** |
| Routing | React Router v7 (`createBrowserRouter`) |
| Language | TypeScript 5, `strict: true` — no `any` in `lib/` |
| CSS | Tailwind, `<html lang="ar" dir="rtl">` |
| Server state | TanStack Query v5 |
| Token | `sessionStorage` Bearer |
| HTTP | `fetch`, **inside this package only** |

No Redux, no Axios, no NextAuth. **Every dashboard is a client-rendered SPA**: the whole
app sits behind a login, so there is nothing to server-render and the token lives only in
the browser.

Use logical CSS properties (`ms-*`, `ps-*`, `text-start`) so RTL works. Phone, OTP and PIN
inputs get `dir="ltr"` and `inputMode="numeric"`.

```bash
npm create vite@latest apps/channel-web -- --template react-ts
cd apps/channel-web && npm i react-router-dom @tanstack/react-query
npm i -D tailwindcss @tailwindcss/vite
```

```ts
// vite.config.ts
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: { port: 3001 },          // this dashboard's dev port
});
```

```dotenv
# .env — Vite only exposes variables prefixed VITE_
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_X_CLIENT=channel-web
```

⚠️ **The prefix is `VITE_`, not `NEXT_PUBLIC_`**, and it is read as
`import.meta.env.VITE_*` — `process.env` does not exist in the browser bundle.

Dev port for this dashboard: **3001**.

### 2.1.1 Guarding routes

There is no `middleware.ts` and no server layer. Gate routes in the router itself:

```tsx
// routes.tsx
import { createBrowserRouter, Navigate, Outlet } from 'react-router-dom';
import { tokenStore } from '@b2b/api-client';

function RequireAuth() {
  return tokenStore.get('channel') ? <Outlet /> : <Navigate to="/login" replace />;
}

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { element: <RequireAuth />, children: [
      { path: '/', element: <SubOrdersPage /> },
      { path: '/products', element: <ProductsPage /> },
  ]},
]);
```

📌 This is a **convenience redirect, not security** — the token check happens on the
server on every request. Its job is to avoid rendering a page that will only 401.

```
packages/b2b-api-client/src/
  index.ts
  envelope.ts      types for data/meta/error
  api-error.ts     ApiError + the code union
  storage.ts       token, per guard
  idempotency.ts   key store bound to a user intent
  client.ts        the single fetch
  can.ts           permission helper
  resources/       one file per surface
```

### 2.2 `envelope.ts`

```ts
export interface Meta {
  server_time: string;
  page?: number; per_page?: number; total?: number; last_page?: number;
  next_cursor?: string | null; prev_cursor?: string | null; has_more?: boolean;
}

export interface Ok<T>  { data: T; meta: Meta }
export interface Fail {
  error: { code: string; message: string; permission?: string; details?: Record<string, string[]> };
}

export type Envelope<T> = Ok<T> | Fail;
export const isFail = <T>(e: Envelope<T>): e is Fail =>
  typeof e === 'object' && e !== null && 'error' in e;
```

### 2.3 `api-error.ts`

```ts
export type ErrorCode =
  | 'idempotency_key_required'
  | 'unauthenticated' | 'token_revoked'
  | 'wrong_guard' | 'insufficient_permission'
  | 'requires_2fa' | 'requires_password_confirm' | 'sod_violation'
  | 'not_found'
  | 'illegal_transition' | 'operation_in_progress'
  | 'stale_version' | 'idempotency_key_conflict'
  | 'validation_failed' | 'ref_in_use'
  | 'plan_limit_exceeded' | 'upgrade_required'
  | 'rate_limited' | 'maintenance_mode'
  | 'otp_invalid';

export class ApiError extends Error {
  constructor(
    readonly code: ErrorCode | string,
    message: string,
    readonly status: number,
    readonly permission?: string,
    readonly details?: Record<string, string[]>,
  ) { super(message); this.name = 'ApiError'; }

  /** The token is worthless. Drop it and re-authenticate. */
  get isAuthLoss() { return this.code === 'unauthenticated' || this.code === 'token_revoked'; }

  /** A live token from the WRONG guard. Re-login will NOT help — it is a routing bug. */
  get isWrongGuard() { return this.code === 'wrong_guard'; }

  /** Retrying the identical request may succeed. */
  get isRetryable() { return this.code === 'operation_in_progress'; }

  fieldErrors(): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(this.details ?? {})) if (v?.[0]) out[k] = v[0];
    return out;
  }
}
```

### 2.4 `storage.ts` — key the token by guard

One browser profile may hold two dashboards open at once. Sharing a storage key is how you
manufacture a `wrong_guard`.

```ts
export type Guard = 'platform' | 'channel' | 'warehouse';

const key = (g: Guard) => `b2b.${g}.token`;

export const tokenStore = {
  get:   (g: Guard) => (typeof window === 'undefined' ? null : sessionStorage.getItem(key(g))),
  set:   (g: Guard, t: string) => sessionStorage.setItem(key(g), t),
  clear: (g: Guard) => sessionStorage.removeItem(key(g)),
};
```

This dashboard uses `tokenStore.get('channel')`.

### 2.5 `idempotency.ts` — a key per intent, not per request

Every write needs `X-Idempotency-Key`. The key belongs to a **user intent** — one press of
Confirm — not to an HTTP attempt.

```ts
const keys = new Map<string, string>();

/** Stable key for one intent. Same intentId in, same key out. */
export function keyFor(intentId: string): string {
  let k = keys.get(intentId);
  if (!k) { k = crypto.randomUUID(); keys.set(intentId, k); }
  return k;
}

/** Call ONLY after the intent finally succeeded, or the user abandoned it. */
export function releaseKey(intentId: string): void { keys.delete(intentId); }
```

Bind it to the button, not to the request:

```tsx
import { useRef, useState } from 'react';
import { keyFor, releaseKey, ApiError } from '@b2b/api-client';

export function ConfirmButton({ id }: { id: number }) {
  const intentId = useRef(`suborder.confirm.${id}.${Date.now()}`).current;
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function onClick() {
    setBusy(true); setErr(null);
    try {
      // Press as often as the user likes: same intentId -> same key -> replay, never a double write.
      await doTheWrite(id, keyFor(intentId));
      releaseKey(intentId);
    } catch (e) {
      if (e instanceof ApiError && e.isRetryable) setErr('Still processing — press again.');
      else if (e instanceof ApiError) setErr(e.message);
      // Key deliberately NOT released: pressing again must reuse it.
    } finally { setBusy(false); }
  }

  return (<><button onClick={onClick} disabled={busy}>Confirm</button>
           {err && <p role="alert">{err}</p>}</>);
}
```

⚠️ Never generate the key inside `client.ts`. A key minted per request turns one confirm
into N writes when the network is bad — the exact failure idempotency exists to prevent.

### 2.6 `client.ts`

```ts
import { Envelope, isFail, Meta } from './envelope';
import { ApiError } from './api-error';
import { Guard, tokenStore } from './storage';

const BASE = import.meta.env.VITE_API_BASE_URL as string;
const WRITE = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

export interface Options {
  method?: string;
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined>;
  /** Required on writes. Get it from keyFor(intentId) — never crypto.randomUUID() here. */
  idempotencyKey?: string;
  /** Set only for the login calls, which must not carry a key. */
  noIdempotency?: boolean;
  signal?: AbortSignal;
}

export function makeClient(guard: Guard) {
  return async function request<T>(path: string, opts: Options = {}): Promise<{ data: T; meta: Meta }> {
    const method = opts.method ?? 'GET';
    const url = new URL(BASE + path);

    for (const [k, v] of Object.entries(opts.query ?? {})) {
      if (v !== undefined) url.searchParams.set(k, String(v));
    }

    const headers: Record<string, string> = {
      Accept: 'application/json',
      // Ignored by the server today — every message comes back in English (§5.2).
      // Keep sending it: the fallback is a temporary switch, not the contract.
      'Accept-Language': 'ar',
    };

    const token = tokenStore.get(guard);
    if (token) headers.Authorization = `Bearer ${token}`;
    if (opts.body !== undefined) headers['Content-Type'] = 'application/json';

    if (WRITE.has(method) && !opts.noIdempotency) {
      if (!opts.idempotencyKey) {
        // Fail loudly here rather than let the server answer 400.
        throw new Error(`${method} ${path} needs an idempotencyKey (see idempotency.ts)`);
      }
      headers['X-Idempotency-Key'] = opts.idempotencyKey;
    }

    const res = await fetch(url, {
      method, headers, signal: opts.signal,
      body: opts.body === undefined ? undefined : JSON.stringify(opts.body),
    });

    if (res.status === 204) return { data: undefined as T, meta: { server_time: '' } };

    // A 500 is NOT in the envelope: it is Laravel's own `{"message":"Server Error"}`
    // (§5.4). `res.json()` still parses it; `isFail` is false; we fall to `unknown`.
    const json = (await res.json()) as Envelope<T>;

    if (!res.ok || isFail(json)) {
      const e = isFail(json)
        ? json.error
        : { code: 'unknown', message: res.statusText, permission: undefined, details: undefined };
      throw new ApiError(e.code, e.message, res.status, e.permission, e.details);
    }
    return json;
  };
}
```

**Why `idempotencyKey` is required rather than defaulted:** a default would be silently
wrong on every retry. A thrown error in development is cheaper than duplicate writes in
production.

### 2.7 Reacting to errors, once

```tsx
import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '@b2b/api-client';

const qc = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (count, err) =>
        err instanceof ApiError
          ? !err.isAuthLoss && !err.isWrongGuard && err.status !== 403 && err.status !== 404 && count < 2
          : count < 2,
    },
    mutations: { retry: false },      // retries are the user's decision — same key, their press
  },
});
```

```ts
import type { NavigateFunction } from 'react-router-dom';   // from useNavigate()

function onApiError(e: ApiError, navigate: NavigateFunction) {
  if (e.isAuthLoss)   { tokenStore.clear('channel'); navigate('/login', { replace: true }); return; }
  if (e.isWrongGuard) { console.error('Wrong guard — this dashboard called another guard\'s path', e); return; }
  if (e.code === 'insufficient_permission') { /* hide the control; e.permission names it */ return; }
  if (e.code === 'illegal_transition') { /* refetch — the order moved on */ return; }
  if (e.code === 'insufficient_stock')  { /* show the shortfall; do not retry blindly */ return; }
  if (e.status === 500) { /* real outage — say so, never retry in a loop */ return; }
}
```

⚠️ `wrong_guard` must **not** clear the token. It means this dashboard called a path
belonging to another guard; logging out hides the bug and the user re-authenticates into
the same failure.

### 2.8 `can.ts` — gate every control

Permissions arrive from `verify-otp` (§3). Every control is gated on the list, never on a
role name.

```ts
export function makeCan(permissions: readonly string[]) {
  const set = new Set(permissions);
  return (code: string) => set.has(code);
}
```

```tsx
{can('sc.orders.confirm') && <ConfirmButton id={id} />}
```

Hiding a control is not security — the server checks again — but it is the difference
between a usable dashboard and one that answers 403 on every click.

The routes on this guard gate on **30 distinct `sc.*` permissions** — see the tables in §4.
The seed `channel_manager` receives **47** at login: every `sc.*` code seeded, including
17 (`sc.finance.*` ×5, `sc.reports.*` ×3, `sc.content.*` ×3, `sc.notify.*` ×2,
`sc.loyalty.manage`, `sc.dashboard.view`, `sc.reps.settle`, `sc.retailers.credit`) whose
routes do not exist yet. Holding a permission is not evidence that its screen can be
built — §7 is.

Four built-in channel roles exist: `channel_manager` (everything), `sales_manager`
(orders, reps, pricing, promotions), `catalog_manager` (catalog, pricing, offers,
content), `accountant` (finance, returns). Only the manager is seeded. Gate on the codes,
never on these names — a real channel will edit them.

A user with `sc.catalog.view` but not `sc.catalog.create` must not see an Add Product
button at all.

### 2.9 A resource module

```ts
// resources/sub-orders.ts
import { makeClient } from '../client';
const api = makeClient('channel');

export interface SubOrderRow { id: number; sub_order_no: string; status: string; zone_id: number | null; total: number }

export const listSubOrders = (q: { page?: number; per_page?: number; 'filter[status]'?: string }) =>
  api<SubOrderRow[]>('/channel/sub-orders', { query: q });

export const confirmSubOrder = (id: number, idempotencyKey: string) =>
  api<{ status: string; picking_list_id: number | null }>(`/channel/sub-orders/${id}/confirm`, {
    method: 'POST', idempotencyKey,
  });
```

Login is the one place `noIdempotency` is correct — see §3.

### 2.10 Money

```ts
export const formatMoney = (amount: number, decimals = 0) =>
  new Intl.NumberFormat('ar-SY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
    .format(decimals === 0 ? amount : amount / 10 ** decimals);
```

Integers in the smallest unit. SYP has **0 decimals**, so the integer is the number you
display. Never `/ 100` by reflex.

⚠️ **One exception on this guard:** `/channel/zones` returns `delivery_fee` and
`min_order_value` as **decimal strings** (`"12.50"`), not integers. Do not run them through
`formatMoney`. See §4.8.

---

## 3. A token in two minutes

```bash
# 1. request an OTP (no auth, NO idempotency key)
curl -s -X POST http://127.0.0.1:8000/api/v1/channel/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"+963900000001"}'
# { "data": { "otp_id": "otp_ab12cd" } }

# 2. read the code from the log — no WhatsApp is sent locally
tail -n 50 storage/logs/laravel.log | grep OTP

# 3. verify (no auth, NO idempotency key)
curl -s -X POST http://127.0.0.1:8000/api/v1/channel/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"otp_id":"otp_ab12cd","code":"123456"}'
```

```jsonc
{ "data": {
    "token": "14|xxxx",
    "channels": [ { "id": 1, "name": "Demo Channel" } ],
    "permissions": [ "sc.catalog.view", "sc.orders.confirm" ]
  }, "meta": { "server_time": "…" } }
```

Then `Authorization: Bearer 14|xxxx` on everything else.

⚠️ **`request-otp` returns only `otp_id`** — no `expires_in`, no `resend_after`, unlike the
mobile OTP. Hardcode the known values: TTL **300 s**, cooldown **60 s**, **5** attempts.
⚠️ **There is no channel resend endpoint.** If the code expires, request a new one.
⚠️ A phone with no `ChannelUser` row, or one with no membership, returns
**`403 insufficient_permission`** — not 404.
📌 Rate limits are per hour: **3/phone**, 10/device, 30/IP. Easy to burn while testing
(skipped entirely while the bypass is on).

What the two calls can answer with:

| Call | HTTP | `error.code` | Meaning |
|---|---|---|---|
| `request-otp` | 429 | `rate_limited` | cooldown (60 s since the last code) **or** an hourly limit hit — same code for both; the message says which |
| `verify-otp` | 401 | `otp_invalid` | wrong code, **or** an unknown `otp_id`, **or** the 6th attempt onward — one code for all three |
| `verify-otp` | 401 | `otp_expired` | more than 300 s old, or already consumed (a second verify of the same `otp_id` lands here) |
| `verify-otp` | 403 | `insufficient_permission` | code fine, phone has no channel membership |

⚠️ `otp_invalid` and `otp_expired` are **401**, but they are not `isAuthLoss` — there is no
token to drop. Keep the user on the OTP screen; on `otp_expired` send them back to the
phone step.

`permissions` is the only source for `can()`. Every control below is gated on it.

---

## 4. Endpoint reference

`Stability`: **stable** = registered at its contract path · **moving** = registered
elsewhere, will move · **missing** = catalogued, no route (§7).

📌 Five channel routes are live but appear in **no catalog entry at all** — the settings
pair and the three zone routes. They are marked `stable*`. They are real and callable; the
catalog has not caught up.

⚠️ Three routes were marked **broken** (inbox + inventory lists). They answer **200**
with the envelope as of 2026-09-14. Treat them as `stable`.

### 4.0 Shared reference reads — not under `/channel`, but yours to read

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-AD-030 | GET | `/governorates` | moving | — |
| — | GET | `/governorates/{id}` | moving* | — |
| EP-AD-032 | GET | `/zones` | moving | — |
| — | GET | `/zones/{id}` | moving* | — |
| EP-AD-039A | GET | `/currencies` | moving | — |
| — | GET | `/currencies/{id}` | moving* | — |

`moving*` — the three single-row reads have no catalog entry at all; the lists do. You
will not need them: the lists are small and unpaginated, so cache and index those.

These sit at `/api/v1/governorates`, `/api/v1/zones`, `/api/v1/currencies` — **no guard
prefix** — and accept a token from any of the four guards. Reads are deliberately open:
your zone-coverage screen needs a zone to pick, `filter[zone_id]` needs a name to show,
and money cannot be rendered without a currency's `decimals`. The `POST`/`PUT`/`PATCH`
siblings are platform-only (`ad.refs.*`) and answer **403 `insufficient_permission`** to
you — do not draw them.

`moving` because the catalog wants them under `/platform/refs/…` for the platform and a
public snapshot (`GET /public/refs`, EP-PB-001, not built) for everyone else. Keep the
three base paths behind **one constant each**.

**`GET /governorates`** — plain array, ordered by `name_ar`, no params, no pagination:

```jsonc
{ "data": [ { "id": 1, "name_ar": "دمشق", "name_en": "Damascus", "code": "DI",
              "status": "active", "order": 1 } ] }
```

**`GET /zones`** — plain array, ordered by `name`, optional `?governorate_id=`:

```jsonc
{ "data": [ { "id": 4, "governorate_id": 1, "name": "المزة", "polygon": null,
              "status": "active" } ] }   // status: active | disabled
```

⚠️ **Both lists include disabled rows.** Filter `status === 'active'` in every picker.
`POST /channel/zones` validates only that the id exists — it will happily cover a disabled
zone (§4.8).

**`GET /currencies`** — plain array, ordered by `iso`:

```jsonc
{ "data": [ { "id": 1, "iso": "SYP", "name": "الليرة السورية", "symbol": "ل.س",
              "decimals": 0, "is_display_currency": true, "status": "active" } ] }
```

📌 `decimals` is the number your money formatter needs (§2.10): SYP is **0**. Load this
list once at startup and key it by `id` — `pricing.currency_id` on a product (§4.2) points
here. Exactly one row has `is_display_currency: true`.

The seed ships **14 governorates and 77 zones**; staging has the same set, minus whatever
the platform has disabled since.

### 4.1 Auth and settings

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-001 | POST | `/channel/auth/request-otp` | stable | — |
| EP-SC-002 | POST | `/channel/auth/verify-otp` | stable | — |
| — | GET | `/channel` | stable* | `sc.settings.view` |
| — | PUT | `/channel` | stable* | `sc.settings.update` |

**`GET` / `PUT /channel`** — your own channel profile.

```jsonc
{ "data": { "id": 1, "name": "…", "slug": "demo-channel",
            "legal_name": null, "tax_number": null, "phone": null, "email": null,
            "status": "active",                 // provisioning | active | suspended | archived
            "allowed_next": ["suspended"],      // ⚠️ new — ignore it, see below
            "settings": {},
            "created_at": "2026-09-01T10:00:00+00:00" } }
```

⚠️ `created_at` here is **UTC**, unlike every other timestamp on this guard. Handle it
explicitly.
⚠️ **`allowed_next` is not for you.** It lists the states the *platform* may move this
channel to next (`POST /admin/channels/{id}/transition`, a platform route). No channel
route changes `status`, so on this dashboard the key is informational at best — do not
render buttons from it. Show `status` as a badge and nothing more.
📌 Nothing on this guard is blocked by `status` today: a `suspended` channel's users still
log in and every route still answers. Suspension is not enforced anywhere yet — the
first enforcement point is ticketed for the retailer's cart (BE-O16), not for this
dashboard. Do not build a "your channel is suspended" wall — you cannot observe one.
**`PUT`** accepts `name` (`sometimes`), `legal_name`, `tax_number`, `phone`, `email`,
`settings`. ⚠️ `slug` and `status` are **not** editable here — only the platform can change
them. The response is the same shape as `GET`, `allowed_next` included.

### 4.2 Catalog

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-020 | GET | `/channel/products` | stable | `sc.catalog.view` |
| EP-SC-021 | POST | `/channel/products` | stable | `sc.catalog.create` |
| EP-SC-022 | PUT | `/channel/products/{id}` | stable | `sc.catalog.update` |
| EP-SC-023 | POST | `/channel/products/bulk` | stable | `sc.catalog.update` |
| EP-SC-024 | POST | `/channel/products/{id}/variants/generate` | stable | `sc.catalog.variants` |
| EP-SC-025 | GET | `/channel/brands` | stable | `sc.catalog.view` |
| EP-SC-026 | POST | `/channel/brands` | stable | `sc.catalog.create` |
| EP-SC-027 | GET | `/channel/categories/tree` | stable | `sc.catalog.view` |
| EP-SC-028 | POST | `/channel/categories` | stable | `sc.catalog.create` |
| EP-SC-029 | POST | `/channel/categories/reorder` | stable | `sc.catalog.update` |
| EP-SC-030 | GET | `/channel/catalog/export` | stable | `sc.catalog.view` |
| EP-SC-031 | POST | `/channel/catalog/import` | stable | `sc.catalog.import` |

**`GET /channel/products`** — paginated, and **thin**:

```jsonc
{ "data": [ { "id": 12, "sku": "SKU-1", "name_ar": "…", "status": "active" } ] }
```

⚠️ Only these four keys — no brand, no category, no price, no image. Build the list around
them, or fetch per row.
Filters: `filter[brand_id]`, `filter[category_id]`, `filter[status]`, `filter[zone_id]`,
`filter[search]` (name/SKU/barcode). `per_page` default 25, max 100.
⚠️ **Never send `sort`.** No list on this guard declares `allowedSorts`, and the query
builder's rule for that case is: a `sort` param is **not applied and not rejected — it
silently cancels the default order** instead. `?sort=id` answers 200 with rows in
whatever order MySQL feels like. Order is `-created_at` only while you send nothing.

**`POST /channel/products`** → **201** `{ "id": 12, "sku": "SKU-1" }`.
**`PUT /channel/products/{id}`** → 200 `{ "id": 12 }` — ⚠️ **`sku` is omitted on update**.

⚠️ **`PUT` is a full replace, not a patch.** It uses the same FormRequest, so `name_ar` and
`sku` remain **required** — send the whole product or you will 422.

Body (abridged; all optional unless marked):

```jsonc
{ "name_ar": "…",              // REQUIRED ≤200
  "sku": "SKU-1",              // REQUIRED ≤80, unique per channel
  "name_en": null, "brand_id": null, "category_id": null,
  "model_no": null, "barcode": null, "status": "active|draft|…",
  "short_description": null, "long_description": null,
  "specs": [ {"key":"…","value":"…"} ],
  "media": { "images": [], "primary": null, "video": null },
  "sale_unit_id": null,
  "unit_factors": [ {"from_unit_id":1,"to_unit_id":2,"factor":12} ],
  "min_order_qty": 1, "order_multiple": 1, "weight_gram": 0,
  "pricing": { "type": "simple|tiered", "base_price": 5000, "currency_id": null,
               "tiers": [ {"from":3,"to":10,"price":4500} ] },
  "inventory": { "tracked": true, "reorder_point": 0, "allow_backorder": false },
  "availability": { "zone_ids": [1], "activity_type_ids": [1], "lead_time_days": 0 },
  "marketing": { "tags": [], "sliders": [], "priority": 0 } }
```

📌 `pricing.base_price` and every tier `price` are **integers in minor units**.
⚠️ `availability.retailer_group_ids` is validated and then **never read** — silently
dropped. Do not build UI for it.
Errors: `422 validation_failed` with `details.sku` on a duplicate; `422
channel_limit_exceeded` when the plan's SKU cap is reached (⚠️ this code is a bare string,
**not** in `ErrorCode`, and it is **422 here, not 423**).

**`POST /channel/products/bulk`** — `{product_ids: [≤200], action: activate|disable|delete_draft, payload?}`
→ `{ "affected_count": 7 }`.
⚠️ `payload` is validated then ignored. ⚠️ `delete_draft` only touches `draft` rows, so
`affected_count` can be **lower than the ids you sent** with no per-id report. Show the
count, not "all done".

**`POST /channel/products/{id}/variants/generate`** — `{axes:[{name, values:[…]}]}`

```jsonc
{ "data": { "variants": [ { "id": 9, "sku": "…", "combination": {"اللون":"أحمر"},
                            "price": 5000, "stock": 0, "barcode": null, "image": null } ] } }
```

⚠️ **`stock` is hardcoded `0`**, `image` always `null`, and **every variant carries the
identical price** — the parent's qty-1 quote, computed once outside the loop. Do not
present per-variant pricing or stock from this response.

**`GET /channel/categories/tree`** — plain array, **not paginated**, no params.

Two node shapes. Roots come from the reference table, children from `categories`:

```jsonc
{ "id":1, "name":"…", "image":"…", "icon":null,
  "parent_id": null, "level": 1, "order": 1, "status": "active",
  "direct_products": 0, "total_products": 42,
  "children": [ { "id":5, "name":"…", "parent_id":1, "level":2,
                  "status":"active", "direct_products":12, "total_products":12,
                  "children": [] } ] }
```

⚠️ On **root** nodes `parent_id` is always `null`, `level` always `1`, `status` always the
literal `"active"`, and `direct_products` always `0`. Only `total_products` is real there.
📌 Category depth is capped at **5** — a deeper `parent_id` is `422 catalog.category_level`.

**`POST /channel/categories`** → 201 `{id}`. `{name, parent_id (required), image?, icon?,
order?, activity_type_ids?}`. New categories are always created `active`.

**`POST /channel/categories/reorder`** — `{moves:[{id, parent_id, order}]}` →
`{ "updated": 3 }`. Runs in a transaction, so **one bad move rolls back all of them**.
Errors (all 422, all keyed on `moves`): `category_not_found`, `parent_required`,
`category_cycle`, `category_level`, `parent_not_found`.

**`GET /channel/catalog/export`** → `{ "job_id": "job_catalog_…" }`. Async, queue
`exports`. ⚠️ **There is no job-status endpoint** — show "queued" and stop; do not poll.
Filter: `filter[status]` only.

**`POST /channel/catalog/import`** — multipart `{file, type: products, dry_run?}` → 200

```jsonc
{ "data": { "preview": [ {"sku":"…","name_ar":"…"} ],
            "errors": [ {"row":2,"message":"…"} ],
            "duplicates": ["SKU-1"] } }
```

📌 Always `dry_run=true` first. ⚠️ `preview` reads **only columns A (sku) and B (name_ar)**
and is capped at the **first 500 rows**; `row` counts from the header, so the first data
row is `2`. ⚠️ When `dry_run` is false the job is dispatched but **the response is
identical — the `job_id` is never returned**, so a real import is unobservable from the
UI. Warn the user before they run it.
⚠️ `type` is validated then ignored.

### 4.3 Pricing

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-040 | GET | `/channel/price-lists` | stable | `sc.pricing.view` |
| EP-SC-041 | POST | `/channel/price-lists` | stable | `sc.pricing.update` |
| EP-SC-042 | POST | `/channel/price-lists/{id}/schedule` | stable | `sc.pricing.schedule` |
| EP-SC-043 | POST | `/channel/pricing/bulk-update` | stable | `sc.pricing.update` |
| EP-SC-044 | GET | `/channel/pricing/change-log` | stable | `sc.pricing.view` |
| EP-SC-045 | PUT | `/channel/products/{id}/pricing` | stable | `sc.pricing.update` |
| EP-SC-046 | PUT | `/channel/reps/{id}/discount-cap` | stable | `sc.reps.update` |

**`GET /channel/price-lists`** — paginated `{id, name, type, status}`. `filter[type]`.

**`POST /channel/price-lists`** → **201** `{id, status}`.

```jsonc
{ "name": "…", "type": "zone|retailer|group",
  "zone_ids": [1], "group_id": null, "retailer_id": null,
  "adjustment": { "mode": "percent|fixed", "value": -10 },
  "effective_from": null, "effective_to": null, "reason": null }
```

📌 `status` is **derived, not supplied**: a future `effective_from` yields `scheduled`,
otherwise `active`.
Errors (422): `zone_ids_required`, `retailer_required`, `group_required`,
`zone_not_found` — depending on `type`.

**`POST /channel/price-lists/{id}/schedule`** — `{changes:[{product_id, base_price}],
effective_from}` → `{ "scheduled_job_id": "job_plist_…" }`. Forces the list to
`scheduled`. ⚠️ No status endpoint for that job.

**`POST /channel/pricing/bulk-update`** — `{product_ids: [≤200], mode, value, reason}` →
`{ "affected_count": 12 }`.
⚠️ Products outside your channel and products with no base-price row are **silently
skipped** — no error, no per-id report. Compare `affected_count` against what you sent and
tell the user if they differ.
📌 Percent maths is **integer truncating** (`intdiv`), so results can be a unit off what a
float calculator predicts. The server is right.

**`GET /channel/pricing/change-log`** — paginated.

```jsonc
{ "at": "…", "user": 3, "product": 12, "before": 5000, "after": 4500, "reason": "…" }
```
⚠️ Keys are `user` and `product` — **bare ints**, not `user_id`/`product_id` and not nested
objects. No names are resolved; resolve them yourself.
Filters: `filter[product_id]`, `filter[user_id]`, `filter[date]`.

**`PUT /channel/products/{id}/pricing`** — `{type, base_price, currency_id?, tiers?, reason?}`
→ `{ "price_change_log_id": 44 }`. Full replace.

**`PUT /channel/reps/{id}/discount-cap`** — `{max_discount_percent: 0..100, max_cash_hold}`

```jsonc
{ "data": { "rep": { "id": 8, "max_discount_percent": 10, "max_cash_hold": 500000 } } }
```
📌 Nested under `rep`. ⚠️ `id` is echoed from the URL, not read back from the row.
📌 **Setting this matters:** with no limit row a rep's cap is **0**, and every discount they
attempt is rejected. If reps report "discount always fails", this is why.
⚠️ `max_cash_hold` is stored but **unenforceable today** — the rep wallet endpoints do not
exist (see [rep.md §7](./rep.md#7-not-built--do-not-mock)).
Errors: `422 validation_failed` keyed on **`id`** when the rep is not in your channel.

### 4.4 Offers

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-050 | GET | `/channel/offers` | stable | `sc.offers.view` |
| EP-SC-051 | POST | `/channel/offers` | stable | `sc.offers.create` |
| EP-SC-052 | GET | `/channel/offers/{id}/performance` | stable | `sc.offers.view` |
| EP-SC-053 | PATCH | `/channel/offers/{id}/stop` | stable | `sc.offers.stop` |

**`GET /channel/offers`** — paginated `{id, name, type, status}`. `filter[status]`, `filter[type]`.

**`POST /channel/offers`** → **201** `{id}`. Defaults: `status: draft`, `targeting.scope: all`.

```jsonc
{ "name": "…", "type": "buy_x_get_y|product_discount|…",
  "media": [], "description": null,
  "components": [ {"product_id":12,"qty":2} ],
  "rules": { "buy_qty": 2, "get_qty": 1 },
  "rewards": { "product_id": 13, "qty": 1, "discount_percent": null, "discount_amount": null },
  "targeting": { "scope":"all|zones|groups|retailers",
                 "activity_type_ids": [], "zone_ids": [], "group_ids": [], "retailer_ids": [] },
  "constraints": { "starts_at": null, "ends_at": null, "total_qty": null,
                   "per_retailer_max": null, "per_order_max": null,
                   "min_invoice_value": null, "min_items": null },
  "stackable": false, "priority": 0, "status": "draft" }
```

⚠️ **`targeting.group_ids` is validated and never persisted** — silently dropped. Do not
offer group targeting.
⚠️ **A reward without `product_id` is silently discarded.** The code only writes the reward
block `if isset($rewards['product_id'])`, so a pure percentage discount saves nothing and
returns 201 as if it worked. Require a reward product in your form until this is fixed.

**`GET /channel/offers/{id}/performance`** — `applied_count` is the redemption counter.
The money keys are **not computed yet** (they need order lines; Promotion must not query
Ordering). Until BE2-PRM05 lands they stay `0` / `[]`. **Do not chart `linked_sales`,
`discount_given` or `net_margin`.** A zero here is "not built", not "sold nothing".
A foreign id is **404 `not_found`**.

```jsonc
{ "data": { "applied_count": 7, "linked_sales": 0, "discount_given": 0,
            "net_margin": 0, "retailers_count": 0, "by_zone": [], "conversion_rate": 0 } }
```

**`PATCH /channel/offers/{id}/stop`** — note **PATCH**. `{reason}` required →
`{"status":"stopped"}`. ⚠️ No state guard — stopping a draft or an already-stopped offer
succeeds.

### 4.5 Inventory

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-060 | GET | `/channel/inventory/levels` | stable | `sc.inventory.view` |
| EP-SC-061 | GET | `/channel/inventory/movements` | stable | `sc.inventory.view` |
| EP-SC-062 | POST | `/channel/inventory/adjust` | stable | `sc.inventory.adjust` |
| EP-SC-063 | POST | `/channel/inventory/transfers` | stable | `sc.inventory.transfer` |
| EP-SC-064 | PUT | `/channel/inventory/reorder-points` | stable | `sc.inventory.reorder` |

The three writes below execute immediately. Catalog `dual: true` on **adjust** is unmet
— there is no Core hook for channel dual-approval yet (BE-C05 is IAM-shaped). Do not
wait for a second confirmer on this path.

The shapes:

**`GET /channel/inventory/levels`** — paginated.

```jsonc
{ "product": { "id": 12, "name_ar": "…" },
  "variant": { "id": 9 },              // null when there is no variant
  "warehouse": { "id": 3, "name": "…" },
  "available": 40, "reserved": 5, "in_transit": 0, "damaged": 2 }
```
⚠️ `warehouse.name` falls back to the **empty string**, not null. ⚠️ `variant` carries only
`id` — no SKU, no combination.
Filters: `filter[warehouse_id]`, `filter[product_id]`.

**`GET /channel/inventory/movements`** — paginated
`{id, at, type, qty_before, qty_after}`.
⚠️ **No product, no warehouse, no reason, no actor** — five keys only. You cannot build an
audit view from this alone.

**`POST /channel/inventory/adjust`** — `{product_id, variant_id?, warehouse_id, qty_delta
(signed), reason}` → `{ "movement_id": 88, "available": 35 }`.
Errors: `422` on `warehouse_id`/`product_id`; **`409 insufficient_stock`** when a negative
delta would go below zero.

**`POST /channel/inventory/transfers`** → **201** `{ "id": 4, "status": "sent" }`.
`{from_warehouse_id, to_warehouse_id, lines:[{product_id, variant_id?, qty}]}`.
Errors: `422 same_warehouse`, `422 warehouse_not_found`, `422` keyed
`lines.{i}.product_id`, and **`409 insufficient_stock`**.
⚠️ **Not transactional** — the transfer row is written *before* stock moves, so a 409
leaves an orphaned transfer. Re-fetch after any 409 here rather than assuming nothing
happened.

**`PUT /channel/inventory/reorder-points`** — `{items:[{product_id, warehouse_id,
variant_id?, point}]}` → `{ "updated": 5 }`.
⚠️ **Not transactional** — items before a failing index are already saved. The 422 names
the failing index (`items.{i}.…`); re-send the remainder.

### 4.6 Sub-orders — the operational core

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-070 | GET | `/channel/sub-orders` | stable | `sc.orders.view` |
| EP-SC-071 | GET | `/channel/sub-orders/{id}` | stable | `sc.orders.view` |
| EP-SC-072 | POST | `/channel/sub-orders/{id}/confirm` | stable | `sc.orders.confirm` |
| EP-SC-073 | POST | `/channel/sub-orders/bulk-confirm` | stable | `sc.orders.confirm` |
| EP-SC-074 | POST | `/channel/sub-orders/{id}/reject` | stable | `sc.orders.reject` |
| EP-SC-075 | POST | `/channel/sub-orders/{id}/cancel` | stable | `sc.orders.cancel` |
| EP-SC-076 | PATCH | `/channel/sub-orders/{id}/lines` | stable | `sc.orders.edit_lines` |
| EP-SC-077 | POST | `/channel/sub-orders/assign` | stable | `sc.orders.assign` |
| EP-SC-078 | POST | `/channel/sub-orders/{id}/reassign` | stable | `sc.orders.reassign` |
| EP-SC-079 | POST | `/channel/sub-orders/{id}/schedule` | stable | `sc.orders.schedule` |

**`GET /channel/sub-orders`** — paginated `{id, sub_order_no, status, zone_id, total}`.
⚠️ No retailer name and no `created_at` in the row.
Filters: `filter[status]`, `filter[zone_id]`, `filter[retailer_id]`, `filter[rep_id]`,
`filter[source]`, and `filter[waiting_over_minutes]` (an SLA filter: orders older than N
minutes).

**`GET /channel/sub-orders/{id}`**

```jsonc
{ "data": {
    "header": { "sub_order_no": "SO-9", "status": "pending", "shop": "…" },
    "lines": [ {"id":31,"product_id":12,"qty":3,"unit_price":5000} ],
    "financials": { "subtotal": 15000, "discount": 0, "total": 15000 },
    "note": null,
    "timeline": [ {"stage":"pending","at":"…"} ],
    "allowed_actions": ["confirm","reject","edit_lines"] } }
```

📌 **`allowed_actions` is the contract for your action buttons.** It is computed from the
state machine *and* filtered by your permissions. Render buttons from it — never from your
own status logic — and the 409s below become unreachable.
⚠️ One caveat: if the user's permission list is **empty**, the filter is skipped and *all*
actions are returned. Do not treat `allowed_actions` as an authorisation check.
⚠️ `header` has no `id` and `lines[]` have no product name or line total.

**The state machine.** Legal source states per action:

| Action | Allowed from |
|---|---|
| `confirm`, `reject` | `pending` |
| `cancel` | `pending, confirmed, assigned, accepted, processing, postponed` |
| `edit_lines` | `pending, confirmed` |
| `assign` | `confirmed, postponed` |
| `reassign` | `assigned, accepted` |
| `schedule` | `confirmed, assigned, accepted` |

Anything else is **`409 illegal_transition`**.

**`POST /{id}/confirm`** — **no body**.

```jsonc
{ "data": { "status": "confirmed", "picking_list_id": 7 } }
```
⚠️ `picking_list_id` can be `null` if the listener has not created it yet in the same
request. Do not deep-link on null.
Errors: `409 illegal_transition` (not `pending`); **`409 insufficient_stock`** when the
reservation fails (rolled back); `404 not_found` if the channel has **no default
warehouse** — ⚠️ note this arrives with code `not_found`/404 despite being an inventory
setup problem, so surface a clear message.

**`POST /sub-orders/bulk-confirm`** — `{ids: [≤50]}`

```jsonc
{ "data": { "confirmed": [9, 10],
            "failed": [ {"id": 11, "error": "insufficient_stock"} ] } }
```
⚠️ **Always 200, even when every id fails.** `error` is the bare domain code, no message.
Render both lists; never report success off the status code.

**`POST /{id}/reject`** and **`/{id}/cancel`** — `{reason}` required ≤255 →
`{"status":"rejected"|"cancelled"}`.
⚠️ `cancel` also returns `409` once the warehouse handover is confirmed, even from a legal
state.

**`PATCH /{id}/lines`** — `{changes:[{line_id, qty, removed?}], reason}` →
`{status, total}`. The order is re-quoted and totals recomputed; status is unchanged.
⚠️ Unknown `line_id`s are **silently skipped**. ⚠️ `reason` is validated but **never
persisted or audited** — do not promise an audit trail. ⚠️ Not transactional, and for
non-`pending` orders it releases and re-reserves stock, so **`409 insufficient_stock`** can
leave quantities already saved. Re-fetch after a 409.

**`POST /sub-orders/assign`** — `{sub_order_ids:[…], rep_id, mode?}` →
`{ "assigned": [9, 10] }`.
⚠️ `mode` is validated then ignored. ⚠️ Unresolvable ids are **silently skipped**, and the
loop is **not transactional** — a failure midway leaves earlier ids assigned. Re-fetch the
list after any error.
Errors: `422 validation_failed` — `rep_off_coverage` (rep not in your channel, or the
order's zone is outside the rep's zones) or `rep_off_duty`; `409 illegal_transition`.

**`POST /{id}/reassign`** — `{rep_id, reason?}` → ⚠️ `{"status":"assigned"}` — note the
**response key differs from `assign`** (`status`, not `assigned`). `reason` is not
persisted. Also 409 once the handover is confirmed.

**`POST /{id}/schedule`** — `{scheduled_at, reason?}` →
`{ "status": "postponed", "scheduled_at": "…" }`.
📌 The action is called *schedule* but the resulting status is **`postponed`**. Label the
button accordingly. `reason` is not persisted.

### 4.7 Returns

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-SC-080 | GET | `/channel/return-requests` | stable | `sc.returns.view` |
| EP-SC-081 | POST | `/channel/return-requests/{id}/decide` | stable | `sc.returns.decide` |

**`GET`** — ⚠️ **plain array, not paginated, no filters**, ordered by id desc:
`{id, request_no, type, status}`. Statuses: `pending, approved, rejected, sorted`. Paginate
client-side; this will grow unbounded.

**`POST /{id}/decide`** — `{decision: approve|reject, reason?}` → `{"status":"approved"}`.
⚠️ **No state guard** — an already-decided request can be decided again. Gate the buttons
on `status === "pending"` yourself.
📌 An approved return then goes to the warehouse to be sorted — see
[warehouse-web.md](./warehouse-web.md).

### 4.8 Zones — ⚠️ money is a string here

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| — | GET | `/channel/zones` | stable* | `sc.zones.view` |
| — | POST | `/channel/zones` | stable* | `sc.zones.view` + `sc.zones.manage` |
| — | DELETE | `/channel/zones/{id}` | stable* | `sc.zones.view` + `sc.zones.manage` |

```jsonc
{ "data": [ { "id": 2, "zone_id": 4, "zone_name": "…",
              "delivery_days": ["sun","mon"],
              "delivery_fee": "12.50",       // ⚠️ STRING
              "min_order_value": "0.00" } ] }  // ⚠️ STRING, or null
```

⚠️ **This is the one place in the channel dashboard where money is a decimal string, not an
integer.** `delivery_fee` and `min_order_value` come back as `"12.50"`-style strings via a
money cast. Everything else on this guard is an integer in minor units. Do not run these
through your integer formatter, and do not `parseInt` them.

**`POST`** — `{zone_id (must exist), delivery_days?: sun..sat, delivery_fee?, min_order_value?}`
→ **201**.
📌 It is an **upsert keyed on `zone_id`** — posting an existing zone **updates** it and
still returns 201. There is no PUT.
⚠️ "Must exist" is all it checks: a zone the platform has **disabled** is accepted without
complaint. Feed the picker from `GET /zones` (§4.0) filtered to `status === 'active'`,
and show a disabled zone that is already covered with a warning rather than hiding it.
📌 `delivery_fee` / `min_order_value` are accepted as a number or a string; either way the
server stores and returns a two-decimal **string**.
**`DELETE /{id}`** → **204 with no body** — do not parse an envelope. Hard delete, no
restore.
⚠️ `zone_name` uses `whenLoaded`, so in principle the key can be **absent** rather than
null. Read it defensively.

---

## 5. The HTTP contract

Everything in this section applies to every call above. It is the same contract the other
four clients obey, restated here so you never need another file.

### 5.1 The envelope

Success carries `data` and always `meta.server_time`:

```jsonc
{ "data": { }, "meta": { "server_time": "2026-09-07T12:00:00+03:00" } }
```

A paginated list:

```jsonc
{ "data": [ ],
  "meta": { "server_time": "…", "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
```

An error — note it has **no `data` and no `meta`**:

```jsonc
{ "error": { "code": "insufficient_permission", "message": "…",
             "permission": "sc.orders.confirm", "details": { } } }
```

`permission` appears only on a 403 from the permission middleware. `204` responses have an
**empty body** — do not parse an envelope from them.

**Bind your UI to `data`. Branch on `error.code`, never on `error.message`** — the message
is localised for display and will change.

### 5.2 Headers

Only three custom headers are read by the server. Send the rest for your own logs.

| Header | When | Value | Read by the server? |
|---|---|---|---|
| `Accept` | always | `application/json` | yes |
| `Accept-Language` | always | `ar` \| `en` | **no — ignored since 2026-09-07.** The locale is pinned to `en` for every request; every `error.message` and validation message is English. Keep sending `ar`: the pin is a temporary switch, and your UI strings are your own anyway |
| `Authorization` | after login | `Bearer {token}` | yes |
| `X-Idempotency-Key` | every write | UUID per user intent | **yes** |
| `X-Channel-Id` | — | channel id | **yes, but **ignored for you** — see below** |
| `X-Client` | always | `channel-web` | **no** — ignored today |
| `X-App-Version` | always | semver | **no** — ignored today |

⚠️ Because `Accept-Language` is ignored, **never show `error.message` to a user as-is** —
it is English on an Arabic dashboard. Map `error.code` (and `details.{field}`) to your
own strings; that was the rule anyway (§5.1).

⚠️ **`X-Channel-Id` does nothing for a channel user.** `ResolveTenant` honours it only for
`platform_admin`. Your tenant comes from your own membership, and the header is silently
ignored — not an error, just no effect. A user in several channels **cannot switch**.

⚠️ `X-Client` and `X-App-Version` are read by **no** middleware.

### 5.3 Idempotency

`EnsureIdempotency` is appended to the whole `api` group, so **every** `POST`, `PUT`,
`PATCH` and `DELETE` needs `X-Idempotency-Key`. A missing header fails before your
controller is reached with `400 idempotency_key_required`.

The key is hashed from **method + path + body**:

| You do | Server does |
|---|---|
| Same key, same body, previous call finished 2xx | Replays the stored response, adds `Idempotent-Replayed: true` |
| Same key, **different body** | `409 idempotency_key_conflict` |
| Same key, first call still running | `409 operation_in_progress` |

⚠️ **You cannot see `Idempotent-Replayed` from a browser.** `config/cors.php` exposes no
response headers, so `fetch` hides every non-safelisted one. A replay is indistinguishable
from a first success on the client side — which is fine, because the body is identical.
Do not write code that waits for that header.

So a key belongs to a **user intent**, not an HTTP attempt. Keep it in form state, not in
the fetch wrapper (§2.5).

Stored responses live **24 hours**. Only 2xx are stored — a failed write releases its key
immediately, so the user can correct the form and resubmit under the same key.

**Exempt paths — for this dashboard, exactly two:**

```
channel/auth/request-otp
channel/auth/verify-otp
```

Omit the header on those. Send it everywhere else, including logout.

📌 Several writes here are **not transactional** — `transfers`, `reorder-points` and
`assign` can leave partial state behind a 409/422. Re-fetch after any error rather than
assuming a rollback.

### 5.4 The error catalogue

| HTTP | `error.code` | What the dashboard does |
|---|---|---|
| 400 | `idempotency_key_required` | Your bug — you omitted the header |
| 401 | `unauthenticated` | No usable token → login |
| 401 | `token_revoked` | Token deleted or expired → drop it, login |
| 401 | `otp_invalid`, `otp_expired` | OTP screen only (§3). **Not** an auth loss — there is no token yet |
| 403 | `wrong_guard` | A live token from another guard — **do not** re-login |
| 403 | `insufficient_permission` | `error.permission` names the missing grant → hide the control |
| 409 | `insufficient_stock` | Reservation failed. Show the shortfall; do not retry blindly |
| 404 | `not_found` | Missing **or not yours** — render as missing, never "forbidden" |
| 409 | `illegal_transition` | The entity is not in a state that allows this. Refetch and re-render |
| 409 | `operation_in_progress` | Wait, retry the **same** key |
| 409 | `stale_version` | Someone wrote first → refetch, show a conflict, do not clobber |
| 409 | `idempotency_key_conflict` | Same key, different body — your bug |
| 422 | `validation_failed` | Map `details.{field}` onto inputs |
| 422 | `ref_in_use` | A reference is used elsewhere; disable instead of deleting |
| 423 | `plan_limit_exceeded` | Banner, stop the spinner, do not retry |
| 429 | `rate_limited` | Back off. Also the OTP cooldown (§3) |
| 503 | `maintenance_mode` | Maintenance screen |
| 409 / 4xx | `conflict`, `http_error` | A framework `abort()` with no domain code. Rare; treat by status |
| **500** | *(no envelope)* | `{"message":"Server Error"}` — Laravel's own body, **no `error` key**. Show "something broke on the server", never retry in a loop |

On `422` the **first** message is flattened into `error.message`, so you can show something
useful without walking `details` — in English (§5.2). A raw Laravel validation payload
never reaches you, and no endpoint returns HTML.

### 5.5 Timestamps

Every timestamp is ISO-8601 in **`Asia/Damascus`** (`+03:00`), already converted. Do not
apply an offset on the client. Prefer `meta.server_time` over the browser clock for
anything the server will judge.

⚠️ **One exception:** `created_at` on `GET /channel` (your own channel profile) is emitted
in **UTC**, not Damascus. Handle it explicitly. See §4.1.

### 5.6 Money

**Every amount is an integer in the smallest currency unit.** No floats anywhere.

- **SYP has 0 decimals**, so the integer *is* the displayed number. Never `/ 100`.
- Never compute a total locally and treat it as truth — re-read the server's total.
- Rounding happens on the server. If your arithmetic disagrees, yours is wrong.

⚠️ **One exception on this guard:** `/channel/zones` returns `delivery_fee` and
`min_order_value` as **decimal strings** (`"12.50"`). Everything else is an integer. See §4.8.

### 5.7 Lists

| Param | Default | Notes |
|---|---|---|
| `page` | 1 | |
| `per_page` | **25** | **hard max 100 — a larger value is silently clamped**, not rejected |
| `filter[x]` | — | bracket syntax, e.g. `filter[status]=pending` |

⚠️ **Never send `sort`** on any list here. No `allowedSorts` is declared anywhere on this
guard, and in that state the query builder neither applies nor rejects a `sort` — it
**silently drops the default order** (§4.2). A sorted-looking list that arrives in table
order is this.

⚠️ `GET /channel/return-requests`, `GET /channel/zones`, `GET /channel/categories/tree` and
the three reference lists (§4.0) are **plain arrays**: unpaginated, unfiltered. Only
`return-requests` grows unbounded; paginate that one client-side.

---
## 6. What to build, in order

| # | Screen | Endpoints | Notes |
|---|---|---|---|
| 1 | Login (phone → OTP) | `auth/request-otp`, `auth/verify-otp` | no resend endpoint; 60 s timer is client-side; `000000` on staging |
| 2 | App shell + nav | `permissions` from login | every item behind `can()` |
| 3 | Reference cache | `/governorates`, `/zones`, `/currencies` | load once; filter `active`; `decimals` feeds the money formatter |
| 4 | Channel settings | `GET`/`PUT /channel` | `created_at` is UTC; ignore `allowed_next` |
| 5 | Zone coverage | `/channel/zones` + the zone picker from #3 | ⚠️ money is a string here |
| 6 | Categories tree | `categories/tree`, `categories`, `reorder` | depth ≤ 5; reorder is all-or-nothing |
| 7 | Brands | `brands` | |
| 8 | Products list + editor | `products`, `POST`, `PUT` | `PUT` is a full replace; never send `sort` |
| 9 | Variants | `variants/generate` | ignore `stock`/`price` in the response |
| 10 | Bulk actions | `products/bulk` | reconcile `affected_count` |
| 11 | Import / export | `catalog/import`, `export` | dry-run first; no job status; export never completes on staging |
| 12 | Pricing lists + bulk | `price-lists*`, `bulk-update` | reconcile `affected_count` |
| 13 | Price change log | `pricing/change-log` | resolve user/product names yourself |
| 14 | Rep discount caps | `reps/{id}/discount-cap` | without this, rep discounts always fail |
| 15 | **Order detail + actions** | `sub-orders/{id}`, confirm/reject/cancel/lines | drive buttons from `allowed_actions` |
| 16 | **Orders queue** | `sub-orders` | paginated; never send `sort` |
| 17 | Bulk confirm | `bulk-confirm` | render `confirmed` **and** `failed` |
| 18 | Assign / reassign / schedule | the three POSTs | note the differing response keys |
| 19 | Inventory adjust, transfers, reorder points | the three writes | expect `409 insufficient_stock`; adjust has **no** dual-approval yet |
| 20 | Inventory levels + movements | `inventory/levels`, `movements` | paginated; filters `warehouse_id`, `product_id` |
| 21 | Returns inbox | `return-requests`, `decide` | gate on `status === pending` yourself |
| 22 | Offers | `offers`, `POST`, `stop` | require a reward product; **no analytics** |

Do not build the offer analytics screen (§4.4) or anything in §7. Inbox (#16) and
inventory lists (#20) are wired — build the real tables.

---

## 7. Not built — do not mock

**`GET /channel/offers/{id}/performance`** returns a live `applied_count` and **zeros** for
every money figure (§4.4). Do not chart sales or margin.

Also absent from this guard, in the catalog with no route:

| Area | Note |
|---|---|
| Channel notifications log (`sc.notify.view`, EP-SC-092) | catalogued; the route does not exist and the permission is no longer seeded |
| Job status for import/export/schedule | three endpoints hand you a `job_id` with nothing to poll |
| Channel finance — invoices, statements, settlements | SP-13; nothing on this guard |
| Channel reporting and dashboards | SP-17; the largest missing block (63 endpoints) |
| Rep wallet oversight | `max_cash_hold` is settable but unenforceable — see [rep.md §7](./rep.md#7-not-built--do-not-mock) |

Every one of these 404s today. Keep a route shell if you like, but disable submit and
never invent numbers — a fabricated sales figure is worse than an empty screen.

---

## 8. Gotchas

| # | Where | Watch out |
|---|---|---|
| 1 | `X-Channel-Id` | Ignored for channel users — honoured only for `platform_admin`. Multi-channel users cannot switch |
| 2 | `auth/request-otp` | Returns **only `otp_id`**. No `expires_in`/`resend_after`, and **no resend endpoint** |
| 3 | Login failure | No membership → **403 `insufficient_permission`**, not 404 |
| 4 | Every list | **Never send `sort`** — no `allowedSorts` anywhere; the param is silently ignored **and drops the default order** |
| 5 | `GET /channel` | `created_at` is **UTC**; every other timestamp is Damascus. `allowed_next` is platform-facing — ignore it |
| 6 | `PUT /channel/products/{id}` | Full replace — `name_ar` and `sku` still required. Response omits `sku` |
| 7 | Products | `channel_limit_exceeded` is **422** here, not 423, and is not in `ErrorCode` |
| 8 | `products/bulk` | `delete_draft` skips non-drafts; `affected_count` may be lower than requested |
| 9 | `variants/generate` | `stock` hardcoded `0`, `image` null, **all variants share the parent price** |
| 10 | `categories/tree` | Root nodes: `parent_id` null, `level` 1, `status` "active", `direct_products` 0 — all fixed |
| 11 | `catalog/import` | Preview is columns A/B only, first 500 rows; a real import returns **no `job_id`** |
| 12 | `pricing/bulk-update` | Silently skips foreign/priceless products; percent maths truncates |
| 13 | `pricing/change-log` | Keys are `user` and `product`, bare ints, no names |
| 14 | `reps/{id}/discount-cap` | No limit row = cap **0** = every rep discount fails |
| 15 | Offers | `targeting.group_ids` silently dropped; a reward **without `product_id`** is silently discarded |
| 16 | `offers/{id}/performance` | **Entirely hardcoded zeros.** Do not chart |
| 17 | `offers/{id}/stop` | PATCH, and no state guard |
| 18 | `sub-orders/{id}` | Drive buttons from `allowed_actions` — but it is **not** an authorisation check |
| 19 | `confirm` | `picking_list_id` may be null; no default warehouse surfaces as **404 `not_found`** |
| 20 | `bulk-confirm` | **Always 200.** Read `failed`; `error` is a bare code |
| 21 | `PATCH .../lines` | Unknown lines skipped silently; `reason` never persisted; not transactional |
| 22 | `assign` vs `reassign` | Response keys differ: `{assigned:[…]}` vs `{status:"assigned"}` |
| 23 | `assign` | Silently skips unresolvable ids and is not transactional |
| 24 | `schedule` | Produces status **`postponed`**, not "scheduled" |
| 25 | `cancel` / `reassign` | Also 409 once the warehouse handover is confirmed |
| 26 | `return-requests` | Unpaginated, unfiltered, and `decide` has **no state guard** |
| 27 | `/channel/zones` | ⚠️ `delivery_fee` and `min_order_value` are **decimal strings**; POST is an upsert returning 201; DELETE is **204 no body** |
| 28 | Inventory writes | `transfers` and `reorder-points` are **not transactional** — re-fetch after a 409/422 |
| 29 | `not_found` | Also means "not yours". Never render "forbidden" |
| 30 | Money | Integers in minor units everywhere **except** `/channel/zones` |
| 31 | `inventory/adjust` | Catalog `dual: true` — **executes on the first request**. No Core dual-approval hook for this guard yet |
| 32 | `Accept-Language` | **Ignored** — every message is English. Never show `error.message` raw on an Arabic screen |
| 33 | `Idempotent-Replayed` | Invisible to `fetch` — CORS exposes no headers. Do not wait for it |
| 34 | `/governorates`, `/zones`, `/currencies` | Readable with your token, no `/channel` prefix, **include disabled rows** — filter `status === 'active'` in pickers |
| 35 | `POST /channel/zones` | Accepts a **disabled** zone; only existence is checked |
| 36 | Staging | `https://api.sentraxsy.com` — OTP is `000000`, `exports` queue unserved, shared data, may lag `main` |
| 37 | `verify-otp` | `otp_invalid` / `otp_expired` are **401 without a token** — stay on the OTP screen, do not "log out" |
