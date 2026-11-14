# المشترك والعام — عقد API (التاجر + المندوب)

| | |
|---|---|
| الغرض | مسارات بلا `retailer`/`rep` في المسار + `/public/*` + `/health`. تُستهلك من التطبيقين. |
| الحالة | مشترك **18/18** · عام **6/6** · أخرى (صحة…) ضمن `status/08` |
| status | [`06-shared-app.md`](../status/06-shared-app.md) · [`07-public.md`](../status/07-public.md) · [`08-other.md`](../status/08-other.md) |
| Postman | مضمّن في [`retailer`](retailer.postman_collection.json) و[`rep`](rep.postman_collection.json) |

---

## 1. عام (بلا حارس)

| EP | طريقة | مسار |
|---|---|---|
| EP-CM-001 | POST | `/public/auth/request-otp` |
| EP-CM-002 | POST | `/public/auth/verify-otp` |
| EP-CM-003 | POST | `/public/auth/resend-otp` |
| EP-PB-001 | GET | `/public/refs` |
| EP-PB-010 | GET | `/public/app-config` |
| EP-PB-011 | GET | `/public/content/intro` |

`request-otp` / `verify-otp` / `resend-otp`: **معفاة** من `X-Idempotency-Key`.  
بعد verify: احفظ `token` · اقرأ `kind` · لا تستخدم توكن تاجر على شاشات مندوب والعكس.

---

## 2. جلسة التطبيق

| EP | طريقة | مسار | ملاحظة |
|---|---|---|---|
| EP-CM-004 | GET | `/app/session` | مندوب: `status` + `channel` (BF-06) |
| EP-CM-005 | POST | `/app/auth/logout` | |

---

## 3. تسعير · عروض · إشعارات · مزامنة · محتوى · ولاء

| مجموعة | مسارات |
|---|---|
| تسعير | `POST /app/pricing/quote` |
| عروض | `GET /app/offers` · `GET /app/offers/{id}` |
| إشعارات | `GET/POST/DELETE /app/notifications*` · `POST /app/devices/push-token` |
| مزامنة | `/app/sync/*` (4 مسارات) |
| محتوى | `GET /app/content/home-blocks` · `POST …/banners/{id}/click` |
| ولاء | `GET /app/loyalty` · `POST /app/loyalty/redeem` |

---

## 4. وصل استلام — مندوب فقط

| EP | مسار | صلاحية |
|---|---|---|
| EP-CM-050 | `POST /app/receipts/reserve` | `rp.payment.collect` |

لا تستدعِه من تطبيق التاجر.

---

## 5. صحة

`GET /api/v1/health` — بلا توكن. تحقق نشر: `status=ok` وchecks db/cache/queue.

---

*آخر تحديث: 2026-11-03.*
