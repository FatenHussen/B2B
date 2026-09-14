# B2B API — live routes only

Generated from `php artisan route:list`, **not** from the API catalog. Every request here
is registered and callable today. Nothing in this collection 404s because it was never
built.

Verified 2026-09-08 · 178 requests.

> The older `b2b-api.postman_collection.json` holds 775 requests generated from the
> catalog. Most of those are **not implemented** and return 404. Use this collection to
> test what exists; use that one to see what is planned.

## Setup — two minutes

1. Import this collection **and** an environment from `docs/api/environments/`.
2. Select the environment matching the client you are testing.
3. `baseUrl` is the **host only** — `http://127.0.0.1:8000`, no `/api/v1`. Each request
   carries `api/v1` in its own path, so the existing environment files work unchanged.
4. Run a login request. **The token is captured automatically** into `{{token}}` by a test
   script; every other request already sends `Authorization: Bearer {{token}}`.

## Getting a token per guard

| Guard | Request | Note |
|---|---|---|
| `app` (retailer / rep) | `00. Shared` → `01. Public OTP` → request-otp, then verify-otp | code is in `storage/logs/laravel.log` |
| `channel` | `03. Channel dashboard` → `00. Auth` | seeded phone `+963900000001` |
| `warehouse` | `04. Warehouse dashboard` → `00. Device login` | register a device first (CLI) |
| `platform` | `05. Platform admin` → `00. Auth` | `admin@platform.sy` / `password` |

OTP codes are written to the log, not sent:

```bash
tail -f storage/logs/laravel.log | grep OTP
```

`request-otp` saves `{{otp_id}}` automatically, so verify-otp works without copying.

## Rules baked into every request

- **`X-Idempotency-Key`** is set to `{{$guid}}` on every write **except** the 8 exempt
  credential paths. Postman regenerates it per send — which is correct for testing, but
  **wrong for a real client**, where the key must stay stable across retries of one intent.
- **`Authorization: Bearer {{token}}`** is present on every guarded request.
- **`Accept-Language`**, **`X-Client`**, **`X-App-Version`** come from the environment.
  Note the server currently reads only `Accept-Language` — the other two are for logs.
- **`X-Device-Id`** is sent on app, public and warehouse routes (OTP rate limiting uses it).

## Folder structure

```
00. Shared — public & app       health, public OTP, app session, pricing, offers
01. Retailer app                auth, catalog, cart, orders, receiving, returns
02. Field rep app               duty/zones/customers, catalog, cart, assignments,
                                warehouse pickup, delivery, returns
03. Channel dashboard           auth, settings, zones, catalog, pricing, offers,
                                inventory, sub-orders, returns
04. Warehouse dashboard         device login, queues, picking, packing, handover,
                                receiving, stocktake, return sorting
05. Platform admin              auth, my account, IAM, audit, channels
06. Reference (MOVING paths)    governorates & zones
```

Each request's **description** carries its EP-ID, guard, permission or role, whether an
idempotency key is required, an example response, and its error codes.

## Stability

Every request description names one of:

| Value | Meaning |
|---|---|
| `stable` | registered at the path the catalog specifies |
| `MOVING -> …` | registered, but at a temporary path — it will move |
| `live, not in catalog` | callable, but the catalog has not caught up |

⚠️ **21 requests are `MOVING`**: `/admin/channels` (→ `/platform/channels`, six requests —
the five channel CRUD routes and `POST …/{id}/transition`), `/governorates`, `/zones` and
`/currencies` (→ `/platform/refs/*`). Anything built against them will need rework. They are
grouped so you can see them at a glance, and each is meant to sit behind one base-path
constant in a client.

## Path variables

Requests with `{{id}}`, `{{subOrderId}}`, `{{handoverId}}` etc. expect you to set those in
the environment or edit them inline. They are left as variables rather than hardcoded ids
so a folder can be run in order.

## Regenerating

```bash
php artisan route:list --json > docs/api/.live-routes.json
php docs/api/generate-live-postman.php
```

Re-run after any sprint merge. The generator reads `route:list` for the routes and the
catalog only for example bodies and EP-IDs — so a newly built endpoint appears
automatically, and one that was never built never appears.

## What is NOT here

180 catalogued endpoints have no route: all money (SP-13), all offline sync (SP-14),
content and loyalty (SP-15), and every platform reference screen (SP-03). They are absent
by design — see `docs/DocsLast/` for what that blocks per app.
