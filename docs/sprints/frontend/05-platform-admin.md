# Platform admin dashboard (web) — Layer 1

| | |
|---|---|
| **Product** | Super-admin / platform console |
| **DOC** | DOC-12E + DOC-07 |
| **Repo** | `apps/platform-web` |
| **Surface** | Web, RTL |
| **Guard** | `platform` |
| **`X-Client`** | `platform-web` |
| **Shared kit** | [`00-shared-api-client.md`](./00-shared-api-client.md) |
| **Backend** | SP-01 account · SP-02 IAM · SP-03 refs · SP-04 channels |
| **Install API first** | [00-install-the-api.md](./00-install-the-api.md) |
| **Dev port** | `3000` |

This is the **largest** L1 frontend. Other products only sign in; this one operates the platform.

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

Seeded login (local):

```
email:    admin@platform.sy
password: password
```

2FA is off until the admin enables it in `/me`. First login should return a Bearer token (no challenge).

```bash
npx create-next-app@15 apps/platform-web --typescript --app --tailwind --eslint --src-dir
cd apps/platform-web
npm install
```

`apps/platform-web/.env.local`:

```
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_X_CLIENT=platform-web
PORT=3000
```

API `.env` already lists `SANCTUM_STATEFUL_DOMAINS=localhost:3000`. L1 still authenticates with **Bearer**, not cookie sessions.

```bash
npx next dev -p 3000
```

Open `http://localhost:3000`. Shared HTTP: [00-shared-api-client.md](./00-shared-api-client.md). Ready routes: [`../L1-ready-apis.md`](../L1-ready-apis.md).

IAM/refs/channels that the API has not shipped yet: keep the route shell, disable submit, do not mock GMV.

---

## 1. What this dashboard is in L1

Identity for admins, IAM, Syrian reference data, supply-channel lifecycle.

It is **not**: live GMV dashboard (SP-16), full billing plans (SP-17), notification campaigns (SP-14), full platform team CRUD (SP-17).

## 2. Information architecture

Show a nav group **only** if `can(permission)`:

| Nav | Route | Minimum permission |
|---|---|---|
| Overview | `/` | Any signed-in admin — welcome, **no charts** |
| Channels | `/channels` | `ad.channels.view` |
| Join applications | `/channels/applications` | `ad.channels.view` |
| References | `/refs/*` | `ad.refs.view` (currencies: `ad.refs.currency`) |
| Access | `/iam/*` | `ad.iam.view_catalog` |
| Audit | `/audit` | `ad.audit.view` |
| My account | `/me` | Every admin |

Do **not** add an operations dashboard that calls `GET /platform/dashboard`.

Tables: pagination, search, `per_page`. Loading / empty / envelope-error states on every list.

## 3. Cross-cutting dialogs

| Pattern | Behaviour |
|---|---|
| Password confirm (15 min) | HTTP 403 `requires_password_confirm` or `crit` routes → modal → `POST /platform/auth/confirm-password` `{ password }` (EP-AD-005) → replay |
| Written `reason` | Required on ref/channel mutations that send `reason` in the catalog — not an optional note |
| Dual approval | `draft`, `pending`, or `deletion_request_id` → “Sent for approval” + inbox link. Never show “Deleted” before execute |
| Background jobs | `job_id` → “Running in the background”. Do not spin on the same HTTP call |

## 4. Auth and my account — SP-01

### 4.1 Login — `FE-AD-LOGIN`

`POST /platform/auth/login` · EP-AD-001 · `{ email, password }`

If `requires_2fa`, keep `challenge_token` in **screen memory only** (not long-lived storage) → `/login/2fa`.  
401: generic failure; do not reveal whether the email exists.

### 4.2 TOTP — `FE-AD-2FA`

`POST /platform/auth/2fa/verify` · EP-AD-002 · `{ challenge_token, code }`

On 200: store `token`, `user.roles`, `user.permissions`, `expires_at` → `/`.  
401 `otp_invalid` on the code field.

### 4.3 Boot / logout — `FE-AD-BOOT`

- Boot: `GET /platform/auth/me` (EP-AD-004) and/or `GET /platform/me` (EP-AD-159A).  
- Logout: `POST /platform/auth/logout` (EP-AD-003) + clear storage.

### 4.4 My account `/me` — `FE-AD-ME`

| Tab | API |
|---|---|
| Profile | `PUT /platform/me` `{ name, phone }` · EP-AD-159B |
| Password | `PUT /platform/me/password` `{ current_password, password, password_confirmation }` · EP-AD-159C · crit |
| Sessions | `GET /platform/auth/sessions` · `DELETE .../sessions/{id}` · EP-AD-006/007. Mark `current`. Revoking current → login |
| 2FA | Enable: `POST /platform/me/2fa/enable` show `qr_svg` / `secret` → `POST .../2fa/confirm` `{ code }`. Show `recovery_codes` **once**. Later `GET .../recovery-codes` is `codes_remaining` only · EP-AD-159D/E/F |
| API tokens | List EP-AD-159G. Create: name + `password_confirmation`; secret shown once · EP-AD-159H. Delete EP-AD-159I |

## 5. IAM — SP-02

### 5.1 Permission catalog — `FE-AD-IAM-PERMS`

`GET /platform/iam/permissions` · filters `system`, `severity`.  
Columns: code, Arabic name, system, module, severity, dual flag, role/user counts.  
Row → `GET /platform/iam/permissions/{code}/holders`.

### 5.2 Roles — `FE-AD-IAM-ROLES`

- List `GET /platform/iam/roles` (`filter[system]`). Builtin roles not deletable in UI.  
- Create: name, `key`, `system`, description, permissions, optional `copy_from_role_id`.  
  Preview first: `POST /platform/iam/roles/preview` → `summary_ar`, `sod_conflicts`.  
  Then `POST /platform/iam/roles` → status **`draft`**, not `active`.  
- Approve (another user): `POST /platform/iam/roles/{id}/approve` `{ reason }`. Creator → 403.  
- Replace permissions: `PUT .../permissions` `{ permissions, reason }`.

### 5.3 Assignments — `FE-AD-IAM-ASSIGN`

`POST /platform/iam/assignments` max 50 `user_ids`. Show `assigned` vs `rejected`.  
Revoke: `DELETE` same path `{ user_id, role_id, reason }`.

### 5.4 Simulate — `FE-AD-IAM-SIM`

Form: `user_type`, `user_id`, `permission`, `resource_type`, `resource_id` → `decision_path` layers. Read-only.

### 5.5 Temp grants — `FE-AD-IAM-TEMP`

`duration_minutes ≤ 240`, `reason` ≥ 20 characters → `pending_approval`.  
Approve: `{ decision, reason }`.

### 5.6 SoD — `FE-AD-IAM-SOD`

`GET /platform/iam/sod-rules`. Read-only in L1.

### 5.7 Dual-approval inbox — `FE-AD-IAM-INBOX`

`GET /platform/iam/approval-requests?filter[status]=pending`.  
Decide: `POST .../{id}/decide` `{ decision: approve\|reject, reason }`. Approver ≠ requester.

### 5.8 Audit — `FE-AD-AUDIT`

Filters: actor, action, `channel_id`, date range. Rows: before/after, IP, impersonation.  
Export: `POST /platform/audit/export` `{ filters, format: "xlsx" }` → `job_id`.

### 5.9 Access review — `FE-AD-IAM-REVIEW`

Start `{ quarter, scope }`. Campaign detail: each item `keep|revoke` + reason. UI must not mark the campaign closed while items are undecided.

## 6. References — SP-03

Pattern for every entity: list → create → update (`reason`) → enable/disable (impact modal `affected`, then confirm). **No hard delete.**

| Screen | List / create | Update | Status |
|---|---|---|---|
| Governorates | EP-AD-030 / 031 | 042A | 043A |
| Zones | 032 / 033 (`filter[governorate_id]`) | 042B | **034** (not 043) |
| Activity types | 035A / B | 042C | 043B |
| Root categories | 036A / B | 042D | 043C · 409 `ref_in_use` |
| Sale units | 037A / B | 042E | 043D · 409 if in use |
| Equipment | 038A / B | 042F | 043E |
| Currencies | 039A / B | 042G | 043F |

Zones: show `affected.retailers / reps / open_orders` **before** the second confirm (REQ-AD-031).

FX: `POST /platform/refs/fx-rates` — `rate` is an integer. List `GET /platform/refs/fx-rates`. Setting `is_display_currency` replaces the previous display currency. Do not claim open orders will reprice.

**Import — `FE-AD-REFS-IMPORT`:** multipart `type` + `dry_run=true` first. Preview rows + `errors[{row,column,message}]`. Execute with `dry_run=false` and a **new** idempotency key.

## 7. Channels — SP-04

### 7.1 List — `FE-AD-CH-LIST`

Filters: `status`, `plan_id`, `governorate_id`, `subscription_status`, `over_limit`, `idle`.  
Columns from the server (zeros allowed; **never invent** GMV).  
List export: EP-AD-059C with **active filters**, not the current page.

Bulk: notify managers (max 50) EP-AD-068. Plan change: preview EP-AD-059A (show `delta`) then EP-AD-059B + `reason`.

### 7.2 Create — `FE-AD-CH-CREATE`

Body per EP-AD-051: name, slug, legal form, CR, documents, governorates/zones/activities, logo, internal note, plan, billing cycle, trial days, limits, discount, manager `{ name, phone, email, invite_via }`.

HTTP returns in well under 500ms with `status: provisioning` and `provisioning_job_id`. **Do not** wait for `active` on that request. Open the card and poll `GET /platform/channels/{id}` until `active` or failure → Retry EP-AD-053 (idempotent).

### 7.3 Channel card (DOC-07 tabs)

| Tab | Behaviour | API |
|---|---|---|
| Overview | Edit + `reason` | GET 052, PUT 062 |
| Users | Read | 063 |
| Manager | Reset invite (72h) | 064 crit |
| Coverage | Zones, overlap warning, save + reason | 065A / B |
| Warehouses | Read only | 066 |
| Features | Read (plan vs override) | 067 |
| Usage | `range=30d`; empty/zero series OK | 056 |
| Limits | Override + `temporary_until` + reason | 055 |
| Status | Buttons from `allowed_next` only. No `active → archived` control | 054 |
| Export | Password then job | 057 |
| Delete | Only if `archived`; type exact name + password + 2FA OTP + second approver | 058 → `deletion_request_id` |

KPI tiles: print server zeros or hide if all zero. No fake sparklines.

### 7.4 Join applications — `FE-AD-CH-APPS`

`GET /platform/channel-applications?filter[status]=under_review`.  
Decide: `approve|reject` + reason + `plan_id` + `trial_days` on approve → channel `provisioning`.

## 8. Out of scope

`GET /platform/dashboard`, notification campaign builder, plan SKU catalog, platform team member CRUD (SP-17), impersonation (later).

## 9. Definition of done

Admin walkthrough:

1. Login → 2FA → channel list (empty or seeded).  
2. Create a governorate and a zone.  
3. Create a channel: UI shows `provisioning` then `active` (or retry).  
4. Create a role that stays `draft` until a second user approves.  
5. Disable a zone: reason + impact counts.  
6. No dashboard-charts route in the app.
