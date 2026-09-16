# Warehouse dashboard (React)

**This is the only file you need.** Backend setup, the React package, the HTTP contract and
every endpoint — all of it is here. No other document is required to build this dashboard.

| | |
|---|---|
| Path | `apps/warehouse-web` |
| Port | **3002** |
| Guard | `warehouse` (Sanctum) |
| `X-Client` | `warehouse-web` *(sent for logs; the server ignores it)* |
| Seed account | **none** — register a device from the CLI (§1.3) |

Verified against `php artisan route:list` and the controller/action source on
**2026-09-07**. Where the API catalog disagrees with this page, the code wins.

**19 endpoints are live** and they cover the whole floor: receive → put away → pick → pack
→ hand over → stocktake, plus return sorting. This is a touch-screen tool for a scanner
station, not a reporting dashboard.

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

You need the API running locally before the dashboard can do anything.

### 1.1 Requirements

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

**The seeder creates no warehouse device.** There is no self-service signup and no public
registration endpoint — a device is created from the CLI, then logs in with a token + PIN:

```bash
php artisan warehouse:register-device wh-token-1 1234 1 1 --label="Bay 1"
#                                     token      pin  wh channel
```

- `pin` is **exactly 4 digits**.
- The token is stored as a SHA-256 hash — **the plain value is unrecoverable**, so write it down.
- `warehouse_id` and `channel_id` must be real. With only `demo-channel` seeded, `1 1`.
- Re-running with the same token **updates** the device, so it is also the PIN reset.

⚠️ **Then assign a role, or nothing will work.** The first successful login auto-creates the
`WarehouseUser` with **no roles and no permissions**. Every route sits behind a `wh.*`
permission, so a fresh device logs in successfully (200, valid token) and then returns
**403 on every single endpoint**, with `permissions: []`. Role assignment is out of band
(Spatie, guard name `warehouse`).

### 1.4 CORS and the dev origin

`config/cors.php` sets `supports_credentials: true` and reads allowed origins from
`CORS_ALLOWED_ORIGINS` (default `http://localhost:3000`). A credentialed request needs an
explicit origin — never `*`. Add your dev port:

```dotenv
CORS_ALLOWED_ORIGINS=http://localhost:3002,http://127.0.0.1:3002
```

⚠️ `localhost` and `127.0.0.1` are **different origins** to a browser. Vite prints
whichever it bound to — list both, or the preflight fails with a CORS error that looks
like an API outage.

### 1.5 Queues

`php artisan horizon` supervises `critical`, `default`, `media`, `reports`.

**Nothing on this dashboard is asynchronous.** No endpoint returns a `job_id`. You do not
need Horizon running to use the warehouse floor.

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
npm create vite@latest apps/warehouse-web -- --template react-ts
cd apps/warehouse-web && npm i react-router-dom @tanstack/react-query
npm i -D tailwindcss @tailwindcss/vite
```

```ts
// vite.config.ts
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: { port: 3002 },          // this dashboard's dev port
});
```

```dotenv
# .env — Vite only exposes variables prefixed VITE_
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_X_CLIENT=warehouse-web
```

⚠️ **The prefix is `VITE_`, not `NEXT_PUBLIC_`**, and it is read as
`import.meta.env.VITE_*` — `process.env` does not exist in the browser bundle.

Dev port for this dashboard: **3002**.

📌 This dashboard runs full-screen on a scanner station. Vite's build output is a static
bundle you can serve from any web server — no Node process is needed in the warehouse.

### 2.1.1 Guarding routes

There is no `middleware.ts` and no server layer. Gate routes in the router itself:

```tsx
// routes.tsx
import { createBrowserRouter, Navigate, Outlet } from 'react-router-dom';
import { tokenStore } from '@b2b/api-client';

function RequireDevice() {
  return tokenStore.get('warehouse') ? <Outlet /> : <Navigate to="/login" replace />;
}

export const router = createBrowserRouter([
  { path: '/login', element: <DeviceLoginPage /> },
  { element: <RequireDevice />, children: [
      { path: '/', element: <QueuesPage /> },
      { path: '/pick/:id', element: <PickingPage /> },
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

This dashboard uses `tokenStore.get('warehouse')`.

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
  const intentId = useRef(`pick.complete.${id}.${Date.now()}`).current;
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
function onApiError(e: ApiError, router: AppRouterInstance) {
  if (e.isAuthLoss)   { tokenStore.clear('warehouse'); router.replace('/login'); return; }
  if (e.isWrongGuard) { console.error('Wrong guard — this dashboard called another guard\'s path', e); return; }
  if (e.code === 'insufficient_permission') { /* hide the control; e.permission names it */ return; }
  if (e.code === 'barcode_not_in_order') { /* "not on this list" — not a scanner fault */ return; }
  if (e.code === 'illegal_transition')   { /* often "already done" — refetch */ return; }
  if (e.code === 'sod_violation')        { /* a second device/user must approve */ return; }
}
```

⚠️ `wrong_guard` must **not** clear the token. It means this dashboard called a path
belonging to another guard; logging out hides the bug and the user re-authenticates into
the same failure.

### 2.8 `can.ts` — gate every control

Permissions arrive from `device-login` (§3). Every control is gated on the list.

```ts
export function makeCan(permissions: readonly string[]) {
  const set = new Set(permissions);
  return (code: string) => set.has(code);
}
```

```tsx
{can('wh.picking.execute') && <ScanPanel listId={id} />}
```

Hiding a control is not security — the server checks again — but it is the difference
between a usable dashboard and one that answers 403 on every click.

⚠️ **If `permissions` is empty after login, say so.** It means the device has no role yet
(§1.3) — show *"this device has no role assigned"*, not an empty floor. Re-logging in will
not fix it.

### 2.9 A resource module

```ts
// resources/picking.ts
import { makeClient } from '../client';
const api = makeClient('warehouse');

export interface PickLine { id: number; name: string; qty_required: number; qty_picked: number;
                            barcode: string | null; location: { aisle: string; shelf: string } | null }

export const getPickingList = (id: number) =>
  api<{ header: unknown; lines: PickLine[] }>(`/warehouse/picking-lists/${id}`);

export const scan = (id: number, barcode: string, qty: number, idempotencyKey: string) =>
  api<{ line_id: number; qty_picked: number; qty_required: number }>(
    `/warehouse/picking-lists/${id}/scan`, { method: 'POST', body: { barcode, qty }, idempotencyKey });
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

Money barely appears on this guard — only `total_value` on the pending-handover list.

---

## 3. A token in two minutes

There is **no self-service signup and no public registration endpoint**. A device is
created from the CLI, then logs in with a token + PIN.

### Step 1 — register the device (ops, once)

```bash
php artisan warehouse:register-device wh-token-1 1234 1 1 --label="Bay 1"
#                                     token      pin  wh channel
```

- `pin` is **exactly 4 digits**.
- The token is stored as a SHA-256 hash — **the plain value is unrecoverable**, so write it
  down.
- `warehouse_id` and `channel_id` must be real. With only `demo-channel` seeded, `1 1`.
- Re-running with the same token **updates** the device, so it is also the PIN reset.

### ⚠️ Step 2 — assign a role, or nothing will work

The first successful login **auto-creates the `WarehouseUser`** — with **no roles and no
permissions**. Every route below sits behind a `wh.*` permission, so a freshly registered
device will:

- log in successfully (200, a valid token), and
- return **`403 insufficient_permission` on every single endpoint**, with `permissions: []`.

Role assignment is out of band (Spatie, guard name `warehouse`). If a new station "logs in
but everything is forbidden", this is why — it is not a token problem, and re-logging in
will not fix it. Handle it in your UI: if `permissions` is empty after login, say *"this
device has no role assigned yet"* rather than showing an empty floor.

### Step 3 — log in

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/warehouse/auth/device-login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"device_token":"wh-token-1","pin":"1234"}'
```

```jsonc
{ "data": { "token": "17|xxxx",
            "warehouse": { "id": 3, "name": "مستودع المزة" },
            "permissions": ["wh.queue.view", "wh.picking.execute"] },
  "meta": { "server_time": "…" } }
```

Send `pin` as a **string** (`"0421"`), never a number — leading zeros matter and the rule
is `string|size:4`.

⚠️ **Logging in evicts the previous token for that device.** The token is named after the
`device_token`, and issuing deletes any existing token with the same name. Two stations
sharing one `device_token` will keep signing each other out — give every station its own.

⚠️ Unknown token, revoked device and wrong PIN **all return the same `401 unauthenticated`**.
The API never says which. There is no lockout counter, but do not build a "wrong PIN"
message — you cannot know.

⚠️ There is **no `expires_at`** and no refresh token in the response. Treat a 401 as "log
in again".

---

## 4. Endpoint reference

`Stability`: **stable** = registered at its contract path · **moving** = registered
elsewhere, will move · **missing** = catalogued, no route (§7). Every endpoint here is
**stable**.

📌 The `{id}` in the picking and packing routes is the **picking-list id** — including
`/packing/{id}/*`, which does **not** take a packing-job id.

### 4.1 Auth and queues

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-WH-001 | POST | `/warehouse/auth/device-login` | stable | — |
| EP-WH-010 | GET | `/warehouse/queues` | stable | `wh.queue.view` |

**`GET /warehouse/queues`** — the home screen. No params.

```jsonc
{ "data": { "queues": { "to_pick": 4, "picking": 1, "to_pack": 2, "ready": 3,
                        "awaiting_rep": 2, "inbound_returns": 0, "inbound_transfers": 1 },
            "alerts": [] } }
```

⚠️ **`inbound_returns` is hardcoded `0`** — it never counts anything. ⚠️ **`alerts` is
always `[]`** — nothing populates it. Do not build an alerts panel.
📌 `ready` counts `packed` lists. The other five counts are real.
Errors: `404 not_found` if the device has no warehouse scope (a revoked device, or one never bound to a user).

### 4.2 Picking

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-WH-020 | GET | `/warehouse/picking-lists/{id}` | stable | `wh.picking.execute` |
| EP-WH-021 | POST | `/warehouse/picking-lists/{id}/scan` | stable | `wh.picking.execute` |
| EP-WH-022 | POST | `/warehouse/picking-lists/{id}/lines/{lineId}/manual` | stable | `wh.picking.execute` |
| EP-WH-023 | POST | `/warehouse/picking-lists/{id}/shortage` | stable | **`wh.picking.shortage`** |
| EP-WH-024 | POST | `/warehouse/picking-lists/{id}/complete` | stable | `wh.picking.execute` |

**`GET /warehouse/picking-lists/{id}`**

```jsonc
{ "data": {
    "header": { "order_no": "SO-9", "shop": "…", "zone": "…",
                "expected_rep": null, "items": 5, "units": 24, "due_at": "…" },
    "lines": [ { "id": 31, "image": null, "name": "…", "variant": null,
                 "qty_required": 6, "qty_picked": 0, "sale_unit": "…",
                 "location": { "aisle": "A", "shelf": "3" },
                 "barcode": "6221…" } ] } }
```

⚠️ `header.expected_rep` and `lines[].variant` are **always `null`** — even when the line
has a variant. `image` is always null too. `location` is null when unassigned.
📌 `items` is the line count, `units` the sum of `qty_required`.
Errors: `404 not_found` — missing list **or a list in another warehouse**. The two are
indistinguishable, by design.

**`POST /{id}/scan`** — `{barcode, qty ≥ 1}` → `{ line_id, qty_picked, qty_required }`.

📌 **Scanning is cumulative and clamped**: `qty_picked = min(qty_required, qty_picked + qty)`.
Scanning the same item twice adds; you can never exceed the requirement.
📌 The barcode is matched against the picking line first, then resolved through the
catalog (a variant barcode wins over the product barcode) and retried.
Errors: **`422 barcode_not_in_order`** when neither match succeeds — the single most common
error on this screen. Show it as "this item is not on this list", not as a scanner fault.

**`POST /{id}/lines/{lineId}/manual`** — `{qty ≥ 0}` →
`{ line_id, qty_picked, manual: true }`.
⚠️ Unlike `scan`, this **replaces** the quantity (`min(qty_required, qty)`), it does not
add. `manual` is hardcoded `true`.
📌 `min:0` here versus `min:1` on scan — manual is how you zero a line.

**`POST /{id}/shortage`** — a **different permission** (`wh.picking.shortage`).
`{line_id, qty_available ≥ 0, reason: out_of_stock|damaged|not_in_location}` →
`{"status":"shortage_reported"}` (hardcoded).
⚠️ It does **not** change the list status — the picker continues.

**`POST /{id}/complete`** — **no body** (send `{}` plus the idempotency key) →
`{"status":"to_pack"}`.
Errors: **`409 illegal_transition`** — the sub-order moves to `processing`, which is only
legal from `confirmed|assigned|accepted`. **Calling complete twice gives 409**, so treat a
409 here as "already completed" and re-fetch rather than as a failure.

### 4.3 Packing

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-WH-030 | POST | `/warehouse/packing/{id}/verify` | stable | `wh.packing.execute` |
| EP-WH-031 | POST | `/warehouse/packing/{id}/complete` | stable | `wh.packing.execute` |

📌 `{id}` is still the **picking-list id**.

**`POST /packing/{id}/verify`** — `{scans:[{barcode, qty ≥ 0}]}`

```jsonc
{ "data": { "mismatches": [ { "line_id": 31, "expected": 6, "scanned": 4 } ] } }
```

An empty array means a clean verify. `expected` is `qty_picked` (not `qty_required`).
⚠️ Matching here is **strict string comparison on the line's own barcode** — there is no
catalog fallback as there is in `scan`. A line whose `barcode` is `null` can **never** be
matched and will always appear as a mismatch with `scanned: 0`. Scans matching no line are
silently ignored.

**`POST /packing/{id}/complete`** — `{packages_count ≥ 1, total_weight, flags?}`

```jsonc
{ "data": { "labels": [ { "package_no": "PKG-9-1", "qr": "qr_pkg_9_1" } ] } }
```

⚠️ **`total_weight` has no type rule and its unit depends on the type you send.** An
**integer is treated as grams**; anything else (float or numeric string) is treated as
**kilograms and multiplied by 1000**. `2.5` → 2500 g, `2500` → 2500 g. Pick one convention
in your client and never let the user's raw input decide.

⚠️ `package_no` and `qr` are **deterministic templates** built from the sub-order id
(`PKG-{subOrderId}-{n}`, `qr_pkg_{subOrderId}_{n}`), not real identifiers. `qr` is a
placeholder string, **not** an image or a signed payload — render it as a barcode yourself
if you need one, and know it is guessable.
⚠️ Calling this twice creates **duplicate package rows with identical numbers**. Guard the
button with one idempotency key per pack.

### 4.4 Handover to the rep

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-WH-040 | GET | `/warehouse/handovers/pending` | stable | `wh.handover.execute` |
| EP-WH-041 | POST | `/warehouse/handovers` | stable | `wh.handover.execute` |
| EP-WH-042 | POST | `/warehouse/handovers/{id}/return-trip` | stable | **`wh.handover.return_trip`** |

**`GET /warehouse/handovers/pending`**

```jsonc
{ "data": { "reps": [ { "id": 4, "name": "", "orders_count": 2,
                        "packages": 0, "total_value": 15000 } ] } }
```

⚠️ **Three of five fields are dead.** `name` is **always the empty string**, `packages`
**always `0`**, and `id` is **`0` for unassigned orders** — every unassigned sub-order
collapses into a single `id: 0` bucket. Only `orders_count` and `total_value` are real.
You will need the rep's name from elsewhere; do not render an empty label.

**`POST /warehouse/handovers`** — `{rep_id, sub_order_ids:[…], rep_qr?}` → **200** (not 201)

```jsonc
{ "data": { "handover_id": 4, "status": "awaiting_rep_confirm", "temp_code": "0473" } }
```

📌 **`temp_code` is a 4-digit zero-padded string** the rep must type into their app to
confirm the pickup. Display it large; keep it a string.
⚠️ `rep_qr` is validated and then **never read** — do not build a QR scan for it.
⚠️ **This is not atomic.** Sub-orders are transitioned in a loop *after* the handover row
is written, with no transaction. If the third id is in a bad state you get a `409` while
the handover and the first two items are already persisted. **Treat a 409 here as "state
unknown" and re-fetch**, never as "nothing happened".
Errors: `409 illegal_transition` (a sub-order not in `processing|accepted|confirmed|assigned`),
`404 not_found`.

**`POST /handovers/{id}/return-trip`** — `{undelivered:[{sub_order_id, reason}]}` →

```jsonc
{ "data": { "restocked": [9, 10], "wallet_matched": true } }
```

⚠️ **`wallet_matched` is hardcoded `true`** — no reconciliation happens. Do not present it
as a cash check; the rep wallet lives on `/app/rep/wallet` (see the Flutter pack)
([rep.md §7](./rep.md#7-not-built--do-not-mock)).
⚠️ `reason` is validated and never persisted. `restocked` just echoes the ids you sent.

### 4.5 Receiving

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-WH-050 | POST | `/warehouse/receiving` | stable | `wh.receiving.execute` |
| EP-WH-051 | POST | `/warehouse/receiving/{id}/qc` | stable | **`wh.receiving.qc`** |

**`POST /warehouse/receiving`** → **201** — the only 201 on this guard.

```jsonc
{ "source": "purchase|transfer|field_return", "reference_no": null,
  "lines": [ { "product_id": 12, "variant_id": null,
               "qty_expected": 100, "qty_received": 98,
               "lot_no": null, "expiry_date": null, "location_id": null } ] }
```
```jsonc
{ "data": { "id": 7, "status": "pending_qc" } }
```

📌 **No stock moves at this stage.** The goods are recorded as pending QC only.

**`POST /warehouse/receiving/{id}/qc`** — `{lines:[{line_id, decision, qty, photos?}]}` →
`{"status":"posted"}`.

⚠️ **`decision` accepts any string, but only the exact literal `"accept"` does anything.**
Every other value — `reject`, `damage`, a typo — is a **silent no-op**: no stock moves, no
error, and the receipt is still marked `posted`. Reject/damage are **not implemented**;
offer only Accept in the UI.
⚠️ `photos` is validated and never persisted.
⚠️ The stock increase uses **the `qty` you send here**, not the line's `qty_received`.
⚠️ `status: "posted"` is returned **unconditionally**, even when zero lines matched. Do not
read it as confirmation that anything was received.

### 4.6 Stocktake

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-WH-060 | POST | `/warehouse/stocktakes` | stable | `wh.stocktake.execute` |
| EP-WH-061 | POST | `/warehouse/stocktakes/{id}/lines` | stable | `wh.stocktake.execute` |
| EP-WH-062 | POST | `/warehouse/stocktakes/{id}/submit` | stable | `wh.stocktake.execute` |
| EP-WH-063 | POST | `/warehouse/stocktakes/{id}/approve` | stable | **`wh.stocktake.approve`** |

**`POST /warehouse/stocktakes`** — `{scope: full|partial, warehouse_id?}` → 200
`{ "id": 5, "status": "counting" }`.
⚠️ **Omit `warehouse_id`.** When supplied it **overrides your device's warehouse scope with
no check that it belongs to your channel**. Let the token decide.

**`POST /{id}/lines`** — `{product_id, variant_id?, location_id?, counted_qty ≥ 0}` →
`{ "line_id": 88 }`.
⚠️ **This always inserts a new row — it is not an upsert.** Recounting the same product
creates a second line, and approval applies **both** deltas. Deduplicate client-side, or
you will double-adjust stock.

**`POST /{id}/submit`** — **no body** → `{"status":"pending_approval"}`.
⚠️ No state guard — submitting an already-posted stocktake **silently reverts it** to
`pending_approval`.

**`POST /{id}/approve`** — `{reason}` required →
`{ "status": "posted", "adjustments": 3 }`, where `adjustments` is the **count of lines
with a non-zero delta**, not the sum.

⚠️ **`403 sod_violation` — the counter cannot approve their own stocktake.** Because one
device maps to one `WarehouseUser`, a **single-device warehouse can never approve a
stocktake at all**. A second device bound to a different user is required. Plan the rollout
around this; it is a deliberate separation-of-duties rule, not a bug.

⚠️ **`409 insufficient_stock` is not atomic.** Each line adjusts in its own transaction, so
a failure on line 5 leaves lines 1–4 already applied and the stocktake still
`pending_approval`. Re-fetch and re-approve; do not assume a rollback.

### 4.7 Return sorting

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-WH-070 | POST | `/warehouse/returns/{id}/sort` | stable | `wh.returns.sort` |

`{lines:[{line_id, condition: resalable|damaged|expired}]}` → `{"status":"sorted"}`.

📌 `line_id` is the **return-line id**, not the order-line id.
⚠️ **`expired` and `damaged` both land in the same `damaged` stock bucket** — the
distinction is recorded on the line but not in stock.
⚠️ A request that is not `approved` returns **`404 not_found`, not 409** — so a `pending`,
`rejected` or already-`sorted` request is indistinguishable from a missing one. The channel
must approve it first ([channel-web.md §4.7](./channel-web.md#47-returns)).
⚠️ `status: "sorted"` is returned even when **no** submitted line matched.
⚠️ The stock goes to the **return's channel default warehouse**, which is not necessarily
your device's warehouse.

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
             "permission": "wh.picking.execute", "details": { } } }
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
| `Accept-Language` | always | `ar` \| `en` | **yes** — anything else falls back to `ar` |
| `Authorization` | after login | `Bearer {token}` | yes |
| `X-Idempotency-Key` | every write | UUID per user intent | **yes** |
| `X-Channel-Id` | — | channel id | **ignored for this guard** |
| `X-Client` | always | `warehouse-web` | **no** — ignored today |
| `X-App-Version` | always | semver | **no** — ignored today |

⚠️ **`X-Device-Id` is not used by this guard.** The device identity is the `device_token`
in the login body (§3), not a header.

⚠️ `X-Channel-Id` is ignored (honoured only for `platform_admin`), and `X-Client` /
`X-App-Version` are read by **no** middleware.

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

So a key belongs to a **user intent**, not an HTTP attempt. Keep it in form state, not in
the fetch wrapper (§2.5).

Stored responses live **24 hours**. Only 2xx are stored — a failed write releases its key
immediately, so the user can correct the form and resubmit under the same key.

**Exempt paths — for this dashboard, exactly one:**

```
warehouse/auth/device-login
```

Omit the header on those. Send it everywhere else, including logout.

📌 Several writes here are **not atomic** — `POST /handovers` and `stocktakes/{id}/approve`
can leave partial state behind a 409. Treat a 409 as "state unknown", re-fetch, and never
assume a rollback.

📌 `packing/{id}/complete` called twice creates **duplicate packages**. One key per pack.

### 5.4 The error catalogue

| HTTP | `error.code` | What the dashboard does |
|---|---|---|
| 400 | `idempotency_key_required` | Your bug — you omitted the header |
| 401 | `unauthenticated` | No usable token → login |
| 401 | `token_revoked` | Token deleted or expired → drop it, login |
| 403 | `wrong_guard` | A live token from another guard — **do not** re-login |
| 403 | `insufficient_permission` | `error.permission` names it. **On a fresh device this means no role is assigned** (§1.3) |
| 403 | `sod_violation` | The counter cannot approve their own stocktake — a **second device** is needed |
| 409 | `insufficient_stock` | Only reachable on stocktake approval. **Not atomic** — earlier lines stay applied |
| 422 | `barcode_not_in_order` | The scan matched no line — "not on this list", not a scanner fault |
| 404 | `not_found` | Missing **or not yours** — render as missing, never "forbidden" |
| 409 | `illegal_transition` | The entity is not in a state that allows this. Refetch and re-render |
| 409 | `operation_in_progress` | Wait, retry the **same** key |
| 409 | `stale_version` | Someone wrote first → refetch, show a conflict, do not clobber |
| 409 | `idempotency_key_conflict` | Same key, different body — your bug |
| 422 | `validation_failed` | Map `details.{field}` onto inputs |
| 422 | `ref_in_use` | A reference is used elsewhere; disable instead of deleting |
| 423 | `plan_limit_exceeded` | Banner, stop the spinner, do not retry |
| 429 | `rate_limited` | Back off |
| 503 | `maintenance_mode` | Maintenance screen |

On `422` the **first** message is flattened into `error.message`, so you can show something
useful without walking `details`. A raw Laravel validation payload never reaches you, and
no endpoint returns HTML.

### 5.5 Timestamps

Every timestamp is ISO-8601 in **`Asia/Damascus`** (`+03:00`), already converted. Do not
apply an offset on the client. Prefer `meta.server_time` over the browser clock for
anything the server will judge.

All timestamps on this guard follow the rule — there is no UTC exception here.

### 5.6 Money

**Every amount is an integer in the smallest currency unit.** No floats anywhere.

- **SYP has 0 decimals**, so the integer *is* the displayed number. Never `/ 100`.
- Never compute a total locally and treat it as truth — re-read the server's total.
- Rounding happens on the server. If your arithmetic disagrees, yours is wrong.

Money barely appears on this guard — only `total_value` on the pending-handover list, an
integer in minor units.

### 5.7 Lists

| Param | Default | Notes |
|---|---|---|
| `page` | 1 | |
| `per_page` | **25** | **hard max 100 — a larger value is silently clamped**, not rejected |
| `filter[x]` | — | bracket syntax, e.g. `filter[status]=pending` |

📌 **Nothing on this guard is paginated, and no endpoint accepts any query or filter
parameter.** Every list returns a plain array. Filter and page client-side.

---
## 6. What to build, in order

| # | Screen | Endpoints | Notes |
|---|---|---|---|
| 1 | Device login (token + PIN) | `auth/device-login` | ⚠️ empty `permissions` → "no role assigned" |
| 2 | Queue home | `queues` | hide `inbound_returns` and `alerts` |
| 3 | Picking list | `picking-lists/{id}` | big touch targets; `location` guides the walk |
| 4 | Scan loop | `scan` | cumulative + clamped; handle `422 barcode_not_in_order` |
| 5 | Manual entry | `lines/{lineId}/manual` | replaces, not adds |
| 6 | Shortage | `shortage` | separate permission |
| 7 | Complete pick | `complete` | 409 = already done → re-fetch |
| 8 | Pack verify | `packing/{id}/verify` | render mismatches; null-barcode lines always mismatch |
| 9 | Pack complete + labels | `packing/{id}/complete` | fix the weight unit; one key per pack |
| 10 | Handover list | `handovers/pending` | names are empty — resolve elsewhere |
| 11 | Create handover | `handovers` | show `temp_code` large; 409 → re-fetch |
| 12 | Return trip | `handovers/{id}/return-trip` | ignore `wallet_matched` |
| 13 | Receiving | `receiving` | then QC |
| 14 | QC | `receiving/{id}/qc` | **Accept only** — other decisions do nothing |
| 15 | Stocktake count | `stocktakes`, `lines` | deduplicate lines client-side |
| 16 | Submit + approve | `submit`, `approve` | ⚠️ needs a **second device/user** |
| 17 | Return sorting | `returns/{id}/sort` | only after the channel approves |

Design for a scanner: large targets, keyboard-wedge input, and a visible offline warning —
there is no offline mode (§7).

---

## 7. Not built — do not mock

Every path below **returns 404 today**. No stub, no feature flag.

| Area | Note |
|---|---|
| Any job-status endpoint | nothing on this guard is async, but nothing is pollable either |
| Warehouse reporting / KPIs | SP-17; there is no productivity or throughput endpoint |
| Reject / damage at QC | the `decision` field accepts them and **silently ignores them** (§4.5) |
| Return / expired stock buckets | `expired` is merged into `damaged` (§4.7) |
| Inbound returns queue | `queues.inbound_returns` is a hardcoded `0` |
| Warehouse alerts | `queues.alerts` is a hardcoded `[]` |
| Offline picking | no sync endpoints exist anywhere; the station must be online |
| Device self-registration | CLI only, by design (§1.3) |
| Rep wallet reconciliation | `wallet_matched: true` is fictional — real wallet is `/app/rep/wallet` |

Do not fake any of these. A fabricated stock count or a fake "wallet matched" is a
warehouse discrepancy someone has to reconcile by hand.

---

## 8. Gotchas

| # | Where | Watch out |
|---|---|---|
| 1 | First login | A new device gets a token with **`permissions: []`** — everything 403s until a role is assigned out of band |
| 2 | `device-login` | Logging in **evicts the previous token** for that `device_token`. One token per station |
| 3 | `device-login` | Bad token, revoked device and wrong PIN are **all `401 unauthenticated`** — you cannot tell them apart |
| 4 | `pin` | Send as a **string**, `size:4`. Leading zeros matter |
| 5 | Whole guard | **Nothing is paginated and no endpoint takes query params.** Filter client-side |
| 6 | `queues` | `inbound_returns` hardcoded `0`; `alerts` hardcoded `[]` |
| 7 | `picking-lists/{id}` | `expected_rep`, `variant`, `image` always `null` |
| 8 | Wrong warehouse | Returns **404**, identical to "missing". By design |
| 9 | `scan` | Cumulative and clamped to `qty_required` |
| 10 | `scan` | `422 barcode_not_in_order` is the common failure — phrase it as "not on this list" |
| 11 | `manual` | **Replaces** the quantity; `scan` adds |
| 12 | `complete` (pick) | Second call → **409**. Treat as already done, re-fetch |
| 13 | `verify` | Strict barcode match, **no catalog fallback**; null-barcode lines always mismatch |
| 14 | `packing/complete` | ⚠️ **integer `total_weight` = grams, non-integer = kilograms** |
| 15 | `packing/complete` | `package_no`/`qr` are guessable templates, not real ids; a second call duplicates packages |
| 16 | `handovers/pending` | `name` always `""`, `packages` always `0`, unassigned orders collapse into `id: 0` |
| 17 | `POST /handovers` | **Not atomic** — a 409 can leave a partial handover. Re-fetch |
| 18 | `POST /handovers` | Returns **200**, not 201. `rep_qr` is ignored |
| 19 | `return-trip` | `wallet_matched` is hardcoded `true`; `reason` never persisted |
| 20 | `receiving` | The only **201** here. No stock moves until QC |
| 21 | `qc` | Only `"accept"` does anything; other decisions are **silent no-ops**, yet `status: "posted"` still returns |
| 22 | `qc` | Stock uses the `qty` you send, not `qty_received`; `photos` discarded |
| 23 | `stocktakes` | Omit `warehouse_id` — it overrides your scope unchecked |
| 24 | `stocktakes/{id}/lines` | **Inserts every time.** Duplicate counts double-adjust |
| 25 | `submit` | No guard — resubmitting reverts a posted stocktake |
| 26 | `approve` | **`403 sod_violation`**: a one-device warehouse can never approve. Needs a second user |
| 27 | `approve` | `409 insufficient_stock` is **not atomic** — earlier lines stay applied |
| 28 | `returns/{id}/sort` | Not-approved → **404**, not 409. `expired` merges into `damaged`. Stock may land in another warehouse |
| 29 | Money | Integers, minor units |
