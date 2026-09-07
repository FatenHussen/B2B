# React API kit (Next.js 15)

One shared package, three dashboards. No dashboard ships its own `fetch` wrapper or an
ad-hoc `Authorization` header.

| | |
|---|---|
| Package | `packages/b2b-api-client` (`@b2b/api-client`) |
| Contract | [01-http-contract.md](./01-http-contract.md) |
| Consumers | [channel-web](./apps/channel-web.md) · [warehouse-web](./apps/warehouse-web.md) · [platform-web](./apps/platform-web.md) |

This page is working code, not description. Paste it and it runs.

---

## 1. Stack (locked)

| Concern | Choice |
|---|---|
| Framework | Next.js 15 App Router |
| UI | React 19 |
| Language | TypeScript 5, `strict: true` — no `any` in `lib/` |
| CSS | Tailwind, `<html lang="ar" dir="rtl">` |
| Server state | TanStack Query v5 |
| Token | `sessionStorage` Bearer |
| HTTP | `fetch`, **inside this package only** |

No Redux, no Axios, no NextAuth. Authenticated calls happen in Client Components —
`middleware.ts` cannot read `sessionStorage`, so do not attempt to gate routes there.

Use logical CSS properties (`ms-*`, `ps-*`, `text-start`) so RTL works. Phone, OTP and PIN
inputs get `dir="ltr"` and `inputMode="numeric"`.

```
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Ports: platform `3000`, channel `3001`, warehouse `3002`.

---

## 2. Layout

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

---

## 3. `envelope.ts`

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

---

## 4. `api-error.ts`

Every code the API can return. Exhaustive, so `switch` statements can be checked.

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

---

## 5. `storage.ts`

Key the token **by guard**. One browser profile may hold a platform session and a channel
session at once; sharing a key is how you manufacture a `wrong_guard`.

```ts
export type Guard = 'platform' | 'channel' | 'warehouse';

const key = (g: Guard) => `b2b.${g}.token`;

export const tokenStore = {
  get:   (g: Guard) => (typeof window === 'undefined' ? null : sessionStorage.getItem(key(g))),
  set:   (g: Guard, t: string) => sessionStorage.setItem(key(g), t),
  clear: (g: Guard) => sessionStorage.removeItem(key(g)),
};
```

---

## 6. `idempotency.ts`

The heart of the kit. A key belongs to a **user intent**, not to an HTTP attempt: same key
on every retry, a new key only when the user starts over.

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

Bind it to the confirm button, not to the request:

```tsx
'use client';
import { useRef, useState } from 'react';
import { keyFor, releaseKey } from '@b2b/api-client';

export function ConfirmButton({ subOrderId }: { subOrderId: number }) {
  const intentId = useRef(`suborder.confirm.${subOrderId}.${Date.now()}`).current;
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function onClick() {
    setBusy(true); setErr(null);
    try {
      // Retry as many times as the user likes: same intentId -> same key -> replay, never a double write.
      await confirmSubOrder(subOrderId, keyFor(intentId));
      releaseKey(intentId);
    } catch (e) {
      if (e instanceof ApiError && e.isRetryable) setErr('Still processing — press again.');
      else if (e instanceof ApiError) setErr(e.message);
      // Key deliberately NOT released: pressing again must reuse it.
    } finally { setBusy(false); }
  }

  return (
    <>
      <button onClick={onClick} disabled={busy}>Confirm</button>
      {err && <p role="alert">{err}</p>}
    </>
  );
}
```

⚠️ Never generate the key inside `client.ts`. A key minted per request turns one confirm
into N orders when the network is bad — which is the exact failure idempotency exists to
prevent.

---

## 7. `client.ts`

```ts
import { Envelope, isFail, Meta } from './envelope';
import { ApiError } from './api-error';
import { Guard, tokenStore } from './storage';

const BASE = process.env.NEXT_PUBLIC_API_BASE_URL!;
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
wrong on every retry. A thrown error in development is cheaper than duplicate orders in
production.

---

## 8. Reacting to errors, once

```tsx
'use client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
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

Handle the three auth outcomes distinctly:

```ts
function onApiError(e: ApiError, guard: Guard, router: AppRouterInstance) {
  if (e.isAuthLoss)     { tokenStore.clear(guard); router.replace('/login'); return; }
  if (e.isWrongGuard)   { console.error('Wrong guard — this dashboard called another guard\'s path', e); return; }
  if (e.code === 'insufficient_permission') { /* hide the control; e.permission names it */ return; }
  if (e.code === 'requires_password_confirm') { /* modal, then replay with the SAME key */ return; }
}
```

⚠️ `wrong_guard` must **not** clear the token. It means this dashboard called a path
belonging to another guard; logging out hides the bug and the user re-authenticates into
the same failure.

---

## 9. `can.ts`

Permissions arrive at login and from the session endpoint. Every control is gated on the
list, never on a role name.

```ts
export function makeCan(permissions: readonly string[]) {
  const set = new Set(permissions);
  return (code: string) => set.has(code);
}
```

```tsx
{can('sc.orders.confirm') && <ConfirmButton subOrderId={id} />}
```

Hiding a control is not security — the server checks again — but it is the difference
between a usable dashboard and one that answers 403 on every click.

⚠️ Platform admin is a special case: `Gate::before` grants `platform_admin` everything, so
a permission check on that role passes vacuously. Channel administration
(`/admin/channels`) is gated on the **role**, not on a permission — gate that nav item on
`roles.includes('platform_admin')`.

---

## 10. A resource module

```ts
// resources/sub-orders.ts
import { makeClient } from '../client';
const api = makeClient('channel');

export interface SubOrderRow { id: number; status: string; /* … */ }

export const listSubOrders = (q: { page?: number; per_page?: number; 'filter[status]'?: string }) =>
  api<SubOrderRow[]>('/channel/sub-orders', { query: q });

export const confirmSubOrder = (id: number, idempotencyKey: string) =>
  api<{ status: string }>(`/channel/sub-orders/${id}/confirm`, {
    method: 'POST', idempotencyKey,
  });
```

Login is the one place `noIdempotency` is correct:

```ts
export const platformLogin = (email: string, password: string) =>
  makeClient('platform')<LoginResponse>('/platform/auth/login', {
    method: 'POST', body: { email, password }, noIdempotency: true,
  });
```

The exempt list is exactly the eight paths in
[01-http-contract.md §6](./01-http-contract.md#6-idempotency--mandatory-on-every-write).
Nowhere else.

---

## 11. Money

Integers in the smallest unit. SYP has **0 decimals**, so the integer is the number you
display.

```ts
export const formatMoney = (amount: number, decimals = 0) =>
  new Intl.NumberFormat('ar-SY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
    .format(decimals === 0 ? amount : amount / 10 ** decimals);
```

Never `/ 100` by reflex, and never total a cart client-side for display — re-read the
server's total after every mutation.

---

## 12. Checklist

| # | Done when |
|---|---|
| 1 | `GET /health` renders `ok` |
| 2 | Token is per guard in `sessionStorage`; logout leaves none |
| 3 | A write without a key throws in dev, never reaches the network |
| 4 | Double-clicking Confirm produces **one** row |
| 5 | `wrong_guard` logs an error and does **not** log out |
| 6 | A missing permission removes the control, not just disables it |
| 7 | No `/ 100` anywhere |
| 8 | 422 maps onto fields via `fieldErrors()` |
