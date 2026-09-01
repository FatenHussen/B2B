# Retailer mobile app — Layer 1

| | |
|---|---|
| **Product** | B2B retailer (shop owner) |
| **DOC** | DOC-12A |
| **Repo** | `apps/retailer` |
| **Platforms** | Android / iOS |
| **Guard** | `app` |
| **`X-Client`** | `retailer-android` · `retailer-ios` |
| **Shared kit** | [`00-shared-api-client.md`](./00-shared-api-client.md) |
| **Backend** | SP-01 (OTP + register) · SP-03 (`/public/refs` for the form) |
| **Install API first** | [00-install-the-api.md](./00-install-the-api.md) |

---

## 0. Install this app

```bash
# API (from B2BPROJECT)
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Confirm `http://127.0.0.1:8000/api/v1/health`. OTP codes: `storage/logs/laravel.log` (`OTP_CHANNEL=log`).

```bash
# App (sibling or apps/retailer)
flutter create --org sy.b2b --project-name retailer_app apps/retailer
cd apps/retailer
flutter pub get
```

Create `apps/retailer/.env` or a Dart config:

```
API_BASE_URL=http://127.0.0.1:8000/api/v1
X_CLIENT=retailer-android
```

Android emulator host: `http://10.0.2.2:8000/api/v1`.

```bash
flutter run
```

Use the shared kit ([00-shared-api-client.md](./00-shared-api-client.md)). Then build only the screens below.

---

## 1. What this app is in L1

A shop **identity** product: prove the Syrian mobile number, complete the store file, wait for review.

It is **not** a shop, catalog, cart, order tracker, or loyalty wallet.

## 2. Screen map (build these only)

```
Splash
  no token                    → Phone
  registration token          → Register
  status pending_review       → Waiting
  status rejected             → Rejected
  full token + active         → Empty home

Phone → OTP → verify
  is_new_user or profile_completed=false → Register
  returning complete profile             → Empty home or Waiting (from session)
Register → Waiting
Waiting / Rejected / Empty home → Logout → Phone
```

No product tabs (Home / Orders / Account). Account in L1 = phone display + logout.

## 3. Screens

### 3.1 Splash — `FE-RT-SPLASH`

On cold start, if a token exists call `GET /app/session` (EP-CM-004) and route with §3.6. If none, go to Phone.

### 3.2 Phone — `FE-RT-PHONE`

| | |
|---|---|
| **Purpose** | Collect a Syrian mobile and request WhatsApp OTP |
| **API** | `POST /public/auth/request-otp` · EP-CM-001 |

**UI**

- One phone field. Accept `09XXXXXXXX` or `+9639XXXXXXXX`; send as typed (server normalises).
- One primary button: send code.
- `purpose`: `register` from a “Sign up” entry, `login` from “Sign in”. Single-screen apps send `login`; the server returns `is_new_user`.

**Body**

```json
{ "phone": "+963933000000", "purpose": "login", "client": "retailer-android" }
```

**On 200:** store `otp_id`, `expires_in` (300), `resend_after` (60), `channel_used` → OTP.  
**On 422:** errors under the phone field. **On 429:** disable the button.

### 3.3 OTP — `FE-RT-OTP`

| | |
|---|---|
| **API verify** | `POST /public/auth/verify-otp` · EP-CM-002 |
| **API resend** | `POST /public/auth/resend-otp` · EP-CM-003 |

**UI**

- Six numeric boxes. Ignore paste longer than 6.
- Countdown from `resend_after`. At 0, enable resend.
- Default resend channel `whatsapp`; user may pick `sms`.

**Verify body**

```json
{
  "otp_id": "{{otp_id}}",
  "code": "482193",
  "device_id": "{{X-Device-Id}}",
  "device_name": "Redmi Note 13",
  "platform": "android"
}
```

**On 401 `otp_invalid`:** clear boxes; do not request a new OTP automatically.  
**On 200:** persist `token`. If `is_new_user` or `profile_completed === false` → Register. Else → session router.

### 3.4 Register shop — `FE-RT-REGISTER`

Requires an `app` token with `registration` ability. No token → Phone.

**Before paint:** `GET /public/refs` (EP-PB-001). Bind `activity_types`, `root_categories`, `equipments`, `governorates`, and `zones` where `zone.governorate_id` matches the selected governorate.

| UI label | API key | Required | Notes |
|---|---|---|---|
| Owner name | `owner_name` | Yes | |
| Shop name | `shop_name` | Yes | |
| Activity type | `activity_type_id` | Yes | From refs |
| Categories | `category_ids` | Yes, ≥ 1 | Multi; prefer activity suggestions first |
| Equipment | `equipment_ids` | No | Multi |
| Governorate | `governorate_id` | Yes | Clears zone on change |
| Zone | `zone_id` | Yes | Zones of that governorate only |
| Map pin | `lat`, `lng` | No | GPS if permitted; never block submit |
| Address | `address` | No | |

**No email. No password.**

**API:** `POST /app/retailer/register` · EP-RT-001 · Bearer + idempotency.

**On 200:** replace the token with `data.token`. Status is `pending_review` → Waiting.  
**On 422:** keep the form; show `details` (e.g. zone not in governorate).

### 3.5 Waiting / rejected — `FE-RT-WAIT`

| Status | UI |
|---|---|
| `pending_review` | “طلبك قيد المراجعة”, phone, refresh (session), logout |
| `rejected` | Rejection copy if the session provides it. No silent re-register on the same token. WhatsApp support as static text is allowed |

### 3.6 Session router — `FE-RT-SESSION`

`GET /app/session` (EP-CM-004) whenever a token exists.

| Result | Route |
|---|---|
| 401 | Phone |
| `profile_completed === false` | Register |
| pending profile | Waiting |
| complete + active | Empty home |

Store `feature_flags`, `sync_cursor`, `legal` but do not build legal/offline products in L1. If `requires_legal_accept` is true, a blocking copy is enough — no extra legal APIs. Do **not** show `points` / `tier` (SP-15) even if catalog examples include them.

### 3.7 Empty home — `FE-RT-HOME-EMPTY`

Shop name + copy: products appear after approval and catalog (L2). No fake grid. No search. One surface.

### 3.8 Logout — `FE-RT-LOGOUT`

`POST /app/auth/logout` (EP-CM-005). Wipe token, refs cache, local files (REQ-CM-056). Local wipe even if the network call fails.

## 4. Out of scope

Retailer home feed, favourites, cart, orders, tracking, payments, loyalty, channel names on products.

## 5. Definition of done

Manual walkthrough:

1. Invalid phone → Arabic 422 on the field.  
2. New number → OTP → form → waiting.  
3. Existing approved shop → OTP → empty home.  
4. Logout returns to phone; OS back must not restore the register form with a live session.

Blocked on SP-03: shop form cannot close without `/public/refs`. OTP can ship on SP-01.
