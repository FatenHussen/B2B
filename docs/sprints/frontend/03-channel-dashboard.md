# Channel dashboard (web) — Layer 1

| | |
|---|---|
| **Product** | Supply-channel operations console |
| **DOC** | DOC-12C |
| **Repo** | `apps/channel-web` |
| **Surface** | Web, RTL |
| **Guard** | `channel` |
| **`X-Client`** | `channel-web` |
| **Shared kit** | [`00-shared-api-client.md`](./00-shared-api-client.md) |
| **Backend** | SP-01 (`/channel/auth/*` only) |
| **Install API first** | [00-install-the-api.md](./00-install-the-api.md) |
| **Dev port** | `3001` |

---

## 0. Install this dashboard

```bash
# API
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Seeded channel user: phone **`+963900000001`**. After request-otp, copy the code from `storage/logs/laravel.log`.

```bash
npx create-next-app@15 apps/channel-web --typescript --app --tailwind --eslint --src-dir
cd apps/channel-web
npm install
```

`apps/channel-web/.env.local`:

```
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_X_CLIENT=channel-web
PORT=3001
```

Add `localhost:3001` to API `.env` `SANCTUM_STATEFUL_DOMAINS` if you use cookies; **L1 uses Bearer tokens**, not Sanctum SPA cookies.

```bash
npx next dev -p 3001
```

Open `http://localhost:3001`. Shared HTTP: [00-shared-api-client.md](./00-shared-api-client.md).

---

## 1. What this dashboard is in L1

A **sign-in shell** for the distributing company: OTP, optional channel picker, empty office.

It is **not** catalog admin, pricing, orders, reps operations, finance, or a KPI board.

## 2. Routes (build these only)

| Path | Screen |
|---|---|
| `/login/phone` | Phone |
| `/login/otp` | OTP |
| `/select-channel` | Picker when `channels.length > 1` |
| `/home` | Empty office |

Do not register `/catalog`, `/orders`, `/reps` except as disabled nav labels with **no** API calls. A 404 is acceptable.

## 3. Screens

### 3.1 Phone — `FE-CH-PHONE`

`POST /channel/auth/request-otp` · EP-CH-001

```json
{ "phone": "+963944000000" }
```

Do **not** send `purpose` (not in this catalog row). Store `otp_id` → OTP.

### 3.2 OTP — `FE-CH-OTP`

`POST /channel/auth/verify-otp` · EP-CH-002

```json
{ "otp_id": "{{otp_id}}", "code": "482193" }
```

**On 200:** persist `token` and `permissions[]`.

| `channels` | Next |
|---|---|
| length = 1 | Set `X-Channel-Id`, go `/home` |
| length > 1 | `/select-channel` |
| empty / 403 | Show server message; do not invent a channel |

### 3.3 Channel picker — `FE-CH-PICK`

Cards from `data.channels`. Selection stores `X-Channel-Id` for every later request until the user switches (header or menu).

### 3.4 Shell — `FE-CH-SHELL`

- Sidebar from `permissions[]` only.
- L2+ items (`sc.catalog.*`, `sc.orders.*`, numeric `sc.dashboard.view`): visible **disabled** with “Available after operations go live”, or omitted. **No page, no fetch.**
- `/home`: selected channel name, user identity. No charts, no GMV.

### 3.5 Logout — `FE-CH-LOGOUT`

Catalog L1 has **no** channel logout endpoint. Clear token and `X-Channel-Id` locally; redirect to `/login/phone`.

## 4. Out of scope

`/channel/brands`, `/channel/products`, orders, stock, finance, dashboard widgets.

## 5. Definition of done

A manager completes OTP, picks a channel if they have two, lands on an empty office, signs out. DevTools shows **zero** calls to `/channel/products` or `/channel/brands`.
