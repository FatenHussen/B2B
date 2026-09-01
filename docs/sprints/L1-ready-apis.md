# APIs الجاهزة — طبقة 1 (SP-00 / SP-01 / SP-02)

الأساس: `http://localhost:8000/api/v1`

**غلاف نجاح**
```json
{ "data": {}, "meta": { "server_time": "2026-09-01T11:00:00+03:00" } }
```
القوائم تضيف في `meta`: `page`, `per_page`, `total`, `last_page`.

**غلاف خطأ**
```json
{ "error": { "code": "validation_failed", "message": "…", "details": {} } }
```

**هيدرات**
| هيدر | متى |
|---|---|
| `Accept: application/json` | دائماً |
| `Accept-Language: ar` أو `en` | افتراضي `ar` |
| `Authorization: Bearer {token}` | كل مسار محمي |
| `X-Idempotency-Key: {uuid}` | إلزامي على POST/PUT/PATCH/DELETE المحمية. غيابه → 400 `idempotency_key_required` |

`data` أدناه هو محتوى `data` فقط.

---

## 1. عام — بلا توكن

### GET `/health`
**Body:** لا  
**Response**
```json
{ "status": "ok" }
```

### POST `/public/auth/request-otp`
**Body**
```json
{
  "phone": "+963933000000",
  "purpose": "login",
  "client": "retailer-android"
}
```
`purpose`: `login` | `register`  
**Response**
```json
{
  "otp_id": "otp_9f2a71",
  "channel_used": "whatsapp",
  "expires_in": 300,
  "resend_after": 60
}
```
أخطاء: 422 `validation_failed` · 429 `rate_limited`

### POST `/public/auth/verify-otp`
**Body**
```json
{
  "otp_id": "otp_9f2a71",
  "code": "482193",
  "device_id": "dev-uuid-1",
  "device_name": "Redmi Note 13",
  "platform": "android"
}
```
**Response (مستخدم مكتمل)**
```json
{
  "token": "12|xxxxx",
  "is_new_user": false,
  "user_type": "retailer",
  "profile_completed": true,
  "user": {
    "id": 481,
    "name": "أبو خالد",
    "phone": "+963933000000",
    "shop_name": "بقالية النور",
    "zone": { "id": 12, "name": "المزة" },
    "activity_type": { "id": 3, "name": "بقالة" },
    "points": 1240,
    "tier": "silver"
  }
}
```
**Response (جديد):** `is_new_user: true`, `profile_completed: false`, توكن قدرة `registration` فقط (ليس جلسة تطبيق كاملة).  
أخطاء: 401 `otp_invalid`

### POST `/public/auth/resend-otp`
**Body**
```json
{ "otp_id": "otp_9f2a71", "prefer_channel": "sms" }
```
`prefer_channel`: `whatsapp` | `sms`  
**Response**
```json
{ "channel_used": "sms", "resend_after": 60 }
```

### POST `/platform/auth/login`
**Body**
```json
{ "email": "admin@platform.sy", "password": "password" }
```
**Response إن وُجد 2FA**
```json
{ "requires_2fa": true, "challenge_token": "cht_8f3a" }
```
**Response بلا 2FA**
```json
{
  "token": "1|platform_xxxxx",
  "user": {
    "id": 1,
    "name": "منصّة",
    "email": "admin@platform.sy",
    "roles": ["platform_admin"],
    "permissions": ["ad.iam.view_catalog"]
  },
  "expires_at": "2026-03-01T21:12:44+03:00"
}
```
أخطاء: 401 `unauthenticated`

### POST `/platform/auth/2fa/verify`
**Body**
```json
{ "challenge_token": "cht_8f3a", "code": "123456" }
```
**Response:** نفس شكل الدخول الناجح (token + user + expires_at).  
أخطاء: 401 `otp_invalid`

### POST `/channel/auth/request-otp`
**Body**
```json
{ "phone": "+963944000000" }
```
**Response**
```json
{ "otp_id": "otp_ch_1" }
```

### POST `/channel/auth/verify-otp`
**Body**
```json
{ "otp_id": "otp_ch_1", "code": "482193" }
```
**Response**
```json
{
  "token": "20|channel_xxxxx",
  "channels": [{ "id": 1, "name": "شركة النور" }],
  "permissions": ["sc.dashboard.view", "sc.orders.view"]
}
```
لا عضوية → 403

### POST `/warehouse/auth/device-login`
**Body**
```json
{ "device_token": "devtok_wh_mezzeh", "pin": "4821" }
```
الجهاز يُسجَّل مسبقاً: `php artisan warehouse:register-device`  
**Response**
```json
{
  "token": "30|warehouse_xxxxx",
  "warehouse": { "id": 1, "name": "مستودع المزة" },
  "permissions": ["wh.queue.view", "wh.picking.execute"]
}
```
أخطاء: 401 `unauthenticated`

---

## 2. منصة — حساب (`Authorization: Bearer` حارس platform)

### POST `/platform/auth/logout`
**Body:** `{}`  
**Response**
```json
{ "success": true }
```

### GET `/platform/auth/me`
**Body:** لا  
**Response**
```json
{
  "id": 1,
  "name": "منصّة",
  "email": "admin@platform.sy",
  "roles": ["platform_admin"],
  "permissions": ["ad.dashboard.view"],
  "two_factor_enabled": true,
  "last_login_at": "2026-03-01T09:12:44+03:00"
}
```

### POST `/platform/auth/confirm-password`
**Body**
```json
{ "password": "password" }
```
**Response**
```json
{ "confirmed_until": "2026-03-01T09:27:44+03:00" }
```
صالحة 15 دقيقة. مطلوبة قبل تغيير كلمة المرور.

### GET `/platform/auth/sessions`
**Body:** لا  
**Response**
```json
[
  {
    "id": "ses_1",
    "ip": "185.1.2.3",
    "agent": "Chrome 131",
    "last_active_at": "2026-03-01T09:12:44+03:00",
    "current": true
  }
]
```

### DELETE `/platform/auth/sessions/{id}`
**Body:** لا  
**Response**
```json
{ "success": true }
```

### GET `/platform/me`
**Body:** لا  
**Response**
```json
{
  "id": 1,
  "name": "منصّة",
  "email": "admin@platform.sy",
  "phone": "+963991000000",
  "two_factor_enabled": true,
  "roles": ["platform_admin"]
}
```

### PUT `/platform/me`
**Body**
```json
{ "name": "منصّة", "phone": "+963991000000" }
```
**Response**
```json
{ "updated": true }
```

### PUT `/platform/me/password`
يحتاج تأكيد كلمة مرور ساري.  
**Body**
```json
{
  "current_password": "password",
  "password": "NewPass12",
  "password_confirmation": "NewPass12"
}
```
**Response**
```json
{ "updated": true }
```

### POST `/platform/me/2fa/enable`
**Body:** `{}`  
**Response**
```json
{ "secret": "otpauth://totp/...", "qr_svg": "<svg/>" }
```

### POST `/platform/me/2fa/confirm`
**Body**
```json
{ "code": "123456" }
```
**Response**
```json
{ "enabled": true, "recovery_codes": ["a1b2c3d4", "e5f6g7h8"] }
```

### GET `/platform/me/2fa/recovery-codes`
**Body:** لا  
**Response**
```json
{ "codes_remaining": 8 }
```

### GET `/platform/me/api-tokens`
**Body:** لا  
**Response**
```json
[
  {
    "id": 3,
    "name": "ci",
    "last_used_at": null,
    "created_at": "2026-02-01T00:00:00+03:00"
  }
]
```

### POST `/platform/me/api-tokens`
**Body**
```json
{ "name": "ci", "password_confirmation": "password" }
```
**Response** (الرمز يُعرض مرة)
```json
{ "id": 4, "token": "2|shown_once" }
```

### DELETE `/platform/me/api-tokens/{id}`
**Body:** لا  
**Response**
```json
{ "success": true }
```

---

## 3. منصة — IAM وتدقيق (حارس platform + صلاحية)

### GET `/platform/iam/permissions`
صلاحية: `ad.iam.view_catalog`  
**Query:** `?filter[system]=platform&filter[severity]=critical&page=1&per_page=25`  
**Body:** لا  
**Response** (`data` = مصفوفة)
```json
[
  {
    "code": "ad.channels.delete",
    "name_ar": "حذف قناة",
    "system": "platform",
    "module": "channels",
    "severity": "critical",
    "dual_approval": true,
    "delegatable": false,
    "roles_count": 1,
    "users_count": 2
  }
]
```

### GET `/platform/iam/permissions/{code}/holders`
مثال: `/platform/iam/permissions/ad.iam.view_catalog/holders`  
**Body:** لا  
**Response**
```json
{
  "roles": [{ "id": 1, "key": "platform_admin" }],
  "users": [{ "id": 1, "name": "منصّة" }],
  "recent_usage": [
    { "at": "2026-03-01T09:00:00+03:00", "actor": 1, "action": "simulate" }
  ]
}
```

### GET `/platform/iam/roles`
**Query:** `?filter[system]=channel`  
**Body:** لا  
**Response**
```json
[
  {
    "id": 4,
    "key": "channel_manager",
    "name": "مدير القناة",
    "system": "channel",
    "status": "active",
    "is_builtin": true,
    "permissions_count": 80,
    "users_count": 12
  }
]
```

### POST `/platform/iam/roles/preview`
**Body**
```json
{ "permissions": ["sc.orders.confirm", "sc.pricing.update"] }
```
**Response**
```json
{
  "summary_ar": "سيستطيع تأكيد الطلبات وتغيير أسعار المناطق",
  "sod_conflicts": []
}
```

### POST `/platform/iam/roles`
صلاحية: `ad.iam.role_create` — يبدأ `draft`  
**Body**
```json
{
  "name": "مشرف كتالوج",
  "key": "catalog_supervisor",
  "system": "channel",
  "description": "صلاحيات الكتالوج دون المالية",
  "permissions": ["sc.catalog.view", "sc.catalog.create", "sc.catalog.update"],
  "copy_from_role_id": null
}
```
`system`: `platform` | `channel` | `warehouse` | `app`  
**Response** `201`
```json
{ "id": 22, "status": "draft", "sod_conflicts": [] }
```

### POST `/platform/iam/roles/{id}/approve`
صلاحية: `ad.iam.role_approve` — المعتمد ≠ المنشئ  
**Body**
```json
{ "reason": "مراجعة SOD مكتملة" }
```
**Response**
```json
{ "status": "active" }
```
أخطاء: 403 `sod_violation`

### PUT `/platform/iam/roles/{id}/permissions`
**Body**
```json
{
  "permissions": ["sc.catalog.view", "sc.catalog.update"],
  "reason": "إزالة صلاحية الإنشاء"
}
```
**Response**
```json
{ "changed": ["sc.catalog.create"], "sod_conflicts": [] }
```

### POST `/platform/iam/assignments`
صلاحية: `ad.iam.role_assign` — حد 50 `user_ids`  
**Body**
```json
{
  "user_ids": [10, 11],
  "role_id": 4,
  "expires_at": null,
  "reason": "تعيين مديري قناة جدد"
}
```
**Response**
```json
{ "assigned": [10, 11], "rejected": [] }
```
مرفوض SoD مثال: `"rejected": [{ "user_id": 10, "reason": "…" }]`

### DELETE `/platform/iam/assignments`
**Body**
```json
{ "user_id": 10, "role_id": 4, "reason": "انتقال لقناة أخرى" }
```
**Response**
```json
{ "success": true }
```

### POST `/platform/iam/simulate`
صلاحية: `ad.iam.simulate`  
**Body**
```json
{
  "user_type": "channel",
  "user_id": 10,
  "permission": "sc.orders.confirm",
  "resource_type": "sub_order",
  "resource_id": 9001
}
```
`user_type`: `platform` | `channel` | `warehouse` | `app`  
**Response**
```json
{
  "allowed": true,
  "decision_path": [
    { "layer": "guard", "result": "pass", "reason": "channel" },
    { "layer": "permission", "result": "pass", "reason": "role:channel_manager" },
    { "layer": "tenant", "result": "pass", "reason": "same channel" },
    { "layer": "sod", "result": "pass", "reason": "clear" },
    { "layer": "temp-grant", "result": "skip", "reason": "none" }
  ]
}
```

### POST `/platform/iam/temp-grants`
صلاحية: `ad.iam.grant_temp`  
`duration_minutes` ≤ 240، `reason` ≥ 20 حرفاً.  
**Body**
```json
{
  "user_id": 10,
  "permission": "sc.finance.void_invoice",
  "duration_minutes": 60,
  "reason": "تصحيح فاتورة مكررة بعد حادث مزامنة — تذكرة SUP-441"
}
```
**Response**
```json
{
  "id": 55,
  "status": "pending_approval",
  "expires_request_at": "2026-03-01T13:12:44+03:00"
}
```

### POST `/platform/iam/temp-grants/{id}/approve`
**Body**
```json
{ "decision": "approve", "reason": "تم التحقق من التذكرة" }
```
`decision`: `approve` | `reject`  
**Response**
```json
{ "granted_until": "2026-03-01T10:12:44+03:00" }
```

### GET `/platform/iam/sod-rules`
صلاحية: `ad.iam.sod_rules`  
**Body:** لا  
**Response**
```json
[
  {
    "code": "SOD-01",
    "permission_a": "sc.orders.confirm",
    "permission_b": "sc.finance.payment",
    "reason": "لا يجتمع تأكيد الطلب مع تسجيل الدفعة",
    "exceptions": { "role_keys": ["channel_manager"] }
  }
]
```

### GET `/platform/iam/approval-requests`
**Query:** `?filter[status]=pending`  
**Body:** لا  
**Response**
```json
[
  {
    "id": 19,
    "permission": "ad.iam.role_create",
    "action": "role.create",
    "requested_by": 1,
    "payload": { "role_id": 22, "permissions": ["sc.catalog.view"] },
    "status": "pending",
    "created_at": "2026-03-01T09:00:00+03:00"
  }
]
```

### POST `/platform/iam/approval-requests/{id}/decide`
صلاحية: `ad.iam.role_approve`  
**Body**
```json
{ "decision": "approve", "reason": "مراجعة مكتملة" }
```
**Response**
```json
{ "status": "approved", "executed": true }
```
أخطاء: 403 `sod_violation` إن كان الباتّ هو الطالب

### POST `/platform/iam/reviews`
صلاحية: `ad.audit.review`  
**Body**
```json
{ "quarter": "2026-Q1", "scope": "platform" }
```
`scope`: `platform` | `channel` | `warehouse` | `app`  
**Response**
```json
{ "campaign_id": 3, "items_count": 42 }
```

### GET `/platform/iam/reviews/{id}`
**Body:** لا  
**Response**
```json
{
  "campaign_id": 3,
  "quarter": "2026-Q1",
  "status": "open",
  "items": [
    {
      "id": 11,
      "user_id": 4,
      "role_id": 2,
      "suggestion": "revoke_unused",
      "decision": null
    }
  ]
}
```

### POST `/platform/iam/reviews/{id}/items/{itemId}/decide`
**Body**
```json
{ "decision": "keep", "reason": "ما زال مدير قناة فعّال" }
```
`decision`: `keep` | `revoke`  
**Response**
```json
{ "item_id": 11, "decision": "keep" }
```

### GET `/platform/audit`
صلاحية: `ad.audit.view`  
**Query:** `?filter[actor]=1&filter[action]=role.assign&filter[channel_id]=1&filter[date_from]=2026-02-01&filter[date_to]=2026-03-01`  
**Body:** لا  
**Response**
```json
[
  {
    "at": "2026-03-01T09:12:44+03:00",
    "actor": 1,
    "action": "role.assign",
    "entity_type": "user",
    "entity_id": 10,
    "before": null,
    "after": { "role_id": 4 },
    "ip": "185.1.2.3",
    "impersonated": false
  }
]
```

### POST `/platform/audit/export`
صلاحية: `ad.audit.export` — Job على طابور `exports`  
**Body**
```json
{
  "filters": { "date_from": "2026-02-01", "date_to": "2026-03-01" },
  "format": "xlsx"
}
```
**Response**
```json
{ "job_id": "job_audit_1" }
```

---

## 4. تطبيق — حارس `app`

تسجيل التاجر/المندوب: توكن من `verify-otp` بقدرة `registration`.  
الجلسة والخروج: توكن تطبيق كامل.

### POST `/app/retailer/register`
**Body**
```json
{
  "owner_name": "أبو خالد",
  "shop_name": "بقالية النور",
  "activity_type_id": 3,
  "category_ids": [10, 11],
  "equipment_ids": [1],
  "governorate_id": 1,
  "zone_id": 12,
  "lat": 33.5031,
  "lng": 36.2765,
  "address": "المزة فيلات شرقية"
}
```
لا حقل email. `zone_id` يجب أن ينتمي لـ `governorate_id`.  
**Response**
```json
{
  "retailer": {
    "id": 481,
    "shop_name": "بقالية النور",
    "zone": { "id": 12, "name": "المزة" },
    "activity_type": { "id": 3, "name": "بقالة" },
    "status": "pending_review"
  },
  "token": "40|retailer_xxxxx"
}
```

### POST `/app/rep/register`
**Body**
```json
{
  "name": "أحمد العلي",
  "supply_channel_id": 1,
  "activity_type_id": 3,
  "zone_ids": [12, 13],
  "note": "خبرة سنتين في المزة"
}
```
القناة يجب أن تكون `active` والمناطق داخل تغطيتها.  
**Response**
```json
{
  "rep": {
    "id": 70,
    "name": "أحمد العلي",
    "channel": { "id": 1, "name": "شركة النور" },
    "zones": [{ "id": 12, "name": "المزة" }],
    "status": "pending_review"
  },
  "token": "50|rep_xxxxx"
}
```
قناة غير نشطة → 409 · منطقة خارج التغطية → 422

### GET `/app/session`
**Body:** لا  
**Response**
```json
{
  "user": {
    "id": 481,
    "name": "أبو خالد",
    "user_type": "retailer",
    "profile_completed": true
  },
  "permissions": ["rt.receive.confirm", "rt.payment.record"],
  "feature_flags": { "offline_orders": true, "loyalty": true },
  "sync_cursor": "c_9f2a71",
  "server_time": "2026-03-01T09:12:44+03:00",
  "requires_legal_accept": false,
  "legal": { "privacy_version": "2026-03", "terms_version": "2026-01" }
}
```

### POST `/app/auth/logout`
**Body:** `{}`  
**Response**
```json
{ "success": true }
```

---

## غير جاهز
`GET /public/refs` و`/platform/refs/*` (SP-03) · `/platform/channels*` (SP-04)
