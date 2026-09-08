# Platform admin (React)

**This is the only file you need.** Backend setup, the React package, the HTTP contract and
every endpoint — all of it is here. No other document is required to build this dashboard.

| | |
|---|---|
| Path | `apps/platform-web` |
| Port | **3000** |
| Guard | `platform` (Sanctum) |
| `X-Client` | `platform-web` *(sent for logs; the server ignores it)* |
| Seed account | **`admin@platform.sy`** / `password` — role `platform_admin` |

Verified against `php artisan route:list` and the controller/action source on
**2026-09-07**. Where the API catalog disagrees with this page, the code wins.

**41 endpoints are live: 36 stable, 5 moving.** Identity, IAM and audit are complete.
Channel administration is a five-route stub at a temporary path, and **the entire platform
reference surface does not exist** (§7) — this is the most heavily blocked of the five
apps.

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

The seeder creates a platform administrator:

| | |
|---|---|
| Email | **`admin@platform.sy`** |
| Password | `password` |
| Role | `platform_admin` |

This account has no 2FA enabled, so login returns a token directly (§3).

📌 It also creates a channel manager (`+963900000001`) and one supply channel
(`demo-channel`, id `1`) — useful as test data for the channel screens in §4.5.

### 1.4 CORS and the dev origin

`config/cors.php` sets `supports_credentials: true` and reads allowed origins from
`CORS_ALLOWED_ORIGINS` (default `http://localhost:3000`). A credentialed request needs an
explicit origin — never `*`. Add your dev port:

```dotenv
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
```

⚠️ `localhost` and `127.0.0.1` are **different origins** to a browser. Vite prints
whichever it bound to — list both, or the preflight fails with a CORS error that looks
like an API outage.

### 1.5 Queues

`php artisan horizon` supervises `critical`, `default`, `media`, `reports`.

One thing on this dashboard is asynchronous: the **audit export**, which returns a `job_id`.
There is **no endpoint to poll it**, so without Horizon running the export queues silently
and never produces a file.

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
npm create vite@latest apps/platform-web -- --template react-ts
cd apps/platform-web && npm i react-router-dom @tanstack/react-query
npm i -D tailwindcss @tailwindcss/vite
```

```ts
// vite.config.ts
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: { port: 3000 },          // this dashboard's dev port
});
```

```dotenv
# .env — Vite only exposes variables prefixed VITE_
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_X_CLIENT=platform-web
```

⚠️ **The prefix is `VITE_`, not `NEXT_PUBLIC_`**, and it is read as
`import.meta.env.VITE_*` — `process.env` does not exist in the browser bundle.

Dev port for this dashboard: **3000**.

### 2.1.1 Guarding routes

There is no `middleware.ts` and no server layer. Gate routes in the router itself — and on
this guard, remember the **role** matters as well as the permissions (§2.8):

```tsx
// routes.tsx
import { createBrowserRouter, Navigate, Outlet } from 'react-router-dom';
import { tokenStore } from '@b2b/api-client';

function RequireAuth() {
  return tokenStore.get('platform') ? <Outlet /> : <Navigate to="/login" replace />;
}

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/login/2fa', element: <TwoFactorPage /> },   // challenge_token lives in router state only
  { element: <RequireAuth />, children: [
      { path: '/', element: <OverviewPage /> },
      { path: '/iam/roles', element: <RolesPage /> },
      { path: '/channels', element: <ChannelsPage /> },  // also needs the platform_admin role
  ]},
]);
```

⚠️ Pass `challenge_token` between the login and 2FA routes with React Router **state**
(`navigate('/login/2fa', { state: { challengeToken } })`), never a URL param and never
storage — it is a credential with a 10-minute life (§3).

📌 The auth check is a **convenience redirect, not security** — the server checks on every
request. Its job is to avoid rendering a page that will only 401.

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

This dashboard uses `tokenStore.get('platform')`.

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
  const intentId = useRef(`role.approve.${id}.${Date.now()}`).current;
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
  if (e.isAuthLoss)   { tokenStore.clear('platform'); router.replace('/login'); return; }
  if (e.isWrongGuard) { console.error('Wrong guard — this dashboard called another guard\'s path', e); return; }
  if (e.code === 'insufficient_permission') { /* hide the control; e.permission names it */ return; }
  if (e.code === 'requires_password_confirm') { /* modal, then replay with the SAME key */ return; }
  if (e.code === 'sod_violation')             { /* a DIFFERENT admin must act — do not retry */ return; }
}
```

⚠️ `wrong_guard` must **not** clear the token. It means this dashboard called a path
belonging to another guard; logging out hides the bug and the user re-authenticates into
the same failure.

### 2.8 `can.ts` — gate every control

Permissions arrive from `login` and `/platform/me` (§3). Every control is gated on the list.

```ts
export function makeCan(permissions: readonly string[]) {
  const set = new Set(permissions);
  return (code: string) => set.has(code);
}
```

```tsx
{can('ad.iam.role_create') && <CreateRoleButton />}
```

Hiding a control is not security — the server checks again — but it is the difference
between a usable dashboard and one that answers 403 on every click.

⚠️ **This guard has two authorisation systems.** `permissions` gates IAM and audit, but
**channel administration is gated on the `platform_admin` role**, not a permission:

```tsx
{roles.includes('platform_admin') && <ChannelsNavItem />}
```

And because `Gate::before` grants `platform_admin` everything, a permission check on that
role passes vacuously — it tells you nothing.

### 2.9 A resource module

```ts
// resources/iam.ts
import { makeClient } from '../client';
const api = makeClient('platform');

export interface RoleRow { id: number; key: string; name: string; system: string;
                           status: string; is_builtin: boolean }

export const listRoles = (q: { page?: number; 'filter[status]'?: string }) =>
  api<RoleRow[]>('/platform/iam/roles', { query: q });

export const approveRole = (id: number, reason: string, idempotencyKey: string) =>
  api<{ status: string }>(`/platform/iam/roles/${id}/approve`, {
    method: 'POST', body: { reason }, idempotencyKey });

// ⚠️ Channel CRUD is on a MOVING path — keep it behind one constant. See §4.5.
export const CHANNELS_BASE = '/admin/channels';
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

Money barely appears on this guard.

---

## 3. A token in two minutes

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/platform/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@platform.sy","password":"password"}'
```

The seeded admin has no 2FA, so you get a token straight away:

```jsonc
{ "data": { "token": "17|xxxx",
            "user": { "id": 1, "name": "…", "email": "…",
                      "roles": ["platform_admin"],
                      "permissions": ["ad.iam.view_catalog", "…"] },
            "expires_at": "2026-09-08T12:00:00+03:00" },
  "meta": { "server_time": "…" } }
```

If 2FA is on you get `{ "requires_2fa": true, "challenge_token": "cht_…" }` instead — post
that plus a TOTP to `/platform/auth/2fa/verify`. Keep `challenge_token` in React state
only: never localStorage, never a cookie. It expires in **10 minutes** and dies after **5**
failed attempts.

⚠️ **`expires_at` is nominal.** `sanctum.expiration` is `null`, so the token does not
actually expire server-side and the value you get is a notional 24 h. Do not build a
client-side auto-logout on it — trust 401.

⚠️ Wrong credentials are a deliberately generic `401 unauthenticated`. Do not tell the user
which half was wrong.

📌 **Two authorisation systems on this guard.** `permissions` gates IAM and audit;
**channel administration is gated on the `platform_admin` role**, not a permission. Gate
that nav item on `roles.includes('platform_admin')` — a user can hold every `ad.*`
permission and still 403 on `/admin/channels`. And note `Gate::before` grants
`platform_admin` everything, so a permission check on that role passes vacuously.

---

## 4. Endpoint reference

`Stability`: **stable** = registered at its contract path · **moving** = registered
elsewhere, will move · **missing** = catalogued, no route (§7).

A 403 from the permission middleware names what it wanted:

```jsonc
{ "error": { "code": "insufficient_permission", "message": "…",
             "permission": "ad.iam.role_create" } }
```

Prefer hiding the control via `can()`; use `error.permission` for the message when it slips
through.

### 4.1 Authentication

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-AD-001 | POST | `/platform/auth/login` | stable | — |
| EP-AD-002 | POST | `/platform/auth/2fa/verify` | stable | — |
| EP-AD-003 | POST | `/platform/auth/logout` | stable | — |
| EP-AD-004 | GET | `/platform/auth/me` | stable | — |
| EP-AD-005 | POST | `/platform/auth/confirm-password` | stable | — |
| EP-AD-006 | GET | `/platform/auth/sessions` | stable | — |
| EP-AD-007 | DELETE | `/platform/auth/sessions/{id}` | stable | — |

**`GET /platform/auth/me`** — also served at `GET /platform/me` (EP-AD-159A), identical.

```jsonc
{ "data": { "id": 1, "name": "…", "email": "…", "phone": null,
            "roles": ["platform_admin"],
            "permissions": ["ad.iam.view_catalog"],
            "two_factor_enabled": false,
            "last_login_at": "…" } }
```

**`POST /platform/auth/confirm-password`** — `{password}` →
`{ "confirmed_until": "…" }`. Opens a **15-minute** window used by exactly one route:
`PUT /platform/me/password`.

📌 The flow: the write returns `403 requires_password_confirm` → show the modal → call this
→ **replay the original write with the same idempotency key and the same body.** A new key
would execute the write twice. Wrong password here is `401`, not 422.

**`GET /platform/auth/sessions`** — not paginated, newest first.
`{ id (string), ip, agent, last_active_at, current }`. Never revoke `current` without a
distinct confirmation.

**`POST /platform/auth/logout`** — deletes only the current token, not other sessions.

### 4.2 Profile, 2FA and API tokens

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-AD-159A | GET | `/platform/me` | stable | — |
| EP-AD-159B | PUT | `/platform/me` | stable | — |
| EP-AD-159C | PUT | `/platform/me/password` | stable | — (needs password confirm) |
| EP-AD-159D | POST | `/platform/me/2fa/enable` | stable | — |
| EP-AD-159E | POST | `/platform/me/2fa/confirm` | stable | — |
| EP-AD-159F | GET | `/platform/me/2fa/recovery-codes` | stable | — |
| EP-AD-159G | GET | `/platform/me/api-tokens` | stable | — |
| EP-AD-159H | POST | `/platform/me/api-tokens` | stable | — |
| EP-AD-159I | DELETE | `/platform/me/api-tokens/{id}` | stable | — |

**`PUT /platform/me`** — `{name (required), phone?}` → `{ "updated": true }`.
⚠️ `name` is required even for a one-field edit — send the whole object. `phone` is
validated as **Syrian** and normalised server-side; echo back what `/me` returns.

**`PUT /platform/me/password`** — `{current_password, password, password_confirmation}`,
`min:8` and `confirmed`. The only route behind the 15-minute window (§4.1).

**`POST /me/2fa/enable`** — no body →
`{ "secret": "otpauth://totp/…", "qr_svg": "<svg/>" }`.
📌 `secret` is a **full `otpauth://` URI** — feed it straight to a QR renderer.
⚠️ **`qr_svg` is an empty placeholder `<svg/>`.** Render the QR client-side from `secret`;
do not ship the server's SVG.
⚠️ 2FA is **not on** after this call — nothing changes until confirm succeeds.

**`POST /me/2fa/confirm`** — `{code}` exactly 6 chars →
`{ "enabled": true, "recovery_codes": ["…", …8] }`.
⚠️ The 8 recovery codes appear **once, here, and never again**. Force a copy/download step
before the dialog can close. A wrong code is `401 otp_invalid` and leaves 2FA off.

**`GET /me/2fa/recovery-codes`** — returns only `{ "codes_remaining": 8 }`. A count, never
the codes. There is no "show my codes again".

**`POST /me/api-tokens`** — ⚠️ the current password goes in **`password_confirmation`**,
which reads like a repeat field but is not. `{name, password_confirmation}` →
`{ id, token }`. `token` is plaintext once.
⚠️ Wrong password → `403 requires_password_confirm`, but this route checks the password
**inline every time** and does **not** use the 15-minute window. Do not route the user
through the confirm-password modal here.

### 4.3 IAM

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-AD-010 | GET | `/platform/iam/permissions` | stable | `ad.iam.view_catalog` |
| EP-AD-011 | GET | `/platform/iam/permissions/{code}/holders` | stable | `ad.iam.view_catalog` |
| EP-AD-012 | GET | `/platform/iam/roles` | stable | `ad.iam.view_catalog` |
| EP-AD-013 | POST | `/platform/iam/roles` | stable | `ad.iam.role_create` |
| EP-AD-027 | POST | `/platform/iam/roles/preview` | stable | `ad.iam.view_catalog` |
| EP-AD-014 | POST | `/platform/iam/roles/{id}/approve` | stable | `ad.iam.role_approve` |
| EP-AD-015 | PUT | `/platform/iam/roles/{id}/permissions` | stable | `ad.iam.role_create` |
| EP-AD-016 | POST | `/platform/iam/assignments` | stable | `ad.iam.role_assign` |
| EP-AD-017 | DELETE | `/platform/iam/assignments` | stable | `ad.iam.role_assign` |
| EP-AD-018 | POST | `/platform/iam/simulate` | stable | `ad.iam.simulate` |
| EP-AD-019 | POST | `/platform/iam/temp-grants` | stable | `ad.iam.grant_temp` |
| EP-AD-020 | POST | `/platform/iam/temp-grants/{id}/approve` | stable | `ad.iam.grant_temp` |
| EP-AD-021 | GET | `/platform/iam/sod-rules` | stable | `ad.iam.sod_rules` |
| EP-AD-024 | POST | `/platform/iam/reviews` | stable | `ad.audit.review` |
| EP-AD-028 | GET | `/platform/iam/reviews/{id}` | stable | `ad.audit.review` |
| EP-AD-029 | POST | `/platform/iam/reviews/{id}/items/{itemId}/decide` | stable | `ad.audit.review` |
| EP-AD-025 | GET | `/platform/iam/approval-requests` | stable | `ad.iam.view_catalog` |
| EP-AD-026 | POST | `/platform/iam/approval-requests/{id}/decide` | stable | `ad.iam.role_approve` |

**`GET /platform/iam/permissions`** — paginated. `filter[system]`
(`platform|channel|warehouse|app`), `filter[severity]`, `search` (code **or** `name_ar`).

```jsonc
{ "code": "ad.iam.role_create", "name_ar": "…",
  "system": "platform", "module": "access", "severity": "critical",
  "dual_approval": true, "delegatable": false,
  "roles_count": 2, "users_count": 5 }
```

📌 **133 codes are seeded**; DOC-08 names 170. A code is added when a route needs it, never
speculatively — an unseeded code is not a bug.
📌 `dual_approval: true` means the action routes through the approval inbox instead of
executing inline.

**`GET .../permissions/{code}/holders`** — not paginated, not a list envelope.

```jsonc
{ "data": { "roles": [ {"id":3,"key":"iam_operator"} ],
            "users": [ {"id":9,"name":"#9"} ],
            "recent_usage": [ {"at":"…","actor":1,"action":"…"} ] } }
```

⚠️ **`users[].name` is literally `"#" + id`** — the query never joins the user tables.
Render it as an id or resolve names yourself. Capped at **50 users with no indication that
more exist**. Unknown code → 404.

**`GET /platform/iam/roles`** — paginated `{id, key, name, system, status, is_builtin,
permissions_count, users_count}`. `filter[system]`, `filter[status]`,
`sort=id|name|created_at` (prefix `-`), `search`. Never offer to delete `is_builtin` roles.

**`POST /iam/roles/preview`** — read-only despite being a POST. Call it on **every** change
to the permission selection, before create.

```jsonc
{ "permissions": ["ad.iam.role_create"] }
```
```jsonc
{ "data": { "summary_ar": "…", "sod_conflicts": [ ] } }
```
📌 `summary_ar` is a ready-to-render Arabic sentence — print it, do not compose your own.

**`POST /iam/roles`** → **201** `{ id, status: "draft", sod_conflicts: [] }`.
`{name, key (alpha_dash ≤64), system, description?, permissions?, copy_from_role_id?}`

⚠️ **The new role is `draft` and grants nothing.** It is inert until a *second* admin
approves it. Never show it as active, and make the pending state obvious in the list — a
role stuck in draft because nobody approved it is the most common IAM support ticket.

**`POST /iam/roles/{id}/approve`** — `{reason}` 3–500 → `{"status":"active"}`.
⚠️ **The creator cannot approve their own role** → `403 sod_violation`. Hide the button for
the creator, and when the 403 arrives explain that a second admin is required — never offer
a retry.

**`PUT /iam/roles/{id}/permissions`** — ⚠️ **a full replacement, not a delta**. Omitted
codes are revoked. `{permissions, reason}` → `{ changed: [...], sod_conflicts: [] }`.
Render the server's `changed`, not your own diff. A permission whose `system` mismatches
the role is 422. Note this is gated on `ad.iam.role_create`, not a separate edit permission.

**`POST /iam/assignments`** — bulk, **max 50** ids.
`{user_ids, role_id, expires_at?, reason}`

```jsonc
{ "data": { "assigned": [9],
            "rejected": [ {"user_id":10,"reason":"sod_violation"} ] } }
```
⚠️ **Partial success is normal and still returns 200.** Never report "done" off the status
code — render both lists with each rejection's reason.

**`DELETE /iam/assignments`** — ⚠️ **a DELETE with a body**: `{user_id, role_id, reason}`.
`fetch` and axios both need explicit configuration to send it; a silently dropped body
arrives as `422`.

**`POST /iam/simulate`** — "why can/can't this user do X".

```jsonc
{ "data": { "allowed": false,
    "decision_path": [
      {"layer":"guard","result":"pass","reason":"platform"},
      {"layer":"permission","result":"fail","reason":"role:viewer"},
      {"layer":"tenant","result":"pass","reason":"same channel"},
      {"layer":"sod","result":"pass","reason":"clear"},
      {"layer":"temp-grant","result":"skip","reason":"none"} ] } }
```
Five layers, always this order, `pass|fail|skip`. Render as a trace so the operator sees
*which* layer refused.
⚠️ **The `tenant` layer is hard-coded to `pass`** — it is not a real check yet. Say so in
the UI. `allowed` is `guard && (permission || temp-grant) && !sod`.

**`POST /iam/temp-grants`** — `{user_id, permission, duration_minutes (1–240), reason
(**≥ 20 chars**)}` → `{ id, status: "pending_approval", expires_request_at }`.
⚠️ Enforce the 20-character minimum in the form — it is the field users most often trip on.
📌 `expires_request_at` is the deadline for **approving**, not the grant's own expiry.

**`POST /iam/temp-grants/{id}/approve`** — `{decision: approve|reject, reason}`. Approve →
`{ granted_until }`; reject → `{ granted_until: null, status: "rejected" }`. Requester
cannot approve their own → `403 sod_violation`.

**`GET /iam/sod-rules`** — read-only, not paginated:
`{code, permission_a, permission_b, reason, exceptions}`. Load once and use it to explain a
`sod_violation` in the operator's own vocabulary. There is no create/update route.

**`GET /iam/approval-requests`** — paginated, `filter[status]`, `filter[type]`.
`{id, permission, action, requested_by, payload, status, created_at}`.
⚠️ `payload` is free-form JSON whose shape varies by `action` — render it generically.

**`POST /iam/approval-requests/{id}/decide`** — `{decision: approve|reject, reason}`

```jsonc
{ "data": { "status": "approved", "executed": true } }
```
⚠️ **`executed` is the field that matters, not `status`.** An approved request whose
execution failed returns `approved` with `executed: false`. Never render "applied" off
`status` alone. Deciding twice → `409 conflict`; approving your own → `403 sod_violation`.

**Access reviews** — `POST /iam/reviews` `{quarter, scope}` →
`{ campaign_id, items_count }`.
`GET /iam/reviews/{id}` — ⚠️ **not paginated**; every item arrives at once. Virtualise the
table rather than paging it. `decision: null` means undecided — drive progress off the null
count.
`POST /iam/reviews/{id}/items/{itemId}/decide` — ⚠️ the vocabulary here is
**`keep|revoke`**, while the approval inbox uses `approve|reject`. Two different dialogs;
do not share one component blindly.

### 4.4 Audit

| EP-ID | Method | Path | Stability | Permission |
|---|---|---|---|---|
| EP-AD-022 | GET | `/platform/audit` | stable | `ad.audit.view` |
| EP-AD-023 | POST | `/platform/audit/export` | stable | `ad.audit.export` |

**`GET /platform/audit`** — paginated. Filters go in a **`filter`** object:
`filter[actor]`, `filter[action]`, `filter[channel_id]`, `filter[date_from]`,
`filter[date_to]`.

```jsonc
{ "at": "…", "actor": 1, "action": "role.approved",
  "entity_type": "…", "entity_id": 7,
  "before": { }, "after": { }, "ip": "…", "impersonated": false }
```
`before`/`after` are arbitrary JSON and may both be null — render a generic diff.
`impersonated: true` deserves a visible badge.

**`POST /platform/audit/export`** — asynchronous → `{ "job_id": "…" }`.
⚠️ The body key is **`filters`** (plural) while the list reads **`filter`** — an easy
mistake when one filter-state object feeds both the table and its export button.
⚠️ **There is no job-status route.** Show "queued" and stop; do not poll, and do not block
the button on a completion you cannot observe. The export honours the filters you send, not
the page on screen.

### 4.5 Channels — ⚠️ `moving`

| EP-ID | Method | Path | Stability | Gate |
|---|---|---|---|---|
| EP-AD-050 | GET | `/admin/channels` | **moving** → `/platform/channels` | role `platform_admin` |
| EP-AD-051 | POST | `/admin/channels` | **moving** → `/platform/channels` | role `platform_admin` |
| EP-AD-052 | GET | `/admin/channels/{id}` | **moving** → `/platform/channels/{id}` | role `platform_admin` |
| EP-AD-062 | PUT | `/admin/channels/{id}` | **moving** → `/platform/channels/{id}` | role `platform_admin` |
| EP-AD-058 | DELETE | `/admin/channels/{id}` | **moving** → `/platform/channels/{id}` | role `platform_admin` |

Gated on the **role**, not a permission — so a 403 here carries **no `error.permission`**.

```jsonc
{ "id": 1, "name": "…", "slug": "acme-dist", "legal_name": null, "tax_number": null,
  "phone": null, "email": null, "status": "active", "settings": {},
  "created_at": "2026-09-01T10:00:00+00:00" }
```

⚠️ **`created_at` is UTC**, unlike every other timestamp on this guard. Handle it
explicitly.

`POST` requires `name` and `slug` (`alpha_dash`, unique); `status` is `active|suspended`.
⚠️ **`PUT` is a partial update** — fields are `sometimes`, so send only what changed. That
is the **opposite** of `PUT /platform/me`, which demands the whole object.
⚠️ **`DELETE` returns 204 with no body** — do not parse an envelope. It is a soft delete
with **no restore route**, so treat it as final.
⚠️ `GET /admin/channels` takes **no filters and no search** — `page` and `per_page` only.

Everything else about channels — provisioning, transitions, usage, limits, coverage, users,
warehouses, features, export, bulk plan, join applications — is **missing** (§7).

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
             "permission": "ad.iam.role_create", "details": { } } }
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
| `X-Channel-Id` | — | channel id | **yes — **only** `platform_admin` may use it** |
| `X-Client` | always | `platform-web` | **no** — ignored today |
| `X-App-Version` | always | semver | **no** — ignored today |

📌 `X-Channel-Id` is honoured **only** for `platform_admin`, which is exactly this guard's
seeded role. It sets the tenant for a request — useful when inspecting a channel's data.

⚠️ `X-Client` and `X-App-Version` are read by **no** middleware, so `426 upgrade_required`
cannot currently be triggered by version.

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

**Exempt paths — for this dashboard, exactly two:**

```
platform/auth/login
platform/auth/2fa/verify
```

Omit the header on those. Send it everywhere else, including logout.

📌 **The `requires_password_confirm` replay depends on this.** When a write returns 403,
confirm the password and then send the **same key and the same body**. A new key would
execute the write twice.

### 5.4 The error catalogue

| HTTP | `error.code` | What the dashboard does |
|---|---|---|
| 400 | `idempotency_key_required` | Your bug — you omitted the header |
| 401 | `unauthenticated` | No usable token → login |
| 401 | `token_revoked` | Token deleted or expired → drop it, login |
| 403 | `wrong_guard` | A live token from another guard — **do not** re-login |
| 403 | `insufficient_permission` | `error.permission` names the missing grant → hide the control |
| 403 | `requires_2fa` | Finish the 2FA challenge first |
| 403 | `requires_password_confirm` | Confirm the password, then **replay with the same idempotency key** |
| 403 | `sod_violation` | A **different** admin must act. Never retry |
| 409 | `conflict` | The approval request was already decided |
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

⚠️ **One exception:** `created_at` on the supply-channel resource (§4.5) is emitted in
**UTC**, not Damascus. Handle it explicitly.

### 5.6 Money

**Every amount is an integer in the smallest currency unit.** No floats anywhere.

- **SYP has 0 decimals**, so the integer *is* the displayed number. Never `/ 100`.
- Never compute a total locally and treat it as truth — re-read the server's total.
- Rounding happens on the server. If your arithmetic disagrees, yours is wrong.

Money barely appears on this guard.

### 5.7 Lists

| Param | Default | Notes |
|---|---|---|
| `page` | 1 | |
| `per_page` | **25** | **hard max 100 — a larger value is silently clamped**, not rejected |
| `filter[x]` | — | bracket syntax, e.g. `filter[status]=pending` |

⚠️ Two IAM endpoints are **not paginated** and return everything at once:
`permissions/{code}/holders` (capped at 50 users, silently) and `iam/reviews/{id}` (every
review item). Virtualise long review campaigns rather than paging them.

---
## 6. What to build, in order

| # | Screen | Endpoints | Notes |
|---|---|---|---|
| 1 | Login (+ 2FA branch) | `auth/login`, `auth/2fa/verify` | `challenge_token` in state only |
| 2 | App shell + nav | `permissions` **and** `roles` | channels nav gates on the **role** |
| 3 | My account | `/platform/me`, sessions | full object on PUT |
| 4 | Password change | `me/password` + confirm-password | replay with the **same** key |
| 5 | 2FA setup | `2fa/enable`, `2fa/confirm` | render the QR yourself; codes shown once |
| 6 | API tokens | `me/api-tokens` | password goes in `password_confirmation` |
| 7 | Permission catalog | `iam/permissions`, holders | `users[].name` is `"#id"` |
| 8 | Roles list | `iam/roles` | show `draft` prominently |
| 9 | Role builder | `roles/preview` → `roles` | preview on every change; lands as `draft` |
| 10 | Role approval | `roles/{id}/approve` | hide for the creator |
| 11 | Role permissions | `roles/{id}/permissions` | full replacement |
| 12 | Assignments | `iam/assignments` (POST/DELETE) | render `rejected`; DELETE needs a body |
| 13 | Simulate | `iam/simulate` | note the tenant layer is fake |
| 14 | Temp grants | `temp-grants`, approve | reason ≥ 20 chars |
| 15 | SoD rules | `iam/sod-rules` | read-only; use to explain 403s |
| 16 | Approval inbox | `approval-requests`, decide | read `executed`, not `status` |
| 17 | Access reviews | `reviews*` | unpaginated — virtualise; `keep\|revoke` |
| 18 | Audit log + export | `audit`, `audit/export` | `filter` vs `filters`; no job status |
| 19 | Channels CRUD | `/admin/channels` | ⚠️ **behind one constant** — it will move |

Do not build a dashboard with charts, any reference-data screen, or any of §5.

---

## 7. Not built — do not mock

Every path below **returns 404 today**. This guard has the largest missing surface of the
five apps: **SP-03 (32 endpoints) and SP-04 (22)** are entirely absent, so **0 of 47** of
their contract paths are live.

**Platform references — SP-03, none of it exists**

| Method | Path | EP |
|---|---|---|
| GET/POST | `/platform/refs/governorates` | EP-AD-030 / EP-AD-031 |
| PUT | `/platform/refs/governorates/{id}` | EP-AD-042A |
| PATCH | `/platform/refs/governorates/{id}/status` | EP-AD-043A |
| GET/POST | `/platform/refs/zones` | EP-AD-032 / EP-AD-033 |
| PUT | `/platform/refs/zones/{id}` | EP-AD-042B |
| PATCH | `/platform/refs/zones/{id}/status` | EP-AD-034 |
| — | activity types, root categories, sale units, equipment, currencies, FX | SP-03 |

⚠️ **`/api/v1/governorates` and `/api/v1/zones` do exist** — five routes each, full CRUD.
But they sit at the **root of `/api/v1`, outside the platform prefix, behind a multi-guard
list** (`platform,channel,warehouse,app`), which contradicts the prefix↔guard rule. They
are `moving` → `/platform/refs/*`.

They are callable with a platform token today. **Do not wire admin screens to them**
without agreeing it with the backend first: they will move, their guard will narrow, and
the response shape (a thin `GovernorateResource`/`ZoneResource`) is not the shape SP-03
specifies. If you must ship something now, hide it behind the same single constant as
`/admin/channels`.

**Channel administration — SP-04, beyond the five CRUD routes**

| Method | Path | EP |
|---|---|---|
| POST | `/platform/channels/{id}/retry-provisioning` | EP-AD-053 |
| POST | `/platform/channels/{id}/transition` | EP-AD-054 |
| PUT | `/platform/channels/{id}/limits` | EP-AD-055 |
| GET | `/platform/channels/{id}/usage` | EP-AD-056 |
| POST | `/platform/channels/{id}/export` | EP-AD-057 |
| GET | `/platform/channels/{id}/users` | EP-AD-063 |
| POST | `/platform/channels/{id}/manager/reset` | EP-AD-064 |
| GET/PUT | `/platform/channels/{id}/coverage` | EP-AD-065A/B |
| GET | `/platform/channels/{id}/warehouses` | EP-AD-066 |
| GET | `/platform/channels/{id}/features` | EP-AD-067 |
| POST | `/platform/channels/notify-managers` | EP-AD-068 |
| POST | `/platform/channels/{id}/plan` | EP-AD-102 |
| POST | `/platform/channels/bulk-plan`, `/bulk-plan/preview`, `/export` | EP-AD-059A/B/C |

So: **no provisioning polling, no `allowed_next` transitions, no usage, no limits, no
coverage editor, no join applications, no bulk plan.** The channel card is five plain CRUD
fields and nothing else.

**Also missing**

| Area | Note |
|---|---|
| `GET /platform/dashboard` | live GMV and charts. **Do not mock this** — a fabricated GMV figure is the worst possible placeholder |
| Notification campaigns, plan SKUs | SP-16 |
| Team CRUD, impersonation, support, feature flags | SP-16/17 |
| Job status for the audit export | you get a `job_id` with nothing to poll |
| Platform billing and reporting | SP-17, 63 endpoints |

**Rule for this dashboard:** if a path is not in §3, it is not live. Ask the backend for
the ticket rather than inferring a shape from a sibling route. Keep a route shell if it
helps, but disable submit and print no numbers.

---

## 8. Gotchas

| # | Where | Watch out |
|---|---|---|
| 1 | `/admin/channels` | **`moving`** → `/platform/channels`. Keep it behind one constant |
| 2 | `/admin/channels` | Gated by **role**, not permission — 403 carries no `error.permission` |
| 3 | `created_at` on a channel | **UTC**, while everything else is `Asia/Damascus` |
| 4 | `PUT /admin/channels/{id}` | **Partial** update — the opposite of `PUT /platform/me`, which needs the full object |
| 5 | `DELETE /admin/channels/{id}` | **204, no body.** Soft delete with no restore |
| 6 | `GET /admin/channels` | No filters, no search — `page`/`per_page` only |
| 7 | All writes | Idempotency key hashes the **body** too: same key + changed body = 409 |
| 8 | `requires_password_confirm` | Replay with the **same** key and body; a new key double-writes |
| 9 | Cross-guard token | **403 `wrong_guard`**, not 401 — re-login will not fix it |
| 10 | `expires_at` at login | Nominal; Sanctum expiration is `null`. Trust 401 |
| 11 | `POST /me/api-tokens` | Current password goes in `password_confirmation`; checked inline, **not** via the 15-min window |
| 12 | `2fa/enable` | `qr_svg` is an **empty placeholder** — render from `secret` |
| 13 | `2fa/confirm` | Recovery codes appear **once**, ever |
| 14 | `permissions/{code}/holders` | `users[].name` is `"#id"`; silently capped at 50 |
| 15 | `POST /iam/assignments` | Partial success returns **200** — read `rejected` |
| 16 | `DELETE /iam/assignments` | A **DELETE with a body** |
| 17 | `PUT /iam/roles/{id}/permissions` | Full replacement, not a delta |
| 18 | New roles | Land as **`draft`** and grant nothing until a second admin approves |
| 19 | Self-approval | `403 sod_violation` on roles, grants and approvals. Never retry |
| 20 | `approval-requests/{id}/decide` | Read **`executed`**, not `status` |
| 21 | Review items | Vocabulary is `keep\|revoke`, not `approve\|reject` |
| 22 | `GET /iam/reviews/{id}` | Unpaginated — virtualise long campaigns |
| 23 | `simulate` | The `tenant` layer is hard-coded `pass` |
| 24 | `temp-grants` | `reason` needs **≥ 20 characters**; cap is 240 minutes |
| 25 | `audit/export` | Body key is `filters`; the list uses `filter`. No status route |
| 26 | `platform_admin` | `Gate::before` grants everything — permission checks on that role are vacuous |
| 27 | `/governorates`, `/zones` | Live at the **v1 root** on a multi-guard list, `moving` → `/platform/refs/*`. Do not build admin screens on them yet |
| 28 | `not_found` | Also used for out-of-tenant access. Never render "forbidden" |
