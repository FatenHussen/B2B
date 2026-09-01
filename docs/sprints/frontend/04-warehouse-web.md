# Warehouse web — Layer 1

| | |
|---|---|
| **Product** | Warehouse floor console |
| **DOC** | DOC-12D |
| **Repo** | `apps/warehouse-web` |
| **Surface** | Web or locked desktop browser, RTL, large hit targets |
| **Guard** | `warehouse` |
| **`X-Client`** | `warehouse-web` |
| **`X-Device-Id`** | Required (shared workstation) |
| **Shared kit** | [`00-shared-api-client.md`](./00-shared-api-client.md) |
| **Backend** | SP-01 (`POST /warehouse/auth/device-login` only) |
| **Install API first** | [00-install-the-api.md](./00-install-the-api.md) |
| **Dev port** | `3002` |

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

Register a device (no UI for this):

```bash
php artisan tinker --execute="Modules\Tenancy\Domain\Models\Warehouse::query()->firstOrCreate(['channel_id'=>1], ['name'=>'مستودع تجريبي','status'=>'active']);"
php artisan warehouse:register-device wh-demo-token 4821 1 1 --label=dev-pc
```

```bash
npx create-next-app@15 apps/warehouse-web --typescript --app --tailwind --eslint --src-dir
cd apps/warehouse-web
npm install
```

`apps/warehouse-web/.env.local`:

```
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_X_CLIENT=warehouse-web
NEXT_PUBLIC_DEVICE_TOKEN=wh-demo-token
PORT=3002
```

Never show `DEVICE_TOKEN` in the production UI.

```bash
npx next dev -p 3002
```

PIN on the pad: `4821`. Shared HTTP: [00-shared-api-client.md](./00-shared-api-client.md).

---

## 1. What this surface is in L1

A **device lock**. The warehouse is a pre-registered terminal plus a 4-digit PIN — not a WhatsApp user.

It is **not** picking queues, packing, cycle count, or inbound returns.

## 2. Device provisioning (not a product screen)

There is **no** “register device” UI.

Ops installs `device_token` after backend `warehouse:register-device` (env / local config). The UI reads it and **must not** display it in production.

## 3. Routes

| Path | Screen |
|---|---|
| `/pin` | PIN pad |
| `/home` | Empty floor |

Do not add `/queues` or `/picking` to the router.

## 4. Screens

### 4.1 PIN — `FE-WH-PIN`

`POST /warehouse/auth/device-login` · EP-WH-001

```json
{ "device_token": "{{installed}}", "pin": "4821" }
```

- PIN is exactly four digits (`/^\d{4}$/`). Large numeric pad (gloves).
- **401:** shake, clear PIN. Do not say whether the token or the PIN failed.
- **200:** store Bearer, show `warehouse.name`, go `/home`. Persist `permissions[]` for later layers; do not build those screens now.

### 4.2 Home — `FE-WH-HOME`

Copy: queues unlock in Layer 3. **No** `to_pick` counts. **Do not** call `GET /warehouse/queues`.

### 4.3 Sign out

Clear the Bearer. **Keep** `device_token` on the machine.

## 5. Out of scope

Picking lists, packing, handover, returns inbound, inventory adjustments.

## 6. Definition of done

Correct PIN → warehouse name on home. Wrong PIN → stay on pad. No queue route in the router. No device-registration form.
