# docs/DocsLast — عقود كل العملاء

**ابدأ من [`ALL.md`](ALL.md)** — خريطة واحدة لكل الأسطح + Postman + status.

التفاصيل الحية للمسارات: `docs/status/`. العقد الخام: `docs/api/catalog/`.

| العميل | ملف العقد | عمق UI / إضافي | Postman | حارس | منفذ / منصة |
|---|---|---|---|---|---|
| **الكل (فهرس)** | [`ALL.md`](ALL.md) | — | [`../api/b2b-api.live.postman_collection.json`](../api/b2b-api.live.postman_collection.json) | — | — |
| سوبر أدمن | [`platform.md`](platform.md) · [`platform.json`](platform.json) | — | [`platform.postman_collection.json`](platform.postman_collection.json) | `platform` | **3000** |
| قناة توريد | [`channel.md`](channel.md) · [`channel.json`](channel.json) | — | [`channel.postman_collection.json`](channel.postman_collection.json) | `channel` | **3001** |
| مستودع | [`warehouse.md`](warehouse.md) | — | [`warehouse.postman_collection.json`](warehouse.postman_collection.json) | `warehouse` | **3002** |
| تطبيق التاجر | [`retailer.md`](retailer.md) | [`flutter-retailer.md`](flutter-retailer.md) | [`retailer.postman_collection.json`](retailer.postman_collection.json) | `app`+`retailer` | Flutter |
| تطبيق المندوب | [`rep.md`](rep.md) | [`flutter-rep.md`](flutter-rep.md) · [`flutter-rep-field.md`](flutter-rep-field.md) · [`flutter-rep.json`](flutter-rep.json) | [`rep.postman_collection.json`](rep.postman_collection.json) | `app`+`rep` | Flutter |
| مشترك + عام | [`shared.md`](shared.md) | — | ضمن retailer/rep | `app` / عام | — |
| دورة الطلب | [`order-flow.md`](order-flow.md) | — | — | كل الأطراف | 2026-11-03 |
| بيئة واجهات | [`env/`](env/) | — | — | — | Vite `VITE_API_BASE_URL` |
| ما قبل الرفع | [`../plan/pre-deploy-checklist.md`](../plan/pre-deploy-checklist.md) | — | — | Ops | — |

الحالة الحية: **436/436** — [`../status/00-overview.md`](../status/00-overview.md).

## قاعدة الأولوية (لا تُكسر)

1. الكود الحي في `app-modules/`
2. `docs/status/*.md` (مولَّد)
3. `docs/api/catalog/*.php`
4. ملف DocsLast لهذا العميل

جملة هنا تخالف الكنترولر → الكود يفوز وهذا الملف يُصلَّح.

## إعادة التوليد

```bash
php docs/api/generate.php && php docs/status/generate.php
php docs/api/generate-platform-live.php
php docs/api/generate-channel-live.php
php docs/api/generate-rep-live.php
php docs/api/generate-dashboard-postman.php
php docs/api/generate-live-postman.php
```

## Postman — Environments

| سطح | Environment |
|---|---|
| منصة | [`../api/environments/platform-web.postman_environment.json`](../api/environments/platform-web.postman_environment.json) |
| قناة | [`../api/environments/channel-web.postman_environment.json`](../api/environments/channel-web.postman_environment.json) |
| مستودع | [`../api/environments/warehouse-desktop.postman_environment.json`](../api/environments/warehouse-desktop.postman_environment.json) |
| تاجر | [`../api/environments/retailer-android.postman_environment.json`](../api/environments/retailer-android.postman_environment.json) |
| مندوب | [`../api/environments/rep-android.postman_environment.json`](../api/environments/rep-android.postman_environment.json) |

استورد المجموعة + الـ environment. لا تخلط التوكن بين الحراس.
