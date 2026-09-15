# Channel Web — frontend handoff (staging)

Short guide for the channel dashboard team. Full contract and endpoint reference:
[`docs/DocsLast/apps/channel-web.md`](../../../DocsLast/apps/channel-web.md).

Updated: **2026-09-14** after staging was reset onto branch `work/be-t01` and
`migrate:fresh --seed` was run successfully.

---

## 1. Point the app at staging

| | |
|---|---|
| Base URL | **`https://api.sentraxsy.com/api/v1`** |
| Health check | `GET https://api.sentraxsy.com/api/v1/health` |
| Package path | `apps/channel-web` |
| Dev port | **3001** |
| Guard | `channel` |
| Header | `X-Client: channel-web` (logging only) |

```dotenv
VITE_API_BASE_URL=https://api.sentraxsy.com/api/v1
VITE_X_CLIENT=channel-web
```

### Common mistakes

| Wrong | Why |
|---|---|
| `https://apisentraxsy.com/...` | Missing the dot — must be `api.sentraxsy.com` |
| Opening `/api/v1` alone | Not a route → `not_found` |
| Opening `/` on the API host | Shows the Laravel welcome page — ignore it |
| Keeping an old Bearer token after a DB reset | → `token_revoked` |

---

## 2. Login (OTP)

Channel auth is passwordless.

```http
POST /api/v1/channel/auth/request-otp
{ "phone": "+963900000001" }

POST /api/v1/channel/auth/verify-otp
{ "otp_id": "<from previous>", "code": "000000", "device_id": "<uuid>", ... }
```

On staging, **OTP bypass is on**. Send any 6-character code (use `000000`).

Store the returned token as a **Bearer** token for the `channel` guard
(`sessionStorage` / your client store). Clear it on logout.

### If you see `token_revoked`

The database was wiped and reseeded. Old tokens are dead.

1. Log out
2. Clear site storage / cookies for the dashboard origin
3. Request OTP + verify again

Do **not** keep retrying with the old token.

---

## 3. Seed accounts (demo channel)

Channel slug: **`demo-channel`**

| Role | Phone | Notes |
|---|---|---|
| Channel manager | **`+963900000001`** | Primary account for the dashboard |
| Sales manager | `+963900000002` | |
| Catalog manager | `+963900000003` | |
| Accountant | `+963900000004` | |

All use the same OTP flow. Manager has the broadest permission set.

Platform (separate dashboard, not this app): `admin@platform.sy` / `password`.

---

## 4. What is already seeded

After the 2026-09-14 reseed, staging has working demo data for:

- Channel settings, coverage zones, warehouses
- Catalog (products / variants)
- Pricing and promotions
- People (retailers + reps)
- Orders (`sub-orders`) and inventory demo rows

You should see data on lists once authenticated with a fresh token.

Shared reference reads (with a channel token):

- `GET /governorates`
- `GET /zones`
- `GET /currencies`

---

## 5. HTTP contract (channel)

| | |
|---|---|
| Success | `{ "data": ..., "meta": { "server_time": "..." } }` |
| List meta | `page`, `per_page`, `total`, `last_page` |
| Error | `{ "error": { "code": "...", "message": "...", "details": {} } }` |
| Auth header | `Authorization: Bearer <token>` |
| Language | `Accept-Language` is **ignored** — API messages are English |
| Money | integer minor units; never floats |
| Writes | send `X-Idempotency-Key` on every mutating call |

Foreign / out-of-tenant ids return **404** `not_found`, not 403.

---

## 6. Build order

1. Login (request-otp → verify-otp) + token storage + auth gate
2. Shell: channel profile `GET /channel`, permissions, nav
3. Sub-orders list + detail
4. Catalog products
5. Pricing / offers
6. Inventory
7. Returns / settings as needed

Exact paths, query params and response shapes: see
[`channel-web.md`](../../../DocsLast/apps/channel-web.md) §4–§6.

---

## 7. Known issues on staging

Check the live doc before treating these as frontend bugs:

- Some list endpoints were previously returning **500** for every caller
  (`GET /channel/sub-orders`, inventory levels/movements). Re-probe after this reseed;
  if still red, it is a backend bug — do not mock around it.
- Staging is **shared**. Data changes when anyone reseeds or edits the demo channel.
- Domains such as `channel.sentraxsy.com` need CORS / Sanctum stateful domains set on
  the API `.env`. Localhost `3001` is the supported CORS origin today.

---

## 8. Quick verification checklist

```bash
curl -s https://api.sentraxsy.com/api/v1/health
```

Then in the app:

1. Login as `+963900000001` with code `000000`
2. Confirm `GET /channel` returns the demo channel
3. Confirm a list call (e.g. products or sub-orders) returns `200` with `data`
4. If you get `token_revoked` → clear storage and login again
```
