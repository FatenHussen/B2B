# Field rep mobile app — Layer 1

| | |
|---|---|
| **Product** | B2B field representative |
| **DOC** | DOC-12B |
| **Repo** | `apps/rep` |
| **Platforms** | Android / iOS |
| **Guard** | `app` |
| **`X-Client`** | `rep-android` · `rep-ios` |
| **Shared kit** | [`00-shared-api-client.md`](./00-shared-api-client.md) |
| **Backend** | SP-01 · SP-03 (activity types / zones) · SP-04 (channel invite id) |
| **Install API first** | [00-install-the-api.md](./00-install-the-api.md) |

---

## 0. Install this app

```bash
# API
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Demo channel slug `demo-channel` (usually id `1`). OTP: `storage/logs/laravel.log`.

```bash
flutter create --org sy.b2b --project-name rep_app apps/rep
cd apps/rep
flutter pub get
```

```
API_BASE_URL=http://127.0.0.1:8000/api/v1
X_CLIENT=rep-android
```

Android emulator: `http://10.0.2.2:8000/api/v1`.

Deep link for register (after API is up):

```
b2b-rep://register?channel_id=1&channel_name=Demo%20Channel
```

```bash
flutter run
```

Separate binary from the retailer app. Shared HTTP: [00-shared-api-client.md](./00-shared-api-client.md).

---

## 1. What this app is in L1

Prove the rep’s phone, attach them to **one supply channel and its zones**, wait for review.

It is **not** a multi-channel catalog, customer book, shop cart, handover, wallet, or delivery map.

Ship as a **separate** binary from the retailer app (icon, `X-Client`). No in-app “switch to retailer”. The API rejects the same phone on two `kind`s.

## 2. Screen map

Same graph as the retailer app, with a different register form and empty home copy.

```
Splash → Phone → OTP → Register (invite) → Waiting
                 └→ Empty home (approved)
```

## 3. Screens

### 3.1 Phone / OTP — `FE-RP-PHONE` · `FE-RP-OTP`

Same contracts as the retailer app:

- `POST /public/auth/request-otp` · EP-CM-001  
- `POST /public/auth/verify-otp` · EP-CM-002  
- `POST /public/auth/resend-otp` · EP-CM-003  

Set `client` to `rep-android` or `rep-ios`. Six-digit OTP, resend countdown, `device_id` / `device_name` / `platform` on verify.

### 3.2 Register — `FE-RP-REGISTER`

Requires `registration` Bearer.

**Load** `GET /public/refs` for activity types and zones.

| UI | API key | Required |
|---|---|---|
| Name | `name` | Yes |
| Supply channel | `supply_channel_id` | Yes — **invite only** |
| Activity type | `activity_type_id` | Yes |
| Zones | `zone_ids` | Yes, ≥ 1 |
| Note | `note` | No |

#### Channel source (hard rule)

`GET /public/refs` does **not** list channels (REQ-IN-06). Do **not** call `/platform/channels`.

L1 UX:

1. **Invite deep link** issued after the platform creates the channel:  
   `b2b-rep://register?channel_id={id}` (optional `channel_name` for display).  
   Lock the channel field; user cannot change it.
2. Opened without `channel_id`: copy only — “Use the invite link from your company.” No channel search.

Zones come from public refs. The server returns 422 if a zone is outside coverage, 409 if the channel is not `active`. Show `error.message` as sent.

**API:** `POST /app/rep/register` · EP-RP-001 · Bearer + idempotency.

**On 200:** store `data.token`; `rep.status = pending_review` → Waiting.

### 3.3 Waiting / empty home — `FE-RP-WAIT` · `FE-RP-HOME-EMPTY`

Same session rules as retailer: `GET /app/session` (EP-CM-004), logout `POST /app/auth/logout` (EP-CM-005).

Empty home: rep name + channel name from the register payload + “Tasks unlock after approval.” No product list.

### 3.4 Logout

Wipe token and local cache. Same as retailer (REQ-CM-056).

## 4. Out of scope

`GET /app/rep/products`, customers, zone shops, carts, assignments, handover, wallet, live location.

## 5. Definition of done

1. Submit is impossible without `channel_id`.  
2. Zones outside coverage keep the form and show the server field error.  
3. Invite → OTP → waiting completes without a platform token on the device.

OTP can ship on SP-01. Register waits for refs (SP-03) and a real `channel_id` from platform provisioning (SP-04).
